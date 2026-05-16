<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * hooks.php
 * 负责 WHMCS 登录时的验证码校验逻辑、拦截未验证用户等
 */

use WHMCS\Database\Capsule;

require_once __DIR__ . '/helpers.php';

// 登录后触发 Hook
add_hook('ClientLogin', 1, function($vars) {
    $userid = (int)$vars['userid'];
    if (!$userid) return;
    // Reset UI language override at the start of each login
    unset($_SESSION['securelogin_ui_lang']);
    // Reset per-login verification method choice; clientarea will re-init from user preferred method
    unset($_SESSION['securelogin_verify_method']);
    $enabled = securelogin_boolval(securelogin_getAddonSetting('enabled', 'on'), true);
    if (!$enabled) {
        $_SESSION['securelogin_verified'] = true;
        return;
    }
    $bypassIfWhmcs2fa = securelogin_boolval(securelogin_getAddonSetting('bypass_if_whmcs_2fa_enabled', ''), false);
    if ($bypassIfWhmcs2fa && function_exists('securelogin_isWhmcs2faEnabledForUser') && securelogin_isWhmcs2faEnabledForUser($userid)) {
        $_SESSION['securelogin_verified'] = true;
        $_SESSION['securelogin_required'] = false;
        securelogin_recordLog($userid, 'bypass_whmcs_2fa_enabled', 'Bypass securelogin because WHMCS native 2FA is enabled');
        if (function_exists('logActivity')) {
            @logActivity('Client bypassed Secure Login because WHMCS native 2FA is enabled', $userid);
        }
        if (function_exists('securelogin_hardenSession')) {
            securelogin_hardenSession();
        }
        if (function_exists('securelogin_touchCoreLastLogin')) {
            securelogin_touchCoreLastLogin($userid);
        }
        return;
    }

    $config = [
        'days_threshold' => (int)securelogin_getAddonSetting('days_threshold', 15),
        'enable_geo_check' => securelogin_boolval(securelogin_getAddonSetting('enable_geo_check', ''), false),
        'enable_first_login' => securelogin_boolval(securelogin_getAddonSetting('enable_first_login', 'on'), true),
        'code_expiry_minutes' => (int)securelogin_getAddonSetting('code_expiry_minutes', 5),
        'resend_interval_seconds' => (int)securelogin_getAddonSetting('resend_interval_seconds', 60),
        'max_resend' => (int)securelogin_getAddonSetting('max_resend', 5),
        'max_attempts' => (int)securelogin_getAddonSetting('max_attempts', 5),
        'lock_minutes' => (int)securelogin_getAddonSetting('lock_minutes', 30),
        'remember_device_days' => (int)securelogin_getAddonSetting('remember_device_days', 30),
    ];

    $state = securelogin_getUserState($userid);
    // 一次性豁免：若设置则本次直接放行，并恢复正常策略
    if ((int)($state->one_time_exempt ?? 0) === 1) {
        $_SESSION['securelogin_verified'] = true;
        $_SESSION['securelogin_required'] = false;
        $ua = securelogin_getUserAgent();
        $al = securelogin_getAcceptLanguage();
        $fp = securelogin_computeServerFingerprint($ua, $al);
        $currentIp = securelogin_getClientIp();
        $update = [
            'force_verify' => 0,
            'last_verified_login_at' => securelogin_now()->format('Y-m-d H:i:s'),
            'last_login_ip' => $currentIp,
            'last_email' => (function($uid){ try{ $c=Capsule::table('tblclients')->where('id',$uid)->first(); return $c? (string)$c->email : null; } catch(Exception $e){ return null; } })($userid),
            'last_login_fingerprint' => $fp,
        ];
        // 若存在 one_time_exempt 列，则清除一次性豁免标记
        try {
            $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_state LIKE 'one_time_exempt'");
            if ($col) {
                $update['one_time_exempt'] = 0;
            }
        } catch (Exception $e) {}
        // 城市信息由前端异步上报后再更新，避免登录过程阻塞
        securelogin_updateUserState($userid, $update);
        securelogin_recordLog($userid, 'one_time_exempt_used', 'One-time exemption consumed; login allowed without verification');
        // 完成登录态：会话固化 + 全局日志
        if (function_exists('logActivity')) {
            @logActivity('Client bypassed Secure Login via one-time admin exemption', $userid);
        }
        if (function_exists('securelogin_hardenSession')) {
            securelogin_hardenSession();
        } else {
            if (session_status() === PHP_SESSION_ACTIVE) { @session_regenerate_id(true); }
        }
        if (function_exists('securelogin_touchCoreLastLogin')) {
            securelogin_touchCoreLastLogin($userid);
        }
        return;
    }
    $now = securelogin_now();
    $needsVerify = false;
    $isFirstLogin = empty($state->last_verified_login_at);

    // 超过N天未验证登录（是否对首次登录启用由配置决定）
    if ($state->last_verified_login_at) {
        $last = new DateTime($state->last_verified_login_at, new DateTimeZone('UTC'));
        $threshold = clone $last;
        $threshold->modify('+' . (int)$config['days_threshold'] . ' days');
        if ($threshold < $now) {
            $needsVerify = true;
        }
    } else {
        // 首次登录无 last_verified_login_at
        if ($config['enable_first_login']) {
            $needsVerify = true;
        }
    }

    // 异地/设备变化（异步城市解析）：为提高登录速度，此处仅依据 IP 与设备指纹判断
    // 放宽：需要“IP变化 且 设备指纹变化”同时满足才触发；城市解析改为登录后异步更新
    if (!$needsVerify && $config['enable_geo_check']) {
        $currentIp = securelogin_getClientIp();
        $ua = securelogin_getUserAgent();
        $al = securelogin_getAcceptLanguage();
        $fp = securelogin_computeServerFingerprint($ua, $al);
        $deviceChanged = ($state->last_login_fingerprint && $state->last_login_fingerprint !== $fp);
        $ipChanged = ($state->last_login_ip && $state->last_login_ip !== $currentIp);
        if ($ipChanged && $deviceChanged) {
            $needsVerify = true;
        }
    }

    // 邮箱变更检测：对比上次已验证邮箱
    if (!$needsVerify) {
        try {
            $client = Capsule::table('tblclients')->where('id', $userid)->first();
            $currentEmail = $client ? (string)$client->email : '';
            $lastEmail = (string)($state->last_email ?? '');
            if ($currentEmail !== '' && $lastEmail !== '' && strcasecmp($currentEmail, $lastEmail) !== 0) {
                $needsVerify = true;
            }
        } catch (Exception $e) {}
    }

    // 管理员强制需验证
    if ((int)$state->force_verify === 1) {
        $needsVerify = true;
    }

    // 记住设备免验证（但不绕过强制验证）
    if (!$needsVerify && securelogin_validateRememberDevice($userid)) {
        $_SESSION['securelogin_verified'] = true;
        $_SESSION['securelogin_required'] = false;
        $ua = securelogin_getUserAgent();
        $al = securelogin_getAcceptLanguage();
        $fp = securelogin_computeServerFingerprint($ua, $al);
        $currentIp = securelogin_getClientIp();
        $update = [
            'last_verified_login_at' => securelogin_now()->format('Y-m-d H:i:s'),
            'last_login_ip' => $currentIp,
            'last_login_fingerprint' => $fp,
        ];
        // 城市信息由前端异步上报后再更新，避免登录过程阻塞
        securelogin_updateUserState($userid, $update);
        securelogin_recordLog($userid, 'remember_device_ok', 'Remember device bypassed verification');
        // 完成登录态：会话固化 + 全局日志
        if (function_exists('logActivity')) {
            @logActivity('Client passed Secure Login via remembered device', $userid);
        }
        if (function_exists('securelogin_hardenSession')) {
            securelogin_hardenSession();
        } else {
            if (session_status() === PHP_SESSION_ACTIVE) { @session_regenerate_id(true); }
        }
        if (function_exists('securelogin_touchCoreLastLogin')) {
            securelogin_touchCoreLastLogin($userid);
        }
        return;
    }

    if ($needsVerify) {
        $_SESSION['securelogin_verified'] = false;
        $_SESSION['securelogin_required'] = true;
        // 检测是否为注册流程（仅注册场景才使用注册模板与支付宽限）
        $isRegistrationFlow = (int)($_SESSION['securelogin_is_new_reg'] ?? 0) === 1;
        if (!$isRegistrationFlow) {
            try {
                $clientForCreate = Capsule::table('tblclients')->where('id', $userid)->first();
                if ($clientForCreate && !empty($clientForCreate->datecreated)) {
                    $ts = @strtotime($clientForCreate->datecreated);
                    if ($ts) {
                        // 24小时内且为首次登录，兜底视为注册流程
                        if ($isFirstLogin && (time() - $ts) >= 0 && (time() - $ts) <= 86400) {
                            $isRegistrationFlow = true;
                        }
                    }
                }
            } catch (Exception $e) {}
        }
        // 注册流程：始终给予支付白名单宽限（仅允许访问支付相关页面，其他被拦截）
        if ($isRegistrationFlow) {
            $_SESSION['securelogin_payment_grace'] = 1;
        } else {
            // 非注册流程：仅当当前页面为支付白名单时给予宽限
            if (securelogin_isPaymentWhitelistedPage()) {
                $_SESSION['securelogin_payment_grace'] = 1;
            } else {
                unset($_SESSION['securelogin_payment_grace']);
            }
        }
        $template = $isRegistrationFlow ? 'Secure Login Registration Code' : 'Secure Login Verification Code';
        $_SESSION['securelogin_email_template'] = $template;
        $builtinTotpEnabled = securelogin_boolval(securelogin_getAddonSetting('enable_builtin_totp', 'on'), true);
        $hasTotp = false;
        $preferredMethod = 'totp';
        $backupEmailEnabled = true;
        if ($builtinTotpEnabled && function_exists('securelogin_getUserTotpSecret')) {
            try { $hasTotp = (securelogin_getUserTotpSecret($userid) !== ''); } catch (Exception $e) { $hasTotp = false; }
            try { $backupEmailEnabled = securelogin_isUserTotpBackupEmailEnabled($userid); } catch (Exception $e) { $backupEmailEnabled = true; }
            try { $preferredMethod = securelogin_getUserPreferredVerifyMethod($userid); } catch (Exception $e) { $preferredMethod = 'totp'; }
        }
        // 首次登录/注册自动发送；其后根据重发冷却与剩余次数决定是否自动发送
        $preferEmail = $hasTotp && $backupEmailEnabled && $preferredMethod === 'email';
        if ($hasTotp && !$preferEmail) {
            securelogin_recordLog($userid, 'totp_enabled_skip_auto_email', 'TOTP enabled and preferred, skip auto email code on login challenge');
        } elseif (securelogin_isLocked($userid)) {
            securelogin_recordLog($userid, 'login_locked_no_send', 'User locked, skip sending code on login');
        } else {
            $row = securelogin_getOrCreateCodeRow($userid);
            list($availableIn, $remaining) = securelogin_canResend($userid, $config);
            $shouldAutoSend = (!$row->last_sent_at) || ((int)$availableIn <= 0);
            if ($shouldAutoSend && (int)$remaining > 0) {
                securelogin_issueNewCode($userid, $config, $template);
            } else {
                if ((int)$remaining <= 0) {
                    securelogin_recordLog($userid, 'login_daily_limit_no_send', 'Daily limit reached');
                } else {
                    securelogin_recordLog($userid, 'login_throttled_no_send', 'Cooldown remaining ' . (int)$availableIn . 's');
                }
            }
        }
        // 一次性标记用后即清除
        unset($_SESSION['securelogin_is_new_reg']);
        securelogin_recordLog($userid, 'trigger_verify', 'Trigger verification after login');
        // 如当前是支付白名单页面，不重定向，允许继续支付；否则跳转到验证页
        if (!$isRegistrationFlow && !securelogin_isPaymentWhitelistedPage()) {
            header('Location: index.php?m=securelogin&action=verify');
            exit;
        }
    } else {
        $_SESSION['securelogin_verified'] = true;
        $_SESSION['securelogin_required'] = false;
        $ua = securelogin_getUserAgent();
        $al = securelogin_getAcceptLanguage();
        $fp = securelogin_computeServerFingerprint($ua, $al);
        $currentIp = securelogin_getClientIp();
        $update = [
            'last_verified_login_at' => $now->format('Y-m-d H:i:s'),
            'last_login_ip' => $currentIp,
            'last_email' => (function($uid){ try{ $c=Capsule::table('tblclients')->where('id',$uid)->first(); return $c? (string)$c->email : null; } catch(Exception $e){ return null; } })($userid),
            'last_login_fingerprint' => $fp,
        ];
        // 城市信息由前端异步上报后再更新，避免登录过程阻塞
        securelogin_updateUserState($userid, $update);
        if (function_exists('securelogin_touchCoreLastLogin')) {
            securelogin_touchCoreLastLogin($userid);
        }
    }
});

