/* My Bingwa Admin V2 — progressive enhancement. No framework. Runs under a strict CSP
   (script-src 'self'), so the theme is stamped server-side; this only toggles it. */
(function () {
  'use strict';

  var root = document.documentElement;
  var app = document.querySelector('.app');

  function bind(sel, evt, fn) {
    document.querySelectorAll(sel).forEach(function (el) { el.addEventListener(evt, fn); });
  }

  /* ---- Sidebar toggle ----
     One header button (data-nav-toggle) drives both layouts: on desktop it collapses
     or expands the icon rail and persists the choice in the mb_nav cookie; on the mobile
     off-canvas layout it opens/closes the sliding drawer. The scrim closes the drawer. */
  var navDesktop = window.matchMedia('(min-width: 901px)');
  bind('[data-nav-toggle]', 'click', function () {
    if (!app) return;
    if (navDesktop.matches) {
      var collapsed = app.classList.toggle('nav-collapsed');
      setCookie('mb_nav', collapsed ? 'collapsed' : 'expanded');
    } else {
      app.classList.toggle('nav-open');
    }
  });
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

  /* ---- Dropdown menus (topbar profile / notifications / table row kebabs) ----
     Row kebab menus live inside a horizontally-scrollable table, which would clip an
     absolutely-positioned panel. Menus flagged .dropdown-menu--fixed are positioned
     with fixed coordinates from the trigger so they escape the table overflow. */
  bind('[data-dropdown]', 'click', function (e) {
    e.stopPropagation();
    var id = this.getAttribute('data-dropdown');
    var menu = document.getElementById(id);
    document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { if (m !== menu) m.classList.remove('open'); });
    if (!menu) return;
    var willOpen = !menu.classList.contains('open');
    menu.classList.toggle('open');
    if (willOpen && menu.classList.contains('dropdown-menu--fixed')) {
      var r = this.getBoundingClientRect();
      var w = menu.offsetWidth || 220;
      var left = r.right - w;
      if (left < 8) left = 8;
      menu.style.position = 'fixed';
      menu.style.top = (r.bottom + 6) + 'px';
      menu.style.left = left + 'px';
      menu.style.right = 'auto';
    }
  });
  function closeDropdowns() {
    document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { m.classList.remove('open'); });
  }
  document.addEventListener('click', closeDropdowns);
  // A fixed-positioned menu would detach from its row on scroll, so close on any scroll.
  window.addEventListener('scroll', closeDropdowns, true);

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

  /* ---- Generic overlay modals (read-only detail views) ----
     A trigger with data-modal-open="#id" reveals the matching .modal-backdrop[data-modal].
     Closes on backdrop click, any [data-modal-close] button, or Escape. */
  bind('[data-modal-open]', 'click', function (e) {
    e.preventDefault();
    var sel = this.getAttribute('data-modal-open');
    var m = sel ? document.querySelector(sel) : null;
    if (m) m.classList.add('open');
  });
  document.querySelectorAll('.modal-backdrop[data-modal]').forEach(function (bd) {
    function close() { bd.classList.remove('open'); }
    bd.addEventListener('click', function (e) { if (e.target === bd) close(); });
    bd.querySelectorAll('[data-modal-close]').forEach(function (b) { b.addEventListener('click', close); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.key === 'Esc') {
      document.querySelectorAll('.modal-backdrop.open').forEach(function (m) { m.classList.remove('open'); });
      closeDropdowns();
    }
  });

  /* ---- Bulk row select (e.g. Payments "Delete selected") ----
     A header [data-check-all] toggles every [data-row-check]; the [data-bulk-delete]
     button disables and [data-bulk-count] updates when nothing is selected. Lives here
     (not inline) because the CSP is script-src 'self' with no inline allowance. */
  (function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-row-check]'));
    if (!rows.length) return;
    var all = document.querySelector('[data-check-all]');
    var btn = document.querySelector('[data-bulk-delete]');
    var label = document.querySelector('[data-bulk-count]');
    function sync() {
      var n = rows.filter(function (c) { return c.checked; }).length;
      if (label) label.textContent = n === 0 ? 'No records selected' : (n + ' selected');
      if (btn) btn.disabled = n === 0;
      if (all) {
        all.checked = n > 0 && n === rows.length;
        all.indeterminate = n > 0 && n < rows.length;
      }
    }
    if (all) all.addEventListener('change', function () {
      rows.forEach(function (c) { c.checked = all.checked; });
      sync();
    });
    rows.forEach(function (c) { c.addEventListener('change', sync); });
    sync();
  })();
})();
