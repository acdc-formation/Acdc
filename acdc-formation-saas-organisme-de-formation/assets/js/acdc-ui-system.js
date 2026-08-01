(function(){
  var acdcKernel = window.AcdcUiKernel || null;
  var cachedRootStyles = null;
  var normalizeQueued = false;
  var modalSelectors = '.acdc-modal-shell, .acdc-modal';

  /* ------------------------------------------------------------------
   * Tooltips — bulle unique attachée au <body>, positionnée via
   * getBoundingClientRect(). Évite tout clipping par les td/overflow.
   * ------------------------------------------------------------------ */
  var ACDC_TOOLTIP_SEL = [
    '.acdc-row-action-icon',
    '.acdc-row-view-link',
    '.acdc-row-edit-link',
    '.acdc-row-delete-link',
    '.acdc-action-icon',
    '.acdc-action-icon-base',
    '.acdc-icon-link',
    '.acdc-table-action-trigger'
  ].join(',');

  var acdcTipEl = null;

  function getOrCreateTip() {
    if (!acdcTipEl) {
      acdcTipEl = document.createElement('div');
      acdcTipEl.className = 'acdc-tooltip-bubble';
      document.body.appendChild(acdcTipEl);
    }
    return acdcTipEl;
  }

  function showTooltip(el) {
    var text = el.getAttribute('data-tooltip');
    if (!text || !text.trim()) { return; }
    var tip = getOrCreateTip();
    tip.textContent = text;
    tip.className = 'acdc-tooltip-bubble';
    tip.style.cssText = 'position:fixed;z-index:99999;pointer-events:none;visibility:hidden;opacity:0;display:block;';
    var er = el.getBoundingClientRect();
    var tw = tip.offsetWidth;
    var th = tip.offsetHeight;
    var gap = 8;
    var arrowH = 5;
    var idealTop = er.top - th - arrowH - gap;
    var isBelow = idealTop < 6;
    var top = isBelow ? (er.bottom + arrowH + gap) : idealTop;
    var idealLeft = er.left + er.width / 2 - tw / 2;
    var left = Math.max(6, Math.min(idealLeft, window.innerWidth - tw - 6));
    var arrowPx = Math.round(er.left + er.width / 2 - left);
    arrowPx = Math.max(10, Math.min(arrowPx, tw - 10));
    tip.className = 'acdc-tooltip-bubble' + (isBelow ? ' is-below' : '');
    tip.style.cssText = 'position:fixed;z-index:99999;pointer-events:none;top:' + top + 'px;left:' + left + 'px;opacity:1;--acdc-tip-arrow:' + arrowPx + 'px;';
  }

  function hideTooltip() {
    if (acdcTipEl) { acdcTipEl.style.opacity = '0'; }
  }

  /* Migration title → data-tooltip sur les icônes d'action */
  function initTooltips(root) {
    var scope = (root && root.querySelectorAll) ? root : document;
    scope.querySelectorAll(ACDC_TOOLTIP_SEL).forEach(function(el) {
      var t = el.getAttribute('title');
      if (t && t.trim() !== '' && !el.hasAttribute('data-tooltip')) {
        el.setAttribute('data-tooltip', t.trim());
        el.removeAttribute('title');
      }
    });
  }

  /* Délégation globale : un seul listener sur document */
  document.addEventListener('mouseover', function(e) {
    var el = e.target ? e.target.closest('[data-tooltip]') : null;
    if (el) { showTooltip(el); }
  }, true);

  document.addEventListener('mouseout', function(e) {
    var el = e.target ? e.target.closest('[data-tooltip]') : null;
    if (el) { hideTooltip(); }
  }, true);

  document.addEventListener('focusin', function(e) {
    if (e.target && e.target.hasAttribute('data-tooltip')) { showTooltip(e.target); }
  }, true);

  document.addEventListener('focusout', function(e) {
    if (e.target && e.target.hasAttribute('data-tooltip')) { hideTooltip(); }
  }, true);

  function rootStyles(forceRefresh){
    if (forceRefresh || !cachedRootStyles) {
      cachedRootStyles = getComputedStyle(document.documentElement);
    }
    return cachedRootStyles;
  }

  function cssVar(name, fallback, styles){
    var source = styles || rootStyles();
    var value = source.getPropertyValue(name);
    value = value ? String(value).trim() : '';
    return value || fallback;
  }

  function numericPx(name, fallback, styles){
    var value = cssVar(name, fallback, styles);
    if (/^[0-9.]+$/.test(value)) {
      value = value + 'px';
    }
    return value;
  }

  function isModalVisible(node){
    if (!node || node.nodeType !== 1) { return false; }
    if (node.hidden || node.getAttribute('aria-hidden') === 'true') { return false; }
    if (node.closest && node.closest('[hidden]')) { return false; }
    var style = window.getComputedStyle ? window.getComputedStyle(node) : null;
    if (style && (style.display === 'none' || style.visibility === 'hidden')) { return false; }
    return true;
  }

  function visibleModalCount(){
    return Array.prototype.filter.call(document.querySelectorAll('.acdc-modal-shell, .acdc-modal'), isModalVisible).length;
  }

  function syncBodyModalState(){
    var active = visibleModalCount() > 0;
    if (acdcKernel && typeof acdcKernel.syncBodyLock === 'function') {
      acdcKernel.syncBodyLock('.acdc-modal-shell, .acdc-modal', 'acdc-modal-open');
      return;
    }
    document.body.classList.toggle('acdc-modal-open', active);
    document.documentElement.classList.toggle('acdc-modal-open', active);
    if (!active) {
      document.body.style.removeProperty('overflow');
      document.documentElement.style.removeProperty('overflow');
    }
  }

  function isSolidMenuIcon(el){
    return !!el.closest('.acdc-row-menu-toggle,[data-acdc-row-menu-toggle],[data-acdc-bpf-row-menu-toggle],.acdc-table-action-trigger,[data-acdc-menu-target]');
  }

  function setIconStyles(scope, styles){
    var iconSize = numericPx('--acdc-icon-size', '25px', styles);
    var iconColor = cssVar('--acdc-icon-color', cssVar('--acdc-primary', '#8b5b23', styles), styles);
    var iconMode = cssVar('--acdc-icon-style', 'outline', styles);
    var dotsMode = cssVar('--acdc-menu-dots-style', 'filled', styles);
    var selectors = [
      '.acdc-actions i','.acdc-actions svg','.acdc-actions span','.acdc-actions img','.acdc-actions .dashicons',
      '.acdc-action-icon svg','.acdc-action-icon .dashicons','.acdc-icon-link svg','.acdc-icon-link .dashicons',
      '.acdc-row-menu-toggle svg','.acdc-row-menu-toggle .dashicons','.acdc-filter-toggle-icons-only svg','.acdc-filter-toggle-icons-only .dashicons',
      '[data-acdc-row-menu-toggle] svg','[data-acdc-row-menu-toggle] .dashicons','[data-acdc-bpf-row-menu-toggle] svg','[data-acdc-bpf-row-menu-toggle] .dashicons',
      '.acdc-table td a svg','.acdc-table td button svg','.acdc-table td .dashicons','.acdc-table-action-trigger svg','.acdc-table-action-trigger .dashicons'
    ];
    scope.querySelectorAll(selectors.join(',')).forEach(function(el){
      el.style.width = iconSize;
      el.style.height = iconSize;
      el.style.minWidth = iconSize;
      el.style.minHeight = iconSize;
      el.style.fontSize = iconSize;
      el.style.lineHeight = iconSize;
      el.style.color = iconColor;
      if (el.classList && el.classList.contains('dashicons')) {
        el.style.width = iconSize;
        el.style.height = iconSize;
      }
      if (el.tagName === 'svg' || el.tagName === 'SVG') {
        var filled = isSolidMenuIcon(el) ? dotsMode !== 'outline' : iconMode === 'filled';
        el.style.overflow = 'visible';
        if (filled) {
          el.style.fill = 'currentColor';
          el.style.stroke = 'none';
        } else {
          el.style.fill = 'none';
          el.style.stroke = 'currentColor';
        }
        el.querySelectorAll('*').forEach(function(child){
          child.style.fill = filled ? 'currentColor' : 'none';
          child.style.stroke = filled ? 'none' : 'currentColor';
        });
      }
    });
    scope.querySelectorAll('.acdc-table td [aria-hidden="true"]').forEach(function(el){
      el.style.display = 'inline-flex';
      el.style.alignItems = 'center';
      el.style.justifyContent = 'center';
      el.style.width = iconSize;
      el.style.height = iconSize;
      el.style.color = iconColor;
    });
    scope.querySelectorAll('.acdc-row-menu-toggle, [data-acdc-row-menu-toggle], [data-acdc-bpf-row-menu-toggle], .acdc-table-action-trigger').forEach(function(btn){
      btn.style.color = iconColor;
    });
  }

  function syncSwitches(scope, styles){
    var activeColor = cssVar('--acdc-toggle-active', cssVar('--acdc-primary', '#8b5b23', styles), styles);
    var inactiveColor = cssVar('--acdc-toggle-inactive', '#d9dfe8', styles);
    scope.querySelectorAll('.acdc-switch').forEach(function(wrapper){
      var input = wrapper.querySelector('input[type="checkbox"]');
      if (!input) return;
      if (input.checked) {
        wrapper.classList.add('is-active');
        wrapper.classList.remove('is-inactive');
        wrapper.setAttribute('data-state','active');
        wrapper.style.background = 'transparent';
      } else {
        wrapper.classList.add('is-inactive');
        wrapper.classList.remove('is-active');
        wrapper.setAttribute('data-state','inactive');
        wrapper.style.background = 'transparent';
      }
      if (!input.dataset.acdcUiBound) {
        input.addEventListener('change', function(){ queueNormalize(document); });
        input.dataset.acdcUiBound = '1';
      }
    });
  }

  function positionFloatingMenus(){
    document.querySelectorAll('.acdc-row-menu-dropdown-floating:not([hidden]), .acdc-prospect-patch-menu[aria-hidden="false"]').forEach(function(menu){
      var trigger = menu._acdcTriggerButton || menu._acdcProspectTrigger;
      if (!trigger || !menu.offsetParent) return;
      if (acdcKernel && typeof acdcKernel.positionFloatingElement === 'function') {
        acdcKernel.positionFloatingElement(menu, trigger, { align: 'left', gap: 10, minWidth: 250 });
        return;
      }
      var rect = trigger.getBoundingClientRect();
      var menuWidth = menu.offsetWidth || 250;
      var menuHeight = menu.offsetHeight || 0;
      var left = rect.left;
      var top = rect.bottom + 10;
      if ((left + menuWidth) > (window.innerWidth - 12)) {
        left = Math.max(12, window.innerWidth - menuWidth - 12);
      }
      if ((top + menuHeight) > (window.innerHeight - 12)) {
        var aboveTop = rect.top - menuHeight - 10;
        top = aboveTop >= 12 ? aboveTop : Math.max(12, window.innerHeight - menuHeight - 12);
      }
      menu.style.left = Math.round(left) + 'px';
      menu.style.top = Math.round(top) + 'px';
    });
  }


  function bindInteractiveFeedback(node){
    if (!node || node.dataset.acdcFeedbackBound === '1') { return; }
    node.dataset.acdcFeedbackBound = '1';
    node.addEventListener('pointerdown', function(){ node.classList.add('is-pressed'); });
    node.addEventListener('pointerup', function(){
      node.classList.remove('is-pressed');
      node.classList.add('is-activated');
      setTimeout(function(){ node.classList.remove('is-activated'); }, 220);
    });
    node.addEventListener('pointerleave', function(){ node.classList.remove('is-pressed'); });
    node.addEventListener('blur', function(){ node.classList.remove('is-pressed'); }, true);
  }

  function bindQualityField(field){
    if (!field || field.dataset.acdcFieldBound === '1') { return; }
    field.dataset.acdcFieldBound = '1';
    field.classList.add('acdc-quality-field');

    var syncFilled = function(){
      var value = '';
      if (field.type === 'checkbox' || field.type === 'radio') {
        field.classList.toggle('is-filled', !!field.checked);
        return;
      }
      value = typeof field.value === 'string' ? field.value.trim() : '';
      field.classList.toggle('is-filled', value !== '');
    };

    field.addEventListener('focus', function(){
      field.classList.add('is-engaged');
      var help = field.closest('.acdc-field, .acdc-form-field, .acdc-quality-field-wrap');
      if (help) { help.classList.add('is-active-help'); }
    });
    field.addEventListener('blur', function(){
      field.classList.remove('is-engaged');
      var help = field.closest('.acdc-field, .acdc-form-field, .acdc-quality-field-wrap');
      if (help) { help.classList.remove('is-active-help'); }
      syncFilled();
    });
    field.addEventListener('input', syncFilled);
    field.addEventListener('change', syncFilled);
    syncFilled();
  }

  function enhanceQualityInteractions(scope){
    scope = scope && scope.querySelectorAll ? scope : document;
    scope.querySelectorAll('.acdc-quality-shell .acdc-button, .acdc-quality-shell .acdc-quality-jump, .acdc-quality-shell button[type="submit"], .acdc-quality-shell input[type="submit"]').forEach(function(node){
      node.classList.add('acdc-quality-interactive');
      bindInteractiveFeedback(node);
    });
    scope.querySelectorAll('.acdc-quality-shell .acdc-quality-kpi, .acdc-quality-shell .acdc-quality-mini').forEach(function(node){
      node.classList.add('acdc-quality-surface-interactive');
    });
    scope.querySelectorAll('.acdc-quality-shell table.acdc-table tbody tr').forEach(function(row){
      row.classList.add('acdc-quality-row-interactive');
    });
    scope.querySelectorAll('.acdc-quality-shell input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="file"]), .acdc-quality-shell select, .acdc-quality-shell textarea').forEach(bindQualityField);
  }

  function normalize(scope){
    scope = scope && scope.querySelectorAll ? scope : document;
    var styles = rootStyles(true);
    setIconStyles(scope, styles);
    syncSwitches(scope, styles);
    syncBodyModalState();
    positionFloatingMenus();
    enhanceQualityInteractions(scope);
  }

  function queueNormalize(scope){
    if (normalizeQueued) return;
    normalizeQueued = true;
    requestAnimationFrame(function(){
      normalizeQueued = false;
      normalize(scope || document);
    });
  }

  function getModalContainer(node){
    if (!node || node.nodeType !== 1) return null;
    if (node.matches && node.matches('.acdc-modal-shell, .acdc-modal, .acdc-modal-backdrop')) {
      return node;
    }
    return node.closest ? node.closest('.acdc-modal-shell, .acdc-modal, .acdc-modal-backdrop') : null;
  }

  function focusFirstModalField(modal){
    if (!modal || !modal.querySelectorAll) { return; }
    var targets = modal.querySelectorAll('[data-acdc-modal-autofocus], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
    var target = Array.prototype.find.call(targets, function(node){
      return node && !node.hidden && node.offsetParent !== null;
    });
    if (!target || !target.focus) { return; }
    setTimeout(function(){ try { target.focus({ preventScroll: true }); } catch(e) { target.focus(); } }, 0);
  }

  function closeFloatingUi(){
    if (acdcKernel && typeof acdcKernel.hideElements === 'function') {
      acdcKernel.hideElements(document.querySelectorAll('.acdc-table-action-menu:not([hidden]), .acdc-row-menu-dropdown-floating:not([hidden]), .acdc-prospect-patch-menu[aria-hidden="false"]'));
    } else {
      document.querySelectorAll('.acdc-table-action-menu:not([hidden])').forEach(function(menu){ menu.hidden = true; });
      document.querySelectorAll('.acdc-row-menu-dropdown-floating:not([hidden])').forEach(function(menu){ menu.hidden = true; });
      document.querySelectorAll('.acdc-prospect-patch-menu[aria-hidden="false"]').forEach(function(menu){ menu.style.display = 'none'; menu.setAttribute('aria-hidden', 'true'); });
    }
    document.querySelectorAll('.acdc-row-actions-menu-cell.is-open').forEach(function(cell){ cell.classList.remove('is-open'); });
    document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.setAttribute('aria-expanded', 'false'); });
  }

  function openModalById(id, opener){
    if (!id) return;
    var modal = document.getElementById(id);
    if (!modal) return;
    closeFloatingUi();
    modal._acdcLastOpener = opener || document.activeElement || null;
    modal.hidden = false;
    void modal.offsetHeight; // force reflow — corrige le stacking backdrop/dialog au premier paint
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('acdc-modal-open');
    document.documentElement.classList.add('acdc-modal-open');
    syncBodyModalState();
    queueNormalize(modal);
    focusFirstModalField(modal);
  }

  function closeModalElement(modal){
    modal = getModalContainer(modal);
    if (!modal) return;
    var opener = modal._acdcLastOpener || null;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    syncBodyModalState();
    if (opener && opener.focus && document.contains(opener)) {
      setTimeout(function(){ try { opener.focus({ preventScroll: true }); } catch(e) { opener.focus(); } }, 0);
    }
  }

  function bindGlobalInteractions(){
    document.addEventListener('click', function(event){
      var opener = event.target.closest('[data-acdc-modal-open],[data-acdc-open-modal]');
      if (opener) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') { event.stopImmediatePropagation(); }
        openModalById(opener.getAttribute('data-acdc-modal-open') || opener.getAttribute('data-acdc-open-modal'), opener);
        return;
      }
      var closer = event.target.closest('[data-acdc-close-modal],.acdc-close-modal,.acdc-modal-close,[data-acdc-modal-close]');
      if (closer) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') { event.stopImmediatePropagation(); }
        closeModalElement(closer);
        return;
      }
      var shell = event.target.classList && (event.target.classList.contains('acdc-modal-shell') || event.target.classList.contains('acdc-modal-backdrop')) ? event.target : null;
      if (shell && event.target === shell) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') { event.stopImmediatePropagation(); }
        closeModalElement(shell);
        return;
      }
      var dismissAlert = event.target.closest('[data-acdc-dismiss-alert]');
      if (dismissAlert) {
        event.preventDefault();
        var alertBox = dismissAlert.closest('.acdc-alert, .acdc-inline-notice, .notice');
        if (alertBox) {
          alertBox.hidden = true;
          alertBox.style.display = 'none';
        }
        return;
      }
      var anyMenuTrigger = event.target.closest('.acdc-table-action-trigger,.acdc-row-menu-toggle,[data-acdc-row-menu-toggle],[data-acdc-bpf-row-menu-toggle],.acdc-prospect-patch-trigger');
      if (!anyMenuTrigger) {
        closeFloatingUi();
      }
    }, true);

    document.addEventListener('keydown', function(event){
      if (event.key === 'Escape') {
        document.querySelectorAll('.acdc-modal-shell, .acdc-modal').forEach(function(modal){
          if (!isModalVisible(modal)) { return; }
          closeModalElement(modal);
        });
        closeFloatingUi();
        syncBodyModalState();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function(){ queueNormalize(document); initTooltips(document); bindGlobalInteractions(); if (acdcKernel && typeof acdcKernel.initRepeatableSections === 'function') { acdcKernel.initRepeatableSections(document); } });
  window.addEventListener('load', function(){ queueNormalize(document); });
  window.addEventListener('resize', function(){ queueNormalize(document); });
  window.addEventListener('scroll', function(){ positionFloatingMenus(); }, true);

  var observer = new MutationObserver(function(mutations){
    mutations.forEach(function(mutation){
      if (mutation.type === 'attributes' && mutation.target && mutation.target.nodeType === 1) {
        queueNormalize(mutation.target);
        return;
      }
      mutation.addedNodes.forEach(function(node){
        if (node && node.nodeType === 1) { queueNormalize(node); initTooltips(node); if (acdcKernel && typeof acdcKernel.initRepeatableSections === 'function') { acdcKernel.initRepeatableSections(node); } }
      });
    });
  });

  function bootObserver(){
    if (document.body) {
      observer.observe(document.body, {childList:true, subtree:true, attributes:true, attributeFilter:['hidden','class','style','aria-hidden']});
    }
  }

  if (document.body) {
    bootObserver();
  } else {
    document.addEventListener('DOMContentLoaded', bootObserver);
  }
})();