// 验证码未通过时，拦截访问 ClientArea 页面
add_hook('ClientAreaPage', 1, function($vars) {
    $enabled = securelogin_boolval(securelogin_getAddonSetting('enabled', 'on'), true);
    if (!$enabled) return;
    if (!isset($_SESSION['uid']) || !(int)$_SESSION['uid']) return;
    $required = (bool)($_SESSION['securelogin_required'] ?? false);
    $verified = (bool)($_SESSION['securelogin_verified'] ?? false);
    if ($required && !$verified) {
        // 支付白名单：在未完成验证前允许访问支付相关页面
        if ((int)($_SESSION['securelogin_payment_grace'] ?? 0) === 1 && securelogin_isPaymentWhitelistedPage()) {
            return;
        }
        $mod = (string)($_GET['m'] ?? '');
        $act = (string)($_GET['action'] ?? 'verify');
        if (!($mod === 'securelogin' && $act === 'verify')) {
            header("Location: index.php?m=securelogin&action=verify");
            exit;
        }
    }

    // 去除邮箱变更强制拦截（改为下次登录时验证）
});

// 用户通过购物车结账创建账户时，允许先完成支付再提示验证
add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {
    // WHMCS 在结账完成后通常会携带返回参数或回到 cart.php?a=complete
    // 我们在这里仅设置一个提示标志，真正的放行由 ClientAreaPage 的白名单控制
    if (isset($_SESSION['uid']) && (int)$_SESSION['uid'] > 0) {
        // 标记本次为注册流程，便于后续使用注册模板
        $_SESSION['securelogin_is_new_reg'] = 1;
        if ((int)($_SESSION['securelogin_required'] ?? 0) === 1 && !(bool)($_SESSION['securelogin_verified'] ?? false)) {
            $_SESSION['securelogin_payment_grace'] = 1;
            securelogin_recordLog((int)$_SESSION['uid'], 'checkout_grace', 'Checkout grace set after registration');
        }
    }
});

