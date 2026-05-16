<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Secure Login 插件主入口
 * 包含插件配置、安装、卸载、后台输出、客户端输出等函数
 */

use WHMCS\Database\Capsule;

require_once __DIR__ . '/helpers.php';

// 插件配置
function securelogin_config()
{
    return [
        'name' => '安全登录 (Secure Login)',
        'description' => '在高风险登录时，强制用户邮箱验证码验证',
        'author' => 'Your Company',
        'language' => 'chinese',
        'version' => '0.1',
        'fields' => [
            'enabled' => [
                'FriendlyName' => '启用',
                'Type' => 'yesno',
                'Description' => '是否启用本插件',
                'Default' => 'on',
            ],
            'days_threshold' => [
                'FriendlyName' => '超过多少天未登录需要验证',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '15',
            ],
            'enable_geo_check' => [
                'FriendlyName' => '启用异地/设备检测',
                'Type' => 'yesno',
                'Description' => '如检测到 IP/设备变化也需要验证码验证',
            ],
            'enable_first_login' => [
                'FriendlyName' => '启用首次登录验证',
                'Type' => 'yesno',
                'Description' => '首次登录时也要求邮箱验证码验证',
                'Default' => 'on',
            ],
            'bypass_if_whmcs_2fa_enabled' => [
                'FriendlyName' => '原生2FA用户跳过本插件',
                'Type' => 'yesno',
                'Description' => '若用户已开启 WHMCS 原生2FA，则登录时跳过本插件邮箱验证码验证',
                'Default' => '',
            ],
            'enable_builtin_totp' => [
                'FriendlyName' => '启用插件内置TOTP',
                'Type' => 'yesno',
                'Description' => '启用后支持 Google Authenticator/Authy 动态口令验证',
                'Default' => 'on',
            ],
            'allow_email_backup_when_totp' => [
                'FriendlyName' => 'TOTP场景允许邮箱备份验证',
                'Type' => 'yesno',
                'Description' => '用户已绑定TOTP时，仍允许使用邮箱验证码作为备份方式',
                'Default' => 'on',
            ],
            // 邮箱变更验证码逻辑已移除
            'code_expiry_minutes' => [
                'FriendlyName' => '验证码有效期(分钟)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '5',
            ],
            'resend_interval_seconds' => [
                'FriendlyName' => '验证码重发间隔(秒)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '60',
            ],
            'max_resend' => [
                'FriendlyName' => '最大重发次数',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '5',
            ],
            'max_attempts' => [
                'FriendlyName' => '最大错误次数',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '5',
            ],
            'lock_minutes' => [
                'FriendlyName' => '错误锁定时间(小时)（配置键: lock_minutes）',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '1',
            ],
            'notify_on_totp_disabled' => [
                'FriendlyName' => '关闭TOTP发送安全通知邮件',
                'Type' => 'yesno',
                'Description' => '默认开启。用户在安全中心关闭 TOTP 后发送安全通知邮件（含时间/IP）',
                'Default' => 'on',
            ],
            'securitycenter_guard_refresh_seconds' => [
                'FriendlyName' => '安全中心状态刷新间隔(秒)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '15',
                'Description' => '锁定倒计时结束后前端等待N秒自动轻量刷新，以同步 guard 状态。',
            ],
            'remember_device_days' => [
                'FriendlyName' => '记住设备天数',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '30',
            ],
            'log_retention_days' => [
                'FriendlyName' => '日志保留天数',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '30',
                'Description' => '自动清理超过 N 天的历史事件日志（仅清理 mod_securelogin_logs，不影响上次登录IP和记住设备信息）',
            ],
            ],
            ];
            }

