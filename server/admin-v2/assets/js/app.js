/* My Bingwa Admin V2 — progressive enhancement. No framework. Runs under a strict CSP
   (script-src 'self'), so the theme is stamped server-side; this only toggles it. */
(function () {
  'use strict';

  var root = document.documentElement;
  var app = document.querySelector('.app');

  /* ---- Mobile navigation drawer ---- */
  function bind(sel, evt, fn) {
    document.querySelectorAll(sel).forEach(function (el) { el.addEventListener(evt, fn); });
  }
  bind('[data-toggle-nav]', 'click', function () { if (app) app.classList.toggle('nav-open'); });
  bind('.scrim', 'click', function () { if (app) app.classList.remove('nav-open'); });

  /* ---- Theme: cycle light -> dark -> system, persisted in a cookie ---- */
  function setCookie(name, value) {
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + '=' + value + '; path=/; max-age=31536000; SameSite=Lax' + secure;
  }
  function applyThemeLabel(theme) {
    document.querySelectorAll('[data-theme-label]').forEach(function (el) {
      el.textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
    });
  }
  bind('[data-theme-toggle]', 'click', function () {
    var order = ['light', 'dark', 'system'];
    var current = root.getAttribute('data-theme') || 'system';
    var next = order[(order.indexOf(current) + 1) % order.length];
    root.setAttribute('data-theme', next);
    setCookie('mb_theme', next);
    applyThemeLabel(next);
  });

  /* ---- Toast auto-dismiss ---- */
  document.querySelectorAll('.toast').forEach(function (t) {
    var timeout = setTimeout(function () { dismiss(t); }, 6000);
    var close = t.querySelector('.close');
    if (close) close.addEventListener('click', function () { clearTimeout(timeout); dismiss(t); });
  });
  function dismiss(t) {
    t.style.transition = 'opacity .2s, transform .2s';
    t.style.opacity = '0';
    t.style.transform = 'translateX(12px)';
    setTimeout(function () { t.remove(); }, 220);
  }

  /* ---- Dropdown menus (topbar profile / notifications) ---- */
  bind('[data-dropdown]', 'click', function (e) {
    e.stopPropagation();
    var id = this.getAttribute('data-dropdown');
    var menu = document.getElementById(id);
    document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { if (m !== menu) m.classList.remove('open'); });
    if (menu) menu.classList.toggle('open');
  });
  document.addEventListener('click', function () {
    document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { m.classList.remove('open'); });
  });

  /* ---- Confirm modal for dangerous / state-changing actions ----
     Any <form data-confirm="Are you sure?"> defers submit until confirmed. */
  var pendingForm = null;
  var backdrop = document.getElementById('confirm-modal');
  bind('form[data-confirm]', 'submit', function (e) {
    if (this.dataset.confirmed === '1') return;
    e.preventDefault();
    pendingForm = this;
    if (!backdrop) { this.submit(); return; }
    backdrop.querySelector('[data-confirm-title]').textContent = this.getAttribute('data-confirm-title') || 'Please confirm';
    backdrop.querySelector('[data-confirm-body]').textContent = this.getAttribute('data-confirm');
    backdrop.classList.add('open');
  });
  if (backdrop) {
    backdrop.querySelector('[data-confirm-cancel]').addEventListener('click', function () {
      backdrop.classList.remove('open'); pendingForm = null;
    });
    backdrop.querySelector('[data-confirm-ok]').addEventListener('click', function () {
      if (!pendingForm) return;
      pendingForm.dataset.confirmed = '1';
      pendingForm.submit();
    });
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) { backdrop.classList.remove('open'); pendingForm = null; } });
  }

  /* ---- Prevent double submit ----
     Skip forms that also use data-confirm: those submit programmatically via the modal
     (which bypasses this event), and disabling the button here would strand it if the
     user cancels the confirmation. The modal itself guards against double submits. */
  bind('form[data-once]', 'submit', function () {
    if (this.hasAttribute('data-confirm')) return;
    var btn = this.querySelector('button[type=submit], .btn[type=submit]');
    if (btn) { btn.classList.add('is-loading'); setTimeout(function () { btn.setAttribute('disabled', 'disabled'); }, 0); }
  });

  /* ---- Copy-to-clipboard ---- */
  bind('[data-copy]', 'click', function () {
    var text = this.getAttribute('data-copy');
    if (navigator.clipboard) navigator.clipboard.writeText(text);
    var old = this.textContent; this.textContent = 'Copied';
    var self = this; setTimeout(function () { self.textContent = old; }, 1400);
  });

  /* ---- Live billboard/notification preview mirroring ---- */
  document.querySelectorAll('[data-preview-src]').forEach(function (input) {
    var target = document.querySelector(input.getAttribute('data-preview-src'));
    if (!target) return;
    input.addEventListener('input', function () { target.textContent = input.value || target.getAttribute('data-empty') || ''; });
  });
})();