// 在支付页面顶部显示提示：首次注册需要完成邮箱验证
add_hook('ClientAreaHeaderOutput', 1, function($vars) {
    if (!isset($_SESSION['uid']) || !(int)$_SESSION['uid']) return '';
    $required = (bool)($_SESSION['securelogin_required'] ?? false);
    $verified = (bool)($_SESSION['securelogin_verified'] ?? false);
    $grace = (int)($_SESSION['securelogin_payment_grace'] ?? 0) === 1;
    if ($required && !$verified && $grace && securelogin_isPaymentWhitelistedPage()) {
        $state = securelogin_getUserState((int)$_SESSION['uid']);
        $isFirstLogin = empty($state->last_verified_login_at);
        if (!$isFirstLogin) return '';
        $html = "<div class=\"alert alert-info\" style=\"margin:0;border-radius:0;\">"
              . "首次注册需要完成邮箱验证。为不影响支付，我们已允许您先完成支付。支付完成后请尽快前往验证页面输入邮件验证码。"
              . "</div>";
        return $html;
    }
    return '';
});

/* 已移除城市异步上报脚本注入（ClientAreaHeaderOutput #2） */

// 已移除邮箱变更验证码与提示逻辑
// 邮箱变更后：标记下次登录必须验证（不拦截、不立刻发码）
add_hook('ClientDetailsChanged', 1, function($vars) {
    $enabled = securelogin_boolval(securelogin_getAddonSetting('enabled', 'on'), true);
    if (!$enabled) return;
    $userId = (int)($vars['userid'] ?? 0);
    if ($userId <= 0) return;
    $oldEmail = '';
    $newEmail = '';
    if (isset($vars['olddata']) && is_array($vars['olddata'])) {
        $oldEmail = (string)($vars['olddata']['email'] ?? '');
    }
    if (isset($vars['newdata']) && is_array($vars['newdata'])) {
        $newEmail = (string)($vars['newdata']['email'] ?? '');
    }
    if ($newEmail === '') {
        $newEmail = (string)($vars['email'] ?? '');
    }
    if ($newEmail === '' || ($oldEmail !== '' && strcasecmp($oldEmail, $newEmail) === 0)) {
        return;
    }
    securelogin_updateUserState($userId, ['force_verify' => 1]);
    securelogin_recordLog($userId, 'email_changed_force_verify', 'Email changed to ' . $newEmail . '; require verify next login');
});

