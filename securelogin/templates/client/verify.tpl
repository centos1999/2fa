<div id="sl-verify-root" class="container" style="max-width:560px;margin-top:-50px;">
  <style>
    .sl-card { border: 1px solid #eef6ff; box-shadow: 0 8px 24px rgba(0,0,0,.06); border-radius: 14px; overflow: hidden; background: #fff; }
    .sl-card-header { background: linear-gradient(135deg,#e0efff,#b9dcff); color:#0b2948; padding:18px 20px; display:flex; align-items:center; gap:10px; }
    .sl-card-header .sl-badge { background: rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.25); padding:2px 8px; border-radius:999px; font-size:12px; }
    .sl-card-title { margin:0; font-size:18px; font-weight:600; }
    .sl-card-body { padding:22px; }
    .sl-sub { color:#6b7280; margin:8px 0 14px; }
    .sl-steps { display:flex; align-items:center; justify-content:center; gap:10px; margin:-6px 0 14px; }
    .sl-step { display:flex; align-items:center; gap:8px; color:#5b86b1; font-size:12px; }
    .sl-dot { width:8px; height:8px; border-radius:50%; background:#b9dcff; }
    .sl-sep { width:28px; height:2px; background:#e6f2ff; border-radius:2px; }
    .sl-otp { display:grid; grid-template-columns: repeat(6, 1fr); gap:8px; max-width: 420px; margin: 10px auto 6px; }
    .sl-otp input { text-align:center; font-size:18px; padding:8px 0; height:40px; border:1px solid #d1d5db; border-radius:8px; outline:none; transition: all .15s ease; background:#ffffff; color:#0b2948; }
    .sl-otp input:hover { border-color:#9ecbff; }
    .sl-otp input:focus { border-color:#6fb1ff; box-shadow: 0 0 0 3px rgba(111,177,255,.25); background:#ffffff; }
    .sl-actions { display:flex; gap:10px; flex-direction:column; }
    .sl-primary { background: linear-gradient(135deg,#3b82f6,#2563eb); color:#ffffff; border:0; text-shadow: 0 1px 0 rgba(0,0,0,.15); }
    .sl-primary:hover { filter: brightness(1.02); }
    .sl-muted { color:#6b7280; font-size:12px; text-align:center; }
    .sl-divider { height:1px; background:#f1f5f9; margin:18px 0; }
    .sl-remember { display:flex; align-items:center; gap:5px; margin:10px 0 2px; color:#4b5563; line-height: 1.25; }
    .sl-remember input[type="checkbox"] { transform: scale(1.08); transform-origin: left center; width: 15px; height: 15px; accent-color: #3b82f6; vertical-align: middle; position: relative; top: -2px; }
    #sl-resend-btn { background:#ffffff; color:#2563eb; border:1px solid #cfe5ff; }
    #sl-resend-btn:hover { background:#f8fbff; }
    #sl-resend-btn:disabled { background:#f6f8fc; color:#94a3b8; border-color:#e5eaf5; }
    .sl-meta { display:flex; justify-content:center; gap:8px; color:#6b7280; font-size:12px; }
    .sl-attempts { text-align:center; color:#64748b; font-size:12px; margin:6px 0 0; }
    @media (max-width: 420px){ .sl-otp { gap:6px; } .sl-otp input { font-size:17px; height:38px; padding:8px 0; } }

    @media (prefers-color-scheme: dark){
      .sl-card { background:#0b1220; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
      .sl-card-header { background: linear-gradient(135deg,#0f1c2f,#132540); color:#e6f0ff; }
      .sl-sub, .sl-remember { color:#c0c8d8; }
      .sl-otp input { background:#0f172a; color:#e5efff; border-color:#273552; }
      .sl-otp input:focus { border-color:#6fb1ff; box-shadow: 0 0 0 3px rgba(111,177,255,.25); }
      .sl-divider { background:#0f2138; }
      .sl-primary { background: linear-gradient(135deg,#3b82f6,#2563eb); color:#eef6ff; }
      .sl-muted, .sl-meta, .sl-attempts { color:#a7b3c9; }
      .sl-step { color:#9db8d6; }
      .sl-sep { background: linear-gradient(90deg,#0f2138,#0f2138); }
    }
  </style>
  <div class="sl-card">
    <div class="sl-card-header">
      <div class="sl-badge" id="sl-i18n-badge">Secure</div>
      <h3 class="sl-card-title" id="sl-i18n-title">安全验证</h3>
      <div style="margin-left:auto; display:flex; align-items:center; gap:8px;">
        <button type="button" id="sl-lang-zh" class="btn btn-default btn-xs" style="background:#ffffff;border:1px solid #e5e7eb;color:#111827;">中文</button>
        <button type="button" id="sl-lang-en" class="btn btn-default btn-xs" style="background:#ffffff;border:1px solid #e5e7eb;color:#111827;">EN</button>
      </div>
    </div>
    <div class="sl-card-body">
      <div class="sl-steps" aria-label="验证步骤">
        <div class="sl-step"><span class="sl-dot"></span><span id="sl-i18n-step-login">登录</span></div>
        <div class="sl-sep"></div>
        <div class="sl-step"><span class="sl-dot"></span><span id="sl-i18n-step-verify">验证</span></div>
        <div class="sl-sep"></div>
        <div class="sl-step"><span class="sl-dot"></span><span id="sl-i18n-step-done">完成</span></div>
      </div>
      {if $error}
      <div class="alert alert-danger" role="alert">{$error}</div>
      {/if}
      {if $message}
      <div class="alert alert-success" role="alert">{$message}</div>
      {/if}
      {if $post_payment_notice}
      <div class="alert alert-info" role="status" id="sl-i18n-payment-notice">您已完成支付。首次注册需要完成邮箱验证，请输入收到的验证码以继续。</div>
      {/if}
      {if $totp_enabled && $verify_method!='email'}
      <p class="sl-sub" id="sl-i18n-sub">您已绑定开启动态口令（TOTP）验证，请输入验证器中的6位口令完成验证。</p>
      {else}
      <p class="sl-sub" id="sl-i18n-sub">为了保护您的账户安全，请点击下方按钮发送验证码，并在 <strong><span id="sl-i18n-exp">{$expiry_minutes}</span> 分钟</strong> 内完成验证。</p>
      {/if}
      {if $lock_seconds>0}
      <div class="alert alert-warning" id="sl-lockinfo"><span id="sl-i18n-lock-prefix">多次失败账户已锁定，请稍后再试。</span> <span id="sl-i18n-lock-remaining-label">剩余</span> <span id="sl-lock-hh">0</span> <span id="sl-i18n-hours">小时</span> <span id="sl-lock-mm">0</span> <span id="sl-i18n-minutes">分</span> <span id="sl-lock-ss">0</span> <span id="sl-i18n-seconds">秒</span>。</div>
      {/if}

      <!-- 验证码输入表单 -->
      <form method="post" class="form" id="sl-verify-form">
        <input type="hidden" name="action" value="verify_code"/>
        <input type="hidden" name="code" id="sl-code" />
        <input type="hidden" name="csrf_token" value="{$csrf_token}" />
        <input type="hidden" name="ui_lang" id="sl-ui-lang" value="" />
        <div class="form-group">
          <label for="sl-otp-1" id="sl-i18n-code-label">验证码</label>
          <div class="sl-otp" aria-label="六位验证码输入">
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" id="sl-otp-1" class="form-control" {if $lock_seconds>0}disabled{/if} />
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" id="sl-otp-2" class="form-control" {if $lock_seconds>0}disabled{/if} />
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" id="sl-otp-3" class="form-control" {if $lock_seconds>0}disabled{/if} />
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" id="sl-otp-4" class="form-control" {if $lock_seconds>0}disabled{/if} />
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" id="sl-otp-5" class="form-control" {if $lock_seconds>0}disabled{/if} />
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" id="sl-otp-6" class="form-control" {if $lock_seconds>0}disabled{/if} />
          </div>
          <div class="sl-muted" id="sl-i18n-paste">也可直接粘贴 6 位验证码</div>
        </div>
        {if $attempts_left}
        <div class="sl-attempts" id="sl-i18n-attempts">剩余尝试次数：{$attempts_left}</div>
        {/if}
        <label class="sl-remember">
          <input type="checkbox" name="remember_device" value="1" {if $lock_seconds>0}disabled{/if} />
          <span id="sl-i18n-remember">记住此设备{$remember_device_days}天</span>
        </label>
        {if $totp_enabled && $allow_email_backup_when_totp}
        <input type="hidden" name="use_email_backup" id="sl-use-email-backup" value="0" />
        <div style="text-align:center;margin:8px 0;">
          <button type="button" class="btn btn-default btn-sm" id="sl-toggle-method-btn">{if $verify_method=='email'}改用动态口令验证{else}改用邮箱验证码（备份方式）{/if}</button>
          <div class="sl-muted" id="sl-method-state" style="margin-top:6px;">{if $verify_method=='email'}当前：邮箱验证码验证{else}当前：动态口令验证{/if}</div>
        </div>
        {/if}
        <div class="sl-actions">
          <button class="btn btn-primary btn-block sl-primary" type="submit" id="sl-verify-btn" {if $lock_seconds>0}disabled{/if}>验证并继续</button>
        </div>
      </form>

      <div class="sl-divider"></div>
      <!-- 重发验证码按钮 -->
      {if !$totp_enabled || ($totp_enabled && $allow_email_backup_when_totp && $verify_method=='email')}
      <form method="post" id="sl-resend-form">
        <input type="hidden" name="action" value="resend_code"/>
        <input type="hidden" name="csrf_token" value="{$csrf_token}" />
        <input type="hidden" name="ui_lang" id="sl-ui-lang-resend" value="" />
        <button class="btn btn-default btn-block" id="sl-resend-btn" type="submit" {if $countdown>0 || $resend_remaining<=0 || $lock_seconds>0}disabled{/if}>
          {if $resend_remaining<=0}
            今日验证码发送次数已达上限
          {elseif $countdown>0}
             <span id="sl-countdown">{$countdown}</span> 秒后可重新发送
          {else}
            重新发送验证码（剩余{$resend_remaining}次）
          {/if}
        </button>
      </form>
      {/if}
      {if !$totp_enabled || ($totp_enabled && $allow_email_backup_when_totp && $verify_method=='email')}
      <div class="sl-meta">
        <span id="sl-i18n-meta">未收到邮件？请检查垃圾箱或稍后重试</span>
      </div>
      {/if}
      <div class="sl-meta" id="sl-totp-help" {if !$totp_enabled || $verify_method=='email'}style="display:none;"{/if}>
        <span id="sl-i18n-totp-help">无法获取动态口令？请联系：support@dnshe.com 获取支持。</span>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var countdown = parseInt('{$countdown|default:0}', 10) || 0;
  var btn = document.getElementById('sl-resend-btn');
  var span = document.getElementById('sl-countdown');
  var lockSec = parseInt('{$lock_seconds|default:0}', 10) || 0;
  var rememberDays = parseInt('{$remember_device_days|default:30}', 10) || 30;
  var attemptsLeft = parseInt('{$attempts_left|default:0}', 10) || 0;

  // Move card beside page title (Six/Twenty-One compatible best-effort)
  try {
    var root = document.getElementById('sl-verify-root');
    if (root) {
      var anchors = [
        '#main-body .page-title',
        '.page-title',
        '.header-lined',
        '#main-body h1',
        'h1'
      ];
      var anchor = null;
      for (var i=0;i<anchors.length;i++){ var a=document.querySelector(anchors[i]); if (a){ anchor=a; break; } }
      if (anchor && anchor.parentNode) {
        if (anchor.nextSibling) anchor.parentNode.insertBefore(root, anchor.nextSibling);
        else anchor.parentNode.appendChild(root);
      }
    }
  } catch(e) {}

  // i18n
  var i18n = {
    zh: {
      badge: 'Secure',
      title: '安全验证',
      step_login: '登录',
      step_verify: '验证',
      step_done: '完成',
      sub_prefix_request: '为了保护您的账户安全，请点击下方按钮发送验证码，并在',
      sub_prefix_sent: '为了保护您的账户安全，我们已向您的邮箱发送了验证码，请在',
      sub_suffix: '内完成验证。',
      minutes: '分钟',
      seconds: '秒',
      hours: '小时',
      code_label: '验证码:',
      paste_hint: '可以直接粘贴您收到的 6 位验证码',
      attempts: function(n){ return '剩余尝试次数：' + n; },
      remember: function(days){ return '记住此设备' + days + '天'; },
      submit: '验证并继续',
      resend_disabled: '今日验证码发送次数已达上限',
      resend_waiting: function(s){ return ' ' + s + ' 秒后可以重新发送'; },
      resend_ready: function(n){ return '重新发送验证码（剩余' + n + '次）'; },
      no_mail: '未收到邮件？请检查邮箱垃圾箱或稍后重试',
      lock_prefix: '多次失败已锁定,请等待锁定时间结束.',
      lock_remaining: '剩余:',
      payment_notice: '您已完成支付。首次注册需要完成邮箱验证，请输入收到的验证码以继续。'
    },
    en: {
      badge: 'Secure',
      title: 'Security Verification',
      step_login: 'Login',
      step_verify: 'Verify',
      step_done: 'Done',
      sub_prefix_request: 'For your account security, click the button below to send a code, then verify within',
      sub_prefix_sent: 'For your account security, we\'ve sent a code to your email. Please verify within',
      sub_suffix: '.',
      minutes: 'min',
      seconds: 's',
      hours: 'h',
      code_label: 'Verification Code',
      paste_hint: 'You can paste the 6-digit code directly',
      attempts: function(n){ return 'Attempts left: ' + n; },
      remember: function(days){ return 'Remember this device for ' + days + ' days'; },
      submit: 'Verify & Continue',
      resend_disabled: 'Daily limit reached',
      resend_waiting: function(s){ return 'Wait ' + s + 's'; },
      resend_ready: function(n){ return 'Resend code (remaining ' + n + ')'; },
      no_mail: 'Didn\'t receive the email? Check spam or try later',
      lock_prefix: 'Too many failed attempts. Please try again later.',
      lock_remaining: 'Remaining',
      payment_notice: 'Payment completed. For first-time registration, please verify your email by entering the code we sent to continue.'
    }
  };
  // Map WHMCS current language to zh/en
  var whmcsLangRaw = '{$whmcs_language|default:$language|default:"chinese"}';
  function mapWhmcsToUi(lang){
    var l = (lang||'').toLowerCase();
    if (l.indexOf('en') === 0) return 'en';
    // common chinese keys: chinese, chinese_cn, zh, zh_cn, chinese simplified
    if (l.indexOf('zh') === 0 || l.indexOf('chinese') === 0) return 'zh';
    return 'en';
  }
  function getLang(){
    // Session preference overrides WHMCS within current login session
    try{
      var s = sessionStorage.getItem('sl_lang_session');
      if (s === 'zh' || s === 'en') return s;
    }catch(e){}
    // Prefer WHMCS current language; fallback to English
    return mapWhmcsToUi(whmcsLangRaw);
  }
  function setLang(lang){ try{ sessionStorage.setItem('sl_lang_session', lang); } catch(e){} }
  function applyLang(lang){
    var L = i18n[lang] || i18n.zh;
    // sync hidden inputs for server i18n
    try{
      var h1 = document.getElementById('sl-ui-lang'); if (h1) h1.value = lang;
      var h2 = document.getElementById('sl-ui-lang-resend'); if (h2) h2.value = lang;
    }catch(e){}
    var el;
    if ((el=document.getElementById('sl-i18n-badge'))) el.textContent = L.badge;
    if ((el=document.getElementById('sl-i18n-title'))) el.textContent = L.title;
    if ((el=document.getElementById('sl-i18n-step-login'))) el.textContent = L.step_login;
    if ((el=document.getElementById('sl-i18n-step-verify'))) el.textContent = L.step_verify;
    if ((el=document.getElementById('sl-i18n-step-done'))) el.textContent = L.step_done;
    var sub = document.getElementById('sl-i18n-sub');
    var alreadySent = (countdown > 0);
    if (sub) {
      var exp = document.getElementById('sl-i18n-exp');
      var methodInput = document.getElementById('sl-use-email-backup');
      var emailMode = methodInput ? (methodInput.value === '1') : ('{$verify_method|default:"totp"}' === 'email');
      var totpOnly = ({if $totp_enabled}true{else}false{/if}) && !emailMode;
      if (totpOnly) {
        sub.textContent = (lang === 'en')
          ? 'TOTP verification is enabled for your account. Please enter the 6-digit authenticator code to complete verification.'
          : '您已绑定开启动态口令（TOTP）验证，请输入验证器中的6位口令完成验证。';
      } else if (exp) {
        var prefix = alreadySent ? (L.sub_prefix_sent || L.sub_prefix_request) : (L.sub_prefix_request || L.sub_prefix_sent);
        sub.innerHTML = prefix + ' <strong><span id="sl-i18n-exp">' + exp.textContent + '</span> ' + L.minutes + '</strong> ' + L.sub_suffix;
      }
    }
    if ((el=document.getElementById('sl-i18n-code-label'))) el.textContent = L.code_label;
    if ((el=document.getElementById('sl-i18n-paste'))) el.textContent = L.paste_hint;
    if (attemptsLeft > 0 && (el=document.getElementById('sl-i18n-attempts'))) el.textContent = L.attempts(attemptsLeft);
    if ((el=document.getElementById('sl-i18n-remember'))) el.textContent = L.remember(rememberDays);
    if ((el=document.getElementById('sl-verify-btn'))) el.textContent = L.submit;
    if ((el=document.getElementById('sl-i18n-meta'))) el.textContent = L.no_mail;
    if ((el=document.getElementById('sl-i18n-totp-help'))) el.textContent = (lang==='en'
      ? 'Cannot access your TOTP code? Contact support@dnshe.com for help.'
      : '无法获取动态口令？请联系：support@dnshe.com 获取支持。');
    // payment notice
    if ((el=document.getElementById('sl-i18n-payment-notice'))) el.textContent = L.payment_notice;
    // lock text pieces
    if ((el=document.getElementById('sl-i18n-lock-prefix'))) el.textContent = L.lock_prefix;
    if ((el=document.getElementById('sl-i18n-lock-remaining-label'))) el.textContent = L.lock_remaining;
    if ((el=document.getElementById('sl-i18n-hours'))) el.textContent = (lang==='en'?'h':'小时');
    if ((el=document.getElementById('sl-i18n-minutes'))) el.textContent = (lang==='en'?'min':'分');
    if ((el=document.getElementById('sl-i18n-seconds'))) el.textContent = (lang==='en'?'s':'秒');
    // resend button immediate state
    if (btn) {
      var remaining = parseInt('{$resend_remaining|default:0}',10)||0;
      if (remaining<=0){ btn.textContent = L.resend_disabled; }
      else if (countdown>0){ btn.textContent = L.resend_waiting(countdown); }
      else { btn.textContent = L.resend_ready(remaining); }
    }
    // active button state
    var zhBtn = document.getElementById('sl-lang-zh');
    var enBtn = document.getElementById('sl-lang-en');
    if (zhBtn && enBtn){
      zhBtn.style.opacity = (lang==='zh')? '1' : '.6';
      enBtn.style.opacity = (lang==='en')? '1' : '.6';
    }
  }
  function tickLock(){
    if (lockSec <= 0) return;
    var hh = document.getElementById('sl-lock-hh');
    var mm = document.getElementById('sl-lock-mm');
    var ss = document.getElementById('sl-lock-ss');
    function step(){
      if (lockSec < 0) lockSec = 0;
      var h = Math.floor(lockSec/3600);
      var m = Math.floor((lockSec % 3600) / 60);
      var s = lockSec % 60;
      if (hh) hh.textContent = h;
      if (mm) mm.textContent = m;
      if (ss) ss.textContent = (s < 10 ? '0' : '') + s;
      if (lockSec > 0) {
        lockSec -= 1;
        setTimeout(step, 1000);
      }
    }
    step();
  }
  function tick(){
    if (!btn) return;
    if (countdown > 0) {
      if (span) span.textContent = countdown;
      btn.disabled = true;
      var L = i18n[getLang()] || i18n.zh;
      btn.textContent = L.resend_waiting(countdown);
      countdown -= 1;
      setTimeout(tick, 1000);
    } else {
      btn.disabled = false;
      if (span) span.textContent = '0';
      var rem = (parseInt('{$resend_remaining|default:0}',10)||0);
      var L2 = i18n[getLang()] || i18n.zh;
      btn.textContent = L2.resend_ready(rem);
    }
  }
  tick();
  tickLock();

  // OTP 交互
  var inputs = [];
  for (var i=1;i<=6;i++){ var el = document.getElementById('sl-otp-'+i); if (el) inputs.push(el); }
  var hidden = document.getElementById('sl-code');
  function updateHidden(){ if (!hidden) return; var v=''; for (var i=0;i<inputs.length;i++){ v += (inputs[i].value||'').replace(/\D/g,''); } hidden.value = v.slice(0,6); }
  function focusNext(idx){ if (idx+1 < inputs.length){ inputs[idx+1].focus(); inputs[idx+1].select && inputs[idx+1].select(); } }
  function focusPrev(idx){ if (idx>0){ inputs[idx-1].focus(); inputs[idx-1].select && inputs[idx-1].select(); } }
  inputs.forEach(function(input, idx){
    input.addEventListener('input', function(e){
      var val = (e.target.value||'').replace(/\D/g,'');
      if (val.length > 1){
        // 粘贴到单格，分配到后续
        var chars = val.split('');
        for (var j=0;j<chars.length && (idx+j)<inputs.length;j++){ inputs[idx+j].value = chars[j]; }
        var jumpTo = Math.min(idx + chars.length, inputs.length - 1);
        inputs[jumpTo].focus();
      } else {
        e.target.value = val;
        if (val !== '') focusNext(idx);
      }
      updateHidden();
    });
    input.addEventListener('keydown', function(e){
      if (e.key === 'Backspace' && !e.target.value){ focusPrev(idx); setTimeout(updateHidden,0); }
      if (e.key === 'ArrowLeft'){ e.preventDefault(); focusPrev(idx); }
      if (e.key === 'ArrowRight'){ e.preventDefault(); focusNext(idx); }
    });
    input.addEventListener('paste', function(e){
      var txt = (e.clipboardData || window.clipboardData).getData('text');
      if (!txt) return; e.preventDefault();
      var digits = (txt+'').replace(/\D/g,'').slice(0,6);
      for (var j=0;j<digits.length && (j)<inputs.length;j++){ inputs[j].value = digits[j]; }
      updateHidden();
      var last = Math.min(digits.length, inputs.length) - 1; if (last>=0) inputs[last].focus();
    });
  });
  // 初始聚焦
  if (inputs.length && !inputs[0].disabled){ inputs[0].focus(); }

  // 提交前校验长度
  var form = document.getElementById('sl-verify-form');
  if (form){
    form.addEventListener('submit', function(e){ updateHidden(); if (!hidden || hidden.value.length !== 6){ e.preventDefault(); inputs[0] && inputs[0].focus(); } });
  }
  var methodBtn = document.getElementById('sl-toggle-method-btn');
  var methodState = document.getElementById('sl-method-state');
  var methodInput = document.getElementById('sl-use-email-backup');
  var verifyMethod = '{$verify_method|default:"totp"}';
  var csrf = '{$csrf_token}';
  function syncMethodUi(){
    if (!(methodBtn && methodInput)) return;
      var emailMode = (methodInput.value === '1');
      var lang = getLang();
      var zh = (lang === 'zh');
      if (methodState) methodState.textContent = emailMode ? (zh ? '当前：邮箱验证码验证' : 'Current: Email code verification') : (zh ? '当前：动态口令验证' : 'Current: TOTP verification');
      methodBtn.textContent = emailMode ? (zh ? '改用动态口令验证' : 'Switch to TOTP verification') : (zh ? '改用邮箱验证码（备份方式）' : 'Switch to email code (backup)');
      var totpHelp = document.getElementById('sl-totp-help');
      if (totpHelp) totpHelp.style.display = emailMode ? 'none' : '';
      var sub = document.getElementById('sl-i18n-sub');
      var exp = document.getElementById('sl-i18n-exp');
      if (sub){
        if (emailMode && exp) {
          if (zh) {
            sub.innerHTML = '为了保护您的账户安全，我们已向您的邮箱发送了验证码，请在 <strong><span id="sl-i18n-exp">'+exp.textContent+'</span> 分钟</strong> 内完成验证。';
          } else {
            sub.innerHTML = 'For your account security, we sent a code to your email. Please verify within <strong><span id="sl-i18n-exp">'+exp.textContent+'</span> min</strong>.';
          }
        } else {
          sub.textContent = zh ? '您已绑定开启动态口令（TOTP）验证，请输入验证器中的6位口令完成验证。' : 'TOTP verification is enabled for your account. Please enter the 6-digit authenticator code to complete verification.';
        }
      }
    }
  if (methodBtn && methodInput) {
    methodBtn.addEventListener('click', function(){
      var toEmail = (methodInput.value !== '1');
      methodInput.value = toEmail ? '1' : '0';
      syncMethodUi();
      var f = document.createElement('form');
      f.method = 'post';
      f.style.display = 'none';
      var a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value = toEmail ? 'switch_to_email_mode' : 'switch_to_totp_mode'; f.appendChild(a);
      var c = document.createElement('input'); c.type='hidden'; c.name='csrf_token'; c.value = csrf; f.appendChild(c);
      var l = document.createElement('input'); l.type='hidden'; l.name='ui_lang'; l.value = getLang(); f.appendChild(l);
      document.body.appendChild(f); f.submit();
    });
    methodInput.value = (verifyMethod === 'email') ? '1' : '0';
    syncMethodUi();
  }

  // Language switch
  var current = getLang();
  applyLang(current);
  var btnZh = document.getElementById('sl-lang-zh');
  var btnEn = document.getElementById('sl-lang-en');
  if (btnZh) btnZh.addEventListener('click', function(){ setLang('zh'); applyLang('zh'); syncMethodUi(); });
  if (btnEn) btnEn.addEventListener('click', function(){ setLang('en'); applyLang('en'); syncMethodUi(); });
})();
</script>
