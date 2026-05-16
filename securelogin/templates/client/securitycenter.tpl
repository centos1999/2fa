<div class="container" style="max-width:980px;margin-top:-45px;" id="sc-root" data-whmcs-lang="{$whmcs_language|default:$language|default:'chinese'}" data-totp-uri="{$totp_provisioning_uri|escape:'html'}" data-disable-attempts-left="{$disable_attempts_left|default:0}" data-disable-max-attempts="{$disable_max_attempts|default:5}" data-disable-lock-seconds="{$disable_lock_seconds|default:0}" data-guard-refresh-seconds="{$securitycenter_guard_refresh_seconds|default:15}">
  {literal}<style>
    .sc-hero{border-radius:16px;padding:20px;background:linear-gradient(135deg,#dff4ff,#dff8ef);color:#0f2f46;box-shadow:0 8px 22px rgba(15,23,42,.08);margin-bottom:16px;border:1px solid #d7ebf8}
    .sc-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .sc-card{background:#fff;border:1px solid #e8eef8;border-radius:14px;padding:16px;box-shadow:0 6px 20px rgba(15,23,42,.06)}
    .sc-title{margin:0 0 10px;font-weight:600}
    .sc-muted{color:#64748b;font-size:12px}
    @media(max-width:900px){.sc-grid{grid-template-columns:1fr}}
  </style>{/literal}
  <div class="sc-hero">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
      <h3 style="margin:0;" id="sc-i18n-title">安全中心</h3>
      <div>
        <button type="button" id="sc-lang-zh" class="btn btn-default btn-xs">中文</button>
        <button type="button" id="sc-lang-en" class="btn btn-default btn-xs">EN</button>
      </div>
    </div>
    <div style="opacity:.95;margin-top:6px;" id="sc-i18n-subtitle">管理动态口令（TOTP）与邮箱备份验证，提升账户安全。</div>
  </div>
  {if $error}<div class="alert alert-danger">{$error}</div>{/if}
  {if $message}<div class="alert alert-success">{$message}</div>{/if}
  <div class="sc-grid">
    <div class="sc-card">
      <h4 class="sc-title" id="sc-i18n-totp-title">动态口令（TOTP）</h4>
      {if $totp_enabled}
        <div class="alert alert-success" id="sc-i18n-totp-enabled" data-pref="{$preferred_method}">已启用：当前登录安全验证场景将优先使用动态口令验证。</div>
        <form method="post">
          <input type="hidden" name="csrf_token" value="{$csrf_token}"/>
          <input type="hidden" name="ui_lang" class="sc-ui-lang" value=""/>
          <div class="alert alert-warning" id="sc-i18n-disable-label">关闭 TOTP 需要账户密码确认。</div>
          {if $disable_lock_seconds>0}
          <div class="alert alert-danger" id="sc-disable-lock-info">
            <span id="sc-i18n-disable-locked">关闭 TOTP 已临时锁定。</span>
            <span id="sc-i18n-disable-lock-remaining-label">剩余</span>
            <span id="sc-disable-lock-h">0</span><span id="sc-i18n-hours">小时</span>
            <span id="sc-disable-lock-m">0</span><span id="sc-i18n-minutes">分</span>
            <span id="sc-disable-lock-s">0</span><span id="sc-i18n-seconds">秒</span>
          </div>
          {else}
          <div class="alert alert-info" id="sc-disable-attempts-info">
            <span id="sc-i18n-disable-attempts">密码确认剩余尝试次数：{$disable_attempts_left} / {$disable_max_attempts}</span>
          </div>
          {/if}
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <input class="form-control" type="password" name="confirm_password" maxlength="255" style="max-width:240px;" placeholder="Account password" autocomplete="current-password" {if $disable_lock_seconds>0}disabled{/if} />
            <button class="btn btn-danger" type="submit" name="action" value="sc_disable_totp" id="sc-i18n-disable-btn" {if $disable_lock_seconds>0}disabled{/if}>确认关闭 TOTP</button>
          </div>
        </form>
      {else}
        <div class="alert alert-info" id="sc-i18n-bind-hint">请使用 Google Authenticator / Authy等工具扫描二维码或添加下方密钥后，输入6位动态码完成绑定。</div>
        <div id="sc-qr" style="width:180px;height:180px;margin:8px auto 12px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;display:flex;align-items:center;justify-content:center;"></div>
        <div class="form-group">
          <label id="sc-i18n-secret-label">Base32 密钥</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input class="form-control" id="sc-totp-secret" readonly value="{$totp_secret_pending}" />
            <button type="button" class="btn btn-default btn-sm" id="sc-copy-secret-btn">复制</button>
          </div>
          <div class="sc-muted" id="sc-copy-secret-msg" style="margin-top:6px;display:none;"></div>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="sc_enable_totp"/>
          <input type="hidden" name="csrf_token" value="{$csrf_token}"/>
          <input type="hidden" name="ui_lang" class="sc-ui-lang" value=""/>
          <label id="sc-i18n-code-label">输入动态码</label>
          <input class="form-control" type="text" name="code" maxlength="6" style="margin:6px 0 10px;" />
          <button class="btn btn-primary" type="submit" id="sc-i18n-enable-btn">启用 TOTP</button>
        </form>
      {/if}
    </div>
    <div class="sc-card">
      <h4 class="sc-title" id="sc-i18n-backup-title">邮箱备份验证</h4>
      <p class="sc-muted" id="sc-i18n-backup-desc">当动态口令不可用时，是否允许改用邮箱验证码完成验证。</p>
      {if !$totp_enabled}
        <div class="alert alert-warning" id="sc-i18n-backup-warn">请先启用 TOTP，再配置邮箱备份策略。</div>
      {else}
        <form method="post">
          <input type="hidden" name="action" value="sc_toggle_email_backup"/>
          <input type="hidden" name="csrf_token" value="{$csrf_token}"/>
          <input type="hidden" name="ui_lang" class="sc-ui-lang" value=""/>
          <label style="display:flex;align-items:center;gap:8px;margin:8px 0 12px;">
            <input type="checkbox" name="backup_email_enabled" value="1" {if $backup_email_enabled}checked{/if}/>
            <span id="sc-i18n-backup-toggle">允许邮箱作为备份验证方式</span>
          </label>
          <div style="margin-bottom:10px;">
            <label id="sc-i18n-preferred-label">首选验证方式</label>
            <select class="form-control" name="preferred_method" id="sc-preferred-method" style="max-width:240px;" {if !$backup_email_enabled}disabled{/if}>
              <option value="totp" {if $preferred_method=='totp'}selected{/if} id="sc-i18n-pref-totp">动态口令（TOTP）</option>
              <option value="email" {if $preferred_method=='email'}selected{/if} id="sc-i18n-pref-email">邮箱验证码</option>
            </select>
            <div class="text-muted" style="margin-top:6px;">
              <span id="sc-i18n-current-pref-label">当前首选验证方式：</span>
              <strong id="sc-i18n-current-pref-value" data-pref="{$preferred_method}">{if $preferred_method=='email'}邮箱验证码{else}动态口令（TOTP）{/if}</strong>
            </div>
          </div>
          <button class="btn btn-default" type="submit" id="sc-i18n-save-btn">保存设置</button>
        </form>
      {/if}
    </div>
  </div>
  <div class="sc-grid" style="margin-top:14px;">
    <div class="sc-card">
      <h4 class="sc-title" id="sc-i18n-summary-title">安全摘要</h4>
      <div><strong id="sc-i18n-score-label">安全评分</strong>：{$security_score}/10（{if $security_level=='high'}<span style="color:#16a34a;" id="sc-i18n-level-text" data-level="high">高</span>{elseif $security_level=='medium'}<span style="color:#d97706;" id="sc-i18n-level-text" data-level="medium">中</span>{else}<span style="color:#dc2626;" id="sc-i18n-level-text" data-level="low">低</span>{/if}）</div>
      <div><strong id="sc-i18n-totp-status-label">TOTP状态</strong>：{if $totp_enabled}<span id="sc-i18n-enabled">已绑定</span>{else}<span id="sc-i18n-disabled">未绑定</span>{/if}</div>
      <div><strong id="sc-i18n-backup-status-label">邮箱备份</strong>：{if $backup_email_enabled}<span id="sc-i18n-backup-on">开启</span>{else}<span id="sc-i18n-backup-off">关闭</span>{/if}</div>
      <div><strong id="sc-i18n-devices-label">记住设备数</strong>：{$remember_device_count}</div>
      <div><strong id="sc-i18n-failed-label">最近异常登录</strong>：{if $recent_failed_at}{$recent_failed_at}{else}<span id="sc-i18n-none">无</span>{/if}</div>
    </div>
    <div class="sc-card">
      <h4 class="sc-title" id="sc-i18n-dev-mgr-title">设备管理</h4>
      <p class="sc-muted" id="sc-i18n-dev-mgr-desc">管理“记住此设备”记录，可移除当前设备或全部设备。</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="sc_remove_current_device"/>
          <input type="hidden" name="csrf_token" value="{$csrf_token}"/>
          <input type="hidden" name="ui_lang" class="sc-ui-lang" value=""/>
          <button class="btn btn-warning btn-sm" type="submit" id="sc-i18n-remove-current">移除当前设备</button>
        </form>
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="sc_remove_all_devices"/>
          <input type="hidden" name="csrf_token" value="{$csrf_token}"/>
          <input type="hidden" name="ui_lang" class="sc-ui-lang" value=""/>
          <button class="btn btn-danger btn-sm" type="submit" id="sc-i18n-remove-all">移除全部设备</button>
        </form>
      </div>
      <div style="overflow:auto;">
        <table class="table table-striped" style="margin-bottom:0;">
          <thead>
            <tr>
              <th id="sc-i18n-col-ua">UA</th>
              <th id="sc-i18n-col-ip">IP</th>
              <th id="sc-i18n-col-exp">过期时间</th>
              <th id="sc-i18n-col-last">最近使用时间</th>
            </tr>
          </thead>
          <tbody>
          {if $device_list|@count > 0}
            {foreach from=$device_list item=d}
            <tr>
              <td style="max-width:320px;word-break:break-word;">{$d.user_agent|escape}</td>
              <td>{$d.last_ip|escape}</td>
              <td>{$d.expires_at|escape}</td>
              <td>{$d.updated_at|escape}</td>
            </tr>
            {/foreach}
          {else}
            <tr><td colspan="4" id="sc-i18n-no-devices">暂无设备记录</td></tr>
          {/if}
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs2@0.0.2/qrcode.min.js"></script>
{literal}<script>
(function(){
  var root = document.getElementById('sc-root');
  var langRaw = (root && root.getAttribute('data-whmcs-lang') || '').toLowerCase();
  var i18n = {
    zh:{title:'安全中心',subtitle:'管理动态口令（TOTP）与邮箱备份验证，提升账户安全。',summaryTitle:'安全摘要',scoreLabel:'安全评分',levelHigh:'高',levelMedium:'中',levelLow:'低',totpStatusLabel:'TOTP状态',backupStatusLabel:'邮箱备份',devicesLabel:'记住设备数',failedLabel:'最近异常登录',none:'无',enabled:'已绑定',disabled:'未绑定',backupOn:'开启',backupOff:'关闭',totpTitle:'动态口令（TOTP）',totpEnabled:'已启用：当前登录安全验证场景将优先使用动态口令验证。',totpEnabledEmailPreferred:'已启用：当前登录安全验证场景将优先使用邮箱验证。',disableLabel:'关闭 TOTP 需要账户密码确认。',disableBtn:'确认关闭 TOTP',disableLocked:'关闭 TOTP 已临时锁定。',disableLockRemaining:'剩余',disableAttempts:function(a,m){return '密码确认剩余尝试次数：'+a+' / '+m;},hours:'小时',minutes:'分',seconds:'秒',bindHint:'请使用 Google Authenticator / Authy等工具扫描二维码或添加下方密钥后，输入6位动态码完成绑定。',secretLabel:'Base32 密钥',copySecret:'复制',copied:'已复制到剪贴板',copyFailed:'复制失败，请手动复制',codeLabel:'输入动态码',enableBtn:'启用 TOTP',devMgrTitle:'设备管理',devMgrDesc:'管理“记住此设备”记录，可移除当前设备或全部设备。',removeCurrent:'移除当前设备',removeAll:'移除全部设备',confirmRemoveAll:'确认移除全部记住设备吗？',colUA:'UA',colIP:'IP',colExp:'过期时间',colLast:'最近使用时间',noDevices:'暂无设备记录',backupTitle:'邮箱备份验证',backupDesc:'当动态口令不可用时，是否允许改用邮箱验证码完成验证。',backupWarn:'请先启用 TOTP，再配置邮箱备份策略。',backupToggle:'允许邮箱作为备份验证方式',preferredLabel:'首选验证方式',prefTotp:'动态口令（TOTP）',prefEmail:'邮箱验证码',saveBtn:'保存设置',currentPrefLabel:'当前首选验证方式：',currentPrefTotp:'动态口令（TOTP）',currentPrefEmail:'邮箱验证码'},
    en:{title:'Security Center',subtitle:'Manage TOTP and email backup verification for stronger account security.',summaryTitle:'Security Summary',scoreLabel:'Security Score',levelHigh:'High',levelMedium:'Medium',levelLow:'Low',totpStatusLabel:'TOTP Status',backupStatusLabel:'Email Backup',devicesLabel:'Remembered Devices',failedLabel:'Latest Abnormal Login',none:'None',enabled:'Bound',disabled:'Not Bound',backupOn:'Enabled',backupOff:'Disabled',totpTitle:'One-time Password (TOTP)',totpEnabled:'Enabled: in login security verification scenarios, authenticator verification will be preferred.',totpEnabledEmailPreferred:'Enabled: in login security verification scenarios, email verification will be preferred.',disableLabel:'Disabling TOTP requires account password confirmation.',disableBtn:'Confirm Disable TOTP',disableLocked:'Disabling TOTP is temporarily locked.',disableLockRemaining:'Remaining',disableAttempts:function(a,m){return 'Password confirmation attempts left: '+a+' / '+m;},hours:'h',minutes:'m',seconds:'s',bindHint:'Use tools like Google Authenticator/Authy to scan the QR code or add the key below, then enter the 6-digit dynamic code to complete binding.',secretLabel:'Base32 Secret',copySecret:'Copy',copied:'Copied to clipboard',copyFailed:'Copy failed, please copy manually',codeLabel:'Enter Authenticator Code',enableBtn:'Enable TOTP',devMgrTitle:'Device Management',devMgrDesc:'Manage remembered devices. You can remove current device or all devices.',removeCurrent:'Remove Current Device',removeAll:'Remove All Devices',confirmRemoveAll:'Confirm removing all remembered devices?',colUA:'UA',colIP:'IP',colExp:'Expires At',colLast:'Last Used At',noDevices:'No device records',backupTitle:'Email Backup Verification',backupDesc:'Allow email code fallback when authenticator is unavailable.',backupWarn:'Enable TOTP first, then configure email backup.',backupToggle:'Allow email as backup verification method',preferredLabel:'Preferred verification method',prefTotp:'One-time Password (TOTP)',prefEmail:'Email verification code',saveBtn:'Save Settings',currentPrefLabel:'Current preferred verification method:',currentPrefTotp:'One-time Password (TOTP)',currentPrefEmail:'Email verification code'}
  };
  var lang = (langRaw.indexOf('zh')===0 || langRaw.indexOf('chinese')===0) ? 'zh' : 'en';
  function t(id,key){ var el=document.getElementById(id); if(el) el.textContent=(i18n[lang]||i18n.en)[key]; }
  var attemptsLeft = parseInt((root && root.getAttribute('data-disable-attempts-left')) || '0', 10) || 0;
  var maxAttempts = parseInt((root && root.getAttribute('data-disable-max-attempts')) || '5', 10) || 5;
  var lockSec = parseInt((root && root.getAttribute('data-disable-lock-seconds')) || '0', 10) || 0;
  var guardRefreshSec = parseInt((root && root.getAttribute('data-guard-refresh-seconds')) || '15', 10) || 15;
  function apply(){ t('sc-i18n-title','title'); t('sc-i18n-subtitle','subtitle'); t('sc-i18n-summary-title','summaryTitle'); t('sc-i18n-score-label','scoreLabel'); t('sc-i18n-totp-status-label','totpStatusLabel'); t('sc-i18n-backup-status-label','backupStatusLabel'); t('sc-i18n-devices-label','devicesLabel'); t('sc-i18n-failed-label','failedLabel'); t('sc-i18n-none','none'); t('sc-i18n-enabled','enabled'); t('sc-i18n-disabled','disabled'); t('sc-i18n-backup-on','backupOn'); t('sc-i18n-backup-off','backupOff'); var lv=document.getElementById('sc-i18n-level-text'); if(lv){ var key=(lv.getAttribute('data-level')==='high'?'levelHigh':(lv.getAttribute('data-level')==='medium'?'levelMedium':'levelLow')); lv.textContent=(i18n[lang]||i18n.en)[key]; } t('sc-i18n-totp-title','totpTitle'); var te=document.getElementById('sc-i18n-totp-enabled'); if(te){ te.textContent=(i18n[lang]||i18n.en)[te.getAttribute('data-pref')==='email'?'totpEnabledEmailPreferred':'totpEnabled']; } t('sc-i18n-disable-label','disableLabel'); t('sc-i18n-disable-btn','disableBtn'); t('sc-i18n-bind-hint','bindHint'); t('sc-i18n-secret-label','secretLabel'); t('sc-i18n-code-label','codeLabel'); t('sc-i18n-enable-btn','enableBtn'); t('sc-i18n-dev-mgr-title','devMgrTitle'); t('sc-i18n-dev-mgr-desc','devMgrDesc'); t('sc-i18n-remove-current','removeCurrent'); t('sc-i18n-remove-all','removeAll'); t('sc-i18n-col-ua','colUA'); t('sc-i18n-col-ip','colIP'); t('sc-i18n-col-exp','colExp'); t('sc-i18n-col-last','colLast'); t('sc-i18n-no-devices','noDevices'); t('sc-i18n-backup-title','backupTitle'); t('sc-i18n-backup-desc','backupDesc'); t('sc-i18n-backup-warn','backupWarn'); t('sc-i18n-backup-toggle','backupToggle'); t('sc-i18n-preferred-label','preferredLabel'); t('sc-i18n-pref-totp','prefTotp'); t('sc-i18n-pref-email','prefEmail'); t('sc-i18n-save-btn','saveBtn'); t('sc-i18n-current-pref-label','currentPrefLabel'); t('sc-i18n-disable-locked','disableLocked'); t('sc-i18n-disable-lock-remaining-label','disableLockRemaining'); t('sc-i18n-hours','hours'); t('sc-i18n-minutes','minutes'); t('sc-i18n-seconds','seconds'); var cp=document.getElementById('sc-i18n-current-pref-value'); if(cp){ cp.textContent=(i18n[lang]||i18n.en)[cp.getAttribute('data-pref')==='email'?'currentPrefEmail':'currentPrefTotp']; } var da=document.getElementById('sc-i18n-disable-attempts'); if(da){ da.textContent=(i18n[lang]||i18n.en).disableAttempts(attemptsLeft,maxAttempts);} var cb=document.getElementById('sc-copy-secret-btn'); if(cb){ cb.textContent=(i18n[lang]||i18n.en).copySecret; } }
  var zh=document.getElementById('sc-lang-zh'), en=document.getElementById('sc-lang-en');
  if (zh) zh.onclick=function(){lang='zh';apply();}; if (en) en.onclick=function(){lang='en';apply();}; apply();
  var removeAllBtn = document.getElementById('sc-i18n-remove-all');
  if (removeAllBtn) {
    removeAllBtn.addEventListener('click', function(e){
      if (!window.confirm((i18n[lang]||i18n.en).confirmRemoveAll)) {
        e.preventDefault();
      }
    });
  }
  function syncUiLangInputs(){
    var inputs=document.querySelectorAll('.sc-ui-lang');
    for (var i=0; i<inputs.length; i++) { inputs[i].value = lang; }
  }
  syncUiLangInputs();
  var forms = document.querySelectorAll('form[method="post"]');
  for (var fi=0; fi<forms.length; fi++) {
    forms[fi].addEventListener('submit', syncUiLangInputs);
  }
  var totpUri = (root && root.getAttribute('data-totp-uri')) || '';
  var qr = document.getElementById('sc-qr');
  if (qr && totpUri && window.QRCode) {
    new QRCode(qr, { text: totpUri, width: 168, height: 168, correctLevel: QRCode.CorrectLevel.M });
  }
  var lh = document.getElementById('sc-disable-lock-h');
  var lm = document.getElementById('sc-disable-lock-m');
  var ls = document.getElementById('sc-disable-lock-s');
  var lockInfo = document.getElementById('sc-disable-lock-info');
  var attemptsInfo = document.getElementById('sc-disable-attempts-info');
  var pwInput = document.querySelector('input[name="confirm_password"]');
  var disableBtn = document.getElementById('sc-i18n-disable-btn');
  var copyBtn = document.getElementById('sc-copy-secret-btn');
  var secretInput = document.getElementById('sc-totp-secret');
  var copyMsg = document.getElementById('sc-copy-secret-msg');
  function tickLock(){
    if (lockSec <= 0) return;
    function step(){
      if (lockSec < 0) lockSec = 0;
      var h = Math.floor(lockSec/3600), m = Math.floor((lockSec%3600)/60), s = lockSec%60;
      if (lh) lh.textContent = h;
      if (lm) lm.textContent = m;
      if (ls) ls.textContent = (s<10?'0':'') + s;
      if (lockSec > 0){
        lockSec -= 1;
        setTimeout(step,1000);
      } else {
        if (lockInfo) lockInfo.style.display = 'none';
        if (attemptsInfo) {
          attemptsInfo.style.display = '';
          var da = document.getElementById('sc-i18n-disable-attempts');
          if (da) da.textContent = (i18n[lang]||i18n.en).disableAttempts(maxAttempts, maxAttempts);
        }
        if (pwInput) pwInput.disabled = false;
        if (disableBtn) disableBtn.disabled = false;
        setTimeout(function(){ window.location.reload(); }, Math.max(3, guardRefreshSec) * 1000);
      }
    }
    step();
  }
  tickLock();
  if (copyBtn && secretInput) {
    copyBtn.addEventListener('click', function(){
      var txt = secretInput.value || '';
      if (!txt) return;
      function show(msg){ if(copyMsg){ copyMsg.style.display='block'; copyMsg.textContent = msg; setTimeout(function(){ copyMsg.style.display='none'; }, 2000);} }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(function(){ show((i18n[lang]||i18n.en).copied); }).catch(function(){ show((i18n[lang]||i18n.en).copyFailed); });
      } else {
        try { secretInput.focus(); secretInput.select(); var ok = document.execCommand('copy'); show(ok ? (i18n[lang]||i18n.en).copied : (i18n[lang]||i18n.en).copyFailed); } catch(e){ show((i18n[lang]||i18n.en).copyFailed); }
      }
    });
  }
  var backupCb = document.querySelector('input[name="backup_email_enabled"]');
  var prefSel = document.getElementById('sc-preferred-method');
  if (backupCb && prefSel) {
    function syncPrefDisabled(){ prefSel.disabled = !backupCb.checked; }
    backupCb.addEventListener('change', syncPrefDisabled);
    syncPrefDisabled();
  }
})();
</script>{/literal}
