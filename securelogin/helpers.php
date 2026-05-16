<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function securelogin_getAddonSetting($key, $default = null)
{
    try {
        static $settingsCache = null;
        if ($settingsCache === null) {
            $settingsCache = [];
            $rows = Capsule::table('tbladdonmodules')
                ->where('module', 'securelogin')
                ->get();
            if ($rows) {
                foreach ($rows as $row) {
                    $settingsCache[(string)$row->setting] = $row->value;
                }
            }
        }
        if (array_key_exists($key, (array)$settingsCache)) {
            $value = $settingsCache[$key];
            if ($value === null) {
                return $default;
            }
            if ($value === '' && !is_string($default)) {
                return $default;
            }
            return $value;
        }
    } catch (Exception $e) {
    }
    return $default;
}

function securelogin_boolval($value, $default = false)
{
    if ($value === null) return $default;
    $v = strtolower((string)$value);
    return in_array($v, ['1', 'true', 'on', 'yes'], true);
}

function securelogin_now()
{
    return new DateTime('now', new DateTimeZone('UTC'));
}

function securelogin_getSystemTimezone()
{
    static $tz = null;
    if ($tz instanceof DateTimeZone) {
        return $tz;
    }
    $tzId = null;
    try {
        $tzId = @date_default_timezone_get();
    } catch (Exception $e) {
        $tzId = null;
    }
    if (!$tzId || !is_string($tzId) || $tzId === '') {
        $tzId = 'UTC';
    }
    try {
        $tz = new DateTimeZone($tzId);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    return $tz;
}

function securelogin_getLocalTodayDate()
{
    $tz = securelogin_getSystemTimezone();
    $nowLocal = new DateTime('now', $tz);
    return $nowLocal->format('Y-m-d');
}

function securelogin_mapLangToUi($raw)
{
    $l = strtolower((string)$raw);
    if (strpos($l, 'en') === 0) return 'en';
    if (strpos($l, 'zh') === 0 || strpos($l, 'chinese') === 0) return 'zh';
    return 'en';
}

function securelogin_getUiLang($vars = null)
{
    try {
        // Session-level override first (set when user toggles language on page within the login session)
        $override = isset($_SESSION['securelogin_ui_lang']) ? (string)$_SESSION['securelogin_ui_lang'] : '';
        if ($override === 'zh' || $override === 'en') {
            return $override;
        }
        // Otherwise follow WHMCS current language (session or provided by $vars)
        $raw = isset($_SESSION['Language']) ? (string)$_SESSION['Language'] : '';
        if (!$raw && is_array($vars) && isset($vars['language'])) {
            $raw = (string)$vars['language'];
        }
        return securelogin_mapLangToUi($raw);
    } catch (Exception $e) {
        return 'en';
    }
}

function securelogin_msg($key, $lang = 'en', array $params = [])
{
    $lang = ($lang === 'zh') ? 'zh' : 'en';
    $dict = [
        'zh' => [
            'session_invalid' => '会话已过期或来源不可信，请刷新页面后重试。',
            'locked' => '多次失败已锁定，请稍后再试。',
            'code_expired' => '验证码已过期，请重新发送。',
            'code_invalid' => '验证码不正确。剩余尝试次数：{attempts}',
            'totp_invalid' => '动态口令错误，剩余尝试次数：{attempts}。',
            'please_send_code' => '请先发送验证码。',
            'resend_too_frequent' => '发送太频繁，请稍后再试。',
            'daily_limit' => '今日验证码发送次数已达上限。',
            'resent_ok' => '验证码已重新发送，请查收邮件。',
            'locked_remain' => '多次验证失败账户已锁定， 请等待{mins} 分 {secs} 秒后重试。',
            'email_change_success' => '邮箱变更验证成功。',
            'email_change_expired' => '邮箱变更验证码已过期，请重新修改邮箱以获取新验证码。',
            'email_change_invalid' => '邮箱变更验证码不正确。剩余尝试次数：{attempts}',
            'email_change_none' => '暂无邮箱变更需要验证。',
            'addon_disabled_or_not_logged_in' => '插件未启用或未登录',
        ],
        'en' => [
            'session_invalid' => 'Session expired or invalid source. Please refresh and try again.',
            'addon_disabled_or_not_logged_in' => 'Module disabled or not logged in.',
            'locked' => 'Too many failed attempts. Please try again later.',
            'code_expired' => 'The verification code has expired. Please resend.',
            'code_invalid' => 'Incorrect verification code. Attempts left: {attempts}',
            'totp_invalid' => 'Invalid authenticator code. Attempts left: {attempts}.',
            'please_send_code' => 'Please send a verification code first.',
            'resend_too_frequent' => 'You are sending too frequently. Please try again later.',
            'daily_limit' => 'Daily limit for verification codes has been reached.',
            'resent_ok' => 'A new verification code has been sent. Please check your email.',
            'locked_remain' => 'Too many failed attempts. Remaining {mins} min {secs} s.',
            'email_change_success' => 'Email change verification succeeded.',
            'email_change_expired' => 'The email change code has expired. Please modify your email again to get a new code.',
            'email_change_invalid' => 'Incorrect email change code. Attempts left: {attempts}',
            'email_change_none' => 'There is no pending email change verification.',
        ],
    ];
    $text = $dict[$lang][$key] ?? ($dict['en'][$key] ?? (string)$key);
    foreach ($params as $k => $v) {
        $text = str_replace('{' . $k . '}', (string)$v, $text);
    }
    return $text;
}

function securelogin_getClientIp()
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) {
        $parts = explode(',', $ip);
        $ip = trim($parts[0]);
    }
    return $ip;
}

