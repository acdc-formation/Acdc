(function(window, document){
  if (!window || !document) { return; }

  const kernel = window.AcdcUiKernel || {};

  kernel.qsa = function(selector, scope){
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  };

  kernel.hideElements = function(elements, callback){
    (elements || []).forEach(function(el){
      if (!el || el.nodeType !== 1) { return; }
      el.hidden = true;
      el.style.display = 'none';
      el.setAttribute('aria-hidden', 'true');
      if (typeof callback === 'function') { callback(el); }
    });
  };

  kernel.positionFloatingElement = function(element, trigger, options){
    if (!element || !trigger || !trigger.getBoundingClientRect) { return; }

    const rect = trigger.getBoundingClientRect();
    const opts = options || {};
    const gap = typeof opts.gap === 'number' ? opts.gap : 8;
    const minLeft = typeof opts.minLeft === 'number' ? opts.minLeft : 12;
    const minTop = typeof opts.minTop === 'number' ? opts.minTop : 12;
    const align = opts.align || 'right';

    element.hidden = false;
    element.style.display = opts.display || 'block';

    const width = Math.max(element.offsetWidth || opts.minWidth || 220, opts.minWidth || 220);
    const height = element.offsetHeight || 0;

    let left = align === 'left' ? rect.left : (rect.right - width);
    let top = rect.bottom + gap;

    if (left < minLeft) { left = minLeft; }

    if ((left + width) > (window.innerWidth - minLeft)) {
      left = Math.max(minLeft, window.innerWidth - width - minLeft);
    }

    if ((top + height) > (window.innerHeight - minTop)) {
      const above = rect.top - height - gap;
      top = above >= minTop ? above : Math.max(minTop, window.innerHeight - height - minTop);
    }

    element.style.left = Math.round(left) + 'px';
    element.style.top = Math.round(top) + 'px';
    element.setAttribute('aria-hidden', 'false');
  };

  kernel.isActuallyVisible = function(el){
    if (!el || el.nodeType !== 1) { return false; }
    if (el.hidden || el.getAttribute('aria-hidden') === 'true') { return false; }
    if (el.closest && el.closest('[hidden]')) { return false; }

    const style = window.getComputedStyle ? window.getComputedStyle(el) : null;
    if (style && (style.display === 'none' || style.visibility === 'hidden')) { return false; }

    return true;
  };

  kernel.visibleCount = function(selector){
    return kernel.qsa(selector).filter(function(el){
      return kernel.isActuallyVisible(el);
    }).length;
  };

  kernel.sanitizeKeyPart = function(value, fallback){
    let text = String(value || '').trim().toLowerCase();

    if (text.normalize) {
      text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    text = text.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return (text || fallback || 'x').slice(0, 80);
  };

  kernel.getPageKey = function(){
    /* ACDC 3.20.65 — Stabilisation de la clé de page.
     * On exclut volontairement window.location.search du calcul : auparavant,
     * un paramètre comme ?action=view&item_id=5 créait une clé de page
     * différente de la liste, ce qui multipliait les entrées sauvegardées
     * (ex. tab-companies, tab-companies-action-view-item-id-2,
     * tab-companies-action-view-item-id-4...). La largeur réglée sur la liste
     * était ensuite écrasée ou perdue selon le contexte de retour.
     * On garde uniquement le chemin et le titre de page, qui restent stables.
     */
    const title = document.querySelector('h1, .acdc-page-title');
    const titleText = title ? title.textContent.trim() : '';
    const path = (window.location && window.location.pathname) ? window.location.pathname : '';

    return kernel.sanitizeKeyPart(path + '|' + titleText, 'page');
  };

  kernel.getTableHeaderSignature = function(table){
    /* ACDC 3.20.65 — Réduction de la signature à 3 en-têtes au lieu de 14.
     * Auparavant, l'ajout ou le retrait d'une colonne optionnelle (filtre actif,
     * permission spécifique, mode édition vs lecture) changeait la signature
     * complète et donc la clé du tableau, ce qui rendait les largeurs
     * sauvegardées invisibles depuis le contexte modifié.
     * Trois en-têtes suffisent à distinguer deux tableaux dans une même page,
     * tout en restant tolérant aux variations mineures de structure.
     */
    const headers = kernel.qsa('th', table).slice(0, 3).map(function(th){
      return kernel.sanitizeKeyPart((th.textContent || '').replace(/\s+/g, ' '), 'col');
    }).filter(Boolean);

    return headers.length ? headers.join('-').slice(0, 80) : 'sans-entetes';
  };

  kernel.getTableContextKey = function(table){
    const zone = table.closest('.acdc-card, .acdc-panel, .acdc-section, section, form, main, article');
    if (!zone) { return ''; }

    const heading = zone.querySelector('h2, h3, legend, .acdc-section-title, .acdc-card-title');
    return heading ? kernel.sanitizeKeyPart(heading.textContent, 'zone') : '';
  };

  kernel.getStableTableIndex = function(table){
    const allTables = kernel.qsa('table, .acdc-table', document).filter(function(item){
      return !!kernel.qsa('th', item).length;
    });
    const index = allTables.indexOf(table);
    return index >= 0 ? index : 0;
  };

  /* ACDC 3.20.65 — Détection automatique de la classe métier d'un tableau.
   * Les tables métier du plugin suivent toutes l'un des deux patterns :
   *   - acdc-{nom}-table  (ex. acdc-companies-table, acdc-learners-table)
   *   - acdc-table-{nom}  (ex. acdc-table-prospects, acdc-table-needs-documents)
   * Quand l'un de ces patterns est détecté, on l'utilise comme source de clé
   * prioritaire, ce qui rend la clé totalement indépendante de l'URL, du titre
   * de page, de la signature d'en-têtes et de l'index. C'est la garantie la
   * plus forte que les largeurs sauvegardées seront retrouvées partout.
   */
  kernel.getBusinessTableClass = function(table){
    if (!table || !table.classList) { return ''; }
    const classes = Array.prototype.slice.call(table.classList);
    for (let i = 0; i < classes.length; i += 1) {
      const cls = classes[i];
      if (cls === 'acdc-table') { continue; }
      /* Pattern 1 : acdc-{nom}-table */
      if (/^acdc-[a-z0-9-]+-table$/.test(cls)) { return cls; }
      /* Pattern 2 : acdc-table-{nom} */
      if (/^acdc-table-[a-z0-9-]+$/.test(cls)) { return cls; }
    }
    return '';
  };

  kernel.getSettings = function(){
    return window.AcdcUiKernelSettings || {};
  };

  kernel.getServerColumnWidth = function(tableKey, columnIndex){
    const settings = kernel.getSettings();
    const widths = settings.columnWidths || {};
    const tableWidths = widths[tableKey] || null;
    if (!tableWidths) { return null; }

    const raw = tableWidths[String(columnIndex)] || tableWidths[columnIndex];
    const parsed = parseInt(raw, 10);
    return parsed ? parsed : null;
  };

  kernel.getSavedColumnWidth = function(tableKeys, columnIndex){
    const keys = Array.isArray(tableKeys) ? tableKeys : [tableKeys];

    for (let i = 0; i < keys.length; i += 1) {
      const serverWidth = kernel.getServerColumnWidth(keys[i], columnIndex);
      if (serverWidth) { return serverWidth; }
    }

    for (let i = 0; i < keys.length; i += 1) {
      try {
        const localWidth = parseInt(localStorage.getItem('acdc_col_width_' + keys[i] + '_' + columnIndex), 10);
        if (localWidth) { return localWidth; }
      } catch (err) {
        return null;
      }
    }

    return null;
  };

  kernel.saveLocalColumnWidths = function(tableKeys, widths){
    const keys = Array.isArray(tableKeys) ? tableKeys : [tableKeys];
    const canonicalKey = keys[0];

    if (!canonicalKey || !widths || !widths.length) { return; }

    try {
      widths.forEach(function(width, index){
        const finalWidth = kernel.normalizeColumnWidth(width);
        if (finalWidth) {
          localStorage.setItem('acdc_col_width_' + canonicalKey + '_' + index, finalWidth);
        }
      });
    } catch (err) {
      // Le stockage local peut être indisponible selon le navigateur.
    }
  };

  /* ACDC 3.20.67 — Logs de diagnostic activables.
   * Pour activer : window.AcdcUiKernelDebug = true; en console.
   * Pour désactiver : window.AcdcUiKernelDebug = false;
   * Trace chaque sauvegarde et chaque application de largeurs avec son
   * origine, ses valeurs et la pile d'appel. Aucune sortie en mode normal.
   */
  kernel.debugLog = function(label, payload){
    if (!window.AcdcUiKernelDebug) { return; }
    try {
      console.log('[ACDC] ' + label, payload);
    } catch (err) {
      /* Silencieux. */
    }
  };

  kernel.saveServerColumnWidths = function(tableKey, widths){
    const settings = kernel.getSettings();
    if (!settings || !settings.ajaxUrl || !settings.nonce || !settings.canSaveGlobal) {
      return;
    }

    if (!tableKey || !widths || !widths.length) { return; }

    const widthMap = {};
    widths.forEach(function(width, index){
      const finalWidth = kernel.normalizeColumnWidth(width);
      if (finalWidth) {
        widthMap[String(index)] = finalWidth;
      }
    });

    if (!Object.keys(widthMap).length) { return; }

    /* ACDC 3.20.67 — Log de diagnostic. Capture la pile d'appel pour tracer
     * la fonction qui a déclenché cette sauvegarde (drag manuel ou autre).
     */
    if (window.AcdcUiKernelDebug) {
      const stack = (new Error()).stack || '';
      kernel.debugLog('saveServer', { tableKey: tableKey, widthMap: widthMap, callerLine: stack.split('\n').slice(1, 4).join(' | ') });
    }

    const body = new URLSearchParams();
    body.set('action', 'acdc_save_global_column_widths');
    body.set('nonce', settings.nonce);
    body.set('table_key', tableKey);
    body.set('widths', JSON.stringify(widthMap));

    window.fetch(settings.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString()
    }).then(function(response){
      return response.json ? response.json() : null;
    }).then(function(payload){
      if (!payload || !payload.success) { return; }
      settings.columnWidths = settings.columnWidths || {};
      settings.columnWidths[tableKey] = Object.assign({}, settings.columnWidths[tableKey] || {}, widthMap);
    }).catch(function(){
      // La sauvegarde locale reste disponible si la sauvegarde serveur échoue.
    });
  };


  kernel.normalizeTableTextAlignment = function(scope){
    const root = scope || document;
    const cells = kernel.qsa([
      ' .acdc-portal-shell table th',
      ' .acdc-portal-shell table td',
      ' .acdc-page table th',
      ' .acdc-page table td',
      ' .acdc-panel table th',
      ' .acdc-panel table td',
      ' .acdc-table-wrap table th',
      ' .acdc-table-wrap table td',
      ' table.acdc-table th',
      ' table.acdc-table td',
      ' .acdc-admin-shell table th',
      ' .acdc-admin-shell table td'
    ].join(','), root);

    cells.forEach(function(cell){
      if (!cell || cell.nodeType !== 1) { return; }
      cell.style.setProperty('text-align', 'left', 'important');
    });

    kernel.qsa('.acdc-actions-cell-icons, .acdc-table td.acdc-actions-cell-icons, .acdc-companies-actions-inline, .acdc-groups-actions-inline', root).forEach(function(el){
      if (!el || el.nodeType !== 1) { return; }
      el.style.setProperty('text-align', 'left', 'important');
      el.style.setProperty('justify-content', 'flex-start', 'important');
    });
  };

  kernel.syncBodyLock = function(selector, className){
    const count = kernel.visibleCount(selector || '.acdc-modal-shell, .acdc-modal');
    const lockClass = className || 'acdc-modal-open';

    document.body.classList.toggle(lockClass, count > 0);
    document.documentElement.classList.toggle(lockClass, count > 0);

    if (!count) {
      document.body.style.removeProperty('overflow');
      document.documentElement.style.removeProperty('overflow');
    }
  };

  kernel.initRepeatableSections = function(scope){
    kernel.qsa('[data-acdc-repeatable-sections]', scope).forEach(function(container){
      if (!container || container._acdcRepeatableBound) { return; }

      container._acdcRepeatableBound = true;

      const prefix = container.getAttribute('data-acdc-input-prefix') || 'items';
      const minItems = parseInt(container.getAttribute('data-acdc-min-items') || '0', 10);
      const addButton = document.querySelector('[data-acdc-repeatable-add="' + container.id + '"]');

      function renderRequired(label, required){
        return required ? (label + ' <span class="acdc-required">*</span>') : label;
      }

      function reindex(){
        kernel.qsa('[data-acdc-repeatable-card]', container).forEach(function(card, index){
          const badge = card.querySelector('.acdc-contract-section-head span');
          if (badge) { badge.textContent = '#' + (index + 1) + ' Section'; }

          kernel.qsa('[data-acdc-field-key]', card).forEach(function(field){
            const key = field.getAttribute('data-acdc-field-key') || '';
            if (!key) { return; }
            field.setAttribute('name', prefix + '[' + index + '][' + key + ']');
          });
        });
      }

      function buildCard(index){
        const titleLabel = container.getAttribute('data-acdc-title-label') || 'Titre de la section';
        const contentLabel = container.getAttribute('data-acdc-content-label') || 'Contenu de la section';
        const titleRequired = container.getAttribute('data-acdc-title-required') === '1';
        const contentRequired = container.getAttribute('data-acdc-content-required') === '1';

        const card = document.createElement('div');
        card.className = 'acdc-contract-section-card';
        card.setAttribute('data-acdc-repeatable-card', '1');

        card.innerHTML = '' +
          '<div class="acdc-contract-section-head">' +
            '<span>#' + (index + 1) + ' Section</span>' +
            '<button type="button" class="acdc-contract-remove-section" data-acdc-repeatable-remove="1">Supprimer</button>' +
          '</div>' +
          '<div class="acdc-contract-section-body">' +
            '<div class="acdc-contract-grid">' +
              '<div class="acdc-contract-label">' + renderRequired(titleLabel, titleRequired) + '</div>' +
              '<div><input type="text" data-acdc-field-key="title"' + (titleRequired ? ' required' : '') + '></div>' +
            '</div>' +
            '<div class="acdc-contract-grid">' +
              '<div class="acdc-contract-label">' + renderRequired(contentLabel, contentRequired) + '</div>' +
              '<div><textarea data-acdc-field-key="content" rows="4"' + (contentRequired ? ' required' : '') + '></textarea></div>' +
            '</div>' +
          '</div>';

        return card;
      }

      container.addEventListener('click', function(event){
        const btn = event.target.closest('[data-acdc-repeatable-remove]');
        if (!btn) { return; }

        event.preventDefault();

        const cards = kernel.qsa('[data-acdc-repeatable-card]', container);
        if (cards.length <= minItems) { return; }

        const card = btn.closest('[data-acdc-repeatable-card]');
        if (card) {
          card.remove();
          reindex();
        }
      });

      if (addButton) {
        addButton.addEventListener('click', function(event){
          event.preventDefault();

          const index = kernel.qsa('[data-acdc-repeatable-card]', container).length;
          container.appendChild(buildCard(index));
          reindex();
        });
      }

      reindex();
    });
  };

  kernel.getLegacyTableKey = function(table, tableIndex){
    const wrapper = table.closest('[data-acdc-table-key]');
    if (wrapper) {
      return wrapper.getAttribute('data-acdc-table-key');
    }

    const id = table.getAttribute('id');
    if (id) {
      return id;
    }

    const title = document.querySelector('h1, .acdc-page-title');
    const pageKey = title ? title.textContent.trim().toLowerCase().replace(/\s+/g, '-') : 'page';

    return pageKey + '-table-' + tableIndex;
  };

  kernel.getTableKeys = function(table, tableIndex){
    /* ACDC 3.20.68 — Identifiant absolu par tableau.
     * Si la balise <table> porte un attribut data-acdc-table-id, on l'utilise
     * comme SEULE source de clé. La clé devient totalement immuable :
     * indépendante de l'URL, du titre de page, des en-têtes, de la zone
     * conteneuse et de l'index dans le DOM. C'est la garantie absolue que
     * les largeurs ne peuvent pas se mélanger entre tableaux ni se perdre
     * lors d'une navigation, d'un filtre ou d'un changement de contexte.
     *
     * ACDC 3.20.69 — Migration douce des sauvegardes existantes.
     * Quand on utilise la clé v3, on inclut aussi en fallback la clé v2
     * (cls-acdc-...) pour retrouver les largeurs sauvegardées avant la
     * 3.20.68, et la clé legacy (page-table-N) pour retrouver les sauvegardes
     * antérieures à la 3.20.65. Aucune sauvegarde existante ne se perd.
     */
    const directId = table.getAttribute('data-acdc-table-id') || '';
    if (directId) {
      const safeDirectId = kernel.sanitizeKeyPart(directId, 'tid');
      const canonical = ('v3:tid-' + safeDirectId).slice(0, 220);
      const legacyKey = kernel.getLegacyTableKey(table, tableIndex);
      const keys = [canonical];

      /* Fallback v2 — classe métier détectée (sauvegardes 3.20.65 à 3.20.67). */
      const businessClass = kernel.getBusinessTableClass(table);
      if (businessClass) {
        const pageKey = kernel.getPageKey();
        const v2Key = ['v2', pageKey, 'cls-' + kernel.sanitizeKeyPart(businessClass, 'table')].filter(Boolean).join(':').slice(0, 220);
        if (v2Key && v2Key !== canonical) {
          keys.push(v2Key);
        }
      }

      /* Fallback legacy (sauvegardes antérieures à 3.20.65). */
      if (legacyKey && keys.indexOf(legacyKey) === -1) {
        keys.push(legacyKey);
      }

      return keys;
    }

    const pageKey = kernel.getPageKey();
    const explicitWrapper = table.closest('[data-acdc-table-key]');
    const explicitKey = explicitWrapper ? explicitWrapper.getAttribute('data-acdc-table-key') : '';
    const id = table.getAttribute('id') || '';
    const businessClass = kernel.getBusinessTableClass(table);
    const stableIndex = kernel.getStableTableIndex(table);
    const contextKey = kernel.getTableContextKey(table);
    const headerKey = kernel.getTableHeaderSignature(table);
    const legacyKey = kernel.getLegacyTableKey(table, tableIndex);

    /* ACDC 3.20.65 — Source de clé : on privilégie la classe métier détectée
     * (acdc-companies-table, acdc-learners-table, etc.) avant de retomber
     * sur le mode auto. Ordre de priorité :
     *   1. Attribut data-acdc-table-key explicite (si jamais utilisé).
     *   2. Attribut id de la table (si présent).
     *   3. Classe métier détectée (acdc-{nom}-table ou acdc-table-{nom}).
     *   4. Fallback auto (zone + signature + index).
     */
    let source = '';
    if (explicitKey) {
      source = 'data-' + kernel.sanitizeKeyPart(explicitKey, 'table');
    } else if (id) {
      source = 'id-' + kernel.sanitizeKeyPart(id, 'table');
    } else if (businessClass) {
      source = 'cls-' + kernel.sanitizeKeyPart(businessClass, 'table');
    } else {
      source = 'auto-' + (contextKey || 'zone') + '-' + headerKey + '-' + stableIndex;
    }

    const canonical = ['v2', pageKey, source].filter(Boolean).join(':').slice(0, 220);
    const keys = [canonical];

    if (legacyKey && legacyKey !== canonical) {
      keys.push(legacyKey);
    }

    return keys;
  };

  kernel.getTableKey = function(table, tableIndex){
    return kernel.getTableKeys(table, tableIndex)[0];
  };

  kernel.normalizeColumnWidth = function(width){
    const parsed = parseInt(width, 10);
    if (!parsed) { return null; }
    if (parsed < 56) { return 56; }
    if (parsed > 600) { return 600; }
    return parsed;
  };

  kernel.measureTableColumnWidths = function(table){
    const headers = kernel.qsa('th', table);
    const count = headers.length;
    const widths = [];

    for (let i = 0; i < count; i += 1) {
      const th = headers[i];
      const rect = th && th.getBoundingClientRect ? th.getBoundingClientRect() : null;
      const measured = rect && rect.width ? rect.width : (th ? th.offsetWidth : 0);
      widths[i] = Math.max(56, Math.round(measured || 80));
    }

    return widths;
  };

  kernel.ensureColumnGroup = function(table, count){
    if (!table || table.tagName !== 'TABLE') {
      return null;
    }

    let colgroup = null;

    Array.prototype.slice.call(table.children || []).some(function(child){
      if (child && child.tagName === 'COLGROUP' && child.classList.contains('acdc-column-widths')) {
        colgroup = child;
        return true;
      }
      return false;
    });

    if (!colgroup) {
      colgroup = document.createElement('colgroup');
      colgroup.className = 'acdc-column-widths';
      table.insertBefore(colgroup, table.firstElementChild || null);
    }

    while (colgroup.children.length < count) {
      colgroup.appendChild(document.createElement('col'));
    }

    while (colgroup.children.length > count) {
      colgroup.removeChild(colgroup.lastChild);
    }

    return colgroup;
  };

  kernel.applyColumnWidths = function(table, widths){
    if (!table || !widths || !widths.length) { return; }

    const normalized = widths.map(function(width){
      return kernel.normalizeColumnWidth(width) || 80;
    });

    const colgroup = kernel.ensureColumnGroup(table, normalized.length);
    const totalWidth = normalized.reduce(function(sum, width){ return sum + width; }, 0);

    table._acdcColumnWidths = normalized.slice();
    table.style.tableLayout = 'fixed';
    table.style.width = totalWidth + 'px';
    table.style.minWidth = totalWidth + 'px';

    if (colgroup) {
      normalized.forEach(function(width, index){
        const col = colgroup.children[index];
        if (col) {
          col.style.width = width + 'px';
          col.style.minWidth = width + 'px';
        }
      });
    }

    kernel.qsa('tr', table).forEach(function(row){
      normalized.forEach(function(width, index){
        const cell = row.children[index];
        if (!cell) { return; }

        cell.style.width = width + 'px';
        cell.style.minWidth = width + 'px';
        cell.style.maxWidth = width + 'px';
        cell.style.overflow = 'hidden';
        cell.style.setProperty('text-align', 'left', 'important');
      });
    });

    kernel.normalizeTableTextAlignment(table);
  };

  kernel.applyColumnWidth = function(table, columnIndex, width){
    if (!table) { return; }

    const finalWidth = kernel.normalizeColumnWidth(width);
    if (!finalWidth) { return; }

    let widths = table._acdcColumnWidths;
    const headers = kernel.qsa('th', table);

    if (!widths || widths.length !== headers.length) {
      widths = kernel.measureTableColumnWidths(table);
    } else {
      widths = widths.slice();
    }

    widths[columnIndex] = finalWidth;
    kernel.applyColumnWidths(table, widths);
  };

  /* ACDC 3.20.67 — Système de verrouillage des largeurs de colonnes par tableau métier.
   * Un cadenas dans la barre de recherche permet de figer les largeurs.
   * État stocké côté serveur dans l'option WordPress acdc_of_table_locks.
   */
  kernel.isTableLocked = function(tableKey){
    if (!tableKey) { return false; }
    const settings = kernel.getSettings();
    const locks = (settings && settings.tableLocks) ? settings.tableLocks : {};
    return !!locks[tableKey];
  };

  kernel.saveTableLock = function(tableKey, locked){
    const settings = kernel.getSettings();
    if (!settings || !settings.ajaxUrl || !settings.lockNonce || !settings.canSaveGlobal) {
      return Promise.resolve(false);
    }
    if (!tableKey) { return Promise.resolve(false); }

    /* Mise à jour optimiste du cache local. */
    settings.tableLocks = settings.tableLocks || {};
    if (locked) {
      settings.tableLocks[tableKey] = true;
    } else {
      delete settings.tableLocks[tableKey];
    }

    const body = new URLSearchParams();
    body.set('action', 'acdc_save_table_lock');
    body.set('nonce', settings.lockNonce);
    body.set('table_key', tableKey);
    body.set('locked', locked ? '1' : '0');

    return window.fetch(settings.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function(response){
      return response.json ? response.json() : null;
    }).then(function(payload){
      return !!(payload && payload.success);
    }).catch(function(){
      return false;
    });
  };

  /* SVG du cadenas : ouvert et fermé. */
  kernel.lockSvgOpen = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0"/></svg>';
  kernel.lockSvgClosed = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';

  /* Recherche d'un emplacement où poser le cadenas pour un tableau donné.
   * Priorité : barre de recherche/filtre de la même zone, sinon coin sup-droit du tableau.
   */
  kernel.findLockMountPoint = function(table){
    if (!table) { return null; }
    /* On remonte jusqu'au panneau ou à la zone qui contient le tableau,
     * puis on cherche une barre de recherche/filtre à l'intérieur de cette zone.
     */
    const zone = table.closest('.acdc-panel, .acdc-card, .acdc-section, section, main, article') || table.parentNode;
    if (zone) {
      const candidates = [
        zone.querySelector('.acdc-search-row'),
        zone.querySelector('.acdc-list-toolbar')
      ];
      for (let i = 0; i < candidates.length; i += 1) {
        if (candidates[i]) { return candidates[i]; }
      }
    }
    return null;
  };

  kernel.installTableLockToggle = function(table, tableKey){
    if (!table || !tableKey || table._acdcLockToggleBound) { return; }
    const settings = kernel.getSettings();
    if (!settings || !settings.canSaveGlobal) { return; }

    table._acdcLockToggleBound = true;

    const mount = kernel.findLockMountPoint(table);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'acdc-table-lock-toggle';
    button.setAttribute('data-acdc-table-lock-key', tableKey);

    function refresh(){
      const locked = kernel.isTableLocked(tableKey);
      button.innerHTML = locked ? kernel.lockSvgClosed : kernel.lockSvgOpen;
      button.title = locked ? 'Largeurs de colonnes verrouillées (cliquer pour déverrouiller)' : 'Largeurs de colonnes modifiables (cliquer pour verrouiller)';
      button.setAttribute('aria-label', button.title);
      button.setAttribute('aria-pressed', locked ? 'true' : 'false');
      button.classList.toggle('is-locked', locked);
      /* Affiche/masque les poignées en fonction du verrou. */
      kernel.qsa('.acdc-col-resize-handle', table).forEach(function(handle){
        handle.style.display = locked ? 'none' : '';
      });
    }
    refresh();

    button.addEventListener('click', function(){
      const next = !kernel.isTableLocked(tableKey);
      kernel.saveTableLock(tableKey, next).then(function(){
        refresh();
      });
    });

    if (mount) {
      /* On positionne le bouton à droite de la barre. */
      mount.appendChild(button);
      /* Si la barre est en flex, le bouton se cale automatiquement à droite
       * via margin-left:auto défini en CSS. */
    } else {
      /* Repli : on pose le bouton dans un wrapper au-dessus du tableau. */
      const wrap = document.createElement('div');
      wrap.className = 'acdc-table-lock-fallback';
      wrap.appendChild(button);
      if (table.parentNode) {
        table.parentNode.insertBefore(wrap, table);
      }
    }
  };

  kernel.initAdminColumnResize = function(scope){
    if (!document.body.classList.contains('logged-in')) {
      return;
    }

    const tables = kernel.qsa('table, .acdc-table', scope);

    tables.forEach(function(table, tableIndex){
      if (!table || table._acdcTableResizeBound) { return; }

      const headers = kernel.qsa('th', table);
      if (!headers.length) { return; }

      table._acdcTableResizeBound = true;
      const tableKeys = kernel.getTableKeys(table, tableIndex);
      const tableKey = tableKeys[0];
      const initialWidths = kernel.measureTableColumnWidths(table);
      let hasSavedWidths = false;

      headers.forEach(function(th, columnIndex){
        const savedWidth = kernel.getSavedColumnWidth(tableKeys, columnIndex);
        if (savedWidth) {
          initialWidths[columnIndex] = savedWidth;
          hasSavedWidths = true;
        }
      });

      /* ACDC 3.20.67 — Log de diagnostic à l'initialisation du tableau. */
      kernel.debugLog('initTable', {
        tableKey: tableKey,
        hasSavedWidths: hasSavedWidths,
        widthsApplied: initialWidths.slice(),
        columnCount: headers.length
      });

      if (hasSavedWidths) {
        kernel.applyColumnWidths(table, initialWidths);
      }

      headers.forEach(function(th, columnIndex){
        if (!th || th._acdcColumnResizeBound) { return; }

        th._acdcColumnResizeBound = true;
        th.style.position = 'relative';

        const handle = document.createElement('span');
        handle.className = 'acdc-col-resize-handle';
        handle.setAttribute('aria-hidden', 'true');

        /* ACDC 3.25.287 — LA POIGNÉE ÉTAIT INSAISISSABLE AU DOIGT.
         * Relevé sur iPad : « je ne peux pas régler la largeur des colonnes
         * comme sur mon Mac ». Deux causes, et il fallait corriger les deux.
         *
         * La première : huit pixels de large. C'est confortable au curseur,
         * qui vise au pixel ; c'est hors de portée d'un doigt, qui couvre une
         * quarantaine de pixels. On élargit donc la ZONE SAISISSABLE sans
         * toucher au trait dessiné — la poignée reste fine à l'œil et devient
         * large à la main.
         *
         * La seconde, plus bas : les événements écoutés.
         */
        const auDoigt = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

        handle.style.position = 'absolute';
        handle.style.top = '0';
        handle.style.right = auDoigt ? '-11px' : '-4px';
        handle.style.width = auDoigt ? '22px' : '8px';
        handle.style.height = '100%';
        handle.style.cursor = 'col-resize';
        handle.style.userSelect = 'none';
        handle.style.zIndex = '50';
        /* Le trait reste au même endroit quelle que soit la largeur de prise :
         * il est centré dans la poignée. */
        handle.style.background = auDoigt
          ? 'linear-gradient(90deg, transparent 0, transparent 10px, #8b5b23 10px, #8b5b23 12px, transparent 12px)'
          : 'linear-gradient(90deg, transparent 0, transparent 3px, #8b5b23 3px, #8b5b23 5px, transparent 5px)';
        /* Un doigt ne survole rien : sans hover, la poignée resterait à demi
         * effacée et rien n'indiquerait qu'elle est saisissable. */
        handle.style.opacity = auDoigt ? '0.75' : '0.45';
        /* Sans cette ligne, le navigateur interprète le glissement comme un
         * défilement et la colonne ne bouge jamais — c'est la moitié invisible
         * du défaut. */
        handle.style.touchAction = 'none';

        handle.addEventListener('mouseenter', function(){
          handle.style.opacity = '1';
        });

        handle.addEventListener('mouseleave', function(){
          handle.style.opacity = auDoigt ? '0.75' : '0.45';
        });

        /* ACDC 3.25.287 — `pointerdown` plutôt que `mousedown`.
         * Safari ne fabrique des événements de souris que pour une TOUCHE
         * BRÈVE, jamais pour un glissement : la poignée ne recevait donc
         * strictement rien quand on la tirait au doigt. Les événements de
         * pointeur couvrent la souris, le doigt et le stylet d'un seul jeu —
         * le comportement au curseur est inchangé, il passe simplement par le
         * même chemin. */
        handle.addEventListener('pointerdown', function(e){
          e.preventDefault();
          e.stopPropagation();

          /* La capture garde le glissement lié à la poignée même si le doigt
           * sort du tableau : sans elle, on perd la colonne dès qu'on dépasse
           * le bord, ce qui arrive tout le temps sur un écran étroit. */
          if (handle.setPointerCapture) {
            try { handle.setPointerCapture(e.pointerId); } catch (err) {}
          }

          /* ACDC 3.20.67 — Lecture du verrou : si le tableau est verrouillé,
           * le mousedown sur la poignée ne fait rien.
           */
          if (kernel.isTableLocked && kernel.isTableLocked(tableKey)) { return; }

          const startX = e.clientX;
          const baseWidths = kernel.measureTableColumnWidths(table);
          const startWidth = baseWidths[columnIndex] || th.offsetWidth || 80;
          let hasDragged = false;

          document.body.style.cursor = 'col-resize';
          document.body.style.userSelect = 'none';

          /* ACDC 3.20.67 — Création de l'indicateur de largeur en temps réel.
           * Une étiquette flottante affiche la largeur courante en pixels
           * pendant le drag, ce qui permet à l'utilisateur de viser une valeur
           * précise et de comparer entre tableaux.
           */
          const widthIndicator = document.createElement('div');
          widthIndicator.className = 'acdc-col-width-indicator';
          widthIndicator.style.cssText = 'position:fixed;z-index:99999;background:#0c2d52;color:#fff;font-size:13px;font-weight:700;padding:6px 10px;border-radius:6px;pointer-events:none;font-family:Rubik,Arial,sans-serif;box-shadow:0 4px 12px rgba(0,0,0,.18);transform:translate(-50%, -130%);';
          widthIndicator.textContent = Math.round(startWidth) + ' px';
          widthIndicator.style.left = e.clientX + 'px';
          widthIndicator.style.top = e.clientY + 'px';
          document.body.appendChild(widthIndicator);

          function move(ev){
            const delta = ev.clientX - startX;

            if (!hasDragged && Math.abs(delta) < 3) {
              return;
            }

            if (!hasDragged) {
              hasDragged = true;
              kernel.applyColumnWidths(table, baseWidths);
            }

            const newWidth = startWidth + delta;
            const clampedWidth = Math.max(56, Math.min(600, Math.round(newWidth)));
            kernel.applyColumnWidth(table, columnIndex, newWidth);

            /* Mise à jour de l'indicateur */
            widthIndicator.textContent = clampedWidth + ' px';
            widthIndicator.style.left = ev.clientX + 'px';
            widthIndicator.style.top = ev.clientY + 'px';
          }

          function up(){
            if (hasDragged) {
              const widths = table._acdcColumnWidths || [];
              const finalWidth = widths[columnIndex] || th.offsetWidth;

              const finalWidths = (table._acdcColumnWidths && table._acdcColumnWidths.length) ? table._acdcColumnWidths.slice() : kernel.measureTableColumnWidths(table);
              finalWidths[columnIndex] = finalWidth;
              kernel.saveLocalColumnWidths(tableKeys, finalWidths);
              kernel.saveServerColumnWidths(tableKey, finalWidths);
            }

            /* Suppression de l'indicateur */
            if (widthIndicator && widthIndicator.parentNode) {
              widthIndicator.parentNode.removeChild(widthIndicator);
            }

            document.body.style.removeProperty('cursor');
            document.body.style.removeProperty('user-select');

            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', up);
            document.removeEventListener('pointercancel', up);
          }

          document.addEventListener('pointermove', move);
          document.addEventListener('pointerup', up);
          /* Un appel entrant, un geste système : le pointeur est annulé sans
           * `pointerup`. Sans cette ligne, la page resterait en mode
           * redimensionnement, curseur figé et sélection bloquée. */
          document.addEventListener('pointercancel', up);
        });

        th.appendChild(handle);
      });

      /* ACDC 3.20.67 — Installation du cadenas de verrouillage pour ce tableau. */
      kernel.installTableLockToggle(table, tableKey);
    });
  };

  function initAll(){
    kernel.initRepeatableSections(document);
    kernel.normalizeTableTextAlignment(document);
    kernel.initAdminColumnResize(document);
  }

  document.addEventListener('DOMContentLoaded', initAll);

  const observer = new MutationObserver(function(){
    kernel.initAdminColumnResize(document);
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true
  });

  window.AcdcUiKernel = kernel;

})(window, document);