// 兼容：资料保存（部分版本/场景触发）
add_hook('ClientEdit', 1, function($vars) {
    $enabled = securelogin_boolval(securelogin_getAddonSetting('enabled', 'on'), true);
    if (!$enabled) return;
    $userId = (int)($vars['userid'] ?? 0);
    if ($userId <= 0) return;
    $newEmail = (string)($vars['email'] ?? '');
    if ($newEmail === '') return;
    try {
        $client = Capsule::table('tblclients')->where('id', $userId)->first();
        if ($client && strcasecmp((string)$client->email, $newEmail) === 0) {
            return;
        }
    } catch (Exception $e) {}
    securelogin_updateUserState($userId, ['force_verify' => 1]);
    securelogin_recordLog($userId, 'email_changed_force_verify', 'Email changed to ' . $newEmail . '; require verify next login');
});

// 兼容：专用邮箱变更钩子（存在则触发）
add_hook('ClientChangeEmail', 1, function($vars) {
    $enabled = securelogin_boolval(securelogin_getAddonSetting('enabled', 'on'), true);
    if (!$enabled) return;
    $userId = (int)($vars['userid'] ?? 0);
    $newEmail = (string)($vars['newemail'] ?? ($vars['newEmail'] ?? ''));
    if ($userId <= 0 || $newEmail === '') return;
    securelogin_updateUserState($userId, ['force_verify' => 1]);
    securelogin_recordLog($userId, 'email_changed_force_verify', 'Email changed to ' . $newEmail . '; require verify next login');
});