function securelogin_getUserAgent()
{
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function securelogin_getAcceptLanguage()
{
    return $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
}

function securelogin_computeServerFingerprint($userAgent, $acceptLanguage)
{
    // Strengthened fingerprint: include Sec-CH hints and Accept header if available
    $secUa = $_SERVER['HTTP_SEC_CH_UA'] ?? '';
    $secUaPlatform = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '';
    $secUaMobile = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $dnt = $_SERVER['HTTP_DNT'] ?? '';
    $parts = [
        (string)$userAgent,
        (string)$acceptLanguage,
        (string)$secUa,
        (string)$secUaPlatform,
        (string)$secUaMobile,
        (string)$accept,
        (string)$dnt,
    ];
    return hash('sha256', implode('|', $parts));
}

// Backward compatible legacy fingerprint (UA + Accept-Language only)
function securelogin_computeLegacyFingerprint($userAgent, $acceptLanguage)
{
    $parts = [$userAgent, $acceptLanguage];
    return hash('sha256', implode('|', $parts));
}

function securelogin_isHttps()
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    $xfp = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($xfp === 'https') return true;
    // Cloudflare header (JSON like): {"scheme":"https"}
    $cfv = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
    if ($cfv && stripos($cfv, 'https') !== false) return true;
    return false;
}

function securelogin_getCsrfToken($context)
{
    $key = 'securelogin_csrf_' . preg_replace('/[^a-z0-9_]/i', '', (string)$context);
    if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || strlen($_SESSION[$key]) < 16) {
        $_SESSION[$key] = bin2hex(random_bytes(16));
    }
    return $_SESSION[$key];
}

function securelogin_validateCsrf($context, $token)
{
    $key = 'securelogin_csrf_' . preg_replace('/[^a-z0-9_]/i', '', (string)$context);
    $t = (string)$token;
    return isset($_SESSION[$key]) && is_string($_SESSION[$key]) && hash_equals($_SESSION[$key], $t);
}

function securelogin_http_get_json($url, $timeoutSeconds = 1.0)
{
    $response = null;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $timeoutMs = max(100, (int)round($timeoutSeconds * 1000));
        if (defined('CURLOPT_CONNECTTIMEOUT_MS')) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $timeoutMs);
        }
        if (defined('CURLOPT_TIMEOUT_MS')) {
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        }
        // Fallback seconds options for older libcurl
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(1, (int)ceil($timeoutSeconds)));
        curl_setopt($ch, CURLOPT_TIMEOUT, max(1, (int)ceil($timeoutSeconds)));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 300) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) return $decoded;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(1, (int)ceil($timeoutSeconds)),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body !== false) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) return $decoded;
        }
    }
    return null;
}

function securelogin_resolveCity($ip)
{
    // 城市解析已停用，统一返回 null，不进行任何外部网络请求
    return null;
}

function securelogin_randomDigits($length = 6)
{
    $digits = '';
    for ($i = 0; $i < $length; $i++) {
        $digits .= random_int(0, 9);
    }
    return $digits;
}

function securelogin_randomToken($bytes = 32)
{
    return bin2hex(random_bytes($bytes));
}

function securelogin_currentScript()
{
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
    if (!$script && isset($_SERVER['REQUEST_URI'])) {
        $script = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }
    $script = basename((string)$script);
    return strtolower($script);
}

function securelogin_isPaymentWhitelistedPage()
{
    // 允许访问的支付相关页面（在支付宽限期内）
    $script = securelogin_currentScript();
    $action = strtolower((string)($_REQUEST['a'] ?? $_REQUEST['action'] ?? ''));

    if ($script === 'viewinvoice.php') {
        return true;
    }
    if ($script === 'creditcard.php') {
        return true;
    }
    if ($script === 'cart.php') {
        if (in_array($action, ['checkout', 'complete', 'view'], true)) {
            return true;
        }
        // 有些支付流程不带 a 参数，视作允许
        if ($action === '') {
            return true;
        }
    }
    if ($script === 'clientarea.php') {
        // 某些支付网关/版本会走这里
        if (in_array($action, ['makepayment', 'masspay', 'addfunds'], true)) {
            return true;
        }
    }
    return false;
}

function securelogin_isCheckoutReturnTarget($returnTo)
{
    $returnTo = (string)$returnTo;
    $returnToLower = strtolower($returnTo);
    if ($returnToLower === '') return false;
    // 常见支付/发票/购物车路径关键词
    $keywords = [
        'viewinvoice.php',
        'creditcard.php',
        'cart.php',
        'a=checkout',
        'a=complete',
        'a=view',
        'makepayment',
        'masspay',
        'addfunds',
    ];
    foreach ($keywords as $kw) {
        if (strpos($returnToLower, $kw) !== false) {
            return true;
        }
    }
    return false;
}

