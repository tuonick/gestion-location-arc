
jQuery(function($){
  const modal = $('#locarc-modal');
  const $dialog = modal.find('.locarc-modal-dialog');
  function openModal(title, bodyHtml, footerHtml){
    modal.find('.locarc-modal-title').text(title||'');
    modal.find('.locarc-modal-body').html(bodyHtml||'');
    modal.find('.locarc-modal-footer').html(footerHtml||'');
    $dialog.removeClass('locarc-modal-dialog-tall locarc-modal-dialog-assign');
    modal.show();
  }
  function closeModal(){ $dialog.removeClass('locarc-modal-dialog-tall locarc-modal-dialog-assign'); modal.hide(); }
  modal.on('click', '.locarc-modal-close, .locarc-modal-backdrop', closeModal);

  function api(action, data, method='POST'){
    data = data || {};
    data.action = action;
    data.nonce = LOCARC.nonce;
    const url = (typeof ajaxurl !== 'undefined' && ajaxurl) ? ajaxurl : LOCARC.ajax_url;
    return $.ajax({
      url: url,
      method: method,
      data: data,
      dataType: 'json'
    });
  }

  // Disable a button while an async action is running (prevents double-click and helps UX)
  function withBusy($btn, fn){
    if(!$btn || !$btn.length) return fn();
    const prevText = $btn.text();
    $btn.prop('disabled', true).addClass('is-busy');
    return $.when(fn()).always(()=>{
      $btn.prop('disabled', false).removeClass('is-busy');
      if(prevText) $btn.text(prevText);
    });
  }

  function warnPdfIfNeeded(resp){
    const data = resp && resp.data ? resp.data : {};
    if(data && data.pdf_generated === false){
      alert('Contrat enregistrÃ©, mais le PDF nâ€™a pas Ã©tÃ© gÃ©nÃ©rÃ© : ' + (data.pdf_error || 'erreur inconnue'));
    }
  }

function confirmDelete(label){
    return window.confirm('Confirmer la suppression de ' + label + ' ?\nCette action est irréversible.');
  }

  function addMonthsIso(iso, months){
    if(!iso) return '';
    const parts = String(iso).split('-');
    if(parts.length !== 3) return '';
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    if(isNaN(y) || isNaN(m) || isNaN(d)) return '';
    const dt = new Date(y, m, d);
    dt.setMonth(dt.getMonth() + months);
    const yyyy = dt.getFullYear();
    const mm = String(dt.getMonth()+1).padStart(2, '0');
    const dd = String(dt.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }

  function contractFinanceFieldsHtml(prefix=''){
    const p = prefix ? prefix + '-' : '';
    return `
      <div>
        <label>Mode de paiement</label>
        <select name="payment_method" id="${p}payment-method">
          <option value="">Non renseigné</option>
          <option value="cheque">Chèque</option>
          <option value="carte_bancaire">Carte Bancaire</option>
          <option value="helloasso">HelloAsso</option>
          <option value="especes">Espèces</option>
        </select>
      </div>
      <div>
        <label>Montant du chèque de caution (€)</label>
        <input type="number" step="0.01" min="0" name="caution_amount" id="${p}caution-amount" placeholder="ex: 200" />
      </div>
      <div class="full" id="${p}cheque-dates-wrap" style="display:none;">
        <label>Échéances trimestrielles (si paiement par chèque)</label>
        <div class="locarc-cheque-grid">
          <div><input type="date" name="payment_due_1" id="${p}payment-due-1" /></div>
          <div><input type="date" name="payment_due_2" id="${p}payment-due-2" /></div>
          <div><input type="date" name="payment_due_3" id="${p}payment-due-3" /></div>
          <div><input type="date" name="payment_due_4" id="${p}payment-due-4" /></div>
        </div>
      </div>
    `;
  }

  function initContractFinanceFields(prefix='', prefill={}){
    const p = prefix ? prefix + '-' : '';
    const $method = $('#' + p + 'payment-method');
    const $wrap = $('#' + p + 'cheque-dates-wrap');
    const $caution = $('#' + p + 'caution-amount');
    let $type = $();
    if(prefix === 'edit') $type = $('#locarc-contract-type');
    else if(prefix === 'renew') $type = $('#locarc-renew-type');
    else $type = $('#locarc-contract-type');

    let $start;
    if(prefix === 'edit') $start = $('#locarc-start');
    else if(prefix === 'renew') $start = $('#renew-start');
    else $start = $('input[name="start_date"]');
    const ids = [1,2,3,4].map(i => $('#' + p + 'payment-due-' + i));

    function fillAutoDates(force){
      const start = ($start.val() || '').trim();
      if(!start) return;
      ids.forEach(function($el, idx){
        if(force || !$el.val()){
          $el.val(addMonthsIso(start, idx * 3));
        }
      });
    }

    function clearFinance(){
      $method.val('');
      $caution.val('');
      ids.forEach(function($el){ $el.val(''); });
    }

    function defaultCautionForType(t){
      if(t === 'pret') return '';
      if(t === 'branches') return '200';
      return '400';
    }

    function applyTypeDefaults(force){
      const t = ($type.val() || '').trim();
      if(t === 'pret'){
        clearFinance();
        $wrap.hide();
        return;
      }
      const def = defaultCautionForType(t);
      if(force || !$caution.val()){
        $caution.val(def);
      }
    }

    function toggleChequeFields(){
      const isCheque = ($method.val() || '') === 'cheque';
      $wrap.toggle(isCheque);
      if(isCheque){
        fillAutoDates(false);
      } else {
        ids.forEach(function($el){ $el.val(''); });
      }
    }

    if(prefill.payment_method){ $method.val(prefill.payment_method); }
    if(prefill.caution_amount !== undefined && prefill.caution_amount !== null && String(prefill.caution_amount) !== ''){ $caution.val(prefill.caution_amount); }
    [1,2,3,4].forEach(function(i){
      const key = 'payment_due_' + i;
      if(prefill[key]) $('#' + p + 'payment-due-' + i).val(prefill[key]);
    });

    $method.off('change.locarcFinance').on('change.locarcFinance', toggleChequeFields);
    $start.off('change.locarcFinance').on('change.locarcFinance', function(){
      if(($method.val() || '') === 'cheque') fillAutoDates(true);
    });
    $type.off('change.locarcFinanceType').on('change.locarcFinanceType', function(){
      applyTypeDefaults(true);
      toggleChequeFields();
    });

    applyTypeDefaults(false);
    toggleChequeFields();
  }


  function buildSuggest($input, kind){
    const wrap = $('<div class="locarc-suggest"></div>');
    $input.wrap(wrap);
    const $wrap = $input.parent();
    const $list = $('<div class="locarc-suggest-list" style="display:none"></div>');
    $wrap.append($list);

    let lastTerm = '';
    let debounceTimer = null;
    let pendingXhr = null;
    $input.on('input', function(){
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function(){
        const term = $input.val().trim();
        if (term.length < 1){ $list.hide(); return; }
        if (term === lastTerm) return;
        lastTerm = term;

        if (pendingXhr && pendingXhr.readyState !== 4 && typeof pendingXhr.abort === 'function') {
          try { pendingXhr.abort(); } catch(e){}
        }

        pendingXhr = api('locarc_autocomplete', {term, kind}, 'GET').done(res=>{
          if(!res.success){ $list.hide(); return; }
          $list.empty();
          res.data.forEach(it=>{
            const div = $('<div class="locarc-suggest-item"></div>').text(it.label)
              .data('value', it.value)
              .data('item', it);
            $list.append(div);
          });
          $list.show();
        });
      }, 250);
    });

    $list.on('click', '.locarc-suggest-item', function(){
      const item = $(this).data('item') || {value: $(this).data('value')};
      $input.val(item.value);
      $list.hide();
      $input.trigger('locarc:selected', [item]);
      $input.trigger('change');
    });

    $(document).on('click', function(e){
      if(!$wrap[0].contains(e.target)) $list.hide();
    });
  }

  // Read-only helper: shows matching identifiers while typing, without allowing click-to-fill.
  // Used only when creating branches/handles to help find a free identifier.
  function buildIdentifierPeek($input, kind){
    const wrap = $('<div class="locarc-suggest"></div>');
    $input.wrap(wrap);
    const $wrap = $input.parent();
    const $list = $('<div class="locarc-suggest-list locarc-peek-list" style="display:none"></div>');
    $wrap.append($list);

    let lastTerm = '';
    let debounceTimer = null;
    let pendingXhr = null;

    $input.on('input', function(){
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function(){
        const term = $input.val().trim();
        if (term.length < 1){ $list.hide(); return; }
        if (term === lastTerm) return;
        lastTerm = term;

        if (pendingXhr && pendingXhr.readyState !== 4 && typeof pendingXhr.abort === 'function') {
          try { pendingXhr.abort(); } catch(e){}
        }

        pendingXhr = api('locarc_autocomplete', {term, kind}, 'GET').done(res=>{
          if(!res.success){ $list.hide(); return; }
          $list.empty();
          if(!res.data || !res.data.length){
            $list.append($('<div class="locarc-suggest-item"></div>').text('Aucun identifiant existant ne matche'));
            $list.show();
            return;
          }
          res.data.forEach(it=>{
            // NOTE: intentionally NOT clickable and does not fill input
            const div = $('<div class="locarc-suggest-item"></div>').text(it.label);
            $list.append(div);
          });
          $list.show();
        });
      }, 150);
    });

    $(document).on('click', function(e){
      if(!$wrap[0].contains(e.target)) $list.hide();
    });
  }

  // Branches / Handles add
  // NOTE: for id=0 (create), do NOT call locarc_get_item (it requires an id and would 400 -> no modal)
  $('#locarc-add-branch').on('click', ()=> openEditItem('branches', 0));
  $('#locarc-add-handle').on('click', ()=> openEditItem('handles', 0));
  $('#locarc-add-member').on('click', ()=> openEditItem('members', 0));
  $('#locarc-add-sight').on('click', ()=> openEditItem('sights', 0));
  $('#locarc-add-init_bow').on('click', ()=> openEditItem('init_bows', 0));

  $('.locarc-table').on('click', '.locarc-edit', function(){
    const kind = $(this).data('kind');
    const id = $(this).closest('tr').data('id');
    openEditItem(kind, id);
  });

  function openEditItem(kind, id){
    const isBranches = (kind === 'branches');
    const isHandles = (kind === 'handles');
    const isMembers = (kind === 'members');
    const isSights = (kind === 'sights');
    const isInitBows = (kind === 'init_bows');
    const kindLabel = isBranches ? ' des branches' : isHandles ? ' une poignée' : isSights ? ' un viseur' : isInitBows ? " un arc d'initiation" : ' un licencié';
    const title = (id ? 'Modifier' : 'Ajouter') + kindLabel;

    const show = (row)=>{
      const body = (isMembers ? buildMemberForm(row||{}) : buildItemForm(kind, row||{}));
      const footer = `
        <button type="button" class="button" id="locarc-cancel">Annuler</button>
        <button type="button" class="button button-primary" id="locarc-save">Enregistrer</button>
      `;
      openModal(title, body, footer);

      // When creating equipment, show a read-only list of existing identifiers while typing.
      if(!id && (kind==='branches' || kind==='handles' || kind==='sights' || kind==='init_bows')){
        const $identifier = modal.find('input[name="identifier"]');
        if($identifier.length) buildIdentifierPeek($identifier, kind);
      }

      modal.off('click', '#locarc-cancel').on('click', '#locarc-cancel', closeModal);
      modal.off('click', '#locarc-save').on('click', '#locarc-save', function(e){
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const payload = formToObject(modal.find('form'));
        payload.kind = kind; payload.id = id;
        withBusy($btn, () => api('locarc_save_item', payload)
          .done(r=>{
            if(!r.success){ alert('Erreur: ' + (r.data?.message || r.data || '')); return; }
            const saved = r.data && r.data.item ? r.data.item : null;
            const newId = r.data && r.data.id ? parseInt(r.data.id,10) : id;
            if(saved){
              upsertItemRow(kind, newId, saved);
              closeModal();
            } else {
              window.location.reload();
            }
          })
          .fail(xhr=>{
            const msg = (xhr.responseJSON && (xhr.responseJSON.data || xhr.responseJSON.message))
              ? (xhr.responseJSON.data || xhr.responseJSON.message)
              : (xhr.responseText || ('HTTP ' + xhr.status));
            alert('Erreur AJAX: ' + msg);
          })
        );
      });
    };

    if(!id){
      show({});
      return;
    }

    api('locarc_get_item', {kind, id}, 'GET').done(res=>{
      show(res.success ? res.data : {});
    }).fail(()=>{
      show({});
    });
  }

  function upsertItemRow(kind, id, row){
    const tableId = kind==='branches' ? 'locarc-branches-table'
      : kind==='handles' ? 'locarc-handles-table'
      : kind==='sights' ? 'locarc-sights-table'
      : kind==='init_bows' ? 'locarc-init_bows-table'
      : 'locarc-members-table';
    const $table = $('#'+tableId);
    if(!$table.length) return;

    let $tr = $table.find('tbody tr[data-id="'+id+'"]');
    const isNew = !$tr.length;
    if(isNew){
      $tr = $('<tr></tr>').attr('data-id', id);
      $table.find('tbody').append($tr);
    }

    if(kind==='branches'){
      const disp = dispLabel(row.is_available);
      $tr.toggleClass('locarc-flag', Number(row.is_available)===2);
      $tr.html(`
        <td></td>
        <td><code>${esc(row.identifier||'')}</code></td>
        <td>${esc(row.brand||'')}</td>
        <td>${esc(row.model||'')}</td>
        <td>${esc(row.size||'')}</td>
        <td>${esc(row.power||'')}</td>
        <td>${disp}</td>
        <td>${esc(row.comment||'')}</td>
        <td></td>
        <td>${esc(row.purchase_year||'')}</td>
        <td>${esc(row.purchase_price||'')}</td>
        <td>
          <button class="button locarc-edit" data-kind="branches">Modifier</button>
          <button class="button button-link-delete locarc-delete" data-kind="branches">Supprimer</button>
        </td>
      `);
    } else if(kind==='handles') {
      const disp = dispLabel(row.is_available);
      $tr.toggleClass('locarc-flag', Number(row.is_available)===2);
      $tr.html(`
        <td></td>
        <td><code>${esc(row.identifier||'')}</code></td>
        <td>${esc(row.brand||'')}</td>
        <td>${esc(row.model||'')}</td>
        <td>${esc(row.size||'')}</td>
        <td>${esc(row.handedness||'')}</td>
        <td>${esc(row.color||'')}</td>
        <td>${disp}</td>
        <td>${esc(row.comment||'')}</td>
        <td></td>
        <td>${esc(row.purchase_year||'')}</td>
        <td>${esc(row.purchase_price||'')}</td>
        <td>
          <button class="button locarc-edit" data-kind="handles">Modifier</button>
          <button class="button button-link-delete locarc-delete" data-kind="handles">Supprimer</button>
        </td>
      `);
    } else if(kind==='sights') {
      const disp = dispLabel(row.is_available);
      $tr.toggleClass('locarc-flag', Number(row.is_available)===2);
      $tr.html(`
        <td></td>
        <td><code>${esc(row.identifier||'')}</code></td>
        <td>${esc(row.brand||'')}</td>
        <td>${esc(row.model||'')}</td>
        <td>${esc(row.handedness||'')}</td>
        <td>${disp}</td>
        <td>${esc(row.comment||'')}</td>
        <td></td>
        <td>${esc(row.purchase_year||'')}</td>
        <td>${esc(row.purchase_price||'')}</td>
        <td>
          <button class="button locarc-edit" data-kind="sights">Modifier</button>
          <button class="button button-link-delete locarc-delete" data-kind="sights">Supprimer</button>
        </td>
      `);
    } else if(kind==='init_bows') {
      const disp = dispLabel(row.is_available);
      $tr.toggleClass('locarc-flag', Number(row.is_available)===2);
      $tr.html(`
        <td></td>
        <td><code>${esc(row.identifier||'')}</code></td>
        <td>${esc(row.brand||'')}</td>
        <td>${esc(row.model||'')}</td>
        <td>${esc(row.size||'')}</td>
        <td>${esc(row.power||'')}</td>
        <td>${esc(row.handedness||'')}</td>
        <td>${disp}</td>
        <td>${esc(row.comment||'')}</td>
        <td></td>
        <td>${esc(row.purchase_year||'')}</td>
        <td>${esc(row.purchase_price||'')}</td>
        <td>
          <button class="button locarc-edit" data-kind="init_bows">Modifier</button>
          <button class="button button-link-delete locarc-delete" data-kind="init_bows">Supprimer</button>
        </td>
      `);
    } else {
      $tr.html(`
        <td><code>${esc(row.licence||'')}</code></td>
        <td>${esc(row.last_name||'')}</td>
        <td>${esc(row.first_name||'')}</td>
        <td>${esc(row.dob||'')}</td>
        <td>${esc(row.email||'')}</td>
        <td>${esc(row.phone||'')}</td>
        <td>${esc(row.address1||'')}</td>
        <td>${esc(row.postal_code||'')}</td>
        <td>${esc(row.city||'')}</td>
        <td><button class="button locarc-edit" data-kind="members">Modifier</button></td>
      `);
    }

    // Re-number first column (inventory tables only)
    if(kind==='branches' || kind==='handles' || kind==='sights' || kind==='init_bows'){
      $table.find('tbody tr').each(function(idx){
        $(this).children('td').first().text(idx+1);
      });
    }

    applyTableFilters(tableId);
  }

  function buildItemForm(kind, row){
    const isBranches = (kind === 'branches');
    const isHandles  = (kind === 'handles');
    const isSights   = (kind === 'sights');
    const isInitBows = (kind === 'init_bows');
    const availVal = (v)=> {
      const n = Number(v);
      if(n===2) return '2';
      if(n===3) return '3';
      if(n===4) return '4';
      if(n===5) return '5';
      return n ? '1' : '0';
    };
    const handednessField = `
      <div>
        <label>Latéralité</label>
        <select name="handedness">
          <option value="Gauche" ${row.handedness==='Gauche'?'selected':''}>Gauche</option>
          <option value="Droite" ${row.handedness!=='Gauche'?'selected':''}>Droite</option>
        </select>
      </div>
    `;

    let sizeAndPower = '';
    if(isBranches || isInitBows){
      sizeAndPower = `
        <div>
          <label>Taille</label>
          <input type="number" name="size" value="${esc(row.size||'')}" ${isBranches?'min="66" max="70"':'min="0"'} required />
        </div>
        <div>
          <label>Puissance</label>
          <input type="number" name="power" min="1" max="60" step="1" value="${esc(row.power||'')}" required />
        </div>
      `;
    } else if(isHandles){
      sizeAndPower = `
        <div>
          <label>Taille</label>
          <input type="number" name="size" value="${esc(row.size||'')}" min="23" max="25" required />
        </div>
      `;
    }

    const colorField = isHandles ? `
      <div>
        <label>Couleur</label>
        <input type="text" name="color" value="${esc(row.color||'')}" />
      </div>
    ` : '';

    return `
      <form class="locarc-form">
        <div class="full">
          <label>Identifiant</label>
          <input type="text" name="identifier" value="${esc(row.identifier||'')}" placeholder="ex: SA-M-25D-7" required />
          <div class="locarc-hint">Identifiant unique (sera utilisé pour les contrats et l'autocomplete).</div>
        </div>
        ${sizeAndPower}
        ${!isBranches ? handednessField : ''}
        ${colorField}
        <div>
          <label>Marque</label>
          <input type="text" name="brand" value="${esc(row.brand||'')}" />
        </div>
        <div>
          <label>Modèle</label>
          <input type="text" name="model" value="${esc(row.model||'')}" />
        </div>
        <div>
          <label>Année</label>
          <input type="number" name="purchase_year" value="${esc(row.purchase_year||'')}" />
        </div>
        <div>
          <label>Prix</label>
          <input type="text" name="purchase_price" value="${esc(row.purchase_price||'')}" placeholder="ex: 127" />
        </div>
        <div>
          <label>Disponible ?</label>
          <select name="is_available">
            <option value="1" ${availVal(row.is_available)==='1'?'selected':''}>Oui</option>
            <option value="0" ${availVal(row.is_available)==='0'?'selected':''}>Non</option>
            <option value="2" ${availVal(row.is_available)==='2'?'selected':''}>FLAG</option>
            <option value="3" ${availVal(row.is_available)==='3'?'selected':''}>Obsolète</option>
            <option value="4" ${availVal(row.is_available)==='4'?'selected':''}>En Réparation</option>
            <option value="5" ${availVal(row.is_available)==='5'?'selected':''}>H-S</option>
          </select>
        </div>
        <div class="full">
          <label>Commentaire</label>
          <textarea name="comment" rows="2" placeholder="ex: fissure, à contrôler, prêt compétition...">${esc(row.comment||'')}</textarea>
        </div>
      </form>
    `;
  }

  function dispLabel(v){
    const n = Number(v);
    if(n===2) return 'FLAG';
    if(n===3) return 'Obsolète';
    if(n===4) return 'En Réparation';
    if(n===5) return 'H-S';
    return n ? 'Oui' : 'Non';
  }

  function buildMemberForm(row){
    return `
      <form class="locarc-form">
        <div>
          <label>Code Adhérent</label>
          <input type="text" name="licence" value="${esc(row.licence||'')}" required />
        </div>
        <div>
          <label>Nom</label>
          <input type="text" name="last_name" value="${esc(row.last_name||'')}" />
        </div>
        <div>
          <label>Prénom</label>
          <input type="text" name="first_name" value="${esc(row.first_name||'')}" />
        </div>
        <div>
          <label>Date de naissance</label>
          <input type="date" name="dob" value="${esc(row.dob||'')}" />
        </div>
        <div>
          <label>Email</label>
          <input type="email" name="email" value="${esc(row.email||'')}" />
        </div>
        <div>
          <label>Téléphone</label>
          <input type="text" name="phone" value="${esc(row.phone||'')}" />
        </div>
        <div class="full">
          <label>Adresse</label>
          <input type="text" name="address1" value="${esc(row.address1||'')}" />
        </div>
        <div>
          <label>Code postal</label>
          <input type="text" name="postal_code" value="${esc(row.postal_code||'')}" />
        </div>
        <div>
          <label>Ville</label>
          <input type="text" name="city" value="${esc(row.city||'')}" />
        </div>
      </form>
    `;
  }

  // Delete
  $('.locarc-table').on('click', '.locarc-delete', function(){
    const kind = $(this).data('kind');
    const $tr = $(this).closest('tr');
    const id = $tr.data('id');
    const label = $tr.find('code').text() || ('ID ' + id);
    if(!confirmDelete(label)) return;
    api('locarc_delete_item', {kind, id}).done(r=>{
      if(!r.success){ alert('Erreur: ' + r.data); return; }
      window.location.reload();
    });
  });

  // Contracts: paid badge click (replaces old <select>)
  $('.locarc-table').on('click', '.locarc-paid-badge', function(){
    const $badge = $(this);
    const $tr    = $badge.closest('tr');
    const id     = $tr.data('id');
    const currently_paid = $badge.hasClass('locarc-paid-badge--paid') ? 1 : 0;
    const is_paid = currently_paid ? 0 : 1;
    api('locarc_update_paid', {id, is_paid}).done(r => {
      if (!r.success){ alert('Erreur: ' + r.data); return; }
      const paid = is_paid === 1;
      $badge.toggleClass('locarc-paid-badge--paid',   paid);
      $badge.toggleClass('locarc-paid-badge--unpaid', !paid);
      $badge.text(paid ? 'Payé' : 'Non payé');
      // update hidden sort value
      $tr.find('.locarc-paid-sort').text(is_paid);
      $tr.toggleClass('locarc-paid',   paid);
      $tr.toggleClass('locarc-unpaid', !paid);
    });
  });

  function priceForType(t, custom){
    const map = (LOCARC && LOCARC.contract_prices) ? LOCARC.contract_prices : {complet:200, arc_nu:120, jeune:160, branches:80, pret:0};
    if(t==='personnalise') return Number(custom||0)||0;
    return Number(map[t] || 0);
  }
  function fmtEur(v){
    try { return (Number(v)||0).toLocaleString('fr-FR', {maximumFractionDigits:0}) + ' €'; }
    catch(e){ return String(v||0) + ' €'; }
  }
  function contractTypeOptionsHtml(selected){
    if(LOCARC && LOCARC.contract_types_html){
      return LOCARC.contract_types_html.replace(/__SELECTED__([a-z_]+)__/g, function(_, key){
        return String(selected||'') === String(key) ? ' selected' : '';
      });
    }
    return '';
  }

  function contractTypeLabels(){
    return (LOCARC && LOCARC.contract_type_labels) ? LOCARC.contract_type_labels : {complet:'Complet', arc_nu:'Arc nu', jeune:'Jeune', branches:'Branches', personnalise:'Personnalisé', pret:'Prêt'};
  }

  // Archive / restore
  $('.locarc-table').on('click', '.locarc-archive', function(){
    const id = $(this).closest('tr').data('id');
    if(!window.confirm('Archiver ce contrat ?')) return;
    api('locarc_archive_contract', {id}).done(()=>window.location.reload());
  });
  $('.locarc-table').on('click', '.locarc-restore', function(){
    const id = $(this).closest('tr').data('id');
    api('locarc_restore_contract', {id}).done(()=>window.location.reload());
  });

  // Permanent delete (archived only)
  $('.locarc-table').on('click', '.locarc-delete-contract', function(){
    const id = $(this).closest('tr').data('id');
    if(!window.confirm('Supprimer définitivement ce contrat archivé ?\nCette action est irréversible.')) return;
    api('locarc_delete_contract_permanent', {id}).done(r=>{
      if(!r.success){ alert('Erreur: ' + r.data); return; }
      window.location.reload();
    });
  });

  // PDF generate
  $('.locarc-table').on('click', '.locarc-pdf', function(){
    const id = $(this).closest('tr').data('id');
    api('locarc_generate_pdf', {id}).done(r=>{
      if(!r.success){ alert('Erreur: ' + r.data); return; }
      window.location.reload();
    });
  });

  // Envoyer le contrat par email (manuel)
  $('#locarc-contracts-table').on('click', '.locarc-send', function(){
    const $btn = $(this);
    const id = $btn.closest('tr').data('id');
    withBusy($btn, () => api('locarc_send_contract_email', {id}).done(r=>{
      if(!r.success){ alert('Erreur: ' + (r.data?.message || r.data || '')); return; }
      alert('✅ Email envoyé');
    }).fail(xhr=>{
      const msg = (xhr.responseJSON && (xhr.responseJSON.data || xhr.responseJSON.message))
        ? (xhr.responseJSON.data || xhr.responseJSON.message)
        : (xhr.responseText || ('HTTP ' + xhr.status));
      alert('Erreur envoi email: ' + msg);
    }));
  });

  // Modifier un contrat : même modal que la création
  $('#locarc-contracts-table').on('click', '.locarc-edit-contract', function(){
    const $tr = $(this).closest('tr');
    const id = $tr.data('id');
    api('locarc_get_item', {kind:'contracts', id}, 'GET').done(r=>{
      if(!r.success){ alert('Erreur: ' + r.data); return; }
      const c = r.data;

      const body = `
        <form class="locarc-form">
          <div class="full">
            <label>Sélection archer</label>
            <input type="text" id="locarc-member" placeholder="Tape prénom, nom ou n° licence" autocomplete="off" required />
            <input type="hidden" name="licence" id="locarc-licence" required />
          </div>
          <div class="full" id="locarc-member-details" style="display:none;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div><label>Adresse</label><input type="text" id="locarc-md-address" readonly /></div>
              <div><label>Ville</label><input type="text" id="locarc-md-city" readonly /></div>
              <div><label>Code postal</label><input type="text" id="locarc-md-postal" readonly /></div>
              <div><label>Téléphone</label><input type="text" id="locarc-md-phone" readonly /></div>
              <div class="full"><label>Email</label><input type="text" id="locarc-md-email" readonly /></div>
            </div>
          </div>
          <div>
            <label>Type de contrat</label>
            <select name="contract_type" id="locarc-contract-type">${contractTypeOptionsHtml(c.contract_type||'complet')}</select>
          </div>
          <div id="locarc-custom-price-wrap" style="display:none;">
            <label>Montant personnalisé (€)</label>
            <input type="number" step="0.01" min="0" name="custom_price" id="locarc-custom-price" placeholder="ex: 150" />
          </div>
          <div id="locarc-paid-wrap">
            <label>Payé ?</label>
            <select name="is_paid" id="locarc-is-paid">
              <option value="0">Non Payé</option>
              <option value="1">Payé</option>
            </select>
            <span id="locarc-paid-dash" style="display:none;line-height:30px;">-</span>
          </div>
          <div>
            <label>Début</label>
            <input type="date" name="start_date" id="locarc-start" />
          </div>
          <div>
            <label>Fin</label>
            <input type="date" name="end_date" id="locarc-end" />
          </div>
          ${contractFinanceFieldsHtml('edit')}
          <div class="full">
            <label>Poignée (ID) — optionnel pour Prêt</label>
            <input type="text" name="handle_identifier" id="locarc-handle2" placeholder="ex: SA-M-25D-7" />
            <div class="locarc-hint" id="locarc-handle2-warn"></div>
          </div>
          <div class="full">
            <label>Branches (ID) — optionnel pour Prêt</label>
            <input type="text" name="branches_identifier" id="locarc-branches2" placeholder="ex: EX-W-7024-1" />
            <div class="locarc-hint" id="locarc-branches2-warn"></div>
          </div>
        </form>
      `;
      const footer = `
        <button type="button" class="button" id="locarc-cancel">Annuler</button>
        <button type="button" class="button button-primary" id="locarc-save-contract">Enregistrer</button>
      `;
      openModal('Modifier contrat', body, footer);

      function setDetails(d){
        $('#locarc-md-address').val(d.address || '');
        $('#locarc-md-postal').val(d.postal_code || '');
        $('#locarc-md-city').val(d.city || '');
        $('#locarc-md-phone').val(d.phone || '');
        $('#locarc-md-email').val(d.email || '');
        $('#locarc-member-details').show();
      }
      function loadMemberDetails(lic){
        api('locarc_get_member_by_licence', {licence: lic}, 'GET').done(x=>{
          if(x && x.success) setDetails(x.data||{});
        });
      }

      buildSuggest($('#locarc-member'), 'members');
      $('#locarc-member').on('locarc:selected', function(e, item){
        $('#locarc-member').val(item.label || item.value || '');
        const lic = (item.licence || item.value || '').trim();
        $('#locarc-licence').val(lic);
        const hasCsv = (item.address || item.postal_code || item.city || item.phone || item.email);
        if(hasCsv) setDetails(item);
        else if(lic) loadMemberDetails(lic);
      });

      // Prefill
      const lic0 = (c.licence||'').trim();
      $('#locarc-licence').val(lic0);
      $('#locarc-member').val(lic0);
      if(lic0) loadMemberDetails(lic0);
      $('#locarc-contract-type').val(c.contract_type||'complet');
      $('#locarc-custom-price').val(c.custom_price||'');
      $('#locarc-is-paid').val(String(c.is_paid||0));
      $('#locarc-start').val(c.start_date||'');
      $('#locarc-end').val(c.end_date||'');
      initContractFinanceFields('edit', c);
      $('#locarc-handle2').val(c.handle_identifier||'');
      $('#locarc-branches2').val(c.branches_identifier||'');

      function toggleCustomPrice(){
        const t = ($('#locarc-contract-type').val()||'');
        if(t==='personnalise') $('#locarc-custom-price-wrap').show();
        else { $('#locarc-custom-price-wrap').hide(); $('#locarc-custom-price').val(''); }
      }
      function applyPretMode(){
        const t = ($('#locarc-contract-type').val()||'');
        const isPret = (t === 'pret');
        if(isPret){
          $('#locarc-is-paid').val('0').prop('disabled', true).hide();
          $('#locarc-paid-dash').show();
        } else {
          $('#locarc-is-paid').prop('disabled', false).show();
          $('#locarc-paid-dash').hide();
        }
      }
      toggleCustomPrice();
      applyPretMode();
      $('#locarc-contract-type').on('change', function(){ toggleCustomPrice(); applyPretMode(); });

      buildSuggest($('#locarc-handle2'), 'handles');
      buildSuggest($('#locarc-branches2'), 'branches');

      function checkAssigned(kind, identifier, $warn, contractId){
        if(!identifier){ $warn.text(''); return; }
        api('locarc_get_by_identifier', {kind, identifier}, 'GET').done(x=>{
          if(!x.success){ $warn.text('⚠️ Inconnu dans la base'); return; }
          if(x.data._assigned_to && String(x.data._assigned_to.id||'') !== String(contractId||0)){
            const who = x.data._assigned_to.display_name || x.data._assigned_to.licence || '';
            $warn.text('⚠️ Déjà affecté à ' + who);
          } else {
            $warn.text('');
          }
        });
      }
      $('#locarc-handle2').on('change blur', function(){
        const idv = $('#locarc-handle2').val().trim();
        checkAssigned('handles', idv, $('#locarc-handle2-warn'), id);
      });
      $('#locarc-branches2').on('change blur', function(){
        const idv = $('#locarc-branches2').val().trim();
        checkAssigned('branches', idv, $('#locarc-branches2-warn'), id);
      });

      modal.off('click', '#locarc-cancel').on('click', '#locarc-cancel', closeModal);
      modal.off('click', '#locarc-save-contract').on('click', '#locarc-save-contract', function(e){
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const data = formToObject(modal.find('form'));
        data.id = id;
        if((data.contract_type||'')==='personnalise' && (!data.custom_price || data.custom_price==='')){
          alert('Montant requis pour un contrat personnalisé');
          return;
        }
        withBusy($btn, () => api('locarc_save_contract', data)
          .done(resp=>{
            if(!resp.success){ alert('Erreur: ' + (resp.data?.message || resp.data || '')); return; }
            warnPdfIfNeeded(resp);
            // Refresh key cells in row
            const typeMap = contractTypeLabels();
            const typeLabel = typeMap[data.contract_type] || data.contract_type;
            $tr.find('.locarc-type-label').text(typeLabel).attr('data-type', data.contract_type);
            $tr.find('.locarc-amount-display').text(fmtEur(priceForType(data.contract_type, data.custom_price)));
            // contracts table now has a row number column at index 0
            $tr.find('td').eq(1).find('code').text(data.licence||'');
            $tr.find('td').eq(6).text(data.end_date || $tr.find('td').eq(6).text());
            closeModal();
          })
          .fail(xhr=>{
            const msg = (xhr.responseJSON && (xhr.responseJSON.data || xhr.responseJSON.message))
              ? (xhr.responseJSON.data || xhr.responseJSON.message)
              : (xhr.responseText || ('HTTP ' + xhr.status));
            alert('Erreur AJAX: ' + msg);
          })
        );
      });
    });
  });

// Renew (open modal prefilled with previous contract)
  $('.locarc-table').on('click', '.locarc-renew', function(){
    const $tr = $(this).closest('tr');
    const id = $tr.data('id');
    api('locarc_get_item', {kind:'contracts', id}, 'GET').done(r=>{
      if(!r.success){ alert('Erreur: ' + r.data); return; }
      const c = r.data;
      const newStart = c.end_date;
      const newEnd = (()=>{
        try{
          const d = new Date(newStart);
          d.setFullYear(d.getFullYear()+1);
          return d.toISOString().slice(0,10);
        }catch(e){ return ''; }
      })();

      const body = `
        <form class="locarc-form">
          <div class="full"><span class="locarc-pill">Renouvellement contrat #${esc(c.contract_number||'')}</span></div>
          <input type="hidden" name="licence" value="${esc(c.licence||'')}" />

          <div>
            <label>Type de contrat</label>
            <select name="contract_type" id="locarc-renew-type">${contractTypeOptionsHtml(c.contract_type||'complet')}</select>
          </div>
          <div id="locarc-renew-custom-wrap" style="display:none;">
            <label>Montant personnalisé (€)</label>
            <input type="number" step="0.01" min="0" name="custom_price" id="locarc-renew-custom" />
          </div>

          <div>
            <label>Payé ?</label>
            <select name="is_paid" disabled>
              <option value="0" selected>Non Payé</option>
              <option value="1">Payé</option>
            </select>
            <input type="hidden" name="is_paid" value="0" />
          </div>

          <div>
            <label>Début</label>
            <input type="date" name="start_date" id="renew-start" value="${esc(newStart||'')}" />
          </div>
          <div>
            <label>Fin</label>
            <input type="date" name="end_date" id="locarc-renew-start-end-anchor" value="${esc(newEnd||'')}" />
          </div>
          ${contractFinanceFieldsHtml('renew')}

          <div class="full">
            <label>Poignée (ID) — optionnel pour Prêt</label>
            <input type="text" name="handle_identifier" id="locarc-renew-handle" value="${esc(c.handle_identifier||'')}" autocomplete="off" />
            <div class="locarc-hint" id="locarc-renew-handle-warn"></div>
          </div>
          <div class="full locarc-eq-grid">
            <div>
              <label>Poignée - Marque</label>
              <input type="text" name="handle_brand" id="locarc-renew-handle-brand" value="${esc(c.handle_brand||'')}" readonly />
            </div>
            <div>
              <label>Poignée - Modèle</label>
              <input type="text" name="handle_model" id="locarc-renew-handle-model" value="${esc(c.handle_model||'')}" readonly />
            </div>
            <div>
              <label>Poignée - Taille</label>
              <input type="number" name="handle_size" id="locarc-renew-handle-size" value="${esc(c.handle_size||'')}" readonly />
            </div>
            <div>
              <label>Poignée - Latéralité</label>
              <input type="text" name="handle_handedness" id="locarc-renew-handle-handedness" value="${esc(c.handle_handedness||'')}" readonly />
            </div>
          </div>

          <div class="full">
            <label>Branches (ID) — optionnel pour Prêt</label>
            <input type="text" name="branches_identifier" id="locarc-renew-branches" value="${esc(c.branches_identifier||'')}" autocomplete="off" />
            <div class="locarc-hint" id="locarc-renew-branches-warn"></div>
          </div>
          <div class="full locarc-eq-grid">
            <div>
              <label>Branches - Marque</label>
              <input type="text" name="branches_brand" id="locarc-renew-branches-brand" value="${esc(c.branches_brand||'')}" readonly />
            </div>
            <div>
              <label>Branches - Modèle</label>
              <input type="text" name="branches_model" id="locarc-renew-branches-model" value="${esc(c.branches_model||'')}" readonly />
            </div>
            <div>
              <label>Branches - Taille</label>
              <input type="number" name="branches_size" id="locarc-renew-branches-size" value="${esc(c.branches_size||'')}" readonly />
            </div>
            <div>
              <label>Branches - Puissance</label>
              <input type="number" name="branches_power" id="locarc-renew-branches-power" value="${esc(c.branches_power||'')}" readonly />
            </div>
          </div>
        </form>
      `;
      const footer = `
        <button type="button" class="button" id="locarc-cancel">Annuler</button>
        <button type="button" class="button button-primary" id="locarc-confirm-renew">Renouveler</button>
      `;
      openModal('Renouveler contrat', body, footer);

      function setRenewEqEditable(isPret){
        const ro = !isPret;
        $('#locarc-renew-handle-brand,#locarc-renew-handle-model,#locarc-renew-handle-size,#locarc-renew-handle-handedness').prop('readonly', ro);
        $('#locarc-renew-branches-brand,#locarc-renew-branches-model,#locarc-renew-branches-size,#locarc-renew-branches-power').prop('readonly', ro);
      }
      function fillRenewHandle(h){
        $('#locarc-renew-handle-brand').val(h.brand||'');
        $('#locarc-renew-handle-model').val(h.model||'');
        $('#locarc-renew-handle-size').val(h.size||'');
        $('#locarc-renew-handle-handedness').val(h.handedness||'');
      }
      function fillRenewBranches(b){
        $('#locarc-renew-branches-brand').val(b.brand||'');
        $('#locarc-renew-branches-model').val(b.model||'');
        $('#locarc-renew-branches-size').val(b.size||'');
        $('#locarc-renew-branches-power').val(b.power||'');
      }
      function fetchRenew(kind, identifier){
        if(!identifier) return;
        api('locarc_get_by_identifier', {kind, identifier}, 'GET').done(x=>{
          if(!x.success) return;
          if(kind==='handles') fillRenewHandle(x.data);
          if(kind==='branches') fillRenewBranches(x.data);
        });
      }

      $('#locarc-renew-type').val(c.contract_type||'complet');
      $('#locarc-renew-custom').val(c.custom_price||'');
      initContractFinanceFields('renew', c);
      function toggleRenewCustom(){
        const t = ($('#locarc-renew-type').val()||'');
        if(t==='personnalise') $('#locarc-renew-custom-wrap').show();
        else { $('#locarc-renew-custom-wrap').hide(); $('#locarc-renew-custom').val(''); }
      }
      toggleRenewCustom();
      $('#locarc-renew-type').on('change', toggleRenewCustom);

      buildSuggest($('#locarc-renew-handle'), 'handles');
      buildSuggest($('#locarc-renew-branches'), 'branches');

      $('#locarc-renew-handle').on('locarc:selected', function(e, it){
        $('#locarc-renew-handle').val(it.value||'');
        fetchRenew('handles', it.value||'');
      });
      $('#locarc-renew-branches').on('locarc:selected', function(e, it){
        $('#locarc-renew-branches').val(it.value||'');
        fetchRenew('branches', it.value||'');
      });

      function applyRenewPret(){
        const t = ($('#locarc-renew-type').val()||'');
        setRenewEqEditable(t==='pret');
      }
      applyRenewPret();
      $('#locarc-renew-type').on('change', applyRenewPret);

      // initial autofill from identifiers if present
      fetchRenew('handles', ($('#locarc-renew-handle').val()||'').trim());
      fetchRenew('branches', ($('#locarc-renew-branches').val()||'').trim());


      function checkAssigned(kind, identifier, $warn, contractId){
        if(!identifier){ $warn.text(''); return; }
        api('locarc_get_by_identifier', {kind, identifier}, 'GET').done(x=>{
          if(!x.success){ $warn.text('⚠️ Inconnu dans la base'); return; }
          if(x.data._assigned_to && String(x.data._assigned_to.id||'') !== String(contractId)){
            $warn.text('⚠️ Déjà affecté à ' + (x.data._assigned_to.display_name || x.data._assigned_to.licence));
          } else $warn.text('');
        });
      }
      $('#locarc-renew-handle').on('change blur', ()=> checkAssigned('handles', $('#locarc-renew-handle').val().trim(), $('#locarc-renew-handle-warn'), id));
      $('#locarc-renew-branches').on('change blur', ()=> checkAssigned('branches', $('#locarc-renew-branches').val().trim(), $('#locarc-renew-branches-warn'), id));

      modal.off('click', '#locarc-cancel').on('click', '#locarc-cancel', closeModal);
      modal.off('click', '#locarc-confirm-renew').on('click', '#locarc-confirm-renew', function(e){
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const data = formToObject(modal.find('form'));
        data.id = id;
        if((data.contract_type||'')==='personnalise' && (!data.custom_price || data.custom_price==='')){
          alert('Montant requis pour un contrat personnalisé');
          return;
        }
        withBusy($btn, () => api('locarc_renew_contract', data)
        .done(resp=>{
          if(!resp.success){ alert('Erreur: ' + (resp.data?.message || resp.data || '')); return; }
          warnPdfIfNeeded(resp);
          closeModal();
          window.location.reload();
        })
        .fail(xhr=>{
          const msg = (xhr.responseJSON && (xhr.responseJSON.data || xhr.responseJSON.message))
            ? (xhr.responseJSON.data || xhr.responseJSON.message)
            : (xhr.responseText || ('HTTP ' + xhr.status));
          alert('Erreur AJAX: ' + msg);
        })
        );
      });
    });
  });

  // Show equipment modal
  $('.locarc-table').on('click', 'a.locarc-eq', function(e){
    e.preventDefault();
    const kind = $(this).data('kind');
    const identifier = $(this).data('identifier');
    if(!identifier || identifier==='-') return;
    api('locarc_get_by_identifier', {kind, identifier}, 'GET').done(r=>{
      if(!r.success){ alert('Introuvable'); return; }
      const d = r.data;
      let html = '<div class="locarc-form">';
      Object.keys(d).forEach(k=>{
        if(k.startsWith('_')) return;
        html += `<div><label>${k}</label><div><code>${esc(String(d[k]??''))}</code></div></div>`;
      });
      html += '</div>';
      if(d._assigned_to){
        html += `<p class="locarc-hint">⚠️ Déjà affecté à une licence: <strong>${esc(d._assigned_to.licence)}</strong></p>`;
      }
      openModal('Matériel: ' + identifier, html, '<button class="button" id="locarc-close">Fermer</button>');
      modal.off('click', '#locarc-close').on('click', '#locarc-close', closeModal);
    });
  });

  // Assignment edit
  $('.locarc-table').on('click', '.locarc-edit-assignment', function(){
    const id = $(this).closest('tr').data('id');
    api('locarc_get_item', {kind:'contracts', id}, 'GET').done(r=>{
      if(!r.success){ alert('Erreur'); return; }
      const c = r.data;
      const body = `
        <form class="locarc-form">
          <div class="full"><span class="locarc-pill">Contrat #${esc(c.contract_number||'')}</span></div>
          <div class="full">
            <label>Poignée (ID)</label>
            <input type="text" name="handle_identifier" id="locarc-handle" value="${esc(c.handle_identifier||'')}" placeholder="ex: SA-M-25D-7" autocomplete="off" />
            <div class="locarc-hint" id="locarc-handle-warn"></div>
          </div>
          <div>
            <label>Marque poignée</label>
            <input type="text" id="locarc-handle-brand" readonly value="${esc(c.handle_brand||'')}" />
          </div>
          <div>
            <label>Modèle poignée</label>
            <input type="text" id="locarc-handle-model" readonly value="${esc(c.handle_model||'')}" />
          </div>
          <div>
            <label>Taille poignée</label>
            <input type="number" id="locarc-handle-size" readonly value="${esc(c.handle_size||'')}" />
          </div>
          <div>
            <label>Latéralité</label>
            <input type="text" id="locarc-handle-handedness" readonly value="${esc(c.handle_handedness||'')}" />
          </div>
          <div class="full">
            <label>Branches (ID)</label>
            <input type="text" name="branches_identifier" id="locarc-branches" value="${esc(c.branches_identifier||'')}" placeholder="ex: EX-W-7024-1" autocomplete="off" />
            <div class="locarc-hint" id="locarc-branches-warn"></div>
          </div>
          <div>
            <label>Marque branches</label>
            <input type="text" id="locarc-branches-brand" readonly value="${esc(c.branches_brand||'')}" />
          </div>
          <div>
            <label>Modèle branches</label>
            <input type="text" id="locarc-branches-model" readonly value="${esc(c.branches_model||'')}" />
          </div>
          <div>
            <label>Taille branches</label>
            <input type="number" id="locarc-branches-size" readonly value="${esc(c.branches_size||'')}" />
          </div>
          <div>
            <label>Puissance</label>
            <input type="number" id="locarc-branches-power" readonly value="${esc(c.branches_power||'')}" />
          </div>
        </form>
      `;
      const footer = `
        <button type="button" class="button" id="locarc-cancel">Annuler</button>
        <button type="button" class="button button-primary" id="locarc-save-assign">Enregistrer</button>
      `;
      openModal('Modifier matériel loué', body, footer);
      $dialog.addClass('locarc-modal-dialog-tall locarc-modal-dialog-assign');
      buildSuggest($('#locarc-handle'), 'handles');
      buildSuggest($('#locarc-branches'), 'branches');

      function checkAssigned(kind, identifier, $warn){
        if(!identifier){ $warn.text(''); return; }
        api('locarc_get_by_identifier', {kind, identifier}, 'GET').done(x=>{
          if(!x.success){ $warn.text('⚠️ Inconnu dans la base'); return; }
          if(x.data._assigned_to && x.data._assigned_to.id != id){
            $warn.text('⚠️ Déjà affecté à ' + (x.data._assigned_to.display_name || x.data._assigned_to.licence));
          } else {
            $warn.text('');
          }
        });
      }
      function fillHandleFields(h){
        h = h || {};
        $('#locarc-handle-brand').val(h.brand || '');
        $('#locarc-handle-model').val(h.model || '');
        $('#locarc-handle-size').val(h.size || '');
        $('#locarc-handle-handedness').val(h.handedness || '');
      }
      function fillBranchesFields(b){
        b = b || {};
        $('#locarc-branches-brand').val(b.brand || '');
        $('#locarc-branches-model').val(b.model || '');
        $('#locarc-branches-size').val(b.size || '');
        $('#locarc-branches-power').val(b.power || '');
      }
      function fetchAssign(kind, identifier){
        if(!identifier){
          if(kind==='handles') fillHandleFields({});
          if(kind==='branches') fillBranchesFields({});
          return;
        }
        api('locarc_get_by_identifier', {kind, identifier}, 'GET').done(x=>{
          if(!x.success){
            if(kind==='handles') fillHandleFields({});
            if(kind==='branches') fillBranchesFields({});
            return;
          }
          if(kind==='handles') fillHandleFields(x.data);
          if(kind==='branches') fillBranchesFields(x.data);
        }).fail(()=>{
          if(kind==='handles') fillHandleFields({});
          if(kind==='branches') fillBranchesFields({});
        });
      }

      $('#locarc-handle').on('locarc:selected', function(e, it){
        $('#locarc-handle').val(it.value||'');
        checkAssigned('handles', $('#locarc-handle').val().trim(), $('#locarc-handle-warn'));
        fetchAssign('handles', $('#locarc-handle').val().trim());
      });
      $('#locarc-branches').on('locarc:selected', function(e, it){
        $('#locarc-branches').val(it.value||'');
        checkAssigned('branches', $('#locarc-branches').val().trim(), $('#locarc-branches-warn'));
        fetchAssign('branches', $('#locarc-branches').val().trim());
      });
      $('#locarc-handle').on('change blur input', ()=> {
        const v = $('#locarc-handle').val().trim();
        checkAssigned('handles', v, $('#locarc-handle-warn'));
        fetchAssign('handles', v);
      });
      $('#locarc-branches').on('change blur input', ()=> {
        const v = $('#locarc-branches').val().trim();
        checkAssigned('branches', v, $('#locarc-branches-warn'));
        fetchAssign('branches', v);
      });
      fetchAssign('handles', ($('#locarc-handle').val()||'').trim());
      fetchAssign('branches', ($('#locarc-branches').val()||'').trim());

      modal.off('click', '#locarc-cancel').on('click', '#locarc-cancel', closeModal);
      modal.off('click', '#locarc-save-assign').on('click', '#locarc-save-assign', function(e){
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const data = formToObject(modal.find('form'));
        data.id = id;
        // keep existing contract metadata
        data.licence = c.licence;
        data.contract_type = c.contract_type;
        data.start_date = c.start_date;
        data.end_date = c.end_date;
        data.is_paid = c.is_paid;
        withBusy($btn, () => api('locarc_save_contract', data)
          .done(resp=>{
            if(!resp.success){ alert('Erreur: ' + (resp.data?.message || resp.data || '')); return; }
            // Update the row in place (no reload) – rented table
            warnPdfIfNeeded(resp);
            const $tr = $('#locarc-rented-table tr[data-id="'+id+'"]');
            const handleHtml = data.handle_identifier ? `<a href="#" class="locarc-eq" data-kind="handles" data-identifier="${esc(data.handle_identifier)}">${esc(data.handle_identifier)}</a>` : '<a href="#" class="locarc-eq" data-kind="handles" data-identifier="">-</a>';
            const branchesHtml = data.branches_identifier ? `<a href="#" class="locarc-eq" data-kind="branches" data-identifier="${esc(data.branches_identifier)}">${esc(data.branches_identifier)}</a>` : '<a href="#" class="locarc-eq" data-kind="branches" data-identifier="">-</a>';
            $tr.find('td').eq(4).html(handleHtml);
            $tr.find('td').eq(5).html(branchesHtml);
            closeModal();
          })
          .fail(xhr=>{
            const msg = (xhr.responseJSON && (xhr.responseJSON.data || xhr.responseJSON.message))
              ? (xhr.responseJSON.data || xhr.responseJSON.message)
              : (xhr.responseText || ('HTTP ' + xhr.status));
            alert('Erreur AJAX: ' + msg);
          })
        );
      });
    });
  });

  // New contract
  $('#locarc-add-contract').on('click', function(){
    const body = `
      <form class="locarc-form">
        <div class="full">
          <label>Sélection archer</label>
          <input type="text" id="locarc-member" placeholder="Tape prénom, nom ou n° licence" autocomplete="off" required />
          <input type="hidden" name="licence" id="locarc-licence" required />
          <div class="locarc-hint">Commence à taper : proposition d'archers (prénom/nom/licence). La licence est enregistrée automatiquement.</div>
        </div>
        <div class="full" id="locarc-member-details" style="display:none;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label>Adresse</label>
              <input type="text" id="locarc-md-address" readonly />
            </div>
            <div>
              <label>Ville</label>
              <input type="text" id="locarc-md-city" readonly />
            </div>
            <div>
              <label>Code postal</label>
              <input type="text" id="locarc-md-postal" readonly />
            </div>
            <div>
              <label>Téléphone</label>
              <input type="text" id="locarc-md-phone" readonly />
            </div>
            <div class="full">
              <label>Email</label>
              <input type="text" id="locarc-md-email" readonly />
            </div>
          </div>
          <div class="locarc-hint" style="margin-top:6px;">Ces informations proviennent de l'onglet « Licenciés » (CSV importé).</div>
        </div>
        <div>
          <label>Type de contrat</label>
          <select name="contract_type" id="locarc-contract-type">${contractTypeOptionsHtml('complet')}</select>
        </div>
        <div id="locarc-custom-price-wrap" style="display:none;">
          <label>Montant personnalisé (€)</label>
          <input type="number" step="0.01" min="0" name="custom_price" id="locarc-custom-price" placeholder="ex: 150" />
          <div class="locarc-hint">Obligatoire si le type est « Personnalisé ».</div>
        </div>
        <div id="locarc-paid-wrap">
          <label>Payé ?</label>
          <select name="is_paid" id="locarc-is-paid">
            <option value="0">Non Payé</option>
            <option value="1">Payé</option>
          </select>
          <span id="locarc-paid-dash" style="display:none;line-height:30px;">-</span>
        </div>
        <div>
          <label>Début</label>
          <input type="date" name="start_date" value="${new Date().toISOString().slice(0,10)}" />
        </div>
        <div>
          <label>Fin</label>
          <input type="date" name="end_date" />
          <div class="locarc-hint">Si vide: +1 an.</div>
        </div>
        ${contractFinanceFieldsHtml('new')}
        <div class="full">
          <label>Poignée (ID) — optionnel pour Prêt</label>
          <input type="text" name="handle_identifier" id="locarc-handle2" autocomplete="off" />
          <div class="locarc-hint" id="locarc-handle2-warn"></div>
        </div>
        <div class="full locarc-eq-grid">
          <div>
            <label>Poignée - Marque</label>
            <input type="text" name="handle_brand" id="locarc-handle-brand" readonly />
          </div>
          <div>
            <label>Poignée - Modèle</label>
            <input type="text" name="handle_model" id="locarc-handle-model" readonly />
          </div>
          <div>
            <label>Poignée - Taille</label>
            <input type="number" name="handle_size" id="locarc-handle-size" readonly />
          </div>
          <div>
            <label>Poignée - Latéralité</label>
            <input type="text" name="handle_handedness" id="locarc-handle-handedness" readonly />
          </div>
        </div>

        <div class="full">
          <label>Branches (ID) — optionnel pour Prêt</label>
          <input type="text" name="branches_identifier" id="locarc-branches2" autocomplete="off" />
          <div class="locarc-hint" id="locarc-branches2-warn"></div>
        </div>
        <div class="full locarc-eq-grid">
          <div>
            <label>Branches - Marque</label>
            <input type="text" name="branches_brand" id="locarc-branches-brand" readonly />
          </div>
          <div>
            <label>Branches - Modèle</label>
            <input type="text" name="branches_model" id="locarc-branches-model" readonly />
          </div>
          <div>
            <label>Branches - Taille</label>
            <input type="number" name="branches_size" id="locarc-branches-size" readonly />
          </div>
          <div>
            <label>Branches - Puissance</label>
            <input type="number" name="branches_power" id="locarc-branches-power" readonly />
          </div>
        </div>
      </form>
    `;
    const footer = `
      <button type="button" class="button" id="locarc-cancel">Annuler</button>
      <button type="button" class="button button-primary" id="locarc-create">Créer</button>
    `;
    openModal('Nouveau contrat', body, footer);

    // Equipment helpers
    function setEqEditable(isPret){
      const ro = !isPret;
      $('#locarc-handle-brand,#locarc-handle-model,#locarc-handle-size,#locarc-handle-handedness').prop('readonly', ro);
      $('#locarc-branches-brand,#locarc-branches-model,#locarc-branches-size,#locarc-branches-power').prop('readonly', ro);
    }
    function fillHandleFields(h){
      $('#locarc-handle-brand').val(h.brand||'');
      $('#locarc-handle-model').val(h.model||'');
      $('#locarc-handle-size').val(h.size||'');
      $('#locarc-handle-handedness').val(h.handedness||'');
    }
    function fillBranchesFields(b){
      $('#locarc-branches-brand').val(b.brand||'');
      $('#locarc-branches-model').val(b.model||'');
      $('#locarc-branches-size').val(b.size||'');
      $('#locarc-branches-power').val(b.power||'');
    }
    function fetchAndFill(kind, identifier){
      if(!identifier) return;
      api('locarc_get_by_identifier', {kind, identifier}, 'GET').done(x=>{
        if(!x.success) return;
        if(kind==='handles') fillHandleFields(x.data);
        if(kind==='branches') fillBranchesFields(x.data);
      });
    }

    function checkAssigned(kind, identifier, $warn, contractId){
      if(!identifier){ $warn.text(''); return; }
      api('locarc_get_by_identifier', {kind, identifier}, 'GET').done(x=>{
        if(!x.success){ $warn.text('⚠️ Inconnu dans la base'); return; }
        if(x.data._assigned_to && String(x.data._assigned_to.id||'') !== String(contractId||0)){
          const who = x.data._assigned_to.display_name || x.data._assigned_to.licence || '';
          $warn.text('⚠️ Déjà affecté à ' + who);
        } else {
          $warn.text('');
        }
      });
    }

    buildSuggest($('#locarc-member'), 'members');
    $("#locarc-member").on("locarc:selected", function(e, item){
      $("#locarc-member").val(item.label || item.value || "");
      $("#locarc-licence").val(item.licence || item.value || "");

      function setDetails(d){
        $('#locarc-md-address').val(d.address || '');
        $('#locarc-md-postal').val(d.postal_code || '');
        $('#locarc-md-city').val(d.city || '');
        $('#locarc-md-phone').val(d.phone || '');
        $('#locarc-md-email').val(d.email || '');
        $('#locarc-member-details').show();
      }

      // Prefer data coming from CSV (locarc_members) when available in the suggest payload
      const hasCsv = (item.address || item.postal_code || item.city || item.phone || item.email);
      if(hasCsv){
        setDetails(item);
      } else {
        // Fallback: fetch member record by licence (may return WP user email at least)
        const lic = (item.licence || item.value || '').trim();
        if(!lic){ $('#locarc-member-details').hide(); return; }
        api('locarc_get_member_by_licence', {licence: lic}, 'GET').done(r=>{
          if(r && r.success) setDetails(r.data||{});
          else $('#locarc-member-details').hide();
        }).fail(()=> $('#locarc-member-details').hide());
      }
    });
    // Identifier suggestions + auto-fill characteristics
    buildSuggest($('#locarc-handle2'), 'handles');
    buildSuggest($('#locarc-branches2'), 'branches');

    $('#locarc-handle2').on('locarc:selected', function(e, it){
      $('#locarc-handle2').val(it.value||'');
      fetchAndFill('handles', it.value||'');
    }).on('change blur', function(){
      const idv = $('#locarc-handle2').val().trim();
      checkAssigned('handles', idv, $('#locarc-handle2-warn'), 0);
      fetchAndFill('handles', idv);
    });

    $('#locarc-branches2').on('locarc:selected', function(e, it){
      $('#locarc-branches2').val(it.value||'');
      fetchAndFill('branches', it.value||'');
    }).on('change blur', function(){
      const idv = $('#locarc-branches2').val().trim();
      checkAssigned('branches', idv, $('#locarc-branches2-warn'), 0);
      fetchAndFill('branches', idv);
    });

    // Pret => allow manual editing if no identifier
    function applyPretMode(){
      const t = ($('#locarc-contract-type').val()||'');
      const isPret = (t === 'pret');
      setEqEditable(isPret);
      if(isPret){
        $('#locarc-is-paid').val('0').prop('disabled', true).hide();
        $('#locarc-paid-dash').show();
      } else {
        $('#locarc-is-paid').prop('disabled', false).show();
        $('#locarc-paid-dash').hide();
      }
      if(!isPret){
        // keep readonly; fields will be auto-filled when identifiers exist
      }
    }
    applyPretMode();
    $('#locarc-contract-type').on('change', applyPretMode);
    initContractFinanceFields('new', {});
    function toggleCustomPrice(){
      const t = ($('#locarc-contract-type').val()||'');
      if(t === 'personnalise') $('#locarc-custom-price-wrap').show();
      else { $('#locarc-custom-price-wrap').hide(); $('#locarc-custom-price').val(''); }
    }
    toggleCustomPrice();
    $('#locarc-contract-type').on('change', toggleCustomPrice);

    // If identifier is manually cleared, clear autofilled fields (manual input only in prêt mode)
    $('#locarc-handle2').on('input', function(){
      if(!$(this).val().trim()) fillHandleFields({});
    });
    $('#locarc-branches2').on('input', function(){
      if(!$(this).val().trim()) fillBranchesFields({});
    });

    modal.off('click', '#locarc-cancel').on('click', '#locarc-cancel', closeModal);
    modal.off('click', '#locarc-create').on('click', '#locarc-create', function(e){
      e.preventDefault();
      e.stopPropagation();
      const $btn = $(this);
      const data = formToObject(modal.find('form'));
      if((data.contract_type||'')==='personnalise' && (!data.custom_price || data.custom_price==='')){
        alert('Montant requis pour un contrat personnalisé');
        return;
      }
      if(!data.end_date){
        const start = data.start_date || new Date().toISOString().slice(0,10);
        const d = new Date(start);
        d.setFullYear(d.getFullYear()+1);
        data.end_date = d.toISOString().slice(0,10);
      }
      withBusy($btn, () => api('locarc_save_contract', data)
        .done(r=>{
          if(!r.success){ alert('Erreur: ' + (r.data?.message || r.data || '')); return; }
          warnPdfIfNeeded(r);
          // Redirect to contracts tab and highlight the new row
          const base = (LOCARC && LOCARC.contracts_url) ? LOCARC.contracts_url : window.location.href;
          const sep = base.indexOf('?')===-1 ? '?' : '&';
          window.location.href = base + sep + 'new_id=' + encodeURIComponent(String(r.data.id||''));
        })
        .fail(xhr=>{
          const msg = (xhr.responseJSON && (xhr.responseJSON.data || xhr.responseJSON.message))
            ? (xhr.responseJSON.data || xhr.responseJSON.message)
            : (xhr.responseText || ('HTTP ' + xhr.status));
          alert('Erreur AJAX: ' + msg);
        })
      );
    });
  });

  function formToObject($form){
    const o = {};
    $form.serializeArray().forEach(it=> o[it.name]=it.value);
    return o;
  }
  function esc(s){
    return (s||'').replace(/[&<>"']/g, (c)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  // --- Filtering (client-side) ---
  function applyTableFilters(tableId){
    const $table = $('#'+tableId);
    if(!$table.length) return;
    const qRaw = ($('.locarc-filter-input[data-table="'+tableId+'"]').val()||'').toString().trim().toLowerCase();
    const q = qRaw;
    const qNoDash = qRaw.replace(/-/g,'');
    const selects = $('.locarc-filter-select[data-table="'+tableId+'"]');
    const activeSelects = [];
    selects.each(function(){
      const col = parseInt($(this).data('col'),10);
      if(isNaN(col)) return;
      const rawVal = $(this).val();
      if(rawVal === null || rawVal === undefined) return;
      const vals = Array.isArray(rawVal) ? rawVal : [rawVal];
      const cleaned = vals.map(v=> (v||'').toString().trim()).filter(v=>v!=='');
      if(cleaned.length===0) return;
      activeSelects.push({col, vals: cleaned.map(v=>v.toLowerCase())});
    });

    $table.find('tbody tr').each(function(){
      const $tr=$(this);
      const rowText=$tr.text().toLowerCase();
      const rowTextNoDash = rowText.replace(/-/g,'');
      let ok = true;
      if(q){
        if(rowText.indexOf(q) === -1 && rowTextNoDash.indexOf(qNoDash) === -1) ok=false;
      }
      if(ok){
        for(const f of activeSelects){
          const cell = $tr.children('td').eq(f.col);
          const cellText = (cell.text()||'').toString().trim().toLowerCase();
          if(!f.vals.includes(cellText)) { ok=false; break; }
        }
      }
      $tr.toggle(ok);
    });

    // Re-number the index column (#) after filtering, so numbers remain consistent.
    const hasIndexCol = ($table.find('thead th').first().text() || '').toString().trim() === '#';
    if(hasIndexCol){
      $table.find('tbody tr:visible').each(function(idx){
        $(this).children('td').eq(0).text(idx+1);
      });
    }

    updateInventoryCounters(tableId);
  }

  function updateInventoryCounters(tableId){
    const $box = $('.locarc-counters[data-table="'+tableId+'"]');
    if(!$box.length) return;
    const $table = $('#'+tableId);
    if(!$table.length) return;

    // Columns (0-based):
    // Branches: dispo=6
    // Handles:  handedness=5, dispo=7
    const isBranches = tableId === 'locarc-branches-table';
    const isHandles = tableId === 'locarc-handles-table';

    let total = 0;
    let dispo = 0;
    let repair = 0;
    let obsolete = 0;
    let left = 0;
    let right = 0;

    $table.find('tbody tr:visible').each(function(){
      total++;
      const $tds = $(this).children('td');
      const status = (isBranches ? $tds.eq(6).text() : $tds.eq(7).text()).toString().trim();
      if(status === 'Oui'){
        dispo++;
        if(isHandles){
          const h = $tds.eq(5).text().toString().trim();
          if(h === 'Gauche') left++; else right++;
        }
      } else if(status === 'En Réparation'){
        repair++;
      } else if(status === 'Obsolète'){
        obsolete++;
      }
    });

    // Update DOM (only the counters relevant for the current table are present)
    $box.find('[data-count="total"]').text(total);
    if(isBranches){
      $box.find('[data-count="dispo"]').text(dispo);
      $box.find('[data-count="repair"]').text(repair);
      $box.find('[data-count="obsolete"]').text(obsolete);
    }
    if(isHandles){
      $box.find('[data-count="left"]').text(left);
      $box.find('[data-count="right"]').text(right);
      $box.find('[data-count="repair"]').text(repair);
    }
  }

  $(document).on('input', '.locarc-filter-input', function(){
    applyTableFilters($(this).data('table'));
  });
  $(document).on('change', '.locarc-filter-select', function(){
    applyTableFilters($(this).data('table'));
  });

  // On mobile, keep <details> filter boxes open while interacting with inner fields.
  $(document).on('mousedown touchstart click', '.locarc-filters .locarc-toolbar-right, .locarc-filters .locarc-toolbar-right *', function(e){
    e.stopPropagation();
  });

  // --- Sorting (client-side) ---
  function parseSortValue(text, mode){
    const t = (text||'').toString().trim();
    if(mode === 'num' || mode === 'number'){
      // price/year/power/size: accept comma decimals
      const v = t.replace(/\s/g,'').replace(',','.');
      const n = parseFloat(v);
      return isNaN(n) ? -Infinity : n;
    }
    if(mode === 'date'){
      // expects YYYY-MM-DD (native in DB) but also accepts DD/MM/YYYY for safety
      if(!t) return -Infinity;
      let dt = null;
      if(/^\d{4}-\d{2}-\d{2}$/.test(t)){
        dt = new Date(t + 'T00:00:00');
      } else if(/^\d{2}\/\d{2}\/\d{4}$/.test(t)){
        const [dd,mm,yyyy] = t.split('/');
        dt = new Date(`${yyyy}-${mm}-${dd}T00:00:00`);
      } else {
        const tmp = new Date(t);
        dt = isNaN(tmp.getTime()) ? null : tmp;
      }
      return dt ? dt.getTime() : -Infinity;
    }
    return t.toLowerCase();
  }

  // Highlight newly created contract row (after redirect with new_id)
  const $newRow = $('tr[data-new="1"]');
  if($newRow.length){
    try{ $newRow[0].scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){}
    $newRow.addClass('locarc-row-new');
    setTimeout(()=> $newRow.removeClass('locarc-row-new'), 3500);
  }

  function makeSortable(tableId){
    const $table = $('#'+tableId);
    if(!$table.length) return;

    // Only some tables have an index column (#) that should be re-numbered after sort.
    const hasIndexCol = ($table.find('thead th').first().text() || '').toString().trim() === '#';

    const $ths = $table.find('thead th');
    $ths.each(function(i){
      const mode = $(this).data('sort') || 'text';
      if(mode === 'none') return;
      // add indicator
      if(!$(this).find('.locarc-sort-indicator').length){
        $(this).append('<span class="locarc-sort-indicator">⇅</span>');
      }
      $(this).data('colIndex', i);
    });

    $table.on('click', 'thead th', function(){
      const mode = $(this).data('sort') || 'text';
      if(mode === 'none') return;
      const col = $(this).data('colIndex');
      const currentDir = $(this).data('dir');
      // First click should be ASC.
      const dir = (currentDir === 'asc') ? 'desc' : 'asc';

      // reset others
      $table.find('thead th').not(this).removeData('dir').find('.locarc-sort-indicator').text('⇅');

      $(this).data('dir', dir);
      $(this).find('.locarc-sort-indicator').text(dir==='asc' ? '↑' : '↓');

      const rows = $table.find('tbody tr').get();
      rows.sort(function(a,b){
        const A = parseSortValue($(a).children('td').eq(col).text(), mode);
        const B = parseSortValue($(b).children('td').eq(col).text(), mode);
        if(A < B) return dir==='asc' ? -1 : 1;
        if(A > B) return dir==='asc' ? 1 : -1;
        return 0;
      });
      $.each(rows, function(_, r){ $table.children('tbody').append(r); });

      if(hasIndexCol){
        // re-number column # (first column)
        $table.find('tbody tr:visible').each(function(idx){
          $(this).children('td').eq(0).text(idx+1);
        });
      }

      // re-apply filters because sort might have moved rows (visibility stays)
      applyTableFilters(tableId);
    });
  }

  makeSortable('locarc-branches-table');
  makeSortable('locarc-handles-table');
  makeSortable('locarc-contracts-table');
  makeSortable('locarc-rented-table');
  makeSortable('locarc-members-table');
  applyTableFilters('locarc-branches-table');
  applyTableFilters('locarc-handles-table');
  applyTableFilters('locarc-rented-table');
  applyTableFilters('locarc-members-table');

  // Mobile-friendly filters: collapse by default on small screens, keep open on desktop.
  // Important: on phones, opening the virtual keyboard triggers resize events.
  // Do not force-close the <details> on every resize, otherwise the filter box
  // collapses as soon as the user taps in the free-text field.
  function syncFiltersOpen(initial){
    const isDesktop = window.matchMedia('(min-width: 782px)').matches;
    $('.locarc-filters').each(function(){
      // Only auto-manage for inventory tabs (branches/poignées) where it takes too much space on mobile.
      const $details = $(this);
      const hasInventoryTable = $details.closest('.locarc-toolbar').parent().find('#locarc-branches-table,#locarc-handles-table').length > 0;
      if(!hasInventoryTable) return;
      if(isDesktop){
        this.open = true;
        $details.data('locarc-mobile-init', true);
      } else if(initial && !$details.data('locarc-mobile-init')) {
        this.open = false;
        $details.data('locarc-mobile-init', true);
      }
    });
  }
  syncFiltersOpen(true);
  $(window).on('resize', function(){ syncFiltersOpen(false); });

  // ── Tab group pills ──────────────────────────────────────────────────────
  $(document).on('click', '.locarc-tab-group-pill', function(){
    const $pill  = $(this);
    const group  = $pill.data('group');
    $('.locarc-tab-group-pill').removeClass('is-active');
    $pill.addClass('is-active');
    $('.locarc-tabs--group-gestion, .locarc-tabs--group-admin').addClass('locarc-tabs--hidden');
    $('.locarc-tabs--group-' + group).removeClass('locarc-tabs--hidden');
  });

  // ── Action dropdown ──────────────────────────────────────────────────────
  // Open/close toggle
  $(document).on('click', '.locarc-dropdown-toggle', function(e){
    e.stopPropagation();
    const $dd = $(this).closest('.locarc-dropdown');
    const isOpen = $dd.hasClass('is-open');
    // close all others first
    $('.locarc-dropdown.is-open').removeClass('is-open');
    if (!isOpen) $dd.addClass('is-open');
  });

  // Close when clicking outside
  $(document).on('click', function(){
    $('.locarc-dropdown.is-open').removeClass('is-open');
  });

  // Prevent clicks inside the menu from closing it unintentionally
  $(document).on('click', '.locarc-dropdown-menu', function(e){
    e.stopPropagation();
  });

  // Close dropdown after an item action fires (via delegation on the table)
  $('.locarc-table').on('click', '.locarc-dropdown-item', function(){
    $(this).closest('.locarc-dropdown').removeClass('is-open');
  });

});