// 每日自动清理历史日志（仅清理 mod_securelogin_logs，不影响上次登录IP和记住设备信息）
add_hook('DailyCronJob', 1, function($vars) {
    $enabled = securelogin_boolval(securelogin_getAddonSetting('enabled', 'on'), true);
    if (!$enabled) return;

    // 日志分批清理
    $days = (int)securelogin_getAddonSetting('log_retention_days', 30);
    if ($days > 0) {
        $deleted = securelogin_cleanupLogs($days);
        if ((int)$deleted > 0) {
            securelogin_recordLog(0, 'logs_pruned_auto', 'Auto cleanup older than ' . (int)$days . ' days, deleted ' . (int)$deleted . ' rows');
        }
    }

    // 清理过期的记住设备
    $delDevices = securelogin_cleanupExpiredDevices();
    if ((int)$delDevices > 0) {
        securelogin_recordLog(0, 'devices_pruned_auto', 'Auto cleanup expired remember devices, deleted ' . (int)$delDevices . ' rows');
    }

    // 清理历史邮箱变更记录（仅已完成/作废，保留 180 天）
    $delEmails = securelogin_cleanupEmailChanges(180);
    if ((int)$delEmails > 0) {
        securelogin_recordLog(0, 'email_changes_pruned_auto', 'Auto cleanup old email change records (>180d), deleted ' . (int)$delEmails . ' rows');
    }
});
