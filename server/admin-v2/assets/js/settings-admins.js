/* Populate the administrator form from a row's data-* attributes when Edit is clicked. */
(function () {
  'use strict';
  var form = document.getElementById('admin-form');
  if (!form) return;
  function setRoles(csv) {
    var ids = (csv || '').split(',').filter(Boolean);
    document.querySelectorAll('.af-role').forEach(function (cb) {
      cb.checked = ids.indexOf(cb.getAttribute('data-role')) !== -1;
    });
  }
  document.querySelectorAll('[data-edit-admin]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('af-id').value = btn.getAttribute('data-id');
      document.getElementById('af-name').value = btn.getAttribute('data-name');
      document.getElementById('af-email').value = btn.getAttribute('data-email');
      document.getElementById('af-password').value = '';
      var sup = document.getElementById('af-super');
      if (sup) sup.checked = btn.getAttribute('data-super') === '1';
      setRoles(btn.getAttribute('data-roles'));
      document.getElementById('admin-form-title').textContent = 'Edit administrator';
      form.scrollIntoView({ behavior: 'smooth' });
    });
  });
  var reset = document.getElementById('af-reset');
  if (reset) reset.addEventListener('click', function () {
    form.reset();
    document.getElementById('af-id').value = '0';
    document.getElementById('admin-form-title').textContent = 'Add administrator';
  });
})();
