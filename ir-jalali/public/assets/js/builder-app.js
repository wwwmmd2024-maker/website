/* IR-Jalali visual builder app — vanilla JS, no dependencies. */
(function () {
'use strict';

var BOOT = window.__BUILDER_BOOT__ || {};
var CSRF = BOOT.csrf || '';

function $(s, r) { return (r || document).querySelector(s); }
function $all(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
function esc(v) {
  return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}
function clone(o) { return JSON.parse(JSON.stringify(o)); }
function debounce(fn, ms) {
  var t = null;
  return function () {
    var args = arguments, self = this;
    clearTimeout(t);
    t = setTimeout(function () { fn.apply(self, args); }, ms);
  };
}

/* ── State ── */
var state = {
  tree: BOOT.doc && BOOT.doc.tree ? BOOT.doc.tree : null,
  selectedId: null,
  device: 'desktop',
  dirty: false,
  past: [],
  future: [],
  clipboard: null,
  tokens: BOOT.tokens || {},
  mediaCache: null,
  previewSeq: 0,
  saving: false,
  propsTab: 'content',
  leftTab: 'components',
};

var CATALOG = BOOT.components || {};
var MAX_HISTORY = 50;

function toast(msg, isErr) {
  var el = $('#ijb-toast');
  el.textContent = msg;
  el.className = isErr ? 'err' : '';
  void el.offsetWidth;
  el.classList.add('on');
  clearTimeout(toast._t);
  toast._t = setTimeout(function () { el.classList.remove('on'); }, 2600);
}

function api(url, opts) {
  opts = opts || {};
  var init = {
    method: opts.method || 'GET',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  };
  if (opts.body !== undefined) {
    opts.body._token = CSRF;
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(opts.body);
  }
  return fetch(url, init).then(function (r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  });
}

/* ── Tree helpers ── */
function ensureNode(n) {
  n.content = n.content && typeof n.content === 'object' ? n.content : {};
  n.children = Array.isArray(n.children) ? n.children : [];
  n.style = n.style && typeof n.style === 'object' ? n.style : {};
  n.responsive = n.responsive && typeof n.responsive === 'object' ? n.responsive : {};
  n.attrs = n.attrs && typeof n.attrs === 'object' ? n.attrs : {};
  n.animation = n.animation && typeof n.animation === 'object' ? n.animation : {};
  n.visibility = n.visibility && typeof n.visibility === 'object' ? n.visibility : {};
  n.conditions = Array.isArray(n.conditions) ? n.conditions : [];
  n.dynamic = n.dynamic && typeof n.dynamic === 'object' ? n.dynamic : {};
  n.children.forEach(ensureNode);
  return n;
}

function newId() {
  var id;
  do { id = 'n_' + Math.random().toString(36).slice(2, 9); } while (findNode(state.tree, id));
  return id;
}

function findNode(node, id) {
  if (!node) return null;
  if (node.id === id) return node;
  for (var i = 0; i < (node.children || []).length; i++) {
    var f = findNode(node.children[i], id);
    if (f) return f;
  }
  return null;
}

function findParent(node, id, parent) {
  if (!node) return null;
  if (node.id === id) return parent || null;
  for (var i = 0; i < (node.children || []).length; i++) {
    var f = findParent(node.children[i], id, node);
    if (f) return f;
  }
  return null;
}

function isContainer(type) {
  var def = CATALOG[type];
  return !!(def && def.container);
}

function nodeTitle(n) {
  if (n.name) return n.name;
  var def = CATALOG[n.type];
  var base = def ? def.title : n.type;
  var c = n.content || {};
  if (c.text && typeof c.text === 'string') return base + ': ' + c.text.slice(0, 28);
  if (c.title && typeof c.title === 'string') return base + ': ' + c.title.slice(0, 28);
  return base;
}

function commit() {
  state.past.push(clone(state.tree));
  if (state.past.length > MAX_HISTORY) state.past.shift();
  state.future = [];
  markDirty();
  updateUndoRedo();
}

function markDirty() {
  state.dirty = true;
  var s = $('#ijb-status');
  s.textContent = '● ذخیره‌نشده';
  s.classList.add('dirty');
}

function markClean(label) {
  state.dirty = false;
  var s = $('#ijb-status');
  s.textContent = label || '✓ ذخیره شد';
  s.classList.remove('dirty');
}

function updateUndoRedo() {
  $('#ijb-undo').disabled = state.past.length === 0;
  $('#ijb-redo').disabled = state.future.length === 0;
}

function undo() {
  if (!state.past.length) return;
  state.future.push(clone(state.tree));
  state.tree = state.past.pop();
  afterStructuralChange();
}
function redo() {
  if (!state.future.length) return;
  state.past.push(clone(state.tree));
  state.tree = state.future.pop();
  afterStructuralChange();
}

function afterStructuralChange() {
  if (state.selectedId && !findNode(state.tree, state.selectedId)) state.selectedId = null;
  markDirty();
  updateUndoRedo();
  renderNavigator();
  renderProps();
  schedulePreview();
}

/* ── Canvas render (server-side preview) ── */
var schedulePreview = debounce(function () { doPreview(); }, 350);

function doPreview() {
  var seq = ++state.previewSeq;
  api('/admin/api/builder/preview', {
    method: 'POST',
    body: { tree: state.tree, entity: BOOT.entity, id: BOOT.id, device: state.device },
  }).then(function (res) {
    if (seq !== state.previewSeq) return; // stale
    if (!res.ok) { toast('خطا در پیش‌نمایش', true); return; }
    $('#ijb-canvas').innerHTML = res.html || '<p class="ijb-empty">بوم خالی است — از پنل چپ جزء اضافه کنید.</p>';
    $('#ijb-canvas-css').textContent = res.css || '';
    applySelectionOutline();
  }).catch(function () {
    if (seq === state.previewSeq) toast('خطای ارتباط با سرور', true);
  });
}

function applySelectionOutline() {
  $all('#ijb-canvas .ijb-selected').forEach(function (el) { el.classList.remove('ijb-selected'); });
  if (!state.selectedId) return;
  var el = $('#ijb-canvas [data-ij-id="' + state.selectedId + '"]');
  if (el) el.classList.add('ijb-selected');
}

function select(id) {
  state.selectedId = id;
  applySelectionOutline();
  renderNavigator();
  renderProps();
}

/* ── Palette ── */
function renderPalette(filter) {
  var pal = $('#ijb-palette');
  var cats = BOOT.categories || {};
  var html = '';
  Object.keys(cats).forEach(function (cat) {
    var items = Object.keys(CATALOG).filter(function (t) {
      var d = CATALOG[t];
      if (d.palette === false) return false;
      if (d.category !== cat) return false;
      if (d.permission === 'pages.publish' && !BOOT.canUseHtml) return false;
      if (filter && d.title.indexOf(filter) === -1 && t.indexOf(filter) === -1) return false;
      return true;
    });
    if (!items.length) return;
    html += '<div class="ijb-cat">' + esc(cats[cat]) + '</div><div class="ijb-pal-grid">';
    items.forEach(function (t) {
      var d = CATALOG[t];
      html += '<button type="button" class="ijb-pal" draggable="true" data-type="' + esc(t) + '" title="' + esc(d.description || d.title) + '">'
        + '<i>' + esc(d.icon || '▪') + '</i><span>' + esc(d.title) + '</span></button>';
    });
    html += '</div>';
  });
  pal.innerHTML = html || '<p class="ijb-empty">موردی یافت نشد.</p>';
}

function makeNode(type) {
  var def = CATALOG[type] || {};
  var node = {
    id: newId(), type: type,
    content: clone((def.defaults && def.defaults.content) || {}),
    children: [],
    style: clone((def.defaults && def.defaults.style) || {}),
  };
  ensureNode(node);
  if (type === 'columns') setColumnCount(node, parseInt(node.content.count, 10) || 2);
  return node;
}

function setColumnCount(node, count) {
  count = Math.min(6, Math.max(1, count));
  node.content.count = count;
  while (node.children.length < count) {
    node.children.push(ensureNode({ id: newId(), type: 'column', content: {} }));
  }
  node.children = node.children.slice(0, count);
  node.children.forEach(function (c) { c.type = 'column'; });
}

function insertNode(node, targetId, pos) {
  if (!targetId) {
    commit();
    state.tree.children.push(node);
    afterStructuralChange();
    select(node.id);
    return;
  }
  var target = findNode(state.tree, targetId);
  if (!target) return;
  commit();
  if (pos === 'inside' && isContainer(target.type)) {
    target.children.push(node);
  } else {
    var parent = findParent(state.tree, targetId);
    var siblings = parent ? parent.children : state.tree.children;
    // root itself: append
    if (targetId === state.tree.id) { state.tree.children.push(node); }
    else {
      var idx = siblings.findIndex(function (c) { return c.id === targetId; });
      siblings.splice(pos === 'after' ? idx + 1 : idx, 0, node);
    }
  }
  afterStructuralChange();
  select(node.id);
}

function deleteNode(id) {
  if (id === state.tree.id) { toast('ریشه حذف‌شدنی نیست', true); return; }
  var parent = findParent(state.tree, id);
  if (!parent) return;
  commit();
  parent.children = parent.children.filter(function (c) { return c.id !== id; });
  state.selectedId = parent.id;
  afterStructuralChange();
}

function moveNode(id, dir) {
  var parent = findParent(state.tree, id);
  if (!parent) return;
  var i = parent.children.findIndex(function (c) { return c.id === id; });
  var j = i + dir;
  if (i < 0 || j < 0 || j >= parent.children.length) return;
  commit();
  var tmp = parent.children[i];
  parent.children[i] = parent.children[j];
  parent.children[j] = tmp;
  afterStructuralChange();
}

function reId(node) {
  node.id = newId();
  (node.children || []).forEach(reId);
  return node;
}

function duplicateNode(id) {
  var node = findNode(state.tree, id);
  if (!node || id === state.tree.id) return;
  commit();
  var copy = reId(clone(node));
  var parent = findParent(state.tree, id);
  var siblings = parent ? parent.children : [];
  siblings.splice(siblings.findIndex(function (c) { return c.id === id; }) + 1, 0, copy);
  afterStructuralChange();
  select(copy.id);
}

/* ── Navigator ── */
function renderNavigator() {
  var el = $('#ijb-tree');
  el.innerHTML = navHtml(state.tree, 0);
}

function navHtml(node, depth) {
  if (depth > 14) return '';
  var def = CATALOG[node.type] || {};
  var sel = node.id === state.selectedId ? ' sel' : '';
  var html = '<div class="ijb-nav-item' + sel + '" data-id="' + esc(node.id) + '">'
    + '<div class="ijb-nav-row" draggable="true" data-id="' + esc(node.id) + '">'
    + '<i>' + esc(def.icon || '▪') + '</i><b>' + esc(nodeTitle(node)) + '</b>'
    + '<button type="button" data-act="dup" title="تکثیر">⧉</button>'
    + '<button type="button" data-act="del" title="حذف">🗑</button>'
    + '</div>';
  if (node.children && node.children.length) {
    html += '<div class="ijb-nav-kids">';
    node.children.forEach(function (c) { html += navHtml(c, depth + 1); });
    html += '</div>';
  }
  return html + '</div>';
}

/* ── Props panel ── */
function currentNode() {
  return state.selectedId ? findNode(state.tree, state.selectedId) : null;
}

function renderProps() {
  var box = $('#ijb-props');
  var node = currentNode();
  $all('#ijb-right-tabs button').forEach(function (b) {
    b.classList.toggle('on', b.dataset.tab === state.propsTab);
  });
  if (!node) {
    box.innerHTML = '<p class="ijb-empty">یک جزء را در بوم انتخاب کنید.<br>یا از پنل چپ جزء جدید بکشید.</p>';
    return;
  }
  var def = CATALOG[node.type] || {};
  var head = '<div class="ijb-cat" style="margin-top:0">' + esc(def.icon || '▪') + ' ' + esc(nodeTitle(node))
    + ' <small style="color:#5b6190">#' + esc(node.id) + '</small></div>';
  if (state.propsTab === 'content') box.innerHTML = head + propsContent(node, def);
  else if (state.propsTab === 'style') box.innerHTML = head + propsStyle(node);
  else box.innerHTML = head + propsAdvanced(node);
  bindProps(box, node, def);
}

function fieldRow(field, inner) {
  return '<div class="ijb-field"><label>' + esc(field.label || field.key) + '</label>' + inner
    + (field.help ? '<div class="ijb-help">' + esc(field.help) + '</div>' : '') + '</div>';
}

function propsContent(node, def) {
  var html = '';
  (def.fields || []).forEach(function (f) {
    html += contentControl(node, f);
  });
  if (node.type === 'block') html += blockDataForm(node, 'block');
  if (node.type === 'widget') html += widgetDataForm(node);
  if (!(def.fields || []).length && node.type !== 'block' && node.type !== 'widget') {
    html += '<p class="ijb-empty">این جزء تنظیم محتوایی ندارد؛ از تب استایل استفاده کنید.</p>';
  }
  return html;
}

function dynButton(node, f) {
  if (!f.dynamic) return '';
  var active = node.dynamic && node.dynamic[f.key];
  var chip = active
    ? '<div class="ijb-dyn-chip"><span>' + esc(active) + '</span><button type="button" data-dyn-clear="' + esc(f.key) + '">✕</button></div>'
    : '';
  return '<button type="button" class="ijb-dyn-btn" data-dyn="' + esc(f.key) + '" title="محتوای داینامیک">⚡ داینامیک</button>' + chip;
}

function contentControl(node, f) {
  var v = node.content[f.key];
  var dir = f.dir ? ' dir="' + f.dir + '"' : '';
  var label = '<label>' + esc(f.label || f.key) + dynButton(node, f) + '</label>';
  var inner = '';
  switch (f.type) {
    case 'text': case 'url': case 'binding':
      inner = '<input type="text" data-k="' + esc(f.key) + '" value="' + esc(v == null ? (f.default || '') : v) + '"' + dir
        + (f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : '') + '>';
      break;
    case 'textarea':
      inner = '<textarea data-k="' + esc(f.key) + '" rows="' + (f.rows || 3) + '"' + dir + '>' + esc(v == null ? (f.default || '') : v) + '</textarea>';
      break;
    case 'richtext':
      inner = '<textarea data-k="' + esc(f.key) + '" rows="6" dir="rtl" placeholder="متن… (ابزار بولد/ایتالیک/لینک)">'
        + esc(v == null ? (f.default || '') : v) + '</textarea>'
        + '<div class="ijb-help"><button type="button" class="ijb-dyn-btn" data-rich="b">B</button> '
        + '<button type="button" class="ijb-dyn-btn" data-rich="i">I</button> '
        + '<button type="button" class="ijb-dyn-btn" data-rich="a">🔗</button></div>';
      break;
    case 'number':
      inner = '<input type="number" data-k="' + esc(f.key) + '" value="' + esc(v == null ? (f.default || '') : v) + '"'
        + (f.min != null ? ' min="' + f.min + '"' : '') + (f.max != null ? ' max="' + f.max + '"' : '') + '>';
      break;
    case 'range':
      inner = '<input type="range" data-k="' + esc(f.key) + '" value="' + esc(v == null ? (f.default || '') : v) + '"'
        + ' min="' + (f.min != null ? f.min : 0) + '" max="' + (f.max != null ? f.max : 100) + '" step="' + (f.step || 1) + '">'
        + '<span class="ijb-help" data-range-val>' + esc(v == null ? (f.default || '') : v) + '</span>';
      break;
    case 'select':
      inner = '<select data-k="' + esc(f.key) + '">';
      Object.keys(f.options || {}).forEach(function (k) {
        inner += '<option value="' + esc(k) + '"' + (String(v == null ? f.default : v) === k ? ' selected' : '') + '>' + esc(f.options[k]) + '</option>';
      });
      inner += '</select>';
      break;
    case 'checkbox':
      return '<div class="ijb-field"><label class="ijb-check"><input type="checkbox" data-k="' + esc(f.key) + '"'
        + (v ? ' checked' : (f.default && v == null ? ' checked' : '')) + '> ' + esc(f.label || f.key) + '</label></div>';
    case 'color':
      inner = '<input type="color" data-k="' + esc(f.key) + '" value="' + esc(v || f.default || '#000000') + '">';
      break;
    case 'image':
      inner = imageControl(f.key, v || '');
      break;
    case 'images':
      inner = imagesControl(f.key, Array.isArray(v) ? v : []);
      break;
    case 'icon':
      inner = '<select data-k="' + esc(f.key) + '">';
      Object.keys(BOOT.icons || {}).forEach(function (k) {
        inner += '<option value="' + esc(k) + '"' + ((v || f.default) === k ? ' selected' : '') + '>' + esc(BOOT.icons[k]) + '</option>';
      });
      inner += '</select>';
      break;
    case 'repeater':
      return repeaterControl(node, f, Array.isArray(v) ? v : []);
    case 'query':
      return queryControl(f.key, v && typeof v === 'object' ? v : {});
    case 'block':
      inner = optionSelect(f.key, v || '', BOOT.blocks || [], 'بلاکی ثبت نشده است.');
      break;
    case 'widget':
      inner = optionSelect(f.key, v || '', BOOT.widgets || [], 'ویجتی ثبت نشده است.');
      break;
    case 'form-select':
      inner = '<select data-k="' + esc(f.key) + '"><option value="0">— قالب آماده —</option>'
        + (BOOT.forms || []).map(function (o) {
          return '<option value="' + o.id + '"' + (parseInt(v, 10) === o.id ? ' selected' : '') + '>' + esc(o.title) + '</option>';
        }).join('') + '</select>';
      break;
    case 'menu-select':
      inner = '<select data-k="' + esc(f.key) + '"><option value="0">— از روی موقعیت —</option>'
        + (BOOT.menus || []).map(function (o) {
          return '<option value="' + o.id + '"' + (parseInt(v, 10) === o.id ? ' selected' : '') + '>' + esc(o.name) + ' (' + esc(o.location || '-') + ')</option>';
        }).join('') + '</select>';
      break;
    default:
      inner = '<input type="text" data-k="' + esc(f.key) + '" value="' + esc(v == null ? '' : v) + '"' + dir + '>';
  }
  return '<div class="ijb-field">' + label + inner
    + (f.help ? '<div class="ijb-help">' + esc(f.help) + '</div>' : '') + '</div>';
}

function optionSelect(key, val, list, emptyMsg) {
  if (!list.length) return '<div class="ijb-help">' + esc(emptyMsg) + '</div>';
  var html = '<select data-k="' + esc(key) + '"><option value="">— انتخاب —</option>';
  list.forEach(function (o) {
    html += '<option value="' + esc(o.slug) + '"' + (val === o.slug ? ' selected' : '') + '>' + esc(o.title) + ' (' + esc(o.slug) + ')</option>';
  });
  return html + '</select>';
}

function imageControl(key, val) {
  return '<div class="ijb-img-row"><input type="text" data-k="' + esc(key) + '" value="' + esc(val) + '" dir="ltr" placeholder="https://…">'
    + '<button type="button" data-media="' + esc(key) + '">رسانه</button></div>'
    + (val ? '<img class="ijb-img-prev" src="' + esc(val) + '" alt="">' : '');
}

function imagesControl(key, list) {
  var html = '<div data-images="' + esc(key) + '">';
  list.forEach(function (img, i) {
    var src = typeof img === 'string' ? img : (img.src || '');
    html += '<div class="ijb-img-row" style="margin-bottom:6px"><input type="text" data-img="' + i + '" value="' + esc(src) + '" dir="ltr">'
      + '<button type="button" data-img-del="' + i + '">🗑</button></div>';
  });
  html += '</div><button type="button" class="ijb-add" data-img-add="' + esc(key) + '">＋ افزودن تصویر</button>';
  return html;
}

function repeaterControl(node, f, items) {
  var html = '<div class="ijb-field"><label>' + esc(f.label || f.key) + '</label><div data-rep="' + esc(f.key) + '">';
  items.forEach(function (item, i) {
    html += '<div class="ijb-rep-item" data-rep-i="' + i + '"><div class="ijb-rep-head"><span>#' + (i + 1) + '</span>'
      + '<button type="button" data-rep-up="' + i + '">↑</button><button type="button" data-rep-down="' + i + '">↓</button>'
      + '<button type="button" class="danger" data-rep-del="' + i + '">حذف</button></div>';
    (f.fields || []).forEach(function (sf) {
      html += subControl(node, f.key, i, sf, item[sf.key]);
    });
    html += '</div>';
  });
  html += '</div>';
  if (!f.max || items.length < f.max) {
    html += '<button type="button" class="ijb-add" data-rep-add="' + esc(f.key) + '">＋ افزودن</button>';
  }
  return html + '</div>';
}

function subControl(node, repKey, idx, sf, v) {
  var dk = repKey + '.' + idx + '.' + sf.key;
  if (sf.type === 'textarea') return '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><textarea data-rk="' + esc(dk) + '" rows="2">' + esc(v == null ? '' : v) + '</textarea></div>';
  if (sf.type === 'richtext') return '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><textarea data-rk="' + esc(dk) + '" rows="3">' + esc(v == null ? '' : v) + '</textarea></div>';
  if (sf.type === 'checkbox') return '<div class="ijb-field"><label class="ijb-check"><input type="checkbox" data-rk="' + esc(dk) + '"' + (v ? ' checked' : '') + '> ' + esc(sf.label || sf.key) + '</label></div>';
  if (sf.type === 'image') return '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label>' + imageControl(dk, v || '').replace(/data-k=/, 'data-rk=') + '</div>';
  if (sf.type === 'select') {
    var html = '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><select data-rk="' + esc(dk) + '">';
    Object.keys(sf.options || {}).forEach(function (k) {
      html += '<option value="' + esc(k) + '"' + (String(v) === k ? ' selected' : '') + '>' + esc(sf.options[k]) + '</option>';
    });
    return html + '</select></div>';
  }
  if (sf.type === 'number') return '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><input type="number" data-rk="' + esc(dk) + '" value="' + esc(v == null ? '' : v) + '"></div>';
  return '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><input type="text" data-rk="' + esc(dk) + '" value="' + esc(v == null ? '' : v) + '"></div>';
}

function queryControl(key, q) {
  q = q || {};
  var pts = BOOT.postTypes || [];
  var html = '<div class="ijb-field"><label>کوئری نوشته‌ها</label><div data-query="' + esc(key) + '">';
  html += '<div class="ijb-field"><label>نوع نوشته</label><select data-q="post_type">'
    + pts.map(function (p) { return '<option value="' + esc(p.slug) + '"' + ((q.post_type || 'post') === p.slug ? ' selected' : '') + '>' + esc(p.name) + '</option>'; }).join('')
    + '</select></div>';
  html += '<div class="ijb-field"><label>تعداد</label><input type="number" data-q="limit" min="1" max="50" value="' + esc(q.limit != null ? q.limit : 6) + '"></div>';
  html += '<div class="ijb-field"><label>شروع از (offset)</label><input type="number" data-q="offset" min="0" max="1000" value="' + esc(q.offset != null ? q.offset : 0) + '"></div>';
  html += '<div class="ijb-field"><label>مرتب‌سازی</label><select data-q="order_by">'
    + ['PUBLISHED_AT', 'CREATED_AT', 'TITLE', 'ID'].map(function (o) {
      return '<option' + ((q.order_by || 'PUBLISHED_AT') === o ? ' selected' : '') + '>' + o + '</option>';
    }).join('') + '</select></div>';
  html += '<div class="ijb-field"><label>جهت</label><select data-q="order">'
    + '<option' + ((q.order || 'DESC') === 'DESC' ? ' selected' : '') + '>DESC</option>'
    + '<option' + (q.order === 'ASC' ? ' selected' : '') + '>ASC</option></select></div>';
  html += '<div class="ijb-field"><label>دسته/برچسب</label><select data-q="term"><option value="">— همه —</option>';
  (BOOT.taxonomies || []).forEach(function (t) {
    (t.terms || []).forEach(function (term) {
      var val = t.slug + ':' + term.slug;
      var sel = (q.taxonomy === t.slug && q.term_slug === term.slug) ? ' selected' : '';
      html += '<option value="' + esc(val) + '"' + sel + '>' + esc(t.name) + ' › ' + esc(term.name) + '</option>';
    });
  });
  html += '</select></div>';
  html += '<div class="ijb-field"><label>شناسه نویسنده (خالی=همه)</label><input type="number" data-q="author_id" min="0" value="' + esc(q.author_id || '') + '"></div>';
  html += '</div></div>';
  return html;
}

function blockDataForm(node, kind) {
  var list = kind === 'block' ? (BOOT.blocks || []) : (BOOT.widgets || []);
  var slug = node.content[kind] || '';
  var def = null;
  list.forEach(function (b) { if (b.slug === slug) def = b; });
  if (!def) return '<p class="ijb-empty">ابتدا ' + (kind === 'block' ? 'بلاک' : 'ویجت') + ' را انتخاب کنید.</p>';
  var data = node.content.data && typeof node.content.data === 'object' ? node.content.data : {};
  var html = '<div class="ijb-cat">تنظیمات «' + esc(def.title) + '»</div><div data-blockdata="' + kind + '">';
  (def.schema || []).forEach(function (sf) {
    var dk = 'data.' + sf.key;
    var v = data[sf.key] != null ? data[sf.key] : def.defaults[sf.key];
    if (sf.type === 'textarea') html += '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><textarea data-bk="' + esc(dk) + '" rows="3">' + esc(v == null ? '' : v) + '</textarea></div>';
    else if (sf.type === 'checkbox') html += '<div class="ijb-field"><label class="ijb-check"><input type="checkbox" data-bk="' + esc(dk) + '"' + (v ? ' checked' : '') + '> ' + esc(sf.label || sf.key) + '</label></div>';
    else if (sf.type === 'select') {
      html += '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><select data-bk="' + esc(dk) + '">';
      Object.keys(sf.options || {}).forEach(function (k) {
        html += '<option value="' + esc(k) + '"' + (String(v) === k ? ' selected' : '') + '>' + esc(sf.options[k]) + '</option>';
      });
      html += '</select></div>';
    }
    else if (sf.type === 'number') html += '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><input type="number" data-bk="' + esc(dk) + '" value="' + esc(v == null ? '' : v) + '"></div>';
    else if (sf.type === 'color') html += '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><input type="color" data-bk="' + esc(dk) + '" value="' + esc(v || '#000000') + '"></div>';
    else if (sf.type === 'image') html += '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label>' + imageControl(dk, v || '').replace(/data-k=/, 'data-bk=') + '</div>';
    else html += '<div class="ijb-field"><label>' + esc(sf.label || sf.key) + '</label><input type="text" data-bk="' + esc(dk) + '" value="' + esc(v == null ? '' : v) + '"></div>';
  });
  return html + '</div>';
}
function widgetDataForm(node) { return blockDataForm(node, 'widget'); }

/* ── Style tab ── */
function propsStyle(node) {
  var perDevice = state.device !== 'desktop';
  var html = perDevice
    ? '<div class="ijb-device-note">✎ در حال ویرایش استایل «' + esc((BOOT.breakpoints || {})[state.device] || state.device) + '» — مقادیر فقط در همین دستگاه اعمال می‌شوند.</div>'
    : '';
  var groups = BOOT.styleGroups || {};
  Object.keys(groups).forEach(function (g, gi) {
    html += '<details class="ijb-group"' + (gi < 2 ? ' open' : '') + '><summary>' + esc(groups[g].label) + '</summary><div class="ijb-group-body">';
    (groups[g].controls || []).forEach(function (c) {
      html += styleControl(node, c, perDevice);
    });
    html += '</div></details>';
  });
  return html;
}

function styleTarget(node, perDevice) {
  if (!perDevice) return node.style;
  if (!node.responsive[state.device] || typeof node.responsive[state.device] !== 'object') node.responsive[state.device] = {};
  return node.responsive[state.device];
}

function styleControl(node, c, perDevice) {
  var target = styleTarget(node, perDevice);
  var v = target[c.key];
  var dk = 'style.' + c.key;
  var dir = c.dir ? ' dir="' + c.dir + '"' : '';
  var label = '<label>' + esc(c.label || c.key) + '</label>';
  if (c.type === 'color') return '<div class="ijb-field">' + label + '<input type="color" data-sk="' + esc(dk) + '" value="' + esc(v || '#000000') + '"><div class="ijb-help">خالی = ارث‌بری از والد</div></div>';
  if (c.type === 'select') {
    var html = '<div class="ijb-field">' + label + '<select data-sk="' + esc(dk) + '"><option value="">— پیش‌فرض —</option>';
    Object.keys(c.options || {}).forEach(function (k) {
      html += '<option value="' + esc(k) + '"' + (String(v) === k ? ' selected' : '') + '>' + esc(c.options[k]) + '</option>';
    });
    return html + '</select></div>';
  }
  if (c.type === 'range') return '<div class="ijb-field">' + label + '<input type="range" data-sk="' + esc(dk) + '" min="' + c.min + '" max="' + c.max + '" step="' + (c.step || 1) + '" value="' + esc(v == null ? '' : v) + '"></div>';
  if (c.type === 'number') return '<div class="ijb-field">' + label + '<input type="number" data-sk="' + esc(dk) + '" value="' + esc(v == null ? '' : v) + '"' + (c.placeholder ? ' placeholder="' + esc(c.placeholder) + '"' : '') + '></div>';
  return '<div class="ijb-field">' + label + '<input type="text" data-sk="' + esc(dk) + '" value="' + esc(v == null ? '' : v) + '"' + dir + (c.placeholder ? ' placeholder="' + esc(c.placeholder) + '"' : '') + '></div>';
}

/* ── Advanced tab ── */
function propsAdvanced(node) {
  var html = '<div class="ijb-field"><label>نام مدیریتی</label><input type="text" data-adv="name" value="' + esc(node.name || '') + '" placeholder="برای ساختار و جستجو"></div>';
  html += '<div class="ijb-field"><label class="ijb-check"><input type="checkbox" data-adv="hidden"' + (node.hidden ? ' checked' : '') + '> مخفی در خروجی نهایی (در ویرایشگر دیده می‌شود)</label></div>';
  html += '<div class="ijb-field"><label class="ijb-check"><input type="checkbox" data-adv="locked"' + (node.locked ? ' checked' : '') + '> قفل (جلوگیری از حذف تصادفی)</label></div>';
  html += '<div class="ijb-field"><label>کلاس CSS اضافه</label><input type="text" data-adv="attrs.cssClass" value="' + esc(node.attrs.cssClass || '') + '" dir="ltr"></div>';
  html += '<div class="ijb-field"><label>لنگر (id)</label><input type="text" data-adv="attrs.anchor" value="' + esc(node.attrs.anchor || '') + '" dir="ltr"></div>';

  html += '<div class="ijb-cat">انیمیشن ورود</div>';
  html += '<div class="ijb-field"><label>نوع</label><select data-adv="animation.type"><option value="">— بدون انیمیشن —</option>'
    + Object.keys(BOOT.animations || {}).map(function (k) {
      return '<option value="' + esc(k) + '"' + ((node.animation.type || '') === k ? ' selected' : '') + '>' + esc(BOOT.animations[k]) + '</option>';
    }).join('') + '</select></div>';
  html += '<div class="ijb-field"><label>تأخیر (ms)</label><input type="number" data-adv="animation.delay" min="0" max="5000" value="' + esc(node.animation.delay != null ? node.animation.delay : 0) + '"></div>';
  html += '<div class="ijb-field"><label>مدت (ms)</label><input type="number" data-adv="animation.duration" min="100" max="5000" value="' + esc(node.animation.duration != null ? node.animation.duration : 600) + '"></div>';

  html += '<div class="ijb-cat">نمایش در دستگاه‌ها</div>';
  ['desktop', 'laptop', 'tablet', 'mobile'].forEach(function (d) {
    var visible = node.visibility[d] !== false;
    html += '<div class="ijb-field"><label class="ijb-check"><input type="checkbox" data-vis="' + d + '"' + (visible ? ' checked' : '') + '> ' + esc((BOOT.breakpoints || {})[d] || d) + '</label></div>';
  });

  html += '<div class="ijb-cat">شرایط نمایش (همه باید برقرار باشند)</div><div data-conds>';
  (node.conditions || []).forEach(function (cond, i) {
    html += '<div class="ijb-rep-item"><div class="ijb-rep-head"><span>شرط #' + (i + 1) + '</span>'
      + '<button type="button" class="danger" data-cond-del="' + i + '">حذف</button></div>';
    html += '<div class="ijb-field"><label>قانون</label><select data-cond-rule="' + i + '">'
      + Object.keys(BOOT.conditionRules || {}).map(function (r) {
        return '<option value="' + esc(r) + '"' + (cond.rule === r ? ' selected' : '') + '>' + esc(BOOT.conditionRules[r].label) + '</option>';
      }).join('') + '</select></div>';
    html += '<div class="ijb-field"><label>مقدار</label>' + condValueControl(cond, i) + '</div></div>';
  });
  html += '</div><button type="button" class="ijb-add" data-cond-add>＋ افزودن شرط</button>';
  return html;
}

function condValueControl(cond, i) {
  var rule = (BOOT.conditionRules || {})[cond.rule] || {};
  var kind = rule.value;
  var v = cond.value;
  if (kind === 'roles') {
    return '<select data-cond-val="' + i + '">' + (BOOT.roles || []).map(function (r) {
      var cur = Array.isArray(v) ? v[0] : v;
      return '<option value="' + esc(r.slug) + '"' + (cur === r.slug ? ' selected' : '') + '>' + esc(r.name) + '</option>';
    }).join('') + '</select><div class="ijb-help">برای چند نقش، چند شرط جدا بسازید.</div>';
  }
  if (kind === 'device') {
    return '<select data-cond-val="' + i + '">' + Object.keys(BOOT.breakpoints || {}).map(function (d) {
      return '<option value="' + esc(d) + '"' + (v === d ? ' selected' : '') + '>' + esc(BOOT.breakpoints[d]) + '</option>';
    }).join('') + '</select>';
  }
  if (kind === 'post_type') {
    return '<select data-cond-val="' + i + '">' + (BOOT.postTypes || []).map(function (p) {
      return '<option value="' + esc(p.slug) + '"' + (v === p.slug ? ' selected' : '') + '>' + esc(p.name) + '</option>';
    }).join('') + '</select>';
  }
  if (kind === 'date-range') {
    var parts = String(v || '').split('..');
    return '<div class="ijb-img-row"><input type="date" data-cond-from="' + i + '" value="' + esc(parts[0] || '') + '">'
      + '<input type="date" data-cond-to="' + i + '" value="' + esc(parts[1] || '') + '"></div>';
  }
  return '<div class="ijb-help">این قانون مقداری نمی‌گیرد.</div>';
}

/* ── Props events ── */
function bindProps(box, node, def) {
  // content controls
  $all('[data-k]', box).forEach(function (el) {
    bindControl(el, function (val) {
      node.content[el.dataset.k] = val;
      if (node.type === 'columns' && el.dataset.k === 'count') setColumnCount(node, parseInt(val, 10) || 2);
      if ((node.type === 'block' || node.type === 'widget') && (el.dataset.k === 'block' || el.dataset.k === 'widget')) {
        node.content.data = {};
        renderProps(); // show new data form
      }
    });
  });
  // repeater sub-controls
  $all('[data-rk]', box).forEach(function (el) {
    bindControl(el, function (val) {
      var parts = el.dataset.rk.split('.');
      var arr = node.content[parts[0]];
      if (Array.isArray(arr) && arr[parseInt(parts[1], 10)]) arr[parseInt(parts[1], 10)][parts[2]] = val;
    });
  });
  // block/widget data
  $all('[data-bk]', box).forEach(function (el) {
    bindControl(el, function (val) {
      var key = el.dataset.bk.split('.')[1];
      if (!node.content.data || typeof node.content.data !== 'object') node.content.data = {};
      node.content.data[key] = val;
    });
  });
  // style controls
  $all('[data-sk]', box).forEach(function (el) {
    var perDevice = state.device !== 'desktop';
    bindControl(el, function (val) {
      var key = el.dataset.sk.split('.')[1];
      var target = styleTarget(node, perDevice);
      if (val === '' || val == null) delete target[key];
      else target[key] = val;
    });
  });
  // query controls
  var qbox = $('[data-query]', box);
  if (qbox) {
    $all('[data-q]', qbox).forEach(function (el) {
      el.addEventListener('change', function () {
        commit();
        collectQuery(node, qbox);
        afterStructuralChange();
      });
    });
  }
  // repeater structural buttons
  $all('[data-rep-add]', box).forEach(function (btn) {
    btn.addEventListener('click', function () {
      commit();
      var key = btn.dataset.repAdd;
      if (!Array.isArray(node.content[key])) node.content[key] = [];
      var f = (def.fields || []).find(function (x) { return x.key === key; }) || {};
      var item = {};
      (f.fields || []).forEach(function (sf) { item[sf.key] = sf.default != null ? clone(sf.default) : ''; });
      node.content[key].push(item);
      afterStructuralChange();
    });
  });

  // images text inputs
  $all('[data-img]', box).forEach(function (el) {
    bindControl(el, function (val) {
      var imgBox = el.closest('[data-images]');
      node.content[imgBox.dataset.images][parseInt(el.dataset.img, 10)] = val;
    });
  });
  // advanced inputs
  $all('[data-adv]', box).forEach(function (el) {
    bindControl(el, function (val) {
      setPath(node, el.dataset.adv, val);
    });
  });
  $all('[data-vis]', box).forEach(function (el) {
    el.addEventListener('change', function () {
      commit();
      if (el.checked) delete node.visibility[el.dataset.vis];
      else node.visibility[el.dataset.vis] = false;
      afterStructuralChange();
    });
  });
  $all('[data-cond-rule]', box).forEach(function (el) {
    el.addEventListener('change', function () {
      commit();
      var cond = node.conditions[parseInt(el.dataset.condRule, 10)];
      cond.rule = el.value;
      delete cond.value;
      afterStructuralChange();
    });
  });
  $all('[data-cond-val]', box).forEach(function (el) {
    el.addEventListener('change', function () {
      commit();
      node.conditions[parseInt(el.dataset.condVal, 10)].value = el.value;
      afterStructuralChange();
    });
  });
  ['From', 'To'].forEach(function (suf) {
    $all('[data-cond-' + suf.toLowerCase() + ']', box).forEach(function (el) {
      el.addEventListener('change', function () {
        commit();
        var i = parseInt(el.dataset['cond' + suf], 10);
        var from = $('[data-cond-from="' + i + '"]', box).value;
        var to = $('[data-cond-to="' + i + '"]', box).value;
        node.conditions[i].value = from + '..' + to;
        afterStructuralChange();
      });
    });
  });
}

function propsDelegatedClick(e) {
  var node = currentNode();
  if (!node) return;
  var def = CATALOG[node.type] || {};

    var t = e.target.closest('button');
    if (!t) return;
    var ds = t.dataset;
    var repKey = null;
    var repBox = t.closest('[data-rep]');
    if (repBox) repKey = repBox.dataset.rep;
    if (ds.repDel != null && repKey) {
      commit();
      node.content[repKey].splice(parseInt(ds.repDel, 10), 1);
      afterStructuralChange();
    } else if (ds.repUp != null && repKey) {
      var i = parseInt(ds.repUp, 10);
      if (i > 0) {
        commit();
        var arr = node.content[repKey];
        var tmp = arr[i - 1]; arr[i - 1] = arr[i]; arr[i] = tmp;
        afterStructuralChange();
      }
    } else if (ds.repDown != null && repKey) {
      var j = parseInt(ds.repDown, 10);
      var arr2 = node.content[repKey];
      if (j < arr2.length - 1) {
        commit();
        var tmp2 = arr2[j + 1]; arr2[j + 1] = arr2[j]; arr2[j] = tmp2;
        afterStructuralChange();
      }
    } else if (ds.imgAdd) {
      openMedia(function (url) {
        commit();
        if (!Array.isArray(node.content[ds.imgAdd])) node.content[ds.imgAdd] = [];
        node.content[ds.imgAdd].push(url);
        afterStructuralChange();
      });
    } else if (ds.imgDel != null) {
      var imgBox = t.closest('[data-images]');
      if (imgBox) {
        commit();
        node.content[imgBox.dataset.images].splice(parseInt(ds.imgDel, 10), 1);
        afterStructuralChange();
      }
    } else if (ds.media) {
      var mediaKey = ds.media;
      var isRep = mediaKey.indexOf('.') !== -1 && !node.content[mediaKey];
      openMedia(function (url) {
        commit();
        if (isRep || mediaKey.indexOf('.') !== -1) {
          // repeater image (rk) or block data (bk)
          var rkEl = $('[data-rk="' + mediaKey + '"], [data-bk="' + mediaKey + '"]', box);
          if (rkEl) {
            if (rkEl.dataset.rk) {
              var p = mediaKey.split('.');
              node.content[p[0]][parseInt(p[1], 10)][p[2]] = url;
            } else {
              if (!node.content.data) node.content.data = {};
              node.content.data[mediaKey.split('.')[1]] = url;
            }
          }
        } else {
          node.content[mediaKey] = url;
        }
        afterStructuralChange();
      });
    } else if (ds.dyn) {
      openBindings(function (binding) {
        commit();
        node.dynamic[ds.dyn] = binding;
        afterStructuralChange();
        toast('بایندینگ ' + binding + ' فعال شد');
      });
    } else if (ds.dynClear) {
      commit();
      delete node.dynamic[ds.dynClear];
      afterStructuralChange();
    } else if (ds.rich) {
      var ta = t.closest('.ijb-field').querySelector('textarea');
      if (ta) wrapRich(ta, ds.rich);
    } else if (ds.condAdd != null) {
      commit();
      node.conditions.push({ rule: 'logged_in' });
      afterStructuralChange();
    } else if (ds.condDel != null) {
      commit();
      node.conditions.splice(parseInt(ds.condDel, 10), 1);
      afterStructuralChange();
    }

}

function bindControl(el, apply) {
  function read() {
    if (el.type === 'checkbox') return el.checked;
    if (el.type === 'number' || el.type === 'range') {
      if (el.value === '') return '';
      var n = parseFloat(el.value);
      return isNaN(n) ? '' : n;
    }
    return el.value;
  }
  el.addEventListener('input', function () {
    apply(read());
    if (el.type === 'range') {
      var badge = el.parentElement.querySelector('[data-range-val]');
      if (badge) badge.textContent = el.value;
    }
    markDirty();
    renderNavigatorSoft();
    schedulePreview();
  });
  el.addEventListener('change', function () {
    // History entry on commit points (blur/select/checkbox/range-release).
    state.past.push(clone(beforeChangeSnapshot()));
    if (state.past.length > MAX_HISTORY) state.past.shift();
    state.future = [];
    updateUndoRedo();
    markDirty();
    schedulePreview();
    renderNavigator();
  });
  function beforeChangeSnapshot() {
    // Reconstruct pre-edit tree: current tree minus this control's latest value
    // is unknowable; use last committed snapshot or sibling approach:
    // we pushed nothing on input, so take the newest past entry as base if
    // it differs, else current-minus-one-edit. Simplest correct: snapshot the
    // tree as it was at focus time.
    return el._focusTree || state.tree;
  }
  el.addEventListener('focus', function () { el._focusTree = clone(state.tree); });
}

function setPath(obj, path, val) {
  var parts = path.split('.');
  var cur = obj;
  for (var i = 0; i < parts.length - 1; i++) {
    if (!cur[parts[i]] || typeof cur[parts[i]] !== 'object') cur[parts[i]] = {};
    cur = cur[parts[i]];
  }
  var last = parts[parts.length - 1];
  if (val === '' || val == null) {
    if (parts[0] === 'animation' || parts[0] === 'attrs') delete cur[last];
    else cur[last] = val;
  } else cur[last] = val;
}

function collectQuery(node, qbox) {
  var q = {};
  $all('[data-q]', qbox).forEach(function (el) {
    var k = el.dataset.q;
    if (k === 'term') {
      if (el.value && el.value.indexOf(':') !== -1) {
        var p = el.value.split(':');
        q.taxonomy = p[0]; q.term_slug = p[1];
      }
    } else if (k === 'author_id') {
      if (parseInt(el.value, 10) > 0) q.author_id = parseInt(el.value, 10);
    } else if (k === 'limit' || k === 'offset') {
      q[k] = parseInt(el.value, 10) || 0;
    } else q[k] = el.value;
  });
  var key = qbox.dataset.query;
  node.content[key] = q;
}

function wrapRich(ta, kind) {
  var s = ta.selectionStart || 0, e = ta.selectionEnd || 0;
  var val = ta.value, sel = val.slice(s, e) || 'متن';
  var out = sel;
  if (kind === 'b') out = '<strong>' + sel + '</strong>';
  else if (kind === 'i') out = '<em>' + sel + '</em>';
  else if (kind === 'a') out = '<a href="#">' + sel + '</a>';
  ta.value = val.slice(0, s) + out + val.slice(e);
  ta.dispatchEvent(new Event('input', { bubbles: true }));
  ta.focus();
}

function renderNavigatorSoft() {
  // Light refresh of the selected row label while typing.
  var row = $('#ijb-tree .ijb-nav-item.sel b');
  var node = currentNode();
  if (row && node) row.textContent = nodeTitle(node);
}

/* ── Canvas interactions ── */
function bindCanvas() {
  var canvas = $('#ijb-canvas');
  var wrap = $('#ijb-canvas-wrap');
  var bar = document.createElement('div');
  bar.id = 'ijb-nodebar';
  wrap.appendChild(bar);
  var line = document.createElement('div');
  line.id = 'ijb-drop-line';
  wrap.appendChild(line);

  canvas.addEventListener('click', function (e) {
    var a = e.target.closest('a');
    if (a) e.preventDefault();
    var node = e.target.closest('[data-ij-id]');
    select(node ? node.dataset.ijId : null);
  });
  canvas.addEventListener('submit', function (e) { e.preventDefault(); });

  var hoverId = null;
  canvas.addEventListener('mouseover', function (e) {
    var node = e.target.closest('[data-ij-id]');
    var id = node ? node.dataset.ijId : null;
    if (id === hoverId) return;
    hoverId = id;
    $all('#ijb-canvas .ijb-hover').forEach(function (el) { el.classList.remove('ijb-hover'); });
    if (!node) { bar.classList.remove('on'); return; }
    node.classList.add('ijb-hover');
    showNodebar(node, id, bar, wrap);
  });
  canvas.addEventListener('mouseleave', function () {
    hoverId = null;
    bar.classList.remove('on');
    $all('#ijb-canvas .ijb-hover').forEach(function (el) { el.classList.remove('ijb-hover'); });
  });

  // Drag & drop onto canvas
  var dropTarget = null;
  canvas.addEventListener('dragover', function (e) {
    var hasType = false, hasNode = false;
    try {
      var types = Array.prototype.slice.call(e.dataTransfer.types || []);
      hasType = types.indexOf('text/ij-type') !== -1;
      hasNode = types.indexOf('text/ij-node') !== -1;
    } catch (err) { /* ignore */ }
    if (!hasType && !hasNode) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    var node = e.target.closest('[data-ij-id]');
    dropTarget = node ? calcDrop(node, e.clientY) : { id: null, pos: 'inside' };
    paintDropLine(node, dropTarget, line, wrap);
  });
  canvas.addEventListener('dragleave', function (e) {
    if (!canvas.contains(e.relatedTarget)) { line.style.display = 'none'; dropTarget = null; }
  });
  canvas.addEventListener('drop', function (e) {
    line.style.display = 'none';
    var type = e.dataTransfer.getData('text/ij-type');
    var moveId = e.dataTransfer.getData('text/ij-node');
    if (!type && !moveId) return;
    e.preventDefault();
    var t = dropTarget || { id: null, pos: 'inside' };
    dropTarget = null;
    if (type) {
      if (type === 'column') { toast('ستون فقط داخل «ستون‌ها» ساخته می‌شود', true); return; }
      insertNode(makeNode(type), t.id, t.pos);
    } else if (moveId) {
      moveNodeTo(moveId, t.id, t.pos);
    }
  });
}

function showNodebar(nodeEl, id, bar, wrap) {
  var node = findNode(state.tree, id);
  if (!node) { bar.classList.remove('on'); return; }
  var def = CATALOG[node.type] || {};
  var wr = wrap.getBoundingClientRect();
  var r = nodeEl.getBoundingClientRect();
  bar.innerHTML = '<button type="button" class="drag" draggable="true" data-bar-drag="' + esc(id) + '" title="بکشید">⠿</button>'
    + '<span>' + esc(def.icon || '') + ' ' + esc(nodeTitle(node)) + '</span>'
    + '<button type="button" data-bar="up" title="بالا">↑</button>'
    + '<button type="button" data-bar="down" title="پایین">↓</button>'
    + '<button type="button" data-bar="dup" title="تکثیر">⧉</button>'
    + '<button type="button" data-bar="copy" title="کپی">📋</button>'
    + '<button type="button" data-bar="del" title="حذف">🗑</button>';
  bar.classList.add('on');
  bar.style.top = Math.max(0, r.top - wr.top + wrap.scrollTop - 36) + 'px';
  bar.style.right = Math.max(8, wr.right - r.right + 8) + 'px';
  var dragBtn = $('[data-bar-drag]', bar);
  dragBtn.addEventListener('dragstart', function (e) {
    e.dataTransfer.setData('text/ij-node', id);
    e.dataTransfer.effectAllowed = 'move';
  });
  bar.onclick = function (e) {
    var b = e.target.closest('button[data-bar]');
    if (!b) return;
    select(id);
    var act = b.dataset.bar;
    if (act === 'del') {
      if (node.locked) { toast('این جزء قفل است', true); return; }
      deleteNode(id);
    }
    else if (act === 'dup') duplicateNode(id);
    else if (act === 'up') moveNode(id, -1);
    else if (act === 'down') moveNode(id, 1);
    else if (act === 'copy') { state.clipboard = clone(node); toast('کپی شد'); }
  };
}

function calcDrop(nodeEl, clientY) {
  var id = nodeEl.dataset.ijId;
  var type = nodeEl.dataset.ijType;
  var r = nodeEl.getBoundingClientRect();
  var ratio = (clientY - r.top) / Math.max(1, r.height);
  if (isContainer(type) && ratio > 0.25 && ratio < 0.75) return { id: id, pos: 'inside' };
  return { id: id, pos: ratio < 0.5 ? 'before' : 'after' };
}

function paintDropLine(nodeEl, t, line, wrap) {
  if (!nodeEl || !t) { line.style.display = 'none'; return; }
  var wr = wrap.getBoundingClientRect();
  var r = nodeEl.getBoundingClientRect();
  line.style.display = 'block';
  line.style.right = (wr.right - r.right) + 'px';
  line.style.width = r.width + 'px';
  if (t.pos === 'inside') {
    line.style.top = (r.bottom - wr.top + wrap.scrollTop - 3) + 'px';
  } else if (t.pos === 'before') {
    line.style.top = (r.top - wr.top + wrap.scrollTop - 2) + 'px';
  } else {
    line.style.top = (r.bottom - wr.top + wrap.scrollTop - 2) + 'px';
  }
}

function moveNodeTo(moveId, targetId, pos) {
  if (moveId === state.tree.id) return;
  if (moveId === targetId) return;
  var node = findNode(state.tree, moveId);
  if (!node || node.locked) { if (node && node.locked) toast('این جزء قفل است', true); return; }
  // Prevent dropping into own descendant
  if (targetId && findNode(node, targetId)) { toast('انتقال به داخل خودش ممکن نیست', true); return; }
  commit();
  var oldParent = findParent(state.tree, moveId);
  oldParent.children = oldParent.children.filter(function (c) { return c.id !== moveId; });
  if (!targetId) {
    state.tree.children.push(node);
  } else {
    var target = findNode(state.tree, targetId);
    if (!target) { state.tree.children.push(node); }
    else if (pos === 'inside' && isContainer(target.type)) target.children.push(node);
    else if (targetId === state.tree.id) state.tree.children.push(node);
    else {
      var parent = findParent(state.tree, targetId);
      var sibs = parent ? parent.children : state.tree.children;
      var idx = sibs.findIndex(function (c) { return c.id === targetId; });
      sibs.splice(pos === 'after' ? idx + 1 : idx, 0, node);
    }
  }
  afterStructuralChange();
  select(moveId);
}

/* ── Left panel bindings ── */
function bindLeft() {
  $all('#ijb-left-tabs button').forEach(function (b) {
    b.addEventListener('click', function () {
      state.leftTab = b.dataset.tab;
      $all('#ijb-left-tabs button').forEach(function (x) { x.classList.toggle('on', x === b); });
      $all('#ijb-main #ijb-left .ijb-tab').forEach(function (t) { t.classList.remove('on'); });
      $('#ijb-tab-' + state.leftTab).classList.add('on');
    });
  });
  if (!BOOT.canPublish) {
    var tb = $('#ijb-tokens-btn');
    if (tb) tb.style.display = 'none';
  }
  $('#ijb-search').addEventListener('input', debounce(function (e) {
    renderPalette(e.target.value.trim());
  }, 200));

  $('#ijb-palette').addEventListener('dragstart', function (e) {
    var item = e.target.closest('[data-type]');
    if (!item) return;
    e.dataTransfer.setData('text/ij-type', item.dataset.type);
    e.dataTransfer.effectAllowed = 'copy';
  });
  $('#ijb-palette').addEventListener('click', function (e) {
    var item = e.target.closest('[data-type]');
    if (!item) return;
    var type = item.dataset.type;
    if (type === 'column') { toast('ستون فقط داخل «ستون‌ها» ساخته می‌شود', true); return; }
    var sel = currentNode();
    var targetId = sel ? sel.id : null;
    var pos = sel && isContainer(sel.type) ? 'inside' : 'after';
    if (!sel) { targetId = null; }
    insertNode(makeNode(type), targetId, pos);
  });

  // Navigator events (delegate)
  var tree = $('#ijb-tree');
  tree.addEventListener('click', function (e) {
    var row = e.target.closest('.ijb-nav-row');
    if (!row) return;
    var id = row.dataset.id;
    var act = e.target.closest('button');
    if (act) {
      if (act.dataset.act === 'del') {
        var n = findNode(state.tree, id);
        if (n && n.locked) { toast('این جزء قفل است', true); return; }
        deleteNode(id);
      }
      else if (act.dataset.act === 'dup') duplicateNode(id);
      return;
    }
    select(id);
  });
  tree.addEventListener('dragstart', function (e) {
    var row = e.target.closest('.ijb-nav-row');
    if (!row) return;
    e.dataTransfer.setData('text/ij-node', row.dataset.id);
    e.dataTransfer.effectAllowed = 'move';
  });
  tree.addEventListener('dragover', function (e) {
    try {
      if (Array.prototype.slice.call(e.dataTransfer.types || []).indexOf('text/ij-node') === -1) return;
    } catch (err) { return; }
    e.preventDefault();
  });
  tree.addEventListener('drop', function (e) {
    var moveId = e.dataTransfer.getData('text/ij-node');
    var row = e.target.closest('.ijb-nav-row');
    if (!moveId || !row) return;
    e.preventDefault();
    var targetId = row.dataset.id;
    var target = findNode(state.tree, targetId);
    moveNodeTo(moveId, targetId, target && isContainer(target.type) ? 'inside' : 'after');
  });
}

/* ── Right tabs / topbar ── */
function bindChrome() {
  $all('#ijb-right-tabs button').forEach(function (b) {
    b.addEventListener('click', function () {
      state.propsTab = b.dataset.tab;
      renderProps();
    });
  });
  $all('.ijb-devices button').forEach(function (b) {
    b.addEventListener('click', function () {
      state.device = b.dataset.device;
      $all('.ijb-devices button').forEach(function (x) { x.classList.toggle('on', x === b); });
      $('#ijb-canvas-device').dataset.device = state.device === 'desktop' ? '' : state.device;
      if (state.device === 'desktop') $('#ijb-canvas-device').removeAttribute('data-device');
      renderProps(); // style tab follows device
      schedulePreview();
    });
  });
  $('#ijb-undo').addEventListener('click', undo);
  $('#ijb-redo').addEventListener('click', redo);
  $('#ijb-save').addEventListener('click', function () { save(false); });
  $('#ijb-history').addEventListener('click', openHistory);
  $('#ijb-preview').addEventListener('click', function () {
    var slug = BOOT.doc && BOOT.doc.settings && BOOT.doc.settings.slug;
    if ((BOOT.entity === 'page' || BOOT.entity === 'post') && slug) window.open('/' + slug, '_blank');
    else toast('برای قالب، پیش‌نمایش از صفحه‌ای که قالب را دارد ببینید');
  });
  $('#ijb-tokens-save').addEventListener('click', saveTokens);

  document.addEventListener('keydown', function (e) {
    var typing = /^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '');
    if (e.key === 'Escape') { closeModal(); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) { if (!typing) { e.preventDefault(); undo(); } return; }
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) { if (!typing) { e.preventDefault(); redo(); } return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(false); return; }
    if (typing || !state.selectedId) return;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') {
      var n = currentNode();
      if (n) { state.clipboard = clone(n); toast('کپی شد'); }
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'x') {
      var n2 = currentNode();
      if (n2 && n2.id !== state.tree.id && !n2.locked) {
        state.clipboard = clone(n2);
        deleteNode(n2.id);
        toast('بریده شد');
      }
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v') {
      if (state.clipboard) {
        var sel = currentNode();
        var pos = sel && isContainer(sel.type) ? 'inside' : 'after';
        insertNode(reId(clone(state.clipboard)), sel ? sel.id : null, pos);
      }
    } else if ((e.key === 'Delete' || e.key === 'Backspace') && state.selectedId) {
      var n3 = currentNode();
      if (n3 && !n3.locked) deleteNode(state.selectedId);
    }
  });

  window.addEventListener('beforeunload', function (e) {
    if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  // Autosave every 30s
  setInterval(function () {
    if (state.dirty && !state.saving) save(true);
  }, 30000);
}

/* ── Save ── */
function save(autosave) {
  if (state.saving) return;
  state.saving = true;
  if (!autosave) $('#ijb-status').textContent = 'در حال ذخیره…';
  api('/admin/api/builder/save', {
    method: 'POST',
    body: { entity: BOOT.entity, id: BOOT.id, tree: state.tree, autosave: autosave ? true : false },
  }).then(function (res) {
    state.saving = false;
    if (!res.ok) {
      toast((res.errors && res.errors[0]) || 'خطا در ذخیره‌سازی', true);
      return;
    }
    markClean(autosave ? '✓ پیش‌نویس خودکار ' + new Date().toLocaleTimeString('fa-IR') : '✓ ذخیره شد');
  }).catch(function () {
    state.saving = false;
    toast('خطای ارتباط با سرور', true);
  });
}

/* ── Tokens tab ── */
var TOKEN_LABELS = {
  primary: 'رنگ اصلی', secondary: 'رنگ ثانویه', accent: 'رنگ تأکیدی',
  background: 'پس‌زمینه', surface: 'سطح', text: 'متن', muted: 'متن کم‌رنگ',
  border: 'کادر', radius: 'گردی گوشه', shadow: 'سایه', container: 'عرض ظرف',
  font_base: 'قلم متن', font_head: 'قلم تیتر',
};

function renderTokens() {
  var box = $('#ijb-tokens-form');
  var html = '';
  Object.keys(state.tokens).forEach(function (k) {
    var v = state.tokens[k];
    var label = TOKEN_LABELS[k] || k;
    if (typeof v === 'string' && /^#[0-9a-fA-F]{3,8}$/.test(v)) {
      html += '<div class="ijb-field"><label>' + esc(label) + '</label><input type="color" data-tok="' + esc(k) + '" value="' + esc(v) + '"></div>';
    } else {
      html += '<div class="ijb-field"><label>' + esc(label) + '</label><input type="text" data-tok="' + esc(k) + '" value="' + esc(v) + '" dir="ltr"></div>';
    }
  });
  box.innerHTML = html || '<p class="ijb-empty">توکنی تعریف نشده است.</p>';
  $all('[data-tok]', box).forEach(function (el) {
    el.addEventListener('input', function () { state.tokens[el.dataset.tok] = el.value; });
  });
}

function saveTokens() {
  api('/admin/api/builder/tokens', { method: 'POST', body: { tokens: state.tokens } }).then(function (res) {
    if (!res.ok) { toast((res.errors && res.errors[0]) || 'خطا', true); return; }
    $('#ij-tokens').textContent = res.css || '';
    toast('استایل سراسری ذخیره شد');
  }).catch(function () { toast('خطای ارتباط', true); });
}

/* ── Modal: media / bindings / history ── */
function openModal(html) {
  $('#ijb-modal-body').innerHTML = html;
  $('#ijb-modal').hidden = false;
}
function closeModal() { $('#ijb-modal').hidden = true; }

function openMedia(cb) {
  function render(items) {
    var html = '<h3>انتخاب تصویر</h3><input type="search" id="ijb-media-q" placeholder="جستجو…" style="width:100%;background:#1a1e31;border:1px solid #3a4066;color:#eef0ff;border-radius:7px;padding:7px 10px;margin-bottom:10px">';
    html += '<div class="ijb-media-grid" id="ijb-media-grid"></div>';
    html += '<p class="ijb-help">۶۰ تصویر اخیر. برای بارگذاری جدید به <a href="/admin/media" target="_blank">کتابخانه رسانه</a> بروید.</p>';
    openModal(html);
    function paint(filter) {
      var grid = $('#ijb-media-grid');
      var list = items.filter(function (m) { return !filter || m.name.indexOf(filter) !== -1; });
      grid.innerHTML = list.map(function (m) {
        return '<button type="button" data-url="' + esc(m.url) + '"><img src="' + esc(m.thumb) + '" alt="" loading="lazy"><span>' + esc(m.name) + '</span></button>';
      }).join('') || '<p class="ijb-empty">تصویری یافت نشد.</p>';
      $all('[data-url]', grid).forEach(function (b) {
        b.addEventListener('click', function () { closeModal(); cb(b.dataset.url); });
      });
    }
    paint('');
    $('#ijb-media-q').addEventListener('input', function (e) { paint(e.target.value.trim()); });
  }
  if (state.mediaCache) { render(state.mediaCache); return; }
  api('/admin/api/builder/media').then(function (res) {
    state.mediaCache = res.items || [];
    render(state.mediaCache);
  }).catch(function () { toast('خطا در بارگذاری رسانه', true); });
}

function openBindings(cb) {
  var html = '<h3>⚡ انتخاب بایندینگ داینامیک</h3><div class="ijb-bind-list">';
  Object.keys(BOOT.bindings || {}).forEach(function (k) {
    html += '<button type="button" data-bind="{{' + esc(k) + '}}"><b>{{' + esc(k) + '}}</b> — ' + esc(BOOT.bindings[k]) + '</button>';
  });
  html += '</div><div class="ijb-field" style="margin-top:10px"><label>یا متا/بایندینگ دستی (مثل {{meta.phone}})</label>'
    + '<div class="ijb-img-row"><input type="text" id="ijb-bind-custom" dir="ltr" placeholder="{{meta.key}}"><button type="button" id="ijb-bind-custom-ok">ثبت</button></div></div>';
  openModal(html);
  $all('[data-bind]').forEach(function (b) {
    b.addEventListener('click', function () { closeModal(); cb(b.dataset.bind); });
  });
  $('#ijb-bind-custom-ok').addEventListener('click', function () {
    var v = $('#ijb-bind-custom').value.trim();
    if (!/^\{\{\s*(site|post|author|user|meta|date)\.[a-z0-9_.]{1,80}\s*\}\}$/i.test(v)) {
      toast('قالب بایندینگ معتبر نیست', true);
      return;
    }
    closeModal();
    cb(v);
  });
}

function openHistory() {
  api('/admin/api/builder/revisions?entity=' + encodeURIComponent(BOOT.entity) + '&id=' + BOOT.id).then(function (res) {
    if (!res.ok) { toast('خطا در بارگذاری تاریخچه', true); return; }
    var rows = res.revisions || [];
    var html = '<h3>🕘 تاریخچه نسخه‌ها</h3>';
    if (!rows.length) html += '<p class="ijb-empty">هنوز نسخه‌ای ثبت نشده است.</p>';
    rows.forEach(function (r) {
      html += '<div class="ijb-rev"><span>#' + r.id + ' — ' + esc(r.title || '') + '</span>'
        + '<small>' + esc(r.author_name || '') + ' · ' + esc(r.created_at || '') + (parseInt(r.is_autosave, 10) ? ' · خودکار' : '') + '</small>'
        + '<button type="button" data-rev-prev="' + r.id + '" style="background:#343a5c">پیش‌نمایش</button>'
        + '<button type="button" data-rev="' + r.id + '">بازیابی</button></div>';
    });
    openModal(html);
    $all('[data-rev-prev]').forEach(function (b) {
      b.addEventListener('click', function () { previewRevision(parseInt(b.dataset.revPrev, 10)); });
    });
    $all('[data-rev]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!confirm('نسخه #' + b.dataset.rev + ' جایگزین محتوای فعلی شود؟ (نسخه فعلی به‌عنوان نسخه جدید ذخیره می‌شود)')) return;
        restoreRevision(parseInt(b.dataset.rev, 10));
      });
    });
  }).catch(function () { toast('خطای ارتباط', true); });
}