// 激活插件时执行
function securelogin_activate()
{
    try {
        // 创建数据库表（如尚未创建）
        if (!Capsule::schema()->hasTable('mod_securelogin_logs')) {
            Capsule::schema()->create('mod_securelogin_logs', function ($table) {
                $table->increments('id');
                $table->integer('userid');
                $table->string('event');
                $table->text('message')->nullable();
                $table->string('ip')->nullable();
                $table->timestamp('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
            });
        }
        // 索引优化：日志表
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_logs WHERE Key_name='idx_logs_userid_id'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_logs ADD INDEX idx_logs_userid_id (userid, id)");
            }
        } catch (Exception $e) {}
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_logs WHERE Key_name='idx_logs_created_at'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_logs ADD INDEX idx_logs_created_at (created_at)");
            }
        } catch (Exception $e) {}

        if (!Capsule::schema()->hasTable('mod_securelogin_state')) {
            Capsule::schema()->create('mod_securelogin_state', function ($table) {
                $table->integer('userid');
                $table->tinyInteger('force_verify')->default(0);
                $table->tinyInteger('one_time_exempt')->default(0);
                $table->timestamp('last_verified_login_at')->nullable();
                $table->string('last_login_ip', 64)->nullable();
                $table->string('last_login_city', 128)->nullable();
                $table->string('last_login_fingerprint', 128)->nullable();
                $table->string('last_email', 255)->nullable();
                $table->timestamp('locked_until')->nullable();
                $table->timestamp('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->index('userid');
            });
        }
        // 迁移：为已有表添加 last_email 列
        try {
            $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_state LIKE 'last_email'");
            if (!$col) {
                Capsule::statement("ALTER TABLE mod_securelogin_state ADD COLUMN last_email VARCHAR(255) NULL AFTER last_login_fingerprint");
            }
        } catch (Exception $e) {}

         // 迁移：为已有表添加 last_login_city 列
         try {
             $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_state LIKE 'last_login_city'");
             if (!$col) {
                 Capsule::statement("ALTER TABLE mod_securelogin_state ADD COLUMN last_login_city VARCHAR(128) NULL AFTER last_login_ip");
             }
         } catch (Exception $e) {}

         // 迁移：为已有表添加 one_time_exempt 列
         try {
             $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_state LIKE 'one_time_exempt'");
             if (!$col) {
                 Capsule::statement("ALTER TABLE mod_securelogin_state ADD COLUMN one_time_exempt TINYINT(1) NOT NULL DEFAULT 0 AFTER force_verify");
             }
         } catch (Exception $e) {}

         if (!Capsule::schema()->hasTable('mod_securelogin_codes')) {
            Capsule::schema()->create('mod_securelogin_codes', function ($table) {
                $table->integer('userid');
                $table->string('code', 12)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->integer('attempts_left')->default(0);
                $table->integer('resend_count')->default(0);
                $table->timestamp('last_sent_at')->nullable();
                $table->date('last_sent_local_date')->nullable();
                $table->timestamp('locked_until')->nullable();
                $table->timestamp('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->index('userid');
            });
        }
        // 迁移：为 codes 表添加 last_sent_local_date 列（用于每日计数原子更新）
        try {
            $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_codes LIKE 'last_sent_local_date'");
            if (!$col) {
                Capsule::statement("ALTER TABLE mod_securelogin_codes ADD COLUMN last_sent_local_date DATE NULL AFTER last_sent_at");
            }
        } catch (Exception $e) {}
        // 索引优化：codes 表唯一索引（若失败，说明存在重复数据，忽略）
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_codes WHERE Key_name='uq_codes_userid'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_codes ADD UNIQUE KEY uq_codes_userid (userid)");
            }
        } catch (Exception $e) {
            try { securelogin_recordLog(0, 'migrate_codes_unique_failed', 'Add unique index on mod_securelogin_codes.userid failed: ' . $e->getMessage()); } catch (Exception $e2) {}
        }
        // 索引优化：state 表唯一索引（若失败，说明存在重复数据，忽略）
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_state WHERE Key_name='uq_state_userid'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_state ADD UNIQUE KEY uq_state_userid (userid)");
            }
        } catch (Exception $e) {
            try { securelogin_recordLog(0, 'migrate_state_unique_failed', 'Add unique index on mod_securelogin_state.userid failed: ' . $e->getMessage()); } catch (Exception $e2) {}
        }

        if (!Capsule::schema()->hasTable('mod_securelogin_devices')) {
            Capsule::schema()->create('mod_securelogin_devices', function ($table) {
                $table->increments('id');
                $table->integer('userid');
                $table->string('token_hash', 128);
                $table->string('fingerprint_hash', 128)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('last_ip', 64)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->index('userid');
                $table->index('token_hash');
            });
        }
        // 索引优化：devices 表（token_hash 唯一 + expires_at 索引）
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_devices WHERE Key_name='uq_devices_token_hash'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_devices ADD UNIQUE KEY uq_devices_token_hash (token_hash)");
            }
        } catch (Exception $e) {}
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_devices WHERE Key_name='idx_devices_expires_at'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_devices ADD INDEX idx_devices_expires_at (expires_at)");
            }
        } catch (Exception $e) {}

        if (!Capsule::schema()->hasTable('mod_securelogin_email_changes')) {
            Capsule::schema()->create('mod_securelogin_email_changes', function ($table) {
                $table->increments('id');
                $table->integer('userid');
                $table->string('new_email', 255);
                $table->string('code', 12)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->integer('attempts_left')->default(0);
                $table->integer('resend_count')->default(0);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->string('status', 20)->default('pending'); // pending, verified, voided
                $table->timestamp('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->index(['userid', 'status']);
            });
        }
        if (!Capsule::schema()->hasTable('mod_securelogin_totp')) {
            Capsule::schema()->create('mod_securelogin_totp', function ($table) {
                $table->integer('userid');
                $table->string('secret_enc', 255);
                $table->tinyInteger('enabled')->default(1);
                $table->tinyInteger('backup_email_enabled')->default(1);
                $table->string('preferred_method', 10)->default('totp');
                $table->timestamp('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->index('userid');
            });
        }
        if (!Capsule::schema()->hasTable('mod_securelogin_totp_disable_guard')) {
            Capsule::schema()->create('mod_securelogin_totp_disable_guard', function ($table) {
                $table->integer('userid');
                $table->integer('attempts_left')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamp('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->index('userid');
            });
        }
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_totp_disable_guard WHERE Key_name='uq_totp_disable_guard_userid'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_totp_disable_guard ADD UNIQUE KEY uq_totp_disable_guard_userid (userid)");
            }
        } catch (Exception $e) {}
        try {
            $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_totp LIKE 'backup_email_enabled'");
            if (!$col) {
                Capsule::statement("ALTER TABLE mod_securelogin_totp ADD COLUMN backup_email_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER enabled");
            }
        } catch (Exception $e) {}
        try {
            $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_totp LIKE 'preferred_method'");
            if (!$col) {
                Capsule::statement("ALTER TABLE mod_securelogin_totp ADD COLUMN preferred_method VARCHAR(10) NOT NULL DEFAULT 'totp' AFTER backup_email_enabled");
            }
        } catch (Exception $e) {}
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_totp WHERE Key_name='uq_totp_userid'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_totp ADD UNIQUE KEY uq_totp_userid (userid)");
            }
        } catch (Exception $e) {}
        // 索引优化：email_changes 表
        try {
            $idx = Capsule::select("SHOW INDEX FROM mod_securelogin_email_changes WHERE Key_name='idx_email_changes_created_at'");
            if (!$idx) {
                Capsule::statement("ALTER TABLE mod_securelogin_email_changes ADD INDEX idx_email_changes_created_at (created_at)");
            }
        } catch (Exception $e) {}

        // 自动创建邮件模板
        $templateName = 'Secure Login Verification Code';
        $existing = Capsule::table('tblemailtemplates')->where('name', $templateName)->first();
        if (!$existing) {
            Capsule::table('tblemailtemplates')->insert([
                'type' => 'general',
                'name' => $templateName,
                'subject' => '【安全登录】您的验证码',
                'message' => "尊敬的客户，\n\n您本次登录操作需要验证邮箱。\n验证码为：{\$code}\n有效期：5 分钟。\n\n如果不是您本人操作，请及时联系客服。\n\n--\n安全团队",
                'plaintext' => 1,
                'custom' => 1,
                'disabled' => 0,
            ]);
        }

        // 邮箱变更验证邮件模板
        $templateName2 = 'Secure Login Email Change Code';
        $existing2 = Capsule::table('tblemailtemplates')->where('name', $templateName2)->first();
        if (!$existing2) {
            Capsule::table('tblemailtemplates')->insert([
                'type' => 'general',
                'name' => $templateName2,
                'subject' => '【邮箱变更验证】验证码',
                'message' => "尊敬的客户，\n\n我们检测到您的账户邮箱地址已变更。\n为确认新邮箱可用，请输入验证码：{\$code}\n有效期：5 分钟。\n\n如非本人操作，请尽快联系客服。",
                'plaintext' => 1,
                'custom' => 1,
                'disabled' => 0,
            ]);
        }

        // 注册验证码模板（仅用于注册场景）
        $templateName3 = 'Secure Login Registration Code';
        $existing3 = Capsule::table('tblemailtemplates')->where('name', $templateName3)->first();
        if (!$existing3) {
            Capsule::table('tblemailtemplates')->insert([
                'type' => 'general',
                'name' => $templateName3,
                'subject' => '【注册验证】验证您的邮箱以激活账户',
                'message' => "尊敬的{\$client_name}，欢迎加入 {\$company_name}！\n\n为确保是您本人操作，请在页面输入以下验证码完成邮箱验证：\n验证码：{\$code}\n有效期：5 分钟。\n\n如非您本人操作，请忽略本邮件或联系支持。\n\n— {\$company_name} 安全团队",
                'plaintext' => 1,
                'custom' => 1,
                'disabled' => 0,
            ]);
        }
        $templateName4 = 'Secure Login TOTP Disabled Notice';
        $existing4 = Capsule::table('tblemailtemplates')->where('name', $templateName4)->first();
        if (!$existing4) {
            Capsule::table('tblemailtemplates')->insert([
                'type' => 'general',
                'name' => $templateName4,
                'subject' => '【安全通知】您的TOTP已被关闭',
                'message' => "尊敬的客户，\n\n检测到您的账户已关闭 TOTP 动态口令验证。\n时间(UTC)：{\$event_time_utc}\nIP：{\$ip}\n\n若非本人操作，请立即联系支持并修改账户密码。\n\n--\n安全团队",
                'plaintext' => 1,
                'custom' => 1,
                'disabled' => 0,
            ]);
        }

        return ['status' => 'success', 'description' => '安全登录插件已激活，邮件模板已创建'];
    } catch (Exception $e) {
        return ['status' => 'error', 'description' => '激活失败: ' . $e->getMessage()];
    }
}

