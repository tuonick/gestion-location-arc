/* ================================================================
   Location d'Arc – Frontend Dashboard JS
   Vanilla JS · no jQuery · no external deps
   ================================================================ */
/* global LOCARC_FE */

(function () {
  'use strict';

  const root     = document.getElementById('locarc-fe');
  if (!root) return;

  const ham      = document.getElementById('locarc-fe-ham');
  const backdrop = document.getElementById('locarc-fe-backdrop');
  const nav      = document.getElementById('locarc-fe-nav');
  const curLabel = document.getElementById('locarc-fe-cur');
  const mainEl   = document.getElementById('locarc-fe-main');
  const toasts   = document.getElementById('locarc-fe-toasts');

  // Drawer elements
  const drawer       = document.getElementById('locarc-fe-drawer');
  const drawerTitle  = document.getElementById('locarc-fe-drawer-title');
  const drawerBody   = document.getElementById('locarc-fe-drawer-body');
  const drawerSubmit = document.getElementById('locarc-fe-drawer-submit');
  const drawerCancel = document.getElementById('locarc-fe-drawer-cancel');
  const drawerClose  = document.getElementById('locarc-fe-drawer-close');

  // Current drawer state
  let drawerMode = null; // 'edit-contract'|'new-contract'|'renew-contract'|'edit-assignment'|'edit-item'
  let drawerData = {};   // data passed when opening

  /* ── Utility ──────────────────────────────────────────────────── */

  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fmtDate(mysqlDate) {
    if (!mysqlDate) return '';
    const [y, m, d] = mysqlDate.split('-');
    return d + '/' + m + '/' + y;
  }

  function toInputDate(mysqlDate) {
    // Returns YYYY-MM-DD for <input type="date">
    return mysqlDate ? mysqlDate.substring(0, 10) : '';
  }

  /* ── AJAX helper ─────────────────────────────────────────────── */

  function ajax(action, data, onOk, onErr) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', LOCARC_FE.nonce);
    Object.entries(data || {}).forEach(([k, v]) => {
      if (v !== null && v !== undefined) fd.append(k, v);
    });
    fetch(LOCARC_FE.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
        if (json && json.success) { onOk && onOk(json.data); }
        else { const m = (json && json.data) ? json.data : 'Erreur serveur'; onErr ? onErr(m) : toast(m, 'error'); }
      })
      .catch(err => { const m = 'Erreur réseau : ' + (err.message || err); onErr ? onErr(m) : toast(m, 'error'); });
  }

  function ajaxGet(action, params, onOk, onErr) {
    const url = new URL(LOCARC_FE.ajaxUrl);
    url.searchParams.set('action', action);
    url.searchParams.set('nonce', LOCARC_FE.nonce);
    Object.entries(params || {}).forEach(([k, v]) => url.searchParams.set(k, v));
    fetch(url.toString(), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
        if (json && json.success) { onOk && onOk(json.data); }
        else { const m = (json && json.data) ? json.data : 'Erreur serveur'; onErr ? onErr(m) : toast(m, 'error'); }
      })
      .catch(err => { const m = 'Erreur réseau : ' + (err.message || err); onErr ? onErr(m) : toast(m, 'error'); });
  }

  /* ── Toast ────────────────────────────────────────────────────── */

  function toast(msg, type) {
    type = type || 'ok';
    const el = document.createElement('div');
    el.className = 'locarc-fe__toast locarc-fe__toast--' + type;
    el.textContent = msg;
    toasts.appendChild(el);
    setTimeout(() => {
      el.style.transition = 'opacity 300ms';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 320);
    }, 3500);
  }

  /* ── Nav (hamburger) ─────────────────────────────────────────── */

  function openNav()  { root.classList.add('nav-open');    ham.setAttribute('aria-expanded', 'true'); }
  function closeNav() { root.classList.remove('nav-open'); ham.setAttribute('aria-expanded', 'false'); }

  ham.addEventListener('click', () => root.classList.contains('nav-open') ? closeNav() : openNav());

  nav.querySelectorAll('.locarc-fe__nav-item').forEach(btn => {
    btn.addEventListener('click', () => {
      nav.querySelectorAll('.locarc-fe__nav-item').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      if (curLabel) curLabel.textContent = btn.dataset.label;
      mainEl.querySelectorAll('.locarc-fe__section').forEach(s => s.classList.remove('is-active'));
      const target = document.getElementById('locarc-fe-' + btn.dataset.section);
      if (target) target.classList.add('is-active');
      closeNav();
    });
  });

  /* ── Drawer ───────────────────────────────────────────────────── */

  function openDrawer() { root.classList.add('drawer-open'); drawerSubmit.disabled = false; }
  function closeDrawer() {
    root.classList.remove('drawer-open');
    drawerMode = null; drawerData = {};
    // Remove any autocomplete dropdowns
    drawerBody.querySelectorAll('.locarc-fe__ac-dropdown').forEach(d => d.remove());
  }

  [drawerClose, drawerCancel].forEach(el => el && el.addEventListener('click', closeDrawer));
  backdrop.addEventListener('click', () => { closeNav(); closeDrawer(); });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeNav(); closeDrawer(); }
  });

  drawerSubmit.addEventListener('click', handleDrawerSubmit);

  /* ── Drawer message helpers ──────────────────────────────────── */

  function showDrawerError(msg) {
    clearDrawerMessages();
    const el = document.createElement('div');
    el.className = 'locarc-fe__drawer-error';
    el.textContent = msg;
    drawerBody.prepend(el);
  }

  function showDrawerSuccess(msg) {
    clearDrawerMessages();
    const el = document.createElement('div');
    el.className = 'locarc-fe__drawer-success';
    el.textContent = msg;
    drawerBody.prepend(el);
  }

  function clearDrawerMessages() {
    drawerBody.querySelectorAll('.locarc-fe__drawer-error, .locarc-fe__drawer-success').forEach(el => el.remove());
  }

  /* ── Field builder ───────────────────────────────────────────── */

  function field(id, label, input, hint, required) {
    const req = required ? '<span class="locarc-fe__required" aria-hidden="true">*</span>' : '';
    return `<div class="locarc-fe__field">
      <label for="${esc(id)}">${esc(label)}${req}</label>
      ${input}
      ${hint ? `<div class="locarc-fe__field-hint">${esc(hint)}</div>` : ''}
    </div>`;
  }

  function inputEl(id, type, value, extra) {
    return `<input type="${esc(type)}" id="${esc(id)}" name="${esc(id)}" value="${esc(value ?? '')}" class="locarc-fe__input" ${extra || ''}>`;
  }

  function selectEl(id, options, selected, extra) {
    const opts = options.map(([v, l]) =>
      `<option value="${esc(v)}" ${v == selected ? 'selected' : ''}>${esc(l)}</option>`
    ).join('');
    return `<select id="${esc(id)}" name="${esc(id)}" class="locarc-fe__select" ${extra || ''}>${opts}</select>`;
  }

  function acInput(id, value, kind) {
    return `<div class="locarc-fe__ac-wrap">
      <input type="text" id="${esc(id)}" name="${esc(id)}" value="${esc(value ?? '')}"
             class="locarc-fe__input locarc-fe__ac-input"
             data-kind="${esc(kind)}"
             autocomplete="off" spellcheck="false">
      <div class="locarc-fe__ac-dropdown" hidden></div>
    </div>`;
  }

  /* ── Item preview helper ─────────────────────────────────────── */

  // Build a readonly preview card HTML for a handle or branches item
  function buildItemPreviewHtml(data, kind) {
    if (!data) return '';
    const val = (v, fallback) => (v !== null && v !== undefined && String(v).trim() !== '') ? esc(v) : (fallback || '<em style="color:var(--fe-muted)">—</em>');
    if (kind === 'handles') {
      return `<div class="locarc-fe__item-preview-grid">
        <div class="locarc-fe__item-preview-cell"><span class="locarc-fe__item-preview-lbl">Marque</span><span class="locarc-fe__item-preview-val">${val(data.brand)}</span></div>
        <div class="locarc-fe__item-preview-cell"><span class="locarc-fe__item-preview-lbl">Modèle</span><span class="locarc-fe__item-preview-val">${val(data.model)}</span></div>
        <div class="locarc-fe__item-preview-cell"><span class="locarc-fe__item-preview-lbl">Taille</span><span class="locarc-fe__item-preview-val">${val(data.size)}</span></div>
        <div class="locarc-fe__item-preview-cell"><span class="locarc-fe__item-preview-lbl">Latéralité</span><span class="locarc-fe__item-preview-val">${val(data.handedness)}</span></div>
      </div>`;
    } else {
      return `<div class="locarc-fe__item-preview-grid">
        <div class="locarc-fe__item-preview-cell"><span class="locarc-fe__item-preview-lbl">Marque</span><span class="locarc-fe__item-preview-val">${val(data.brand)}</span></div>
        <div class="locarc-fe__item-preview-cell"><span class="locarc-fe__item-preview-lbl">Modèle</span><span class="locarc-fe__item-preview-val">${val(data.model)}</span></div>
        <div class="locarc-fe__item-preview-cell"><span class="locarc-fe__item-preview-lbl">Taille</span><span class="locarc-fe__item-preview-val">${val(data.size)}</span></div>
        <div class="locarc-fe__item-preview-cell"><span class="locarc-fe__item-preview-lbl">Puissance</span><span class="locarc-fe__item-preview-val">${val(data.power) !== '<em style="color:var(--fe-muted)">—</em>' ? val(data.power) + ' #' : val(data.power)}</span></div>
      </div>`;
    }
  }

  // Fetch item details by identifier and populate preview block
  function loadItemPreview(previewEl, kind, identifier) {
    if (!identifier || !previewEl) return;
    previewEl.classList.remove('is-visible');
    previewEl.innerHTML = '<span style="font-size:12px;color:var(--fe-muted)">Chargement…</span>';
    previewEl.classList.add('is-visible');
    ajaxGet('locarc_get_by_identifier', { kind, identifier }, data => {
      previewEl.innerHTML = buildItemPreviewHtml(data, kind);
      previewEl.classList.add('is-visible');
    }, () => {
      previewEl.classList.remove('is-visible');
      previewEl.innerHTML = '';
    });
  }

  /* ── View modal (read-only item details) ────────────────────── */

  const viewModal      = document.getElementById('locarc-fe-view-modal');
  const viewModalBody  = document.getElementById('locarc-fe-view-body');
  const viewModalTitle = document.getElementById('locarc-fe-view-title');
  const viewModalClose = document.getElementById('locarc-fe-view-close');

  function closeViewModal() { if (viewModal) viewModal.classList.remove('is-open'); }

  if (viewModalClose) viewModalClose.addEventListener('click', closeViewModal);
  if (viewModal) {
    viewModal.addEventListener('click', e => { if (e.target === viewModal) closeViewModal(); });
  }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeViewModal(); });

  function buildViewModalHtml(data, kind) {
    if (!data) return '<p style="color:var(--fe-muted)">Aucune donnée.</p>';
    const val = v => (v !== null && v !== undefined && String(v).trim() !== '') ? esc(String(v)) : '<span style="color:var(--fe-muted)">—</span>';
    const row = (label, value) => `<div class="locarc-fe__view-row"><span class="locarc-fe__view-label">${label}</span><span class="locarc-fe__view-value">${value}</span></div>`;
    if (kind === 'handles') {
      return `<div class="locarc-fe__view-grid">
        ${row('Identifiant', val(data.identifier))}
        ${row('Marque', val(data.brand))}
        ${row('Modèle', val(data.model))}
        ${row('Taille', val(data.size))}
        ${row('Latéralité', val(data.handedness))}
        ${row('Couleur', val(data.color))}
        ${row('Année d\'achat', val(data.purchase_year))}
        ${row('Disponibilité', val(data.availability_label || data.is_available))}
        ${data.comment ? row('Commentaire', val(data.comment)) : ''}
      </div>`;
    } else {
      const pow = (data.power !== null && data.power !== undefined && String(data.power).trim() !== '') ? esc(String(data.power)) + ' lbs' : '<span style="color:var(--fe-muted)">—</span>';
      return `<div class="locarc-fe__view-grid">
        ${row('Identifiant', val(data.identifier))}
        ${row('Marque', val(data.brand))}
        ${row('Modèle', val(data.model))}
        ${row('Taille', val(data.size))}
        ${row('Puissance', pow)}
        ${row('Année d\'achat', val(data.purchase_year))}
        ${row('Disponibilité', val(data.availability_label || data.is_available))}
        ${data.comment ? row('Commentaire', val(data.comment)) : ''}
      </div>`;
    }
  }

  function openViewItem(identifier, kind) {
    if (!viewModal || !identifier || !kind) return;
    const label = kind === 'handles' ? 'Poignée' : 'Branches';
    if (viewModalTitle) viewModalTitle.textContent = label + ' · ' + identifier;
    if (viewModalBody) viewModalBody.innerHTML = '<p style="color:var(--fe-muted);padding:8px 0">Chargement…</p>';
    viewModal.classList.add('is-open');
    ajaxGet('locarc_get_by_identifier', { kind, identifier }, data => {
      if (viewModalBody) viewModalBody.innerHTML = buildViewModalHtml(data, kind);
    }, err => {
      if (viewModalBody) viewModalBody.innerHTML = '<p style="color:#c00">' + esc(err) + '</p>';
    });
  }

  /* ── Autocomplete wiring ─────────────────────────────────────── */

  function wireAutocomplete(wrap, onSelect) {
    const input    = wrap.querySelector('.locarc-fe__ac-input');
    const dropdown = wrap.querySelector('.locarc-fe__ac-dropdown');
    if (!input || !dropdown) return;
    const kind = input.dataset.kind;
    let timer;

    input.addEventListener('input', () => {
      clearTimeout(timer);
      const term = input.value.trim();
      if (term.length < 2) { dropdown.hidden = true; dropdown.innerHTML = ''; return; }
      timer = setTimeout(() => {
        ajaxGet('locarc_autocomplete', { kind, term }, results => {
          if (!results || !results.length) {
            dropdown.innerHTML = '<div class="locarc-fe__ac-empty">Aucun résultat</div>';
            dropdown.hidden = false;
            return;
          }
          dropdown.innerHTML = results.map(item => {
            const main = item.label || item.value || '';
            const sub  = item.sublabel || '';
            return `<button class="locarc-fe__ac-item" data-value="${esc(item.value)}" data-item='${JSON.stringify(item).replace(/'/g, '&#39;')}' type="button">
              <span class="locarc-fe__ac-main">${esc(main)}</span>
              ${sub ? `<span class="locarc-fe__ac-sub">${esc(sub)}</span>` : ''}
            </button>`;
          }).join('');
          dropdown.hidden = false;
          dropdown.querySelectorAll('.locarc-fe__ac-item').forEach(btn => {
            btn.addEventListener('click', () => {
              input.value = btn.dataset.value;
              let parsed = {};
              try { parsed = JSON.parse(btn.dataset.item); } catch(e) {}
              dropdown.hidden = true;
              // If this is a handle/branches field, load preview
              if ((kind === 'handles' || kind === 'branches') && btn.dataset.value) {
                const preview = wrap.parentElement && wrap.parentElement.querySelector('.locarc-fe__item-preview');
                if (preview) loadItemPreview(preview, kind, btn.dataset.value);
              }
              onSelect && onSelect(btn.dataset.value, parsed);
            });
          });
        });
      }, 280);
    });

    // When user manually types an identifier and leaves the field, load preview
    if (kind === 'handles' || kind === 'branches') {
      input.addEventListener('blur', () => {
        const val = input.value.trim();
        if (!val) return;
        const preview = wrap.parentElement && wrap.parentElement.querySelector('.locarc-fe__item-preview');
        if (preview) loadItemPreview(preview, kind, val);
      });
    }

    // Close on outside click
    document.addEventListener('click', e => {
      if (!wrap.contains(e.target)) { dropdown.hidden = true; }
    }, { passive: true });
  }

  /* ─────────────────────────────────────────────────────────────────
     DRAWER FORMS
  ───────────────────────────────────────────────────────────────── */

  /* ── Contract form (edit / new / renew) ─────────────────────── */

  function buildContractForm(data) {
    const ct = (LOCARC_FE.contractTypes || []).map(t => [t.key, t.label + (t.price > 0 ? ` (${t.price} €)` : '')]);

    const paidCheck = `<label class="locarc-fe__checkbox-label">
      <input type="checkbox" id="fe_is_paid" name="fe_is_paid" ${data.is_paid == 1 ? 'checked' : ''}>
      <span>Contrat payé</span>
    </label>`;

    return `
      ${field('fe_licence', 'N° Licence', acInput('fe_licence', data.licence, 'members'), 'Tapez la licence ou le nom du licencié', true)}
      ${field('fe_contract_type', 'Type de contrat', selectEl('fe_contract_type', ct, data.contract_type), '', true)}
      <div class="locarc-fe__field-row">
        ${field('fe_start_date', 'Date de début', inputEl('fe_start_date', 'date', toInputDate(data.start_date)), '', true)}
        ${field('fe_end_date', 'Date de fin', inputEl('fe_end_date', 'date', toInputDate(data.end_date)), '', true)}
      </div>
      <div class="locarc-fe__field" id="fe_custom_price_wrap" style="${data.contract_type === 'personnalise' ? '' : 'display:none'}">
        <label for="fe_custom_price">Montant personnalisé (€) <span class="locarc-fe__required">*</span></label>
        <input type="number" id="fe_custom_price" name="fe_custom_price" min="0" step="0.01"
               value="${esc(data.custom_price ?? '')}" class="locarc-fe__input">
      </div>
      <hr class="locarc-fe__separator">
      <div class="locarc-fe__field">
        <label for="fe_handle_identifier">Poignée (identifiant)</label>
        ${acInput('fe_handle_identifier', data.handle_identifier, 'handles')}
        <div class="locarc-fe__field-hint">Laisser vide si pas de poignée</div>
        <div class="locarc-fe__item-preview" data-for="handle"></div>
      </div>
      <div class="locarc-fe__field">
        <label for="fe_branches_identifier">Branches (identifiant)</label>
        ${acInput('fe_branches_identifier', data.branches_identifier, 'branches')}
        <div class="locarc-fe__field-hint">Laisser vide si pas de branches</div>
        <div class="locarc-fe__item-preview" data-for="branches"></div>
      </div>
      <hr class="locarc-fe__separator">
      <div class="locarc-fe__field">
        ${paidCheck}
      </div>`;
  }

  function wireContractForm() {
    // Show/hide custom price field
    const typeSelect = drawerBody.querySelector('#fe_contract_type');
    const priceWrap  = drawerBody.querySelector('#fe_custom_price_wrap');
    if (typeSelect && priceWrap) {
      typeSelect.addEventListener('change', () => {
        priceWrap.style.display = typeSelect.value === 'personnalise' ? '' : 'none';
      });
    }
    // Wire autocomplete
    drawerBody.querySelectorAll('.locarc-fe__ac-wrap').forEach(wrap => {
      const input = wrap.querySelector('.locarc-fe__ac-input');
      wireAutocomplete(wrap, (value, item) => {
        // If members autocomplete: fill licence field
        if (input && input.id === 'fe_licence') {
          input.value = item.licence || value;
        }
      });
    });
  }

  function collectContractFormData() {
    const v = id => (drawerBody.querySelector('#' + id) || {}).value || '';
    const chk = id => (drawerBody.querySelector('#' + id) || {}).checked ? 1 : 0;
    return {
      licence:              v('fe_licence'),
      contract_type:        v('fe_contract_type'),
      start_date:           v('fe_start_date'),
      end_date:             v('fe_end_date'),
      handle_identifier:    v('fe_handle_identifier'),
      branches_identifier:  v('fe_branches_identifier'),
      is_paid:              chk('fe_is_paid'),
      custom_price:         v('fe_custom_price'),
    };
  }

  /* ── Edit assignment form ─────────────────────────────────────── */

  function buildAssignmentForm(data) {
    return `
      <p style="color:var(--fe-muted);font-size:13px;margin:0 0 16px">
        Modifiez l'affectation du matériel pour ce contrat.<br>
        Laissez vide pour retirer un élément.
      </p>
      <div class="locarc-fe__field">
        <label for="fe_handle_identifier">Poignée (identifiant)</label>
        ${acInput('fe_handle_identifier', data.handle_identifier, 'handles')}
        <div class="locarc-fe__field-hint">Identifiant exact de la poignée</div>
        <div class="locarc-fe__item-preview" data-for="handle"></div>
      </div>
      <div class="locarc-fe__field">
        <label for="fe_branches_identifier">Branches (identifiant)</label>
        ${acInput('fe_branches_identifier', data.branches_identifier, 'branches')}
        <div class="locarc-fe__field-hint">Identifiant exact des branches</div>
        <div class="locarc-fe__item-preview" data-for="branches"></div>
      </div>`;
  }

  /* ── Edit item form (branches or handles) ───────────────────── */

  function buildItemForm(data, kind) {
    const availOptions = [
      ['1', 'Disponible'],
      ['0', 'Non disponible (loué)'],
      ['2', 'FLAG'],
      ['3', 'Obsolète'],
      ['4', 'En Réparation'],
      ['5', 'H-S'],
    ];

    if (kind === 'branches') {
      return `
        <div class="locarc-fe__field-row">
          ${field('fe_identifier', 'Identifiant', inputEl('fe_identifier', 'text', data.identifier, data.id ? '' : ''), '', true)}
          ${field('fe_size', 'Taille', inputEl('fe_size', 'number', data.size, 'min="0"'))}
        </div>
        <div class="locarc-fe__field-row">
          ${field('fe_power', 'Puissance (lbs)', inputEl('fe_power', 'number', data.power, 'min="0"'))}
          ${field('fe_purchase_year', 'Année d\'achat', inputEl('fe_purchase_year', 'number', data.purchase_year, 'min="2000" max="2099"'))}
        </div>
        ${field('fe_brand', 'Marque', inputEl('fe_brand', 'text', data.brand))}
        ${field('fe_model', 'Modèle', inputEl('fe_model', 'text', data.model))}
        ${field('fe_is_available', 'Disponibilité', selectEl('fe_is_available', availOptions, data.is_available))}
        ${field('fe_comment', 'Commentaire', inputEl('fe_comment', 'text', data.comment))}`;
    } else {
      // handles
      const handOptions = [['Droite', 'Droite'], ['Gauche', 'Gauche']];
      return `
        <div class="locarc-fe__field-row">
          ${field('fe_identifier', 'Identifiant', inputEl('fe_identifier', 'text', data.identifier), '', true)}
          ${field('fe_size', 'Taille', inputEl('fe_size', 'number', data.size, 'min="0"'))}
        </div>
        <div class="locarc-fe__field-row">
          ${field('fe_handedness', 'Latéralité', selectEl('fe_handedness', handOptions, data.handedness || 'Droite'))}
          ${field('fe_color', 'Couleur', inputEl('fe_color', 'text', data.color))}
        </div>
        ${field('fe_brand', 'Marque', inputEl('fe_brand', 'text', data.brand))}
        ${field('fe_model', 'Modèle', inputEl('fe_model', 'text', data.model))}
        ${field('fe_purchase_year', 'Année d\'achat', inputEl('fe_purchase_year', 'number', data.purchase_year, 'min="2000" max="2099"'))}
        ${field('fe_is_available', 'Disponibilité', selectEl('fe_is_available', availOptions, data.is_available))}
        ${field('fe_comment', 'Commentaire', inputEl('fe_comment', 'text', data.comment))}`;
    }
  }

  /* ─────────────────────────────────────────────────────────────────
     OPEN DRAWER FUNCTIONS
  ───────────────────────────────────────────────────────────────── */

  function openEditContract(id) {
    drawerTitle.textContent = 'Modifier le contrat';
    drawerBody.innerHTML = '<p style="color:var(--fe-muted)">Chargement…</p>';
    drawerMode = 'edit-contract';
    drawerData = { id };
    openDrawer();
    ajaxGet('locarc_get_item', { kind: 'contracts', id }, data => {
      drawerData.original = data;
      drawerBody.innerHTML = buildContractForm(data);
      wireContractForm();
      if (data.handle_identifier) {
        const hp = drawerBody.querySelector('[data-for="handle"].locarc-fe__item-preview');
        if (hp) loadItemPreview(hp, 'handles', data.handle_identifier);
      }
      if (data.branches_identifier) {
        const bp = drawerBody.querySelector('[data-for="branches"].locarc-fe__item-preview');
        if (bp) loadItemPreview(bp, 'branches', data.branches_identifier);
      }
    }, err => { showDrawerError(err); });
  }

  function openNewContract() {
    drawerTitle.textContent = 'Nouveau contrat';
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm   = String(today.getMonth() + 1).padStart(2, '0');
    const dd   = String(today.getDate()).padStart(2, '0');
    const todayStr    = `${yyyy}-${mm}-${dd}`;
    const nextYearStr = `${yyyy + 1}-${mm}-${dd}`;
    drawerMode = 'new-contract';
    drawerData = {};
    drawerBody.innerHTML = buildContractForm({
      licence: '', contract_type: 'complet',
      start_date: todayStr, end_date: nextYearStr,
      handle_identifier: '', branches_identifier: '', is_paid: 0,
    });
    wireContractForm();
    openDrawer();
  }

  function openRenewContract(id) {
    drawerTitle.textContent = 'Renouveler le contrat';
    drawerBody.innerHTML = '<p style="color:var(--fe-muted)">Chargement…</p>';
    drawerMode = 'renew-contract';
    drawerData = { id };
    openDrawer();
    ajaxGet('locarc_get_item', { kind: 'contracts', id }, data => {
      // Pre-fill with old end_date as new start_date
      const newStart = data.end_date || '';
      const ts = newStart ? new Date(newStart) : new Date();
      ts.setFullYear(ts.getFullYear() + 1);
      const newEnd = ts.toISOString().substring(0, 10);
      drawerData.original = data;
      drawerBody.innerHTML = buildContractForm({
        ...data, start_date: newStart, end_date: newEnd, is_paid: 0,
      });
      wireContractForm();
      if (data.handle_identifier) {
        const hp = drawerBody.querySelector('[data-for="handle"].locarc-fe__item-preview');
        if (hp) loadItemPreview(hp, 'handles', data.handle_identifier);
      }
      if (data.branches_identifier) {
        const bp = drawerBody.querySelector('[data-for="branches"].locarc-fe__item-preview');
        if (bp) loadItemPreview(bp, 'branches', data.branches_identifier);
      }
    }, err => { showDrawerError(err); });
  }

  function openEditAssignment(id) {
    drawerTitle.textContent = 'Modifier l\'affectation matériel';
    drawerBody.innerHTML = '<p style="color:var(--fe-muted)">Chargement…</p>';
    drawerMode = 'edit-assignment';
    drawerData = { id };
    openDrawer();
    ajaxGet('locarc_get_item', { kind: 'contracts', id }, data => {
      drawerData.original = data;
      drawerBody.innerHTML = buildAssignmentForm(data);
      drawerBody.querySelectorAll('.locarc-fe__ac-wrap').forEach(w => wireAutocomplete(w));
      if (data.handle_identifier) {
        const hp = drawerBody.querySelector('[data-for="handle"].locarc-fe__item-preview');
        if (hp) loadItemPreview(hp, 'handles', data.handle_identifier);
      }
      if (data.branches_identifier) {
        const bp = drawerBody.querySelector('[data-for="branches"].locarc-fe__item-preview');
        if (bp) loadItemPreview(bp, 'branches', data.branches_identifier);
      }
    }, err => { showDrawerError(err); });
  }

  function openEditItem(id, kind) {
    drawerTitle.textContent = kind === 'branches' ? 'Modifier les branches' : 'Modifier la poignée';
    drawerBody.innerHTML = '<p style="color:var(--fe-muted)">Chargement…</p>';
    drawerMode = 'edit-item';
    drawerData = { id, kind };
    openDrawer();
    ajaxGet('locarc_get_item', { kind, id }, data => {
      drawerData.original = data;
      drawerBody.innerHTML = buildItemForm(data, kind);
    }, err => { showDrawerError(err); });
  }

  /* ─────────────────────────────────────────────────────────────────
     DRAWER SUBMIT
  ───────────────────────────────────────────────────────────────── */

  function handleDrawerSubmit() {
    clearDrawerMessages();
    drawerSubmit.classList.add('is-busy');
    drawerSubmit.disabled = true;

    const done = () => { drawerSubmit.classList.remove('is-busy'); drawerSubmit.disabled = false; };

    if (drawerMode === 'edit-contract' || drawerMode === 'new-contract') {
      const payload = collectContractFormData();
      if (!payload.licence) { showDrawerError('La licence est requise.'); done(); return; }
      if (!payload.contract_type) { showDrawerError('Le type de contrat est requis.'); done(); return; }
      if (!payload.start_date || !payload.end_date) { showDrawerError('Les dates sont requises.'); done(); return; }
      if (drawerMode === 'edit-contract') payload.id = drawerData.id;
      ajax('locarc_save_contract', payload, data => {
        done();
        toast(drawerMode === 'new-contract' ? 'Contrat créé !' : 'Contrat mis à jour !', 'ok');
        if (data && data.pdf_error) toast('PDF : ' + data.pdf_error, 'warn');
        closeDrawer();
        reloadSection('contracts');
      }, err => { done(); showDrawerError(err); });

    } else if (drawerMode === 'renew-contract') {
      const payload = collectContractFormData();
      payload.id = drawerData.id;
      ajax('locarc_renew_contract', payload, () => {
        done();
        toast('Contrat renouvelé !', 'ok');
        closeDrawer();
        reloadSection('contracts');
      }, err => { done(); showDrawerError(err); });

    } else if (drawerMode === 'edit-assignment') {
      const v = id => (drawerBody.querySelector('#' + id) || {}).value || '';
      const payload = {
        id: drawerData.id,
        licence: drawerData.original.licence,
        contract_type: drawerData.original.contract_type,
        start_date: drawerData.original.start_date,
        end_date: drawerData.original.end_date,
        handle_identifier:   v('fe_handle_identifier'),
        branches_identifier: v('fe_branches_identifier'),
        is_paid: drawerData.original.is_paid,
      };
      ajax('locarc_save_contract', payload, () => {
        done();
        toast('Affectation mise à jour !', 'ok');
        closeDrawer();
        reloadSection('rented');
      }, err => { done(); showDrawerError(err); });

    } else if (drawerMode === 'edit-item') {
      const kind = drawerData.kind;
      const v  = id => (drawerBody.querySelector('#' + id) || {}).value;
      const payload = { id: drawerData.id, kind };
      const fields = kind === 'branches'
        ? ['identifier','size','power','brand','model','comment','is_available','purchase_year']
        : ['identifier','size','handedness','brand','model','color','comment','is_available','purchase_year'];
      fields.forEach(f => { const val = v('fe_' + f); if (val !== undefined) payload[f] = val; });

      if (!payload.identifier) { showDrawerError('L\'identifiant est requis.'); done(); return; }
      ajax('locarc_save_item', payload, () => {
        done();
        toast('Matériel mis à jour !', 'ok');
        closeDrawer();
        reloadSection(kind);
      }, err => { done(); showDrawerError(err); });

    } else { done(); }
  }

  /* ── Section reload (re-renders via full page reload or soft update) */
  // Simplest reliable approach: page reload preserving section
  function reloadSection(section) {
    // Store desired section so we can re-activate it after reload
    try { sessionStorage.setItem('locarc_fe_section', section); } catch(e) {}
    location.reload();
  }

  // Restore section after reload
  (function restoreSection() {
    try {
      const s = sessionStorage.getItem('locarc_fe_section');
      if (!s) return;
      sessionStorage.removeItem('locarc_fe_section');
      const navBtn = nav.querySelector('[data-section="' + s + '"]');
      if (navBtn) navBtn.click();
    } catch(e) {}
  })();

  /* ─────────────────────────────────────────────────────────────────
     TABLE ACTIONS (event delegation)
  ───────────────────────────────────────────────────────────────── */

  mainEl.addEventListener('click', e => {
    // Sort column header click
    const th = e.target.closest('th[data-sort]');
    if (th && th.dataset.sort !== 'none') { handleSortClick(th); return; }

    // View item modal (clicking an identifier badge)
    const viewBtn = e.target.closest('.js-view-item');
    if (viewBtn) { openViewItem(viewBtn.dataset.identifier, viewBtn.dataset.kind); return; }

    const btn = e.target.closest('button[data-id], button.js-new-contract, a[data-id]');
    if (!btn) return;
    const id = btn.dataset.id;

    // New contract
    if (btn.classList.contains('js-new-contract')) { openNewContract(); return; }

    // Edit contract
    if (btn.classList.contains('js-edit-contract')) { openEditContract(id); return; }

    // Renew contract
    if (btn.classList.contains('js-renew-contract')) { openRenewContract(id); return; }

    // Edit equipment assignment
    if (btn.classList.contains('js-edit-assignment')) { openEditAssignment(id); return; }

    // Edit inventory item
    if (btn.classList.contains('js-edit-item')) { openEditItem(id, btn.dataset.kind); return; }

    // Toggle paid
    if (btn.classList.contains('js-paid')) {
      e.preventDefault();
      const cur = parseInt(btn.dataset.paid, 10);
      const nxt = cur ? 0 : 1;
      btn.classList.add('is-busy');
      ajax('locarc_update_paid', { id, is_paid: nxt }, () => {
        btn.dataset.paid = nxt;
        btn.textContent  = nxt ? 'Payé' : 'Non payé';
        btn.classList.toggle('locarc-fe__badge--ok',   !!nxt);
        btn.classList.toggle('locarc-fe__badge--warn', !nxt);
        const row = btn.closest('.locarc-fe__row');
        if (row) { row.classList.toggle('is-paid', !!nxt); row.classList.toggle('is-unpaid', !nxt); }
        btn.classList.remove('is-busy');
        toast(nxt ? 'Contrat marqué payé.' : 'Contrat marqué non payé.', 'ok');
      }, err => { btn.classList.remove('is-busy'); toast(err, 'error'); });
    }

    // Archive contract
    else if (btn.classList.contains('js-archive')) {
      if (!confirm('Archiver ce contrat ?')) return;
      btn.classList.add('is-busy');
      ajax('locarc_archive_contract', { id }, () => {
        const row = btn.closest('.locarc-fe__row');
        if (row) row.style.display = 'none';
        btn.classList.remove('is-busy');
        toast('Contrat archivé.', 'ok');
      }, err => { btn.classList.remove('is-busy'); toast(err, 'error'); });
    }

    // Restore contract
    else if (btn.classList.contains('js-restore')) {
      if (!confirm('Restaurer ce contrat en actif ?')) return;
      btn.classList.add('is-busy');
      ajax('locarc_restore_contract', { id }, () => {
        btn.classList.remove('is-busy');
        toast('Contrat restauré.', 'ok');
        reloadSection('contracts');
      }, err => { btn.classList.remove('is-busy'); toast(err, 'error'); });
    }

    // Generate PDF
    else if (btn.classList.contains('js-genpdf')) {
      btn.classList.add('is-busy');
      ajax('locarc_generate_pdf', { id }, () => {
        btn.classList.remove('is-busy');
        toast('PDF généré ! Rechargez pour le télécharger.', 'ok');
        reloadSection('contracts');
      }, err => { btn.classList.remove('is-busy'); toast('PDF : ' + err, 'error'); });
    }
  });

  /* ── Search + filter ─────────────────────────────────────────── */

  // targetName = the value of data-target on .locarc-fe__search (e.g. "contracts-table")
  function applyFilters(targetName) {
    const table = document.getElementById('fe-' + targetName);
    if (!table) return;

    // Search term
    const si = mainEl.querySelector(`.locarc-fe__search[data-target="${targetName}"]`);
    const term = si ? si.value.trim().toLowerCase() : '';

    // Active select filters for this table
    const filters = [];
    mainEl.querySelectorAll(`.locarc-fe__filter[data-filter-table="${targetName}"]`).forEach(sel => {
      if (sel.value !== '') filters.push({ col: sel.dataset.filterCol, value: sel.value });
    });

    // Contracts: respect the "show archived" toggle
    const isContracts = targetName === 'contracts-table';
    const showArchived = isContracts && (document.getElementById('fe-contracts-show-archived') || {}).checked;

    table.querySelectorAll('.locarc-fe__row').forEach(row => {
      // Archived rows are hidden by default unless the toggle is on
      if (isContracts && row.dataset.status === 'archived') {
        if (!showArchived) { row.classList.add('is-hidden'); return; }
      }

      // Text search
      if (term) {
        const hay = (row.dataset.search || '').toLowerCase();
        if (!hay.includes(term)) { row.classList.add('is-hidden'); return; }
      }

      // Select filters — each active filter must match the row's data-* attribute
      for (const f of filters) {
        const rowVal = String(row.dataset[f.col] ?? '');
        // Case-sensitive for values like "Droite"/"Gauche"; otherwise compare as-is
        if (rowVal !== f.value) { row.classList.add('is-hidden'); return; }
      }

      row.classList.remove('is-hidden');
    });
  }

  // Wire search inputs
  mainEl.querySelectorAll('.locarc-fe__search').forEach(input => {
    input.addEventListener('input', () => applyFilters(input.dataset.target));
  });

  // Wire filter selects
  mainEl.querySelectorAll('.locarc-fe__filter').forEach(sel => {
    sel.addEventListener('change', () => applyFilters(sel.dataset.filterTable));
  });

  // Archived toggle (contracts)
  const archivedCb = document.getElementById('fe-contracts-show-archived');
  if (archivedCb) {
    archivedCb.addEventListener('change', () => applyFilters('contracts-table'));
  }

  /* ── Table sorting ────────────────────────────────────────────── */

  const tableSortState = new Map(); // tableId → { colIdx, dir }

  function handleSortClick(th) {
    const table = th.closest('table');
    if (!table) return;
    const colIdx  = Array.from(th.parentElement.children).indexOf(th);
    const sortType = th.dataset.sort; // 'text' | 'num' | 'date'
    const cur  = tableSortState.get(table.id);
    const dir  = (cur && cur.colIdx === colIdx && cur.dir === 'asc') ? 'desc' : 'asc';
    tableSortState.set(table.id, { colIdx, dir });

    // Update visual indicators on all headers of this table
    table.querySelectorAll('th[data-sort]').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
    th.classList.add('sort-' + dir);

    sortTableRows(table, colIdx, dir, sortType);
  }

  function sortTableRows(table, colIdx, dir, sortType) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('.locarc-fe__row'));

    rows.sort((a, b) => {
      const aC = a.cells[colIdx], bC = b.cells[colIdx];
      if (!aC || !bC) return 0;
      // Use text content; strip extra whitespace
      const getText = cell => cell.textContent.replace(/\s+/g, ' ').trim();
      const av = getText(aC), bv = getText(bC);

      let cmp;
      if (sortType === 'num') {
        cmp = (parseFloat(av) || 0) - (parseFloat(bv) || 0);
      } else if (sortType === 'date') {
        // Dates are displayed as dd/mm/yyyy
        const toTs = s => {
          const p = s.split('/');
          return p.length === 3 ? (new Date(p[2] + '-' + p[1] + '-' + p[0]).getTime() || 0) : (new Date(s).getTime() || 0);
        };
        cmp = toTs(av) - toTs(bv);
      } else {
        cmp = av.localeCompare(bv, 'fr', { sensitivity: 'base' });
      }
      return dir === 'asc' ? cmp : -cmp;
    });

    // Re-append in sorted order (hidden rows keep their class, just move in DOM)
    rows.forEach(r => tbody.appendChild(r));
  }

})();