function previewRevision(revId) {
  api('/admin/api/builder/revision?entity=' + encodeURIComponent(BOOT.entity) + '&id=' + BOOT.id + '&revision=' + revId).then(function (res) {
    if (!res.ok || !res.tree) { toast('خطا در بارگذاری نسخه', true); return; }
    return api('/admin/api/builder/preview', {
      method: 'POST',
      body: { tree: res.tree, entity: BOOT.entity, id: BOOT.id, device: 'desktop' },
    }).then(function (p) {
      if (!p.ok) { toast('خطا در پیش‌نمایش', true); return; }
      openModal('<h3>پیش‌نمایش نسخه #' + revId + '</h3><div style="background:#fff;border-radius:8px;padding:10px;color:#1c2240"><style>' + (p.css || '') + '</style>' + (p.html || '') + '</div>'
        + '<p style="margin-top:10px"><button type="button" class="ijb-add" data-rev-restore="' + revId + '">بازیابی این نسخه</button></p>');
      $('[data-rev-restore]').addEventListener('click', function () {
        if (!confirm('بازیابی شود؟')) return;
        restoreRevision(revId);
      });
    });
  }).catch(function () { toast('خطای ارتباط', true); });
}

function restoreRevision(revId) {
  api('/admin/api/builder/restore', {
    method: 'POST',
    body: { entity: BOOT.entity, id: BOOT.id, revision_id: revId },
  }).then(function (res) {
    if (!res.ok || !res.doc) { toast((res.errors && res.errors[0]) || 'بازیابی ناموفق بود', true); return; }
    commit();
    state.tree = ensureNode(res.doc.tree);
    state.selectedId = null;
    closeModal();
    afterStructuralChange();
    markClean('✓ بازیابی شد');
    toast('نسخه بازیابی شد');
  }).catch(function () { toast('خطای ارتباط', true); });
}

/* ── Boot ── */
function boot() {
  if (!state.tree) {
    $('#ijb-canvas').innerHTML = '<p class="ijb-empty">خطا در بارگذاری سند.</p>';
    return;
  }
  ensureNode(state.tree);
  var slug = BOOT.doc && BOOT.doc.settings && BOOT.doc.settings.slug;
  $('#ijb-doc-title').textContent = 'ویرایشگر: ' + (slug || BOOT.entity + ' #' + BOOT.id);
  var exit = $('#ijb-exit');
  exit.href = '/admin';

  $('#ijb-props').addEventListener('click', propsDelegatedClick);
  renderPalette('');
  renderNavigator();
  renderProps();
  renderTokens();
  bindLeft();
  bindCanvas();
  bindChrome();
  updateUndoRedo();
  markClean('آماده');
  $('#ijb-app').dataset.loading = '0';
  doPreview();

  $('#ijb-modal-close').addEventListener('click', closeModal);
  $('#ijb-modal').addEventListener('click', function (e) {
    if (e.target.id === 'ijb-modal') closeModal();
  });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();

})();