function securelogin_recordLog($userId, $event, $message = '', $ip = null)
{
    try {
        Capsule::table('mod_securelogin_logs')->insert([
            'userid' => (int)$userId,
            'event' => $event,
            'message' => $message,
            'ip' => $ip ?? securelogin_getClientIp(),
            'created_at' => securelogin_now()->format('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
    }
}

function securelogin_getUserState($userId)
{
    $state = Capsule::table('mod_securelogin_state')->where('userid', (int)$userId)->first();
    if (!$state) {
        // 建立初始状态记录（向后兼容：若不存在 one_time_exempt 列则不写入）
        $insert = [
            'userid' => (int)$userId,
            'force_verify' => 0,
            'last_verified_login_at' => null,
            'last_login_ip' => null,
            'last_login_fingerprint' => null,
            'last_email' => null,
            'locked_until' => null,
            'created_at' => securelogin_now()->format('Y-m-d H:i:s'),
            'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
        ];
        try {
            $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_state LIKE 'one_time_exempt'");
            if ($col) {
                $insert['one_time_exempt'] = 0;
            }
        } catch (Exception $e) {}
        Capsule::table('mod_securelogin_state')->insert($insert);
        $state = Capsule::table('mod_securelogin_state')->where('userid', (int)$userId)->first();
    }
    return $state;
}

function securelogin_updateUserState($userId, array $data)
{
    $data['updated_at'] = securelogin_now()->format('Y-m-d H:i:s');
    Capsule::table('mod_securelogin_state')->where('userid', (int)$userId)->update($data);
}

function securelogin_getOrCreateCodeRow($userId)
{
    $row = Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->first();
    if (!$row) {
        Capsule::table('mod_securelogin_codes')->insert([
            'userid' => (int)$userId,
            'code' => null,
            'expires_at' => null,
            'attempts_left' => 0,
            'resend_count' => 0,
            'last_sent_at' => null,
            'locked_until' => null,
            'created_at' => securelogin_now()->format('Y-m-d H:i:s'),
            'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
        ]);
        $row = Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->first();
    }
    return $row;
}

function securelogin_isLocked($userId)
{
    $row = Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->first();
    if ($row && $row->locked_until) {
        $now = securelogin_now();
        $locked = new DateTime($row->locked_until, new DateTimeZone('UTC'));
        return $locked > $now;
    }
    return false;
}

function securelogin_getLockRemainingSeconds($userId)
{
    $row = Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->first();
    if ($row && $row->locked_until) {
        $now = securelogin_now();
        $locked = new DateTime($row->locked_until, new DateTimeZone('UTC'));
        $diff = $locked->getTimestamp() - $now->getTimestamp();
        return max(0, (int)$diff);
    }
    return 0;
}

function securelogin_lockUntil($userId, $minutes)
{
    $until = securelogin_now()->modify("+{$minutes} minutes")->format('Y-m-d H:i:s');
    Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->update([
        'locked_until' => $until,
        'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
    ]);
}

function securelogin_clearLock($userId)
{
    Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->update([
        'locked_until' => null,
        'attempts_left' => 0,
        'resend_count' => 0,
        'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
    ]);
}

function securelogin_sendCodeEmail($userId, $code, $templateName = 'Secure Login Verification Code', $overrideEmail = null)
{
    try {
        if (!function_exists('sendMessage')) {
            securelogin_recordLog($userId, 'email_send_failed', 'sendMessage unavailable for template ' . $templateName);
            return false;
        }
        $merge = ['code' => $code];
        if ($overrideEmail) {
            $merge['email'] = $overrideEmail;
        }
        // WHMCS sendMessage throws on failure; treat no-exception as success
        sendMessage($templateName, $userId, $merge);
        return true;
    } catch (Exception $e) {
        securelogin_recordLog($userId, 'email_send_failed', 'Template ' . $templateName . ' error: ' . $e->getMessage());
        return false;
    }
}

function securelogin_sendEmailChangeCode($userId, $newEmail, $code)
{
    try {
        if (!function_exists('sendMessage')) {
            securelogin_recordLog($userId, 'email_send_failed', 'sendMessage unavailable for email change');
            return false;
        }
        // 指定接收邮箱：WHMCS sendMessage 支持通过 merge fields 的 email 参数覆盖接收方
        sendMessage('Secure Login Email Change Code', $userId, [
            'code' => $code,
            'email' => $newEmail,
        ]);
        return true;
    } catch (Exception $e) {
        securelogin_recordLog($userId, 'email_send_failed', 'Email change template error: ' . $e->getMessage());
        return false;
    }
}

function securelogin_issueEmailChangeCode($userId, $newEmail, $config)
{
    $expiryMinutes = (int)($config['code_expiry_minutes'] ?? 5);
    $maxAttempts = (int)($config['max_attempts'] ?? 5);

    $code = securelogin_randomDigits(6);
    $expiresAt = securelogin_now()->modify("+{$expiryMinutes} minutes")->format('Y-m-d H:i:s');
    $sentAt = securelogin_now()->format('Y-m-d H:i:s');

    // 作废该用户其它 pending 记录
    Capsule::table('mod_securelogin_email_changes')
        ->where('userid', (int)$userId)
        ->where('status', 'pending')
        ->update([
            'status' => 'voided',
            'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
        ]);

    Capsule::table('mod_securelogin_email_changes')->insert([
        'userid' => (int)$userId,
        'new_email' => $newEmail,
        'code' => $code,
        'expires_at' => $expiresAt,
        'attempts_left' => $maxAttempts,
        'resend_count' => 0,
        'last_sent_at' => $sentAt,
        'status' => 'pending',
        'created_at' => securelogin_now()->format('Y-m-d H:i:s'),
        'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
    ]);

    securelogin_recordLog($userId, 'email_change_code_issued', 'Issued code to new email ' . $newEmail);
    securelogin_sendEmailChangeCode($userId, $newEmail, $code);
}

function securelogin_verifyEmailChangeCode($userId, $inputCode)
{
    $row = Capsule::table('mod_securelogin_email_changes')
        ->where('userid', (int)$userId)
        ->where('status', 'pending')
        ->orderBy('id', 'desc')
        ->first();
    if (!$row) return ['ok' => false, 'reason' => 'no_request'];
    $now = securelogin_now();
    if ($row->expires_at && new DateTime($row->expires_at, new DateTimeZone('UTC')) < $now) {
        return ['ok' => false, 'reason' => 'expired'];
    }
    if ((string)$row->code === (string)$inputCode) {
        Capsule::table('mod_securelogin_email_changes')->where('id', (int)$row->id)->update([
            'status' => 'verified',
            'verified_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);
        securelogin_recordLog($userId, 'email_change_verified', 'Email change verified to ' . $row->new_email);
        return ['ok' => true, 'new_email' => $row->new_email];
    }
    $attemptsLeft = max(0, ((int)$row->attempts_left) - 1);
    Capsule::table('mod_securelogin_email_changes')->where('id', (int)$row->id)->update([
        'attempts_left' => $attemptsLeft,
        'updated_at' => $now->format('Y-m-d H:i:s'),
    ]);
    securelogin_recordLog($userId, 'email_change_invalid', 'Invalid code for email change');
    return ['ok' => false, 'reason' => 'invalid', 'attempts_left' => $attemptsLeft];
}

function securelogin_issueNewCode($userId, $config, $templateName = 'Secure Login Verification Code')
{
    // 若当前用户处于验证码锁定期，禁止重新发送/生成验证码
    if (securelogin_isLocked($userId)) {
        securelogin_recordLog($userId, 'skip_issue_locked', 'Skip issuing code while locked');
        $_SESSION['securelogin_send_last_ok'] = false;
        $_SESSION['securelogin_send_last_error'] = 'locked';
        return [null, null];
    }

    $expiryMinutes = (int)($config['code_expiry_minutes'] ?? 5);
    $maxAttempts = (int)($config['max_attempts'] ?? 5);

    $row = securelogin_getOrCreateCodeRow($userId);
    list($availableIn, $remaining) = securelogin_canResend($userId, $config);

    $now = securelogin_now();
    $hasValid = ($row->code && $row->expires_at && (new DateTime($row->expires_at, new DateTimeZone('UTC'))) >= $now);

    // 日上限优先于冷却（互斥处理）：达到上限直接返回且清零倒计时
    if ($remaining <= 0) {
        securelogin_recordLog($userId, 'send_skipped_max', 'Daily max reached');
        $_SESSION['securelogin_send_last_ok'] = false;
        $_SESSION['securelogin_send_last_error'] = 'maxed';
        $_SESSION['securelogin_send_last_available_in'] = 0;
        $_SESSION['securelogin_send_last_remaining'] = 0;
        return [null, null];
    }

    // 频控：任意入口均遵循 resend_interval 限制
    if ($availableIn > 0) {
        securelogin_recordLog($userId, 'send_skipped_throttle', 'Throttled, wait ' . (int)$availableIn . 's');
        $_SESSION['securelogin_send_last_ok'] = false;
        $_SESSION['securelogin_send_last_error'] = 'throttle';
        $_SESSION['securelogin_send_last_available_in'] = (int)$availableIn;
        $_SESSION['securelogin_send_last_remaining'] = (int)$remaining;
        return [null, null];
    }

    if ($hasValid) {
        $code = securelogin_randomDigits(6);
        $expiresAt = securelogin_now()->modify("+{$expiryMinutes} minutes")->format('Y-m-d H:i:s');
        $sentAt = securelogin_now()->format('Y-m-d H:i:s');
        $ok = securelogin_sendCodeEmail($userId, $code, $templateName);
        if (!$ok) {
            $_SESSION['securelogin_send_last_ok'] = false;
            $_SESSION['securelogin_send_last_error'] = 'mail_failed';
            $_SESSION['securelogin_send_last_available_in'] = 0;
            $_SESSION['securelogin_send_last_remaining'] = (int)$remaining;
            return [null, null];
        }
        // 原子更新每日计数：基于 last_sent_local_date（系统时区的自然日）
        $todayLocal = securelogin_getLocalTodayDate();
        $conn = Capsule::connection();
        $conn->affectingStatement(
            "UPDATE mod_securelogin_codes SET code = ?, expires_at = ?, last_sent_at = ?, resend_count = IF(last_sent_local_date = ?, resend_count + 1, 1), last_sent_local_date = ?, updated_at = ? WHERE userid = ?",
            [
                $code,
                $expiresAt,
                $sentAt,
                $todayLocal,
                $todayLocal,
                securelogin_now()->format('Y-m-d H:i:s'),
                (int)$userId,
            ]
        );
        securelogin_recordLog($userId, 'code_resent_login', 'Code resent by login trigger');
        $_SESSION['securelogin_send_last_ok'] = true;
        unset($_SESSION['securelogin_send_last_error']);
        return [$code, $expiresAt];
    }

    // 发行全新验证码（遵循每日次数限制，重置尝试次数）
    if ($remaining <= 0) {
        securelogin_recordLog($userId, 'send_skipped_max', 'Daily max reached');
        $_SESSION['securelogin_send_last_ok'] = false;
        $_SESSION['securelogin_send_last_error'] = 'maxed';
        $_SESSION['securelogin_send_last_available_in'] = 0;
        $_SESSION['securelogin_send_last_remaining'] = 0;
        return [null, null];
    }
    $code = securelogin_randomDigits(6);
    $expiresAt = securelogin_now()->modify("+{$expiryMinutes} minutes")->format('Y-m-d H:i:s');
    $sentAt = securelogin_now()->format('Y-m-d H:i:s');
    $ok = securelogin_sendCodeEmail($userId, $code, $templateName);
    if (!$ok) {
        $_SESSION['securelogin_send_last_ok'] = false;
        $_SESSION['securelogin_send_last_error'] = 'mail_failed';
        $_SESSION['securelogin_send_last_available_in'] = 0;
        $_SESSION['securelogin_send_last_remaining'] = (int)$remaining;
        return [null, null];
    }
    // 原子更新每日计数（新发行）：基于 last_sent_local_date（系统时区自然日）
    $todayLocal = securelogin_getLocalTodayDate();
    $conn = Capsule::connection();
    $conn->affectingStatement(
        "UPDATE mod_securelogin_codes SET code = ?, expires_at = ?, attempts_left = ?, last_sent_at = ?, resend_count = IF(last_sent_local_date = ?, resend_count + 1, 1), last_sent_local_date = ?, updated_at = ? WHERE userid = ?",
        [
            $code,
            $expiresAt,
            (int)$maxAttempts,
            $sentAt,
            $todayLocal,
            $todayLocal,
            securelogin_now()->format('Y-m-d H:i:s'),
            (int)$userId,
        ]
    );

    securelogin_recordLog($userId, 'code_issued', 'Issued new code');
    $_SESSION['securelogin_send_last_ok'] = true;
    unset($_SESSION['securelogin_send_last_error']);

    return [$code, $expiresAt];
}

function securelogin_canResend($userId, $config, $scene = 'login')
{
    $scene = (string)$scene;
    if ($scene === 'totp_disable') {
        $interval = (int)($config['totp_disable_resend_interval_seconds'] ?? 60);
        $maxPerDay = (int)($config['totp_disable_max_resend'] ?? 3);
    } else {
        $interval = (int)($config['resend_interval_seconds'] ?? 60);
        $maxPerDay = (int)($config['max_resend'] ?? 5);
    }
    if ($interval <= 0) $interval = 60;
    if ($maxPerDay <= 0) $maxPerDay = ($scene === 'totp_disable') ? 3 : 5;
    $row = securelogin_getOrCreateCodeRow($userId);

    $availableIn = 0;
    if ($row->last_sent_at) {
        $last = new DateTime($row->last_sent_at, new DateTimeZone('UTC'));
        $canAt = clone $last;
        $canAt->modify("+{$interval} seconds");
        $diff = $canAt->getTimestamp() - securelogin_now()->getTimestamp();
        $availableIn = max(0, $diff);
    }
    // Daily window now based on last_sent_local_date (system timezone local date)
    $todayLocal = securelogin_getLocalTodayDate();
    $countToday = 0;
    if (!empty($row->last_sent_local_date) && (string)$row->last_sent_local_date === (string)$todayLocal) {
        $countToday = (int)$row->resend_count;
    }
    $remaining = max(0, $maxPerDay - $countToday);
    return [$availableIn, $remaining];
}

function securelogin_resendCode($userId, $config, $templateName = 'Secure Login Verification Code', $scene = 'login')
{
    // 若处于锁定状态，直接拒绝重发
    if (securelogin_isLocked($userId)) {
        securelogin_recordLog($userId, 'skip_resend_locked', 'Skip resending code while locked');
        return [false, 0, 0];
    }

    list($availableIn, $remaining) = securelogin_canResend($userId, $config, $scene);
    // 日上限优先于冷却：达上限时清零倒计时
    if ($remaining <= 0) {
        return [false, 0, 0];
    }
    if ($availableIn > 0) {
        return [false, $availableIn, $remaining];
    }
    $row = securelogin_getOrCreateCodeRow($userId);
    $code = securelogin_randomDigits(6);
    $expiryMinutes = (int)($config['code_expiry_minutes'] ?? 5);
    $expiresAt = securelogin_now()->modify("+{$expiryMinutes} minutes")->format('Y-m-d H:i:s');
    $sentAt = securelogin_now()->format('Y-m-d H:i:s');

    // 先发送，成功后再更新数据库与计数，避免失败时产生"假阳性"
    $ok = securelogin_sendCodeEmail($userId, $code, $templateName);
    if (!$ok) {
        securelogin_recordLog($userId, 'email_send_failed', 'Resend failed for template ' . $templateName);
        return [false, 0, (int)$remaining];
    }

    // 原子更新每日计数：基于 last_sent_local_date（系统时区自然日）
    $todayLocal = securelogin_getLocalTodayDate();
    $conn = Capsule::connection();
    $conn->affectingStatement(
        "UPDATE mod_securelogin_codes SET code = ?, expires_at = ?, last_sent_at = ?, resend_count = IF(last_sent_local_date = ?, resend_count + 1, 1), last_sent_local_date = ?, updated_at = ? WHERE userid = ?",
        [
            $code,
            $expiresAt,
            $sentAt,
            $todayLocal,
            $todayLocal,
            securelogin_now()->format('Y-m-d H:i:s'),
            (int)$userId,
        ]
    );
    securelogin_recordLog($userId, 'code_resent', 'Code resent');
    // 重新计算剩余次数，确保返回最新数值
    list($_ai, $remainingAfter) = securelogin_canResend($userId, $config, $scene);
    return [true, 0, (int)$remainingAfter];
}

function securelogin_verifyCodeValue($userId, $inputCode, $config)
{
    if (securelogin_isLocked($userId)) {
        return ['ok' => false, 'reason' => 'locked'];
    }
    $row = securelogin_getOrCreateCodeRow($userId);
    if (!$row->code || !$row->expires_at) {
        return ['ok' => false, 'reason' => 'no_code'];
    }
    $now = securelogin_now();
    $expiresAt = new DateTime($row->expires_at, new DateTimeZone('UTC'));
    if ($expiresAt < $now) {
        return ['ok' => false, 'reason' => 'expired', 'attempts_left' => (int)$row->attempts_left];
    }
    if ((string)$row->code === (string)$inputCode) {
        Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->update([
            'code' => null,
            'expires_at' => null,
            'attempts_left' => 0,
            'resend_count' => 0,
            'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
        ]);
        securelogin_recordLog($userId, 'verify_success', 'Code verified');
        return ['ok' => true];
    }
    $attemptsLeft = max(0, ((int)$row->attempts_left) - 1);
    Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->update([
        'attempts_left' => $attemptsLeft,
        'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
    ]);
    securelogin_recordLog($userId, 'verify_failed', 'Invalid code');
    if ($attemptsLeft <= 0) {
        // interpret addon setting as hours and convert to minutes
        $lockHours = (int)($config['lock_minutes'] ?? 1);
        $lockMinutes = max(1, $lockHours * 60);
        securelogin_lockUntil($userId, $lockMinutes);
        return ['ok' => false, 'reason' => 'locked'];
    }
    return ['ok' => false, 'reason' => 'invalid', 'attempts_left' => $attemptsLeft];
}

function securelogin_consumeAttemptAndMaybeLock($userId, $config, $logEvent = 'verify_failed')
{
    if (securelogin_isLocked($userId)) {
        return ['ok' => false, 'reason' => 'locked', 'attempts_left' => 0];
    }
    $maxAttempts = max(1, (int)($config['max_attempts'] ?? 5));
    $row = securelogin_getOrCreateCodeRow($userId);
    $currentAttempts = (int)($row->attempts_left ?? 0);
    if ($currentAttempts <= 0) {
        $currentAttempts = $maxAttempts;
    }
    $attemptsLeft = max(0, $currentAttempts - 1);
    Capsule::table('mod_securelogin_codes')->where('userid', (int)$userId)->update([
        'attempts_left' => $attemptsLeft,
        'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
    ]);
    securelogin_recordLog($userId, $logEvent, 'Invalid verification token');
    if ($attemptsLeft <= 0) {
        $lockHours = (int)($config['lock_minutes'] ?? 1);
        $lockMinutes = max(1, $lockHours * 60);
        securelogin_lockUntil($userId, $lockMinutes);
        return ['ok' => false, 'reason' => 'locked', 'attempts_left' => 0];
    }
    return ['ok' => false, 'reason' => 'invalid', 'attempts_left' => $attemptsLeft];
}

function securelogin_getRememberCookieName()
{
    return 'sl_device';
}

function securelogin_findDeviceByToken($userId, $token)
{
    if (!$token) return null;
    $tokenHash = hash('sha256', $token);
    $rec = Capsule::table('mod_securelogin_devices')
        ->where('userid', (int)$userId)
        ->where('token_hash', $tokenHash)
        ->first();
    return $rec ?: null;
}

function securelogin_validateRememberDevice($userId)
{
    $cookieName = securelogin_getRememberCookieName();
    $token = $_COOKIE[$cookieName] ?? '';
    if (!$token) return false;
    $device = securelogin_findDeviceByToken($userId, $token);
    if (!$device) return false;
    $now = securelogin_now();
    if ($device->expires_at && new DateTime($device->expires_at, new DateTimeZone('UTC')) < $now) {
        return false;
    }
    $ua = securelogin_getUserAgent();
    $al = securelogin_getAcceptLanguage();
    $fpNew = securelogin_computeServerFingerprint($ua, $al);
    $fpLegacy = securelogin_computeLegacyFingerprint($ua, $al);
    if ($device->fingerprint_hash) {
        if ($device->fingerprint_hash !== $fpNew && $device->fingerprint_hash !== $fpLegacy) {
            return false;
        }
    }
    return true;
}

function securelogin_rememberDevice($userId, $fpHash, $rememberDays)
{
    $token = securelogin_randomToken(32);
    $tokenHash = hash('sha256', $token);
    $ip = securelogin_getClientIp();
    $ua = securelogin_getUserAgent();
    $expiresAt = securelogin_now()->modify("+{$rememberDays} days")->format('Y-m-d H:i:s');
    Capsule::table('mod_securelogin_devices')->insert([
        'userid' => (int)$userId,
        'token_hash' => $tokenHash,
        'fingerprint_hash' => $fpHash,
        'user_agent' => substr((string)$ua, 0, 500),
        'last_ip' => $ip,
        'expires_at' => $expiresAt,
        'created_at' => securelogin_now()->format('Y-m-d H:i:s'),
        'updated_at' => securelogin_now()->format('Y-m-d H:i:s'),
    ]);

    $cookieName = securelogin_getRememberCookieName();
    $expireTs = time() + (86400 * (int)$rememberDays);
    $isHttps = securelogin_isHttps();
    if (PHP_VERSION_ID >= 70300) {
        setcookie($cookieName, $token, [
            'expires' => $expireTs,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        // Best-effort for older PHP: append SameSite in path attribute
        $path = '/; samesite=Lax';
        setcookie($cookieName, $token, $expireTs, $path, '', $isHttps, true);
    }
}

function securelogin_cleanupLogs($days)
{
    $days = (int)$days;
    if ($days <= 0) return 0;
    try {
        $cutoff = securelogin_now()->modify("-{$days} days")->format('Y-m-d H:i:s');
        $total = 0;
        $batch = 5000;
        $iterations = 0;
        $conn = Capsule::connection();
        do {
            $affected = 0;
            try {
                $affected = (int)$conn->affectingStatement("DELETE FROM mod_securelogin_logs WHERE created_at < ? LIMIT {$batch}", [$cutoff]);
            } catch (Exception $e) {
                // Fallback: attempt builder delete without LIMIT once
                if ($total === 0) {
                    $affected = (int)Capsule::table('mod_securelogin_logs')->where('created_at', '<', $cutoff)->delete();
                } else {
                    $affected = 0;
                }
            }
            $total += $affected;
            $iterations++;
            if ($iterations > 200) break;
        } while ($affected >= $batch);
        return (int)$total;
    } catch (Exception $e) {
        return 0;
    }
}

function securelogin_cleanupExpiredDevices()
{
    try {
        $now = securelogin_now()->format('Y-m-d H:i:s');
        $total = 0; $batch = 5000; $iter = 0;
        $conn = Capsule::connection();
        do {
            $affected = (int)$conn->affectingStatement("DELETE FROM mod_securelogin_devices WHERE expires_at IS NOT NULL AND expires_at < ? LIMIT {$batch}", [$now]);
            $total += $affected;
            $iter++;
            if ($iter > 200) break;
        } while ($affected >= $batch);
        return (int)$total;
    } catch (Exception $e) {
        return 0;
    }
}

function securelogin_cleanupEmailChanges($retentionDays = 180)
{
    $days = (int)$retentionDays;
    if ($days <= 0) return 0;
    try {
        $cutoff = securelogin_now()->modify("-{$days} days")->format('Y-m-d H:i:s');
        $total = 0; $batch = 5000; $iter = 0;
        $conn = Capsule::connection();
        do {
            $affected = (int)$conn->affectingStatement("DELETE FROM mod_securelogin_email_changes WHERE created_at < ? AND status IN ('verified','voided') LIMIT {$batch}", [$cutoff]);
            $total += $affected;
            $iter++;
            if ($iter > 200) break;
        } while ($affected >= $batch);
        return (int)$total;
    } catch (Exception $e) {
        return 0;
    }
}

function securelogin_touchCoreLastLogin($userId)
{
    try {
        Capsule::table('tblclients')->where('id', (int)$userId)->update([
            'lastlogin' => Capsule::raw('NOW()'),
        ]);
    } catch (Exception $e) {}
}

function securelogin_hardenSession()
{
    try {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    } catch (Exception $e) {}
}

function securelogin_isWhmcs2faEnabledForUser($userId)
{
    $uid = (int)$userId;
    if ($uid <= 0) {
        return false;
    }
    try {
        $client = Capsule::table('tblclients')
            ->where('id', $uid)
            ->first();
        if (!$client) {
            return false;
        }

        // WHMCS 7 常见字段：authmodule（开启2FA后通常为具体模块名）
        if (isset($client->authmodule)) {
            $authModule = trim((string)$client->authmodule);
            if ($authModule !== '') {
                return true;
            }
        }

        // 兼容部分环境/版本字段（若存在且为真值则视为已开启）
        if (isset($client->twofaenabled)) {
            $v = strtolower(trim((string)$client->twofaenabled));
            if (in_array($v, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

function securelogin_base32Decode($b32)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string)$b32));
    $bits = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $v = strpos($alphabet, $b32[$i]);
        if ($v === false) continue;
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $out .= chr(bindec(substr($bits, $i, 8)));
    }
    return $out;
}

function securelogin_base32Encode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0; $i < strlen($bits); $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function securelogin_generateTotpSecret($bytes = 20)
{
    return securelogin_base32Encode(random_bytes((int)$bytes));
}

function securelogin_totpCodeForSlice($secretBase32, $timeSlice)
{
    $secret = securelogin_base32Decode($secretBase32);
    $time = pack('N*', 0) . pack('N*', (int)$timeSlice);
    $hash = hash_hmac('sha1', $time, $secret, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    $code = $truncated % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

function securelogin_verifyTotpCode($secretBase32, $inputCode, $window = 1)
{
    $code = preg_replace('/\D/', '', (string)$inputCode);
    if (strlen($code) !== 6) return false;
    $slice = (int)floor(time() / 30);
    for ($w = -$window; $w <= $window; $w++) {
        if (hash_equals(securelogin_totpCodeForSlice($secretBase32, $slice + $w), $code)) {
            return true;
        }
    }
    return false;
}

function securelogin_getTotpRow($userId)
{
    return Capsule::table('mod_securelogin_totp')->where('userid', (int)$userId)->first();
}

function securelogin_ensureTotpPreferenceColumns()
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    try {
        $col1 = Capsule::select("SHOW COLUMNS FROM mod_securelogin_totp LIKE 'backup_email_enabled'");
        if (!$col1) {
            Capsule::statement("ALTER TABLE mod_securelogin_totp ADD COLUMN backup_email_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER enabled");
        }
        $col2 = Capsule::select("SHOW COLUMNS FROM mod_securelogin_totp LIKE 'preferred_method'");
        if (!$col2) {
            Capsule::statement("ALTER TABLE mod_securelogin_totp ADD COLUMN preferred_method VARCHAR(10) NOT NULL DEFAULT 'totp' AFTER backup_email_enabled");
        }
    } catch (Exception $e) {
        // Ignore here; caller will gracefully fallback.
    }
    $ensured = true;
}

function securelogin_setUserTotpSecret($userId, $secretBase32)
{
    securelogin_ensureTotpPreferenceColumns();
    $now = securelogin_now()->format('Y-m-d H:i:s');
    $row = securelogin_getTotpRow($userId);
    $payload = [
        'secret_enc' => base64_encode((string)$secretBase32),
        'enabled' => 1,
        'backup_email_enabled' => 1,
        'updated_at' => $now,
    ];
    if ($row) {
        Capsule::table('mod_securelogin_totp')->where('userid', (int)$userId)->update($payload);
    } else {
        $payload['userid'] = (int)$userId;
        $payload['created_at'] = $now;
        Capsule::table('mod_securelogin_totp')->insert($payload);
    }
}

function securelogin_setUserTotpBackupEmailEnabled($userId, $enabled)
{
    securelogin_ensureTotpPreferenceColumns();
    $uid = (int)$userId;
    if ($uid <= 0) return;
    $now = securelogin_now()->format('Y-m-d H:i:s');
    $row = securelogin_getTotpRow($uid);
    if ($row) {
        Capsule::table('mod_securelogin_totp')->where('userid', $uid)->update([
            'backup_email_enabled' => $enabled ? 1 : 0,
            'updated_at' => $now,
        ]);
    } else {
        Capsule::table('mod_securelogin_totp')->insert([
            'userid' => $uid,
            'secret_enc' => '',
            'enabled' => 0,
            'backup_email_enabled' => $enabled ? 1 : 0,
            'preferred_method' => 'totp',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function securelogin_isUserTotpBackupEmailEnabled($userId)
{
    securelogin_ensureTotpPreferenceColumns();
    $row = securelogin_getTotpRow((int)$userId);
    if (!$row) return true;
    return (int)($row->backup_email_enabled ?? 1) === 1;
}

function securelogin_disableUserTotp($userId)
{
    $uid = (int)$userId;
    if ($uid <= 0) return;
    Capsule::table('mod_securelogin_totp')->where('userid', $uid)->delete();
}

function securelogin_getUserPreferredVerifyMethod($userId)
{
    securelogin_ensureTotpPreferenceColumns();
    $row = securelogin_getTotpRow((int)$userId);
    if (!$row) return 'totp';
    $m = strtolower(trim((string)($row->preferred_method ?? 'totp')));
    return in_array($m, ['totp', 'email'], true) ? $m : 'totp';
}

function securelogin_setUserPreferredVerifyMethod($userId, $method)
{
    securelogin_ensureTotpPreferenceColumns();
    $uid = (int)$userId;
    if ($uid <= 0) return;
    $m = strtolower(trim((string)$method));
    if (!in_array($m, ['totp', 'email'], true)) $m = 'totp';
    $now = securelogin_now()->format('Y-m-d H:i:s');
    $row = securelogin_getTotpRow($uid);
    if ($row) {
        Capsule::table('mod_securelogin_totp')->where('userid', $uid)->update([
            'preferred_method' => $m,
            'updated_at' => $now,
        ]);
    } else {
        Capsule::table('mod_securelogin_totp')->insert([
            'userid' => $uid,
            'secret_enc' => '',
            'enabled' => 0,
            'backup_email_enabled' => 1,
            'preferred_method' => $m,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function securelogin_clearRememberCookie()
{
    $cookieName = securelogin_getRememberCookieName();
    $isHttps = securelogin_isHttps();
    if (PHP_VERSION_ID >= 70300) {
        setcookie($cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        $path = '/; samesite=Lax';
        setcookie($cookieName, '', time() - 3600, $path, '', $isHttps, true);
    }
    unset($_COOKIE[$cookieName]);
}

function securelogin_getUserTotpSecret($userId)
{
    $row = securelogin_getTotpRow($userId);
    if (!$row || (int)$row->enabled !== 1 || empty($row->secret_enc)) return '';
    $secret = base64_decode((string)$row->secret_enc, true);
    return is_string($secret) ? $secret : '';
}