// 卸载插件
function securelogin_deactivate()
{
    return ['status' => 'success', 'description' => '安全登录插件已停用'];
}

// 后台输出
function securelogin_output($vars)
{
    $action = $_POST['sl_action'] ?? $_GET['sl_action'] ?? '';
    $targetUserId = (int)($_POST['userid'] ?? $_GET['userid'] ?? 0);

    $isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
    $adminTokenOk = $isPost && securelogin_validateCsrf('admin', (string)($_POST['csrf_token'] ?? ''));
    if ($isPost && !$adminTokenOk && $action) {
        echo '<div class="errorbox"><strong>CSRF 校验失败：</strong>请刷新页面后重试。</div>';
    }

    // 轻量迁移：确保 one_time_exempt 列存在（便于“取消强制验证”授予一次性豁免）
    try {
        $col = Capsule::select("SHOW COLUMNS FROM mod_securelogin_state LIKE 'one_time_exempt'");
        if (!$col) {
            Capsule::statement("ALTER TABLE mod_securelogin_state ADD COLUMN one_time_exempt TINYINT(1) NOT NULL DEFAULT 0 AFTER force_verify");
        }
    } catch (Exception $e) {}

    if ($adminTokenOk && $targetUserId) {
        if ($action === 'unlock') {
            securelogin_clearLock($targetUserId);
            echo '<div class="infobox"><strong>已解除锁定：</strong> 用户ID ' . (int)$targetUserId . '</div>';
        } elseif ($action === 'force_lock') {
            $minutes = (int)($_POST['minutes'] ?? 0);
            if ($minutes <= 0) { $minutes = 30; }
            securelogin_lockUntil($targetUserId, $minutes);
            echo '<div class="infobox"><strong>已锁定用户：</strong> 用户ID ' . (int)$targetUserId . '，时长 ' . (int)$minutes . ' 分钟</div>';
        } elseif ($action === 'force_on') {
            securelogin_updateUserState($targetUserId, ['force_verify' => 1]);
            echo '<div class="infobox"><strong>已设置下次登录需要验证：</strong> 用户ID ' . (int)$targetUserId . '</div>';
        } elseif ($action === 'force_off') {
            // 取消强制验证并授予一次性豁免，确保下次登录直接放行
            securelogin_updateUserState($targetUserId, ['force_verify' => 0, 'one_time_exempt' => 1]);
            securelogin_recordLog($targetUserId, 'admin_force_off_exempt', 'Admin cancelled force verify; one-time exemption granted');
            echo '<div class="infobox"><strong>已取消强制验证并授予一次性豁免：</strong> 用户ID ' . (int)$targetUserId . '</div>';
        } elseif ($action === 'purge_devices') {
            Capsule::table('mod_securelogin_devices')->where('userid', $targetUserId)->delete();
            echo '<div class="infobox"><strong>已清除记住设备：</strong> 用户ID ' . (int)$targetUserId . '</div>';
        } elseif ($action === 'reset_totp') {
            $deleted = 0;
            try { $deleted = (int)Capsule::table('mod_securelogin_totp')->where('userid', $targetUserId)->delete(); } catch (Exception $e) {}
            securelogin_recordLog($targetUserId, 'admin_totp_reset', 'Admin reset user TOTP binding, deleted rows=' . (int)$deleted);
            echo '<div class="infobox"><strong>已重置 TOTP 绑定：</strong> 用户ID ' . (int)$targetUserId . '</div>';
        }
    }

    // 全局操作：手动清理日志
    if ($adminTokenOk && $action === 'cleanup_logs') {
        $cfgDays = (int)securelogin_getAddonSetting('log_retention_days', 30);
        $days = (int)($_POST['cleanup_days'] ?? 0);
        if ($days <= 0) { $days = $cfgDays; }
        $deleted = securelogin_cleanupLogs($days);
        securelogin_recordLog(0, 'logs_pruned_manual', 'Manual cleanup older than ' . (int)$days . ' days, deleted ' . (int)$deleted . ' rows');
        echo '<div class="infobox"><strong>日志清理完成：</strong> 删除了超过 ' . (int)$days . ' 天的 ' . (int)$deleted . ' 条日志。</div>';
    }

    echo '<h2>安全登录插件后台</h2>';
    echo '<p>查看用户状态、日志，并可进行手动解锁、强制验证、清除记住设备。</p>';

    $csrfAdmin = securelogin_getCsrfToken('admin');
    $logRetentionDays = (int)securelogin_getAddonSetting('log_retention_days', 30);

    // 日志维护：手动清理
    echo '<div class="tablebg" style="margin:10px 0; padding:10px;">
            <h3 style="margin-top:0;">日志维护</h3>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfAdmin) . '" />
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="sl_action" value="cleanup_logs" />
                    <span>清理超过</span>
                    <input type="number" name="cleanup_days" value="' . (int)$logRetentionDays . '" min="1" style="width:90px;" />
                    <span>天的历史事件日志</span>
                    <button class="btn btn-danger" type="submit" onclick="return confirm(\'确认立即清理历史日志？\');">手动清理日志</button>
                </div>
            </form>
          </div>';

    echo '<form method="get" action="">
            <input type="hidden" name="module" value="securelogin" />
            <div style="margin:10px 0;display:flex;gap:8px;align-items:center;">
                <label>用户ID：</label>
                <input type="number" name="userid" value="' . ($targetUserId ?: '') . '" />
                <span style="margin-left:12px;">或 邮箱：</span>
                <input type="text" name="email" value="' . htmlspecialchars((string)($_GET['email'] ?? '')) . '" />
                <button class="btn btn-default" type="submit">查询</button>
            </div>
          </form>';

    // 若未提供ID但提供了邮箱，则先解析邮箱对应的ID
    if (!$targetUserId && isset($_GET['email']) && $_GET['email'] !== '') {
        $email = trim((string)$_GET['email']);
        try {
            $client = Capsule::table('tblclients')->where('email', $email)->first();
            if ($client) {
                $targetUserId = (int)$client->id;
            } else {
                echo '<div class="errorbox">未找到该邮箱对应的用户：' . htmlspecialchars($email) . '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="errorbox">查询失败：' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    if ($targetUserId) {
        $state = securelogin_getUserState($targetUserId);
        echo '<div class="tablebg">
                <table class="datatable" width="100%" cellspacing="0" cellpadding="3">
                    <tr><th colspan="2">用户状态</th></tr>
                    <tr><td>用户ID</td><td>' . (int)$targetUserId . '</td></tr>
                    <tr><td>强制验证</td><td>' . ((int)$state->force_verify ? '是' : '否') . '</td></tr>
                    <tr><td>上次已验证登录时间</td><td>' . ($state->last_verified_login_at ?: '-') . '</td></tr>
                    <tr><td>上次登录IP</td><td>' . ($state->last_login_ip ?: '-') . '</td></tr>
                </table>
              </div>';

        echo '<form method="post" style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="userid" value="' . (int)$targetUserId . '" />
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfAdmin) . '" />
                <button class="btn btn-primary" name="sl_action" value="unlock" type="submit" onclick="return confirm(\'确认执行该操作？\');">解除锁定</button>
                <button class="btn btn-warning" name="sl_action" value="force_on" type="submit" onclick="return confirm(\'确认执行该操作？\');">强制下次验证</button>
                <button class="btn btn-success" name="sl_action" value="force_off" type="submit" onclick="return confirm(\'确认执行该操作？\');">取消强制验证</button>
                <button class="btn btn-danger" name="sl_action" value="purge_devices" type="submit" onclick="return confirm(\'确认执行该操作？\');">清除记住设备</button>
                <button class="btn btn-danger" name="sl_action" value="reset_totp" type="submit" onclick="return confirm(\'确认重置该用户 TOTP 绑定？该操作不可撤销。\');">重置TOTP绑定</button>
                <span style="margin-left:10px;">强制锁定：</span>
                <input type="number" name="minutes" value="30" min="1" style="width:90px;" />
                <button class="btn btn-danger" name="sl_action" value="force_lock" type="submit" onclick="return confirm(\'确认执行该操作？\');">锁定</button>
                <div style="display:flex; gap:6px;">
                  <button class="btn btn-danger" name="sl_action" value="force_lock" onclick="this.form.minutes.value=30; return confirm(\'确认执行该操作？\');" type="submit">30 分钟</button>
                  <button class="btn btn-danger" name="sl_action" value="force_lock" onclick="this.form.minutes.value=60; return confirm(\'确认执行该操作？\');" type="submit">60 分钟</button>
                  <button class="btn btn-danger" name="sl_action" value="force_lock" onclick="this.form.minutes.value=120; return confirm(\'确认执行该操作？\');" type="submit">120 分钟</button>
                </div>
              </form>';

        $logs = Capsule::table('mod_securelogin_logs')->where('userid', $targetUserId)->orderBy('id', 'desc')->limit(100)->get();
        echo '<h3 style="margin-top:18px;">最近日志</h3>';
        echo '<div class="tablebg">
                <table class="datatable" width="100%" cellspacing="0" cellpadding="3">
                    <tr><th>ID</th><th>时间</th><th>事件</th><th>消息</th><th>IP</th></tr>';
        foreach ($logs as $log) {
            $createdLocal = (string)$log->created_at;
            try {
                $dt = new DateTime($createdLocal, new DateTimeZone('UTC'));
                $dt->setTimezone(securelogin_getSystemTimezone());
                $createdLocal = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {}
            echo '<tr>' .
                 '<td>' . (int)$log->id . '</td>' .
                 '<td>' . htmlspecialchars($createdLocal) . '</td>' .
                 '<td>' . htmlspecialchars($log->event) . '</td>' .
                 '<td>' . htmlspecialchars((string)$log->message) . '</td>' .
                 '<td>' . htmlspecialchars((string)$log->ip) . '</td>' .
                 '</tr>';
        }
        echo '   </table>
              </div>';
    } else {
        $logs = Capsule::table('mod_securelogin_logs')->orderBy('id', 'desc')->limit(100)->get();
        echo '<h3>最近全局日志</h3>';
        echo '<div class="tablebg">
                <table class="datatable" width="100%" cellspacing="0" cellpadding="3">
                    <tr><th>ID</th><th>用户ID</th><th>时间</th><th>事件</th><th>消息</th><th>IP</th></tr>';
        foreach ($logs as $log) {
            $createdLocal = (string)$log->created_at;
            try {
                $dt = new DateTime($createdLocal, new DateTimeZone('UTC'));
                $dt->setTimezone(securelogin_getSystemTimezone());
                $createdLocal = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {}
            echo '<tr>' .
                 '<td>' . (int)$log->id . '</td>' .
                 '<td>' . (int)$log->userid . '</td>' .
                 '<td>' . htmlspecialchars($createdLocal) . '</td>' .
                 '<td>' . htmlspecialchars($log->event) . '</td>' .
                 '<td>' . htmlspecialchars((string)$log->message) . '</td>' .
                 '<td>' . htmlspecialchars((string)$log->ip) . '</td>' .
                 '</tr>';
        }
        echo '   </table>
              </div>';
    }
}

// 客户端输出
function securelogin_clientarea($vars)
{
    $userId = (int)($_SESSION['uid'] ?? 0);
    $enabled = securelogin_boolval(securelogin_getAddonSetting('enabled', 'on'), true);
    /* 已移除城市异步更新端点（async_city_update） */
    if (!$enabled || !$userId) {
        $uiLang = function_exists('securelogin_getUiLang') ? securelogin_getUiLang($vars) : 'en';
        $err = function_exists('securelogin_msg') ? securelogin_msg('addon_disabled_or_not_logged_in', $uiLang) : 'Module disabled or not logged in.';
        $pageTitle = ($uiLang === 'zh') ? '安全验证' : 'Security Verification';
        return [
            'pagetitle' => $pageTitle,
            'templatefile' => 'client/verify',
            'requirelogin' => true,
            'vars' => ['error' => $err],
        ];
    }

    $config = [
        'days_threshold' => (int)securelogin_getAddonSetting('days_threshold', 15),
        'enable_geo_check' => securelogin_boolval(securelogin_getAddonSetting('enable_geo_check', ''), false),
        'code_expiry_minutes' => (int)securelogin_getAddonSetting('code_expiry_minutes', 5),
        'resend_interval_seconds' => (int)securelogin_getAddonSetting('resend_interval_seconds', 60),
        'max_resend' => (int)securelogin_getAddonSetting('max_resend', 5),
        'max_attempts' => (int)securelogin_getAddonSetting('max_attempts', 5),
        'lock_minutes' => (int)securelogin_getAddonSetting('lock_minutes', 1),
        'remember_device_days' => (int)securelogin_getAddonSetting('remember_device_days', 30),
        'enable_builtin_totp' => securelogin_boolval(securelogin_getAddonSetting('enable_builtin_totp', 'on'), true),
        'allow_email_backup_when_totp' => securelogin_boolval(securelogin_getAddonSetting('allow_email_backup_when_totp', 'on'), true),
        'notify_on_totp_disabled' => securelogin_boolval(securelogin_getAddonSetting('notify_on_totp_disabled', 'on'), true),
        'securitycenter_guard_refresh_seconds' => max(3, (int)securelogin_getAddonSetting('securitycenter_guard_refresh_seconds', 15)),
    ];
    $actionParam = (string)($_GET['action'] ?? '');

    $csrfToken = securelogin_getCsrfToken('client');

    // Default UI language follows WHMCS; can be overridden per-session by posted ui_lang
    $uiLang = function_exists('securelogin_getUiLang') ? securelogin_getUiLang($vars) : 'en';

    $state = securelogin_getUserState($userId);
    $ua = securelogin_getUserAgent();
    $al = securelogin_getAcceptLanguage();
    $fp = securelogin_computeServerFingerprint($ua, $al);
    $lockedSeconds = securelogin_isLocked($userId) ? securelogin_getLockRemainingSeconds($userId) : 0;

    $message = '';
    $error = '';
    $attemptsLeft = null;
    $lockedUntil = null;
    $countdown = 0;
    $resendRemaining = 0;

    $postAction = (string)($_POST['action'] ?? '');
    $totpSecret = '';
    $totpEnabled = false;
    $totpBackupEmailEnabled = true;
    $preferredMethod = 'totp';
    if ($config['enable_builtin_totp']) {
        try {
            $totpSecret = securelogin_getUserTotpSecret($userId);
            $totpEnabled = ($totpSecret !== '');
            $totpBackupEmailEnabled = securelogin_isUserTotpBackupEmailEnabled($userId);
            $preferredMethod = securelogin_getUserPreferredVerifyMethod($userId);
        } catch (Exception $e) {}
    }

    if ($actionParam === 'securitycenter') {
        $uiLang = function_exists('securelogin_getUiLang') ? securelogin_getUiLang($vars) : 'en';
        if (isset($_POST['ui_lang'])) {
            $uiSel = function_exists('securelogin_mapLangToUi') ? securelogin_mapLangToUi((string)$_POST['ui_lang']) : 'en';
            if ($uiSel === 'zh' || $uiSel === 'en') {
                $_SESSION['securelogin_ui_lang'] = $uiSel;
                $uiLang = $uiSel;
            }
        }
        $scMessage = '';
        $scError = '';
        $disableAttemptsLeft = max(1, (int)$config['max_attempts']);
        $disableLockSeconds = 0;
        if ($totpEnabled) {
            try {
                $guard = securelogin_refreshTotpDisableGuardState($userId, $config);
                $disableAttemptsLeft = max(0, (int)($guard->attempts_left ?? $disableAttemptsLeft));
                $disableLockSeconds = securelogin_getTotpDisableLockRemainingSeconds($userId);
            } catch (Exception $e) {}
        }
        if ($postAction === 'sc_enable_totp') {
            if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
                $scError = ($uiLang === 'zh') ? 'CSRF 校验失败，请刷新后重试。' : 'CSRF validation failed. Please refresh and try again.';
            } else {
                $pending = (string)($_SESSION['securelogin_totp_pending_secret'] ?? '');
                if ($pending === '') {
                    $pending = securelogin_generateTotpSecret(20);
                    $_SESSION['securelogin_totp_pending_secret'] = $pending;
                }
                $code = trim((string)($_POST['code'] ?? ''));
                if (securelogin_verifyTotpCode($pending, $code, 1)) {
                    securelogin_setUserTotpSecret($userId, $pending);
                    unset($_SESSION['securelogin_totp_pending_secret']);
                    $totpSecret = $pending; $totpEnabled = true; $totpBackupEmailEnabled = true;
                    $scMessage = ($uiLang === 'zh') ? 'TOTP 已启用。' : 'TOTP enabled.';
                    securelogin_recordLog($userId, 'totp_enabled', 'User enabled built-in TOTP in security center');
                } else {
                    $scError = ($uiLang === 'zh') ? '动态口令无效，请检查手机时间后重试。' : 'Invalid TOTP code. Please check device time and retry.';
                }
            }
        } elseif ($postAction === 'sc_disable_totp') {
            if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
                $scError = ($uiLang === 'zh') ? 'CSRF 校验失败，请刷新后重试。' : 'CSRF validation failed. Please refresh and try again.';
            } else {
                if (!$totpEnabled) {
                    $scError = ($uiLang === 'zh') ? '当前未启用 TOTP。' : 'TOTP is not enabled.';
                } else {
                    try {
                        $guard = securelogin_refreshTotpDisableGuardState($userId, $config);
                        $disableAttemptsLeft = max(0, (int)($guard->attempts_left ?? $disableAttemptsLeft));
                    } catch (Exception $e) {}
                    $disableLockSeconds = securelogin_getTotpDisableLockRemainingSeconds($userId);
                    if ($disableLockSeconds > 0) {
                        $h = floor($disableLockSeconds / 3600);
                        $m = floor(($disableLockSeconds % 3600) / 60);
                        $s = (int)($disableLockSeconds % 60);
                        $scError = ($uiLang === 'zh')
                            ? ('关闭 TOTP 密码确认已被锁定，请稍后重试（剩余 ' . (int)$h . ' 小时 ' . (int)$m . ' 分 ' . (int)$s . ' 秒）。')
                            : ('TOTP disable password confirmation is temporarily locked. Retry later (remaining ' . (int)$h . 'h ' . (int)$m . 'm ' . (int)$s . 's).');
                    } else {
                    $confirmPassword = trim((string)($_POST['confirm_password'] ?? ''));
                    if ($confirmPassword === '') {
                        $scError = ($uiLang === 'zh') ? '请输入账户密码以确认关闭 TOTP。' : 'Please enter your account password to confirm disabling TOTP.';
                    } else {
                        $client = null;
                        try {
                            $client = Capsule::table('tblclients')->where('id', (int)$userId)->first();
                        } catch (Exception $e) {
                            $client = null;
                        }
                        $storedHash = $client ? (string)($client->password ?? '') : '';
                        $passwordOk = false;
                        if ($storedHash !== '') {
                            $passwordOk = @password_verify($confirmPassword, $storedHash);
                        }
                        if ($passwordOk) {
                        securelogin_disableUserTotp($userId);
                        securelogin_resetTotpDisableGuard($userId, $config);
                        $totpSecret = ''; $totpEnabled = false; $totpBackupEmailEnabled = true;
                        $scMessage = ($uiLang === 'zh') ? 'TOTP 已关闭。' : 'TOTP disabled.';
                        securelogin_recordLog($userId, 'totp_disabled', 'User disabled built-in TOTP in security center via password confirmation');
                        if (!empty($config['notify_on_totp_disabled'])) {
                            securelogin_sendTotpDisabledNoticeEmail($userId, securelogin_getClientIp());
                        }
                        } else {
                            $attemptResult = securelogin_consumeTotpDisablePasswordAttempt($userId, $config);
                            $disableAttemptsLeft = (int)($attemptResult['attempts_left'] ?? 0);
                            $disableLockSeconds = (int)($attemptResult['lock_seconds'] ?? 0);
                            if (!empty($attemptResult['locked'])) {
                                $h = floor($disableLockSeconds / 3600);
                                $m = floor(($disableLockSeconds % 3600) / 60);
                                $s = (int)($disableLockSeconds % 60);
                                $scError = ($uiLang === 'zh')
                                    ? ('账户密码错误次数过多，已禁止关闭 TOTP（剩余 ' . (int)$h . ' 小时 ' . (int)$m . ' 分 ' . (int)$s . ' 秒）。')
                                    : ('Too many incorrect password attempts. Disabling TOTP is locked (remaining ' . (int)$h . 'h ' . (int)$m . 'm ' . (int)$s . 's).');
                            } else {
                                $scError = ($uiLang === 'zh')
                                    ? ('账户密码错误，无法关闭 TOTP。剩余尝试次数：' . (int)$disableAttemptsLeft . '。')
                                    : ('Incorrect account password. Unable to disable TOTP. Attempts left: ' . (int)$disableAttemptsLeft . '.');
                            }
                            securelogin_recordLog($userId, 'totp_disable_password_invalid', 'Failed to disable TOTP due to incorrect password');
                        }
                    }
                    }
                }
            }
        } elseif ($postAction === 'sc_toggle_email_backup') {
            if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
                $scError = ($uiLang === 'zh') ? 'CSRF 校验失败，请刷新后重试。' : 'CSRF validation failed. Please refresh and try again.';
            } else {
                $newVal = (int)($_POST['backup_email_enabled'] ?? 0) === 1;
                securelogin_setUserTotpBackupEmailEnabled($userId, $newVal);
                $pref = (string)($_POST['preferred_method'] ?? 'totp');
                if ($newVal) {
                    securelogin_setUserPreferredVerifyMethod($userId, $pref);
                    $preferredMethod = in_array($pref, ['totp','email'], true) ? $pref : 'totp';
                } else {
                    securelogin_setUserPreferredVerifyMethod($userId, 'totp');
                    $preferredMethod = 'totp';
                }
                $totpBackupEmailEnabled = $newVal;
                if ($newVal) {
                    $methodLabelZh = ($preferredMethod === 'email') ? '邮箱验证码' : '动态口令（TOTP）';
                    $methodLabelEn = ($preferredMethod === 'email') ? 'Email verification code' : 'One-time Password (TOTP)';
                    $scMessage = ($uiLang === 'zh')
                        ? ('已开启邮箱备份验证。首选验证方式已切换为：' . $methodLabelZh)
                        : ('Email backup verification enabled. Preferred verification method switched to: ' . $methodLabelEn);
                } else {
                    $scMessage = ($uiLang === 'zh')
                        ? '已关闭邮箱备份验证。首选验证方式已切换为：动态口令（TOTP）'
                        : 'Email backup verification disabled. Preferred verification method switched to: One-time Password (TOTP).';
                }
                securelogin_recordLog($userId, 'totp_backup_email_toggled', 'Backup email set to ' . ($newVal ? '1' : '0'));
            }
        } elseif ($postAction === 'sc_remove_current_device') {
            if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
                $scError = ($uiLang === 'zh') ? 'CSRF 校验失败，请刷新后重试。' : 'CSRF validation failed. Please refresh and try again.';
            } else {
                $cookieName = securelogin_getRememberCookieName();
                $token = (string)($_COOKIE[$cookieName] ?? '');
                if ($token === '') {
                    $scError = ($uiLang === 'zh') ? '当前未发现记住设备凭据。' : 'No remembered-device credential found.';
                } else {
                    $rec = securelogin_findDeviceByToken($userId, $token);
                    if ($rec) {
                        Capsule::table('mod_securelogin_devices')->where('id', (int)$rec->id)->delete();
                        securelogin_clearRememberCookie();
                        $scMessage = ($uiLang === 'zh') ? '当前设备已移除。' : 'Current device removed.';
                        securelogin_recordLog($userId, 'device_removed_current', 'Removed current remembered device');
                    } else {
                        securelogin_clearRememberCookie();
                        $scError = ($uiLang === 'zh') ? '未找到当前设备记录，已清理本地凭据。' : 'Current device record not found; local token cleared.';
                    }
                }
            }
        } elseif ($postAction === 'sc_remove_all_devices') {
            if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
                $scError = ($uiLang === 'zh') ? 'CSRF 校验失败，请刷新后重试。' : 'CSRF validation failed. Please refresh and try again.';
            } else {
                $deleted = (int)Capsule::table('mod_securelogin_devices')->where('userid', $userId)->delete();
                securelogin_clearRememberCookie();
                $scMessage = ($uiLang === 'zh')
                    ? ('已移除全部记住设备（' . $deleted . ' 条）。')
                    : ('Removed all remembered devices (' . $deleted . ').');
                securelogin_recordLog($userId, 'device_removed_all', 'Removed all remembered devices, count=' . $deleted);
            }
        }

        $provisioning = '';
        $pending = '';
        $rememberDeviceCount = 0;
        $recentFailedAt = '';
        $securityScore = 0;
        $securityLevel = 'low';
        try {
            $rememberDeviceCount = (int)Capsule::table('mod_securelogin_devices')->where('userid', $userId)->count();
        } catch (Exception $e) { $rememberDeviceCount = 0; }
        $deviceList = [];
        try {
            $rows = Capsule::table('mod_securelogin_devices')
                ->where('userid', $userId)
                ->orderBy('updated_at', 'desc')
                ->limit(50)
                ->get();
            foreach ($rows as $r) {
                $deviceList[] = [
                    'id' => (int)$r->id,
                    'user_agent' => (string)($r->user_agent ?? ''),
                    'last_ip' => (string)($r->last_ip ?? ''),
                    'expires_at' => (string)($r->expires_at ?? ''),
                    'updated_at' => (string)($r->updated_at ?? ''),
                ];
            }
        } catch (Exception $e) { $deviceList = []; }
        try {
            $failed = Capsule::table('mod_securelogin_logs')
                ->where('userid', $userId)
                ->where(function($q){
                    $q->where('event', 'like', '%failed%')->orWhere('event', 'like', '%invalid%');
                })
                ->orderBy('id', 'desc')
                ->first();
            if ($failed) { $recentFailedAt = (string)$failed->created_at; }
        } catch (Exception $e) { $recentFailedAt = ''; }
        // 10 分制评分：0-10
        $securityScore = 0;
        if ($totpEnabled) {
            $securityScore += 4;
        }
        if ($totpBackupEmailEnabled) {
            $securityScore += 2;
        }
        if ($recentFailedAt === '') {
            $securityScore += 2;
        }
        if ($rememberDeviceCount <= 5) {
            $securityScore += 2;
        }
        if ($securityScore < 0) $securityScore = 0;
        if ($securityScore > 10) $securityScore = 10;
        if ($securityScore >= 8) $securityLevel = 'high';
        elseif ($securityScore >= 5) $securityLevel = 'medium';
        else $securityLevel = 'low';
        if (!$totpEnabled && $config['enable_builtin_totp']) {
            $pending = (string)($_SESSION['securelogin_totp_pending_secret'] ?? '');
            if ($pending === '') {
                $pending = securelogin_generateTotpSecret(20);
                $_SESSION['securelogin_totp_pending_secret'] = $pending;
            }
            $company = 'WHMCS';
            try { $g = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->first(); if ($g && !empty($g->value)) $company = (string)$g->value; } catch (Exception $e) {}
            $clientEmail = '';
            try { $c = Capsule::table('tblclients')->where('id', $userId)->first(); if ($c && !empty($c->email)) $clientEmail = (string)$c->email; } catch (Exception $e) {}
            $labelRaw = $company . ':' . ($clientEmail !== '' ? $clientEmail : $userId);
            $label = rawurlencode($labelRaw);
            $issuer = rawurlencode($company);
            $provisioning = 'otpauth://totp/' . $label . '?secret=' . rawurlencode($pending) . '&issuer=' . $issuer . '&digits=6&period=30&algorithm=SHA1';
        }

        return [
            'pagetitle' => (($uiLang === 'zh') ? '安全中心' : 'Security Center'),
            'templatefile' => 'client/securitycenter',
            'requirelogin' => true,
            'vars' => [
                'csrf_token' => $csrfToken,
                'message' => $scMessage,
                'error' => $scError,
                'totp_enabled' => (bool)$totpEnabled,
                'totp_secret_pending' => (string)$pending,
                'totp_provisioning_uri' => (string)$provisioning,
                'backup_email_enabled' => (bool)$totpBackupEmailEnabled,
                'whmcs_language' => (string)($_SESSION['Language'] ?? ($vars['language'] ?? 'chinese')),
                'security_score' => (int)$securityScore,
                'security_level' => (string)$securityLevel,
                'remember_device_count' => (int)$rememberDeviceCount,
                'recent_failed_at' => (string)$recentFailedAt,
                'device_list' => $deviceList,
                'preferred_method' => (string)$preferredMethod,
                'disable_attempts_left' => (int)$disableAttemptsLeft,
                'disable_lock_seconds' => (int)$disableLockSeconds,
                'disable_max_attempts' => max(1, (int)$config['max_attempts']),
                'securitycenter_guard_refresh_seconds' => (int)$config['securitycenter_guard_refresh_seconds'],
            ],
        ];
    }

    // Apply per-session UI language override if provided by the form
    if (isset($_POST['ui_lang'])) {
        $uiSel = function_exists('securelogin_mapLangToUi') ? securelogin_mapLangToUi((string)$_POST['ui_lang']) : 'en';
        if ($uiSel === 'zh' || $uiSel === 'en') {
            $_SESSION['securelogin_ui_lang'] = $uiSel;
            $uiLang = $uiSel;
        }
    }
    $defaultPreferred = 'totp';
    if ($totpEnabled && function_exists('securelogin_getUserPreferredVerifyMethod')) {
        try { $defaultPreferred = securelogin_getUserPreferredVerifyMethod($userId); } catch (Exception $e) { $defaultPreferred = 'totp'; }
    }
    if (!$totpBackupEmailEnabled && $defaultPreferred === 'email') {
        $defaultPreferred = 'totp';
    }
    $verifyMethod = (string)($_SESSION['securelogin_verify_method'] ?? '');
    if ($verifyMethod !== 'email' && $verifyMethod !== 'totp') {
        $verifyMethod = $defaultPreferred;
        $_SESSION['securelogin_verify_method'] = $verifyMethod;
    }
    if (!$totpEnabled) {
        $verifyMethod = 'email';
        $_SESSION['securelogin_verify_method'] = 'email';
    } elseif (!$totpBackupEmailEnabled && $verifyMethod === 'email') {
        $verifyMethod = 'totp';
        $_SESSION['securelogin_verify_method'] = 'totp';
    }

    // 登录后不自动发送验证码，仅在用户手动点击“重新发送”时发送

    if ($postAction === 'switch_to_email_mode') {
        if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
            $error = function_exists('securelogin_msg') ? securelogin_msg('session_invalid', $uiLang) : 'Session expired or invalid source. Please refresh and try again.';
        } else {
            $_SESSION['securelogin_verify_method'] = 'email';
            $verifyMethod = 'email';
            $tpl = (string)($_SESSION['securelogin_email_template'] ?? 'Secure Login Verification Code');
            list($ok, $availableIn, $remaining) = securelogin_resendCode($userId, $config, $tpl);
            if ($ok) {
                $message = $uiLang === 'zh' ? '已切换为邮箱验证，验证码已发送。' : 'Switched to email verification and code sent.';
            } else {
                $error = $availableIn > 0
                    ? ($uiLang === 'zh' ? '切换成功，但发送过于频繁，请稍后重试。' : 'Switched, but sending too frequently. Please retry later.')
                    : ($uiLang === 'zh' ? '切换成功，但今日发送次数已达上限。' : 'Switched, but daily email code limit reached.');
            }
            $countdown = (int)$availableIn;
            $resendRemaining = (int)$remaining;
        }
    } elseif ($postAction === 'switch_to_totp_mode') {
        if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
            $error = function_exists('securelogin_msg') ? securelogin_msg('session_invalid', $uiLang) : 'Session expired or invalid source. Please refresh and try again.';
        } else {
            $_SESSION['securelogin_verify_method'] = 'totp';
            $verifyMethod = 'totp';
            $message = $uiLang === 'zh' ? '已切换为动态口令验证。' : 'Switched to TOTP verification.';
        }
    } elseif ($postAction === 'enable_totp') {
        if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
            $error = function_exists('securelogin_msg') ? securelogin_msg('session_invalid', $uiLang) : 'Session expired or invalid source. Please refresh and try again.';
        } else {
            $pending = (string)($_SESSION['securelogin_totp_pending_secret'] ?? '');
            $code = trim((string)($_POST['code'] ?? ''));
            if ($pending !== '' && securelogin_verifyTotpCode($pending, $code, 1)) {
                securelogin_setUserTotpSecret($userId, $pending);
                unset($_SESSION['securelogin_totp_pending_secret']);
                $totpSecret = $pending;
                $totpEnabled = true;
                $message = $uiLang === 'zh' ? 'TOTP 已成功绑定。后续将优先使用动态口令验证。' : 'TOTP has been enabled.';
            } else {
                $error = $uiLang === 'zh' ? '动态口令校验失败，请确认时间同步后重试。' : 'Invalid TOTP code.';
            }
        }
    } elseif ($postAction === 'verify_code') {
        if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
            $error = function_exists('securelogin_msg') ? securelogin_msg('session_invalid', $uiLang) : 'Session expired or invalid source. Please refresh and try again.';
        } else {
            $inputCode = trim((string)($_POST['code'] ?? ''));
            $remember = (int)($_POST['remember_device'] ?? 0) === 1;
            $useEmailBackup = ($verifyMethod === 'email') || ((int)($_POST['use_email_backup'] ?? 0) === 1);
            $verifiedByTotp = false;
            $totpAttemptResult = null;
            if ($totpEnabled && !$useEmailBackup) {
                $verifiedByTotp = securelogin_verifyTotpCode($totpSecret, $inputCode, 1);
                if (!$verifiedByTotp) {
                    $totpAttemptResult = securelogin_consumeAttemptAndMaybeLock($userId, $config, 'totp_verify_failed');
                }
            }
            $result = ['ok' => false];
            if (!$totpEnabled || $useEmailBackup) {
                $result = securelogin_verifyCodeValue($userId, $inputCode, $config);
            } elseif (!$verifiedByTotp && is_array($totpAttemptResult)) {
                $result = $totpAttemptResult;
            } elseif ($verifiedByTotp) {
                $result = ['ok' => true];
            }
            if (!empty($result['ok'])) {
                $_SESSION['securelogin_verified'] = true;
                $_SESSION['securelogin_required'] = false;
                unset($_SESSION['securelogin_payment_grace']);
                $currentIp = securelogin_getClientIp();
                $update = [
                    'force_verify' => 0,
                    'last_verified_login_at' => securelogin_now()->format('Y-m-d H:i:s'),
                    'last_login_ip' => $currentIp,
                    'last_email' => (function($uid){ try{ $c=Capsule::table('tblclients')->where('id',$uid)->first(); return $c? (string)$c->email : null; } catch(Exception $e){ return null; } })($userId),
                    'last_login_fingerprint' => $fp,
                ];
                // 已移除城市解析与写入 last_login_city
                securelogin_updateUserState($userId, $update);
                if ($remember) {
                    securelogin_rememberDevice($userId, $fp, (int)$config['remember_device_days']);
                }
                // 完成登录态：会话固化 + 全局日志
                if (function_exists('logActivity')) {
                    @logActivity('Client completed Secure Login email verification', $userId);
                }
                if (function_exists('securelogin_hardenSession')) {
                    securelogin_hardenSession();
                } else {
                    if (session_status() === PHP_SESSION_ACTIVE) { @session_regenerate_id(true); }
                }
                securelogin_recordLog($userId, 'post_verify_continue', 'User verified and continued');
                if (function_exists('securelogin_touchCoreLastLogin')) { securelogin_touchCoreLastLogin($userId); }
                header('Location: clientarea.php');
                exit;
            } else {
                if ($result['reason'] === 'locked') {
                    $row = securelogin_getOrCreateCodeRow($userId);
                    $lockedUntil = $row->locked_until ?: '';
                    $error = function_exists('securelogin_msg') ? securelogin_msg('locked', $uiLang) : 'Too many failed attempts. Please try again later.';
                } elseif ($result['reason'] === 'expired') {
                    $error = function_exists('securelogin_msg') ? securelogin_msg('code_expired', $uiLang) : 'The verification code has expired. Please resend.';
                    $attemptsLeft = (int)($result['attempts_left'] ?? 0);
                } elseif ($result['reason'] === 'invalid') {
                    $attemptsLeft = (int)($result['attempts_left'] ?? 0);
                    $msgKey = ($totpEnabled && !$useEmailBackup) ? 'totp_invalid' : 'code_invalid';
                    $error = function_exists('securelogin_msg') ? securelogin_msg($msgKey, $uiLang, ['attempts' => $attemptsLeft]) : ('Incorrect verification code. Attempts left: ' . $attemptsLeft);
                } else {
                    $error = function_exists('securelogin_msg') ? securelogin_msg('please_send_code', $uiLang) : 'Please send a verification code first.';
                }
            }
        }
    } elseif ($postAction === 'resend_code') {
        if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
            $error = function_exists('securelogin_msg') ? securelogin_msg('session_invalid', $uiLang) : 'Session expired or invalid source. Please refresh and try again.';
        } else if ($lockedSeconds > 0) {
            $mins = floor($lockedSeconds / 60);
            $secs = $lockedSeconds % 60;
            $error = function_exists('securelogin_msg') ? securelogin_msg('locked_remain', $uiLang, ['mins' => (int)$mins, 'secs' => (int)$secs]) : ('Too many failed attempts. Remaining ' . (int)$mins . ' min ' . (int)$secs . ' s.');
        } else {
            $tpl = (string)($_SESSION['securelogin_email_template'] ?? 'Secure Login Verification Code');
            list($ok, $availableIn, $remaining) = securelogin_resendCode($userId, $config, $tpl);
            if ($ok) {
                $message = function_exists('securelogin_msg') ? securelogin_msg('resent_ok', $uiLang) : 'A new verification code has been sent. Please check your email.';
            } else {
                if ($availableIn > 0) {
                    $error = function_exists('securelogin_msg') ? securelogin_msg('resend_too_frequent', $uiLang) : 'You are sending too frequently. Please try again later.';
                } else {
                    $error = function_exists('securelogin_msg') ? securelogin_msg('daily_limit', $uiLang) : 'Daily limit for verification codes has been reached.';
                }
            }
            $countdown = (int)$availableIn;
            $resendRemaining = (int)$remaining;
        }
    } elseif ($postAction === 'verify_email_change') {
        if (!securelogin_validateCsrf('client', (string)($_POST['csrf_token'] ?? ''))) {
            $error = function_exists('securelogin_msg') ? securelogin_msg('session_invalid', $uiLang) : 'Session expired or invalid source. Please refresh and try again.';
        } else {
            $inputCode = trim((string)($_POST['code'] ?? ''));
            $result = securelogin_verifyEmailChangeCode($userId, $inputCode);
            if ($result['ok']) {
                $message = function_exists('securelogin_msg') ? securelogin_msg('email_change_success', $uiLang) : 'Email change verification succeeded.';
                $_SESSION['securelogin_email_change_verified'] = true;
                $_SESSION['securelogin_email_change_required'] = false;
            } else {
                if ($result['reason'] === 'expired') {
                    $error = function_exists('securelogin_msg') ? securelogin_msg('email_change_expired', $uiLang) : 'The email change code has expired. Please modify your email again to get a new code.';
                } elseif ($result['reason'] === 'invalid') {
                    $attemptsLeft = (int)($result['attempts_left'] ?? 0);
                    $error = function_exists('securelogin_msg') ? securelogin_msg('email_change_invalid', $uiLang, ['attempts' => $attemptsLeft]) : ('Incorrect email change code. Attempts left: ' . $attemptsLeft);
                } else {
                    $error = function_exists('securelogin_msg') ? securelogin_msg('email_change_none', $uiLang) : 'There is no pending email change verification.';
                }
            }
        }
    }

    list($availableIn, $remaining) = securelogin_canResend($userId, $config);
    if ($countdown <= 0) $countdown = (int)$availableIn;
    if ($resendRemaining <= 0) $resendRemaining = (int)$remaining;
    if ($resendRemaining <= 0) $countdown = 0;

    $postPaymentNotice = false;
    // 如果携带支付返回标记或处于支付宽限，展示提示
    $script = function_exists('securelogin_currentScript') ? securelogin_currentScript() : '';
    if ((int)($_SESSION['securelogin_payment_grace'] ?? 0) === 1) {
        if (in_array($script, ['cart.php','viewinvoice.php','creditcard.php','clientarea.php'], true)) {
            $postPaymentNotice = true;
        }
    }

    $totpProvisioningUri = '';
    if ($config['enable_builtin_totp'] && !$totpEnabled) {
        $pending = (string)($_SESSION['securelogin_totp_pending_secret'] ?? '');
        if ($pending === '') {
            $pending = securelogin_generateTotpSecret(20);
            $_SESSION['securelogin_totp_pending_secret'] = $pending;
        }
        $company = 'WHMCS';
        try {
            $g = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->first();
            if ($g && !empty($g->value)) { $company = (string)$g->value; }
        } catch (Exception $e) {}
        $label = rawurlencode($company . ':' . $userId);
        $issuer = rawurlencode($company);
        $totpProvisioningUri = 'otpauth://totp/' . $label . '?secret=' . rawurlencode($pending) . '&issuer=' . $issuer . '&digits=6&period=30&algorithm=SHA1';
    }

    $verifyPageTitle = ($uiLang === 'zh') ? '安全验证' : 'Security Verification';
    return [
        'pagetitle' => $verifyPageTitle,
        'templatefile' => 'client/verify',
        'requirelogin' => true,
        'vars' => [
            'message' => $message,
            'error' => $error,
            'attempts_left' => $attemptsLeft,
            'locked_until' => $lockedUntil,
            'expiry_minutes' => (int)$config['code_expiry_minutes'],
            'remember_device_days' => (int)$config['remember_device_days'],
            'resend_interval_seconds' => (int)$config['resend_interval_seconds'],
            'countdown' => (int)$countdown,
            'resend_remaining' => (int)$resendRemaining,
            'post_payment_notice' => $postPaymentNotice,
            'lock_seconds' => (int)$lockedSeconds,
            'csrf_token' => $csrfToken,
            'whmcs_language' => (string)($_SESSION['Language'] ?? ($vars['language'] ?? 'chinese')),
            'totp_enabled' => (bool)$totpEnabled,
            'totp_provisioning_uri' => (string)$totpProvisioningUri,
            'totp_secret_pending' => (string)($_SESSION['securelogin_totp_pending_secret'] ?? ''),
            'allow_email_backup_when_totp' => (bool)($config['allow_email_backup_when_totp'] && $totpBackupEmailEnabled),
            'verify_method' => (string)$verifyMethod,
        ],
    ];
}
