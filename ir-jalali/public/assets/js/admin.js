/* IR-Jalali admin helpers (vanilla JS, no dependencies). */
(function () {
  'use strict';

  // Confirm destructive forms.
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // ── Command palette (Ctrl+K) ─────────────────────────────────
  var overlay = document.getElementById('paletteOverlay');
  var input = document.getElementById('paletteInput');
  var list = document.getElementById('paletteList');
  var trigger = document.getElementById('paletteTrigger');
  var commands = [];
  // The palette must start closed on every page load.
  if (overlay) overlay.hidden = true;
  var selected = 0;

  var blob = document.getElementById('paletteCommands');
  if (blob) {
    try { commands = JSON.parse(blob.textContent || '[]'); } catch (e) { commands = []; }
  }

  function render() {
    if (!list) return;
    var q = (input && input.value ? input.value : '').trim().toLowerCase();
    var matches = commands.filter(function (c) {
      return q === '' || c.label.toLowerCase().indexOf(q) !== -1;
    });
    if (selected >= matches.length) selected = 0;
    list.innerHTML = '';
    matches.forEach(function (c, i) {
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'palette-item' + (i === selected ? ' selected' : '');
      item.textContent = c.label;
      item.addEventListener('click', function () { window.location.href = c.url; });
      list.appendChild(item);
    });
    if (!matches.length) {
      var empty = document.createElement('div');
      empty.className = 'palette-empty';
      empty.textContent = '—';
      list.appendChild(empty);
    }
  }

  function open() {
    if (!overlay) return;
    overlay.hidden = false;
    selected = 0;
    if (input) { input.value = ''; input.focus(); }
    render();
  }

  function close() { if (overlay) overlay.hidden = true; }

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
      e.preventDefault();
      if (overlay && overlay.hidden) open(); else close();
      return;
    }
    if (overlay && !overlay.hidden) {
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        var delta = e.key === 'ArrowDown' ? 1 : -1;
        var count = list ? list.children.length : 0;
        selected = (selected + delta + count) % Math.max(1, count);
        render();
        return;
      }
      if (e.key === 'Enter') {
        var active = list ? list.children[selected] : null;
        if (active) active.click();
      }
    }
  });
  if (overlay) overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  if (trigger) trigger.addEventListener('click', open);
  if (input) input.addEventListener('input', function () { selected = 0; render(); });

  // Media drag & drop upload.
  var drop = document.getElementById('dropzone');
  var fileInput = document.getElementById('fileinput');
  if (!drop || !fileInput) return;

  var token = drop.getAttribute('data-token') || '';
  var status = document.getElementById('upload-status');

  drop.addEventListener('click', function () { fileInput.click(); });
  fileInput.addEventListener('change', function () { upload(fileInput.files); fileInput.value = ''; });

  ['dragenter', 'dragover'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); });
  });
  drop.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files.length) upload(e.dataTransfer.files);
  });

  function upload(files) {
    if (!files.length) return;
    var fd = new FormData();
    for (var i = 0; i < files.length && i < 10; i++) fd.append('files[]', files[i]);
    fd.append('_token', token);
    status.textContent = 'در حال بارگذاری...';
    fetch('/admin/media/upload', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) {
          window.location.reload();
        } else {
          status.textContent = (res.errors && res.errors.join(' | ')) || 'خطا در بارگذاری';
        }
      })
      .catch(function () { status.textContent = 'خطای ارتباط با سرور'; });
  }
})();
