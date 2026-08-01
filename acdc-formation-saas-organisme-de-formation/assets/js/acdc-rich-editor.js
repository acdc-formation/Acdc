/**
 * ACDC Rich Editor — moteur JavaScript centralisé
 *
 * Composant éditeur de texte enrichi maison.
 * Pas de dépendance externe (pas de jQuery, pas de TinyMCE, pas de Quill).
 * Manipulation DOM via API standard (Selection, Range, Node).
 *
 * Distribué pour tout le plugin via render_acdc_rich_editor() côté PHP.
 *
 * Usage :
 *   - Au DOMContentLoaded, scanne tous les [data-acdc-rich-editor] et les active.
 *   - Synchronise en continu le contenu HTML vers le <textarea hidden> jumelé.
 *   - Expose window.ACDCRichEditor.init(node) pour activation manuelle.
 */
(function () {
  'use strict';

  if (window.ACDCRichEditor) { return; } // Déjà chargé.

  /* =========================================================================
   * 1. Configuration et constantes
   * ========================================================================= */

  var FONT_SIZE_PRESETS = [10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48];

  var COLOR_PALETTE = [
    '#000000', '#2a3a55', '#44546d', '#6b7990', '#9aa5b8', '#d9dfe8',
    '#8b5b23', '#b78a4f', '#d6a353', '#e7c98a', '#f4e3b8', '#fdf6e6',
    '#1a7d3b', '#218e4d', '#4caf6f', '#a3d9b1', '#c62828', '#e57373',
    '#1565c0', '#42a5f5', '#7b1fa2', '#ba68c8', '#f57c00', '#ffb74d'
  ];

  var SPECIAL_CHARS = [
    'À','Â','Ä','Æ','Ç','É','È','Ê','Ë','Î','Ï','Ô','Œ','Ù','Û','Ü','Ÿ',
    'à','â','ä','æ','ç','é','è','ê','ë','î','ï','ô','œ','ù','û','ü','ÿ',
    '\u20AC','\u00A3','$','\u00A5','\u00A2','\u00A9','\u00AE','\u2122','\u00A7','\u00B6','\u2020','\u2021','\u00B0','\u00B1','\u00D7','\u00F7',
    '\u00AB','\u00BB','\u201C','\u201D','\u2018','\u2019','\u2014','\u2013','\u2026','\u2022','\u00B7','\u2012','\u2039','\u203A','\u00A1','\u00BF',
    '\u2192','\u2190','\u2191','\u2193','\u2194','\u21D2','\u21D0','\u2713','\u2714','\u2717','\u2718','\u2605','\u2606','\u2665','\u2666','\u2663','\u2660'
  ];

  var SHORTCUTS = [
    { keys: 'Ctrl+B', label: 'Gras' },
    { keys: 'Ctrl+I', label: 'Italique' },
    { keys: 'Ctrl+U', label: 'Souligné' },
    { keys: 'Ctrl+K', label: 'Insérer un lien' },
    { keys: 'Ctrl+Z', label: 'Annuler' },
    { keys: 'Ctrl+Shift+Z', label: 'Refaire' },
    { keys: 'Tab', label: 'Indenter (dans une liste)' },
    { keys: 'Shift+Tab', label: 'Désindenter (dans une liste)' }
  ];

  /* =========================================================================
   * 2. Icônes SVG inline (cohérentes avec le système ACDC)
   * ========================================================================= */

  var ICONS = {
    bold: '<svg viewBox="0 0 24 24"><path d="M7 5h6a4 4 0 0 1 0 8H7zM7 13h7a4 4 0 0 1 0 8H7z"/></svg>',
    italic: '<svg viewBox="0 0 24 24"><line x1="14" y1="4" x2="10" y2="20"/><line x1="8" y1="4" x2="16" y2="4"/><line x1="6" y1="20" x2="14" y2="20"/></svg>',
    underline: '<svg viewBox="0 0 24 24"><path d="M6 4v8a6 6 0 0 0 12 0V4"/><line x1="4" y1="21" x2="20" y2="21"/></svg>',
    strike: '<svg viewBox="0 0 24 24"><line x1="4" y1="12" x2="20" y2="12"/><path d="M16 6a4 4 0 0 0-8 0M8 18a4 4 0 0 0 8 0"/></svg>',
    link: '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>',
    image: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>',
    ul: '<svg viewBox="0 0 24 24"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.2" fill="currentColor"/><circle cx="4" cy="12" r="1.2" fill="currentColor"/><circle cx="4" cy="18" r="1.2" fill="currentColor"/></svg>',
    ol: '<svg viewBox="0 0 24 24"><line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><text x="3" y="8" font-size="6" fill="currentColor" stroke="none">1</text><text x="3" y="14" font-size="6" fill="currentColor" stroke="none">2</text><text x="3" y="20" font-size="6" fill="currentColor" stroke="none">3</text></svg>',
    indent: '<svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="11" y1="12" x2="21" y2="12"/><line x1="11" y1="18" x2="21" y2="18"/><polyline points="3,10 7,12 3,14"/></svg>',
    outdent: '<svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="11" y1="12" x2="21" y2="12"/><line x1="11" y1="18" x2="21" y2="18"/><polyline points="7,10 3,12 7,14"/></svg>',
    alignLeft: '<svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>',
    alignCenter: '<svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>',
    alignRight: '<svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/></svg>',
    justify: '<svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
    blockquote: '<svg viewBox="0 0 24 24"><path d="M6 8h4v6H6zM14 8h4v6h-4z"/><path d="M6 14c0 2 1 4 3 4M14 14c0 2 1 4 3 4"/></svg>',
    code: '<svg viewBox="0 0 24 24"><polyline points="8,7 3,12 8,17"/><polyline points="16,7 21,12 16,17"/></svg>',
    codeblock: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><polyline points="8,10 6,12 8,14"/><polyline points="14,10 16,12 14,14"/></svg>',
    hr: '<svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="9" y2="6"/><line x1="15" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="9" y2="18"/><line x1="15" y1="18" x2="21" y2="18"/></svg>',
    color: '<svg viewBox="0 0 24 24"><path d="M5 18h14M9 14l3-9 3 9M10 11h4"/></svg>',
    highlight: '<svg viewBox="0 0 24 24"><path d="M9 12l-2 6 4-2 8-8a2 2 0 0 0 0-3l-1-1a2 2 0 0 0-3 0l-8 8z"/></svg>',
    fontsize: '<svg viewBox="0 0 24 24"><polyline points="4,7 4,5 14,5 14,7"/><line x1="9" y1="5" x2="9" y2="20"/><polyline points="14,11 14,9 21,9 21,11"/><line x1="17.5" y1="9" x2="17.5" y2="20"/></svg>',
    specialchar: '<svg viewBox="0 0 24 24"><path d="M12 6a4 4 0 0 0-4 4M16 10a4 4 0 0 0-4-4M12 14a4 4 0 0 0 4-4M8 10a4 4 0 0 0 4 4M9 18l-2 2M15 18l2 2"/></svg>',
    undo: '<svg viewBox="0 0 24 24"><polyline points="9,14 4,9 9,4"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg>',
    redo: '<svg viewBox="0 0 24 24"><polyline points="15,14 20,9 15,4"/><path d="M20 9H9a5 5 0 0 0 0 10h3"/></svg>',
    clear: '<svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/><line x1="10" y1="11" x2="14" y2="15"/><line x1="14" y1="11" x2="10" y2="15"/></svg>',
    viewToggle: '<svg viewBox="0 0 24 24"><polyline points="9,8 5,12 9,16"/><polyline points="15,8 19,12 15,16"/><line x1="13" y1="6" x2="11" y2="18"/></svg>',
    fullscreen: '<svg viewBox="0 0 24 24"><polyline points="4,9 4,4 9,4"/><polyline points="20,9 20,4 15,4"/><polyline points="4,15 4,20 9,20"/><polyline points="20,15 20,20 15,20"/></svg>',
    help: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 1-1 1.7v.5"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>',
    variable: '<svg viewBox="0 0 24 24"><polyline points="8,4 4,12 8,20"/><polyline points="16,4 20,12 16,20"/><line x1="13" y1="8" x2="11" y2="16"/></svg>'
  };

  /* =========================================================================
   * 3. Utilitaires
   * ========================================================================= */

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      for (var k in attrs) {
        if (k === 'className') { node.className = attrs[k]; }
        else if (k === 'innerHTML') { node.innerHTML = attrs[k]; }
        else if (k === 'dataset') { for (var d in attrs[k]) { node.dataset[d] = attrs[k][d]; } }
        else { node.setAttribute(k, attrs[k]); }
      }
    }
    if (children) {
      if (typeof children === 'string') { node.innerHTML = children; }
      else if (Array.isArray(children)) { children.forEach(function (c) { if (c) { node.appendChild(c); } }); }
      else { node.appendChild(children); }
    }
    return node;
  }

  function getSelection(editorContent) {
    var sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) { return null; }
    var range = sel.getRangeAt(0);
    if (!editorContent.contains(range.commonAncestorContainer) && range.commonAncestorContainer !== editorContent) {
      return null;
    }
    return { selection: sel, range: range };
  }

  function saveSelection(editorContent) {
    var ctx = getSelection(editorContent);
    return ctx ? ctx.range.cloneRange() : null;
  }

  function restoreSelection(range) {
    if (!range) { return; }
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }

  function wrapInline(editorContent, tagName, attrs) {
    var ctx = getSelection(editorContent);
    if (!ctx || ctx.range.collapsed) { return; }
    var wrapper = document.createElement(tagName);
    if (attrs) {
      for (var k in attrs) { wrapper.setAttribute(k, attrs[k]); }
    }
    try {
      wrapper.appendChild(ctx.range.extractContents());
      ctx.range.insertNode(wrapper);
      // Replacer la sélection sur le contenu emballé.
      var newRange = document.createRange();
      newRange.selectNodeContents(wrapper);
      ctx.selection.removeAllRanges();
      ctx.selection.addRange(newRange);
    } catch (e) { /* ignore */ }
  }

  function findParentTag(node, root, tagName) {
    tagName = tagName.toUpperCase();
    while (node && node !== root) {
      if (node.nodeType === 1 && node.tagName === tagName) { return node; }
      node = node.parentNode;
    }
    return null;
  }

  function unwrapNode(node) {
    var parent = node.parentNode;
    while (node.firstChild) { parent.insertBefore(node.firstChild, node); }
    parent.removeChild(node);
  }

  function setBlockFormat(editorContent, blockTag) {
    var ctx = getSelection(editorContent);
    if (!ctx) { return; }
    var node = ctx.range.startContainer;
    var block = node;
    while (block && block !== editorContent && block.parentNode !== editorContent) {
      block = block.parentNode;
    }
    if (!block || block === editorContent) {
      // Pas de bloc parent — créer un nouveau bloc avec la sélection.
      var newBlock = document.createElement(blockTag);
      try {
        newBlock.appendChild(ctx.range.extractContents());
        ctx.range.insertNode(newBlock);
      } catch (e) { /* ignore */ }
      return;
    }
    var newEl = document.createElement(blockTag);
    while (block.firstChild) { newEl.appendChild(block.firstChild); }
    block.parentNode.replaceChild(newEl, block);
    // Replacer la sélection.
    var range = document.createRange();
    range.selectNodeContents(newEl);
    ctx.selection.removeAllRanges();
    ctx.selection.addRange(range);
  }

  function insertList(editorContent, listTag) {
    var ctx = getSelection(editorContent);
    if (!ctx) { return; }
    var content = ctx.range.toString() || 'Élément';
    var list = document.createElement(listTag);
    var items = content.split('\n');
    for (var i = 0; i < items.length; i++) {
      var li = document.createElement('li');
      li.textContent = items[i] || ' ';
      list.appendChild(li);
    }
    if (!ctx.range.collapsed) {
      ctx.range.deleteContents();
    }
    ctx.range.insertNode(list);
  }

  function indentSelection(editorContent, dir) {
    // dir = +1 pour indenter, -1 pour désindenter
    var ctx = getSelection(editorContent);
    if (!ctx) { return; }
    var li = findParentTag(ctx.range.startContainer, editorContent, 'LI');
    if (li && dir > 0) {
      // Indenter : créer un sous-list ul/ol
      var prev = li.previousElementSibling;
      if (prev) {
        var nestedList = prev.querySelector(':scope > ul, :scope > ol');
        if (!nestedList) {
          nestedList = document.createElement(li.parentNode.tagName);
          prev.appendChild(nestedList);
        }
        nestedList.appendChild(li);
      }
      return;
    }
    if (li && dir < 0) {
      // Désindenter : remonter d'un cran si possible
      var parentList = li.parentNode;
      var grandLi = parentList.parentNode;
      if (grandLi && grandLi.tagName === 'LI') {
        var grandList = grandLi.parentNode;
        grandList.insertBefore(li, grandLi.nextSibling);
        if (!parentList.children.length) { parentList.remove(); }
      }
      return;
    }
    // Hors liste : ajuster margin-left du bloc
    var node = ctx.range.startContainer;
    var block = node;
    while (block && block !== editorContent && block.parentNode !== editorContent) { block = block.parentNode; }
    if (!block || block === editorContent) { return; }
    var current = parseInt(block.style.marginLeft || '0', 10);
    var next = Math.max(0, current + (dir * 24));
    block.style.marginLeft = next ? (next + 'px') : '';
  }

  function alignBlock(editorContent, alignment) {
    var ctx = getSelection(editorContent);
    if (!ctx) { return; }
    var node = ctx.range.startContainer;
    var block = node;
    while (block && block !== editorContent && block.parentNode !== editorContent) { block = block.parentNode; }
    if (!block || block === editorContent) { return; }
    block.style.textAlign = alignment;
  }

  function applyFontSize(editorContent, sizeStr) {
    wrapInline(editorContent, 'span', { style: 'font-size:' + sizeStr });
  }

  function applyColor(editorContent, color) {
    wrapInline(editorContent, 'span', { style: 'color:' + color });
  }

  function applyHighlight(editorContent, color) {
    wrapInline(editorContent, 'span', { style: 'background-color:' + color });
  }

  function clearFormatting(editorContent) {
    var ctx = getSelection(editorContent);
    if (!ctx || ctx.range.collapsed) { return; }
    var text = ctx.range.toString();
    ctx.range.deleteContents();
    var textNode = document.createTextNode(text);
    ctx.range.insertNode(textNode);
  }

  function insertHTML(editorContent, html) {
    var ctx = getSelection(editorContent);
    if (!ctx) {
      // Pas de sélection : insérer à la fin.
      var div = document.createElement('div');
      div.innerHTML = html;
      while (div.firstChild) { editorContent.appendChild(div.firstChild); }
      return;
    }
    ctx.range.deleteContents();
    var frag = document.createRange().createContextualFragment(html);
    ctx.range.insertNode(frag);
  }

  function cleanPaste(html) {
    // Strip Word/Office classes, styles inline excepté ceux utiles.
    var div = document.createElement('div');
    div.innerHTML = html;
    var walker = document.createTreeWalker(div, NodeFilter.SHOW_ELEMENT, null);
    var toRemove = [];
    var node;
    while ((node = walker.nextNode())) {
      // Supprimer balises non sûres
      if (/^(SCRIPT|STYLE|META|LINK|IFRAME|OBJECT|EMBED|FORM)$/i.test(node.tagName)) {
        toRemove.push(node);
        continue;
      }
      // Retirer attributs dangereux
      var attrs = Array.prototype.slice.call(node.attributes);
      attrs.forEach(function (attr) {
        var name = attr.name.toLowerCase();
        if (name.indexOf('on') === 0 || name === 'class' || name === 'id' || /^data-(?!acdc-)/.test(name)) {
          node.removeAttribute(attr.name);
        }
        if (name === 'style') {
          // Conserver uniquement font-size, color, background-color, text-align, font-weight, font-style, text-decoration
          var allowed = [];
          attr.value.split(';').forEach(function (rule) {
            var kv = rule.split(':');
            if (kv.length !== 2) { return; }
            var prop = kv[0].trim().toLowerCase();
            var val = kv[1].trim();
            if (['font-size', 'color', 'background-color', 'text-align', 'font-weight', 'font-style', 'text-decoration', 'margin-left'].indexOf(prop) >= 0) {
              if (!/expression|javascript|url\(/i.test(val)) {
                allowed.push(prop + ':' + val);
              }
            }
          });
          if (allowed.length) { node.setAttribute('style', allowed.join(';')); }
          else { node.removeAttribute('style'); }
        }
      });
    }
    toRemove.forEach(function (n) { if (n.parentNode) { n.parentNode.removeChild(n); } });
    return div.innerHTML;
  }

  /* =========================================================================
   * 4. Modales internes (lien, image, caractère spécial, aide)
   * ========================================================================= */

  function showModal(title, contentBuilder, onConfirm) {
    var existing = document.querySelector('.acdc-rich-modal-backdrop');
    if (existing) { existing.remove(); }
    var backdrop = el('div', { className: 'acdc-rich-modal-backdrop' });
    var modal = el('div', { className: 'acdc-rich-modal' });
    var header = el('div', { className: 'acdc-rich-modal-header' });
    header.appendChild(el('h4', null, title));
    var closeBtn = el('button', { type: 'button', className: 'acdc-rich-modal-close', 'aria-label': 'Fermer' }, '×');
    closeBtn.addEventListener('click', function () { backdrop.remove(); });
    header.appendChild(closeBtn);
    var body = el('div', { className: 'acdc-rich-modal-body' });
    var footer = el('div', { className: 'acdc-rich-modal-footer' });
    contentBuilder(body, footer, function () { backdrop.remove(); });
    modal.appendChild(header);
    modal.appendChild(body);
    if (footer.children.length) { modal.appendChild(footer); }
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);
    requestAnimationFrame(function () { backdrop.classList.add('is-open'); });
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) { backdrop.remove(); } });
  }

  function showLinkModal(editorContent, savedRange) {
    var existing = savedRange ? findParentTag(savedRange.startContainer, editorContent, 'A') : null;
    var defaultText = savedRange ? savedRange.toString() : '';
    var defaultUrl = existing ? existing.getAttribute('href') : '';
    var defaultBlank = existing ? existing.getAttribute('target') === '_blank' : true;
    showModal('Insérer / modifier un lien', function (body, footer, close) {
      var labelUrl = el('label', null, 'URL du lien');
      var inputUrl = el('input', { type: 'url', placeholder: 'https://...', value: defaultUrl });
      var labelText = el('label', null, 'Texte affiché');
      var inputText = el('input', { type: 'text', placeholder: 'Texte du lien', value: defaultText });
      var checkLine = el('div', { className: 'acdc-rich-checkbox-line' });
      var inputBlank = el('input', { type: 'checkbox', id: 'acdc-rich-link-blank' });
      if (defaultBlank) { inputBlank.checked = true; }
      var labelBlank = el('label', { 'for': 'acdc-rich-link-blank', style: 'margin:0;' }, 'Ouvrir dans un nouvel onglet');
      checkLine.appendChild(inputBlank); checkLine.appendChild(labelBlank);
      body.appendChild(labelUrl); body.appendChild(inputUrl);
      body.appendChild(labelText); body.appendChild(inputText);
      body.appendChild(checkLine);
      var btnCancel = el('button', { type: 'button', className: 'acdc-rich-btn-secondary' }, 'Annuler');
      var btnOk = el('button', { type: 'button', className: 'acdc-rich-btn-primary' }, 'Insérer');
      btnCancel.addEventListener('click', close);
      btnOk.addEventListener('click', function () {
        var url = inputUrl.value.trim();
        if (!url) { return; }
        // Validation : seuls http(s), mailto, tel
        if (!/^(https?:\/\/|mailto:|tel:|#|\/)/i.test(url)) { url = 'https://' + url; }
        var text = inputText.value || url;
        var target = inputBlank.checked ? ' target="_blank" rel="noopener noreferrer"' : '';
        if (existing) {
          existing.setAttribute('href', url);
          if (inputBlank.checked) { existing.setAttribute('target', '_blank'); existing.setAttribute('rel', 'noopener noreferrer'); }
          else { existing.removeAttribute('target'); existing.removeAttribute('rel'); }
          existing.textContent = text;
        } else {
          if (savedRange) { restoreSelection(savedRange); }
          insertHTML(editorContent, '<a href="' + url.replace(/"/g, '&quot;') + '"' + target + '>' + text.replace(/</g, '&lt;') + '</a>');
        }
        close();
      });
      footer.appendChild(btnCancel); footer.appendChild(btnOk);
      setTimeout(function () { inputUrl.focus(); }, 50);
    });
  }

  function showImageModal(editorContent, savedRange) {
    showModal('Insérer une image (par URL)', function (body, footer, close) {
      var labelUrl = el('label', null, 'URL de l\'image');
      var inputUrl = el('input', { type: 'url', placeholder: 'https://...' });
      var labelAlt = el('label', null, 'Texte alternatif');
      var inputAlt = el('input', { type: 'text', placeholder: 'Description de l\'image' });
      body.appendChild(labelUrl); body.appendChild(inputUrl);
      body.appendChild(labelAlt); body.appendChild(inputAlt);
      var info = el('p', { style: 'font-size:12px;color:#6b7990;margin:8px 0 0;' },
        'L\'upload de fichier sera disponible dans une version ultérieure. Pour l\'instant, indiquez l\'URL d\'une image hébergée.');
      body.appendChild(info);
      var btnCancel = el('button', { type: 'button', className: 'acdc-rich-btn-secondary' }, 'Annuler');
      var btnOk = el('button', { type: 'button', className: 'acdc-rich-btn-primary' }, 'Insérer');
      btnCancel.addEventListener('click', close);
      btnOk.addEventListener('click', function () {
        var url = inputUrl.value.trim();
        if (!url) { return; }
        if (!/^(https?:\/\/|\/)/i.test(url)) { return; }
        var alt = (inputAlt.value || '').replace(/"/g, '&quot;');
        if (savedRange) { restoreSelection(savedRange); }
        insertHTML(editorContent, '<img src="' + url.replace(/"/g, '&quot;') + '" alt="' + alt + '">');
        close();
      });
      footer.appendChild(btnCancel); footer.appendChild(btnOk);
      setTimeout(function () { inputUrl.focus(); }, 50);
    });
  }

  function showSpecialCharModal(editorContent, savedRange) {
    showModal('Insérer un caractère spécial', function (body, footer, close) {
      var grid = el('div', { className: 'acdc-rich-specialchars' });
      SPECIAL_CHARS.forEach(function (ch) {
        var b = el('button', { type: 'button' }, ch);
        b.addEventListener('click', function () {
          if (savedRange) { restoreSelection(savedRange); }
          insertHTML(editorContent, ch);
          close();
        });
        grid.appendChild(b);
      });
      body.appendChild(grid);
    });
  }

  function showHelpModal() {
    showModal('Raccourcis clavier', function (body) {
      var ul = el('ul', { className: 'acdc-rich-help-list' });
      SHORTCUTS.forEach(function (s) {
        var li = el('li');
        li.appendChild(el('span', null, s.label));
        li.appendChild(el('kbd', null, s.keys));
        ul.appendChild(li);
      });
      body.appendChild(ul);
    });
  }

  /* =========================================================================
   * 5. Construction de la toolbar
   * ========================================================================= */

  function buildButton(action, icon, title, onClick) {
    var btn = el('button', {
      type: 'button',
      className: 'acdc-rich-button',
      title: title,
      'aria-label': title,
      'data-action': action,
      innerHTML: icon
    });
    btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); onClick(); });
    btn.addEventListener('mousedown', function (e) { e.preventDefault(); }); // Conserve sélection
    return btn;
  }

  function buildSeparator() {
    return el('span', { className: 'acdc-rich-toolbar-sep' });
  }

  function buildParagraphSelect(editorContent) {
    var sel = el('select', { className: 'acdc-rich-select', title: 'Format du paragraphe', 'aria-label': 'Format du paragraphe' });
    var options = [
      { value: 'p', label: 'Paragraphe' },
      { value: 'h2', label: 'Titre 2' },
      { value: 'h3', label: 'Titre 3' },
      { value: 'h4', label: 'Titre 4' },
      { value: 'pre', label: 'Préformaté' }
    ];
    options.forEach(function (o) { sel.appendChild(el('option', { value: o.value }, o.label)); });
    sel.addEventListener('mousedown', function (e) { e.stopPropagation(); });
    sel.addEventListener('change', function () {
      editorContent.focus();
      setBlockFormat(editorContent, sel.value);
      sel.value = 'p';
    });
    return sel;
  }

  function buildFontSizeDropdown(editorContent) {
    var wrap = el('div', { className: 'acdc-rich-dropdown' });
    var btn = buildButton('fontsize', ICONS.fontsize, 'Taille de police', function () {
      wrap.classList.toggle('is-open');
    });
    var menu = el('div', { className: 'acdc-rich-dropdown-menu' });
    var list = el('div', { className: 'acdc-rich-fontsize-list' });
    FONT_SIZE_PRESETS.forEach(function (size) {
      var item = el('button', { type: 'button', className: 'acdc-rich-fontsize-item' }, size + ' px');
      item.style.fontSize = size + 'px';
      item.addEventListener('mousedown', function (e) { e.preventDefault(); });
      item.addEventListener('click', function () {
        applyFontSize(editorContent, size + 'px');
        wrap.classList.remove('is-open');
      });
      list.appendChild(item);
    });
    var custom = el('div', { className: 'acdc-rich-fontsize-custom' });
    var input = el('input', { type: 'number', min: '6', max: '200', placeholder: 'Taille px' });
    var apply = el('button', { type: 'button' }, 'OK');
    input.addEventListener('mousedown', function (e) { e.stopPropagation(); });
    apply.addEventListener('mousedown', function (e) { e.preventDefault(); });
    apply.addEventListener('click', function () {
      var v = parseInt(input.value, 10);
      if (!v || v < 6 || v > 200) { return; }
      applyFontSize(editorContent, v + 'px');
      wrap.classList.remove('is-open');
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); apply.click(); }
    });
    custom.appendChild(input); custom.appendChild(apply);
    menu.appendChild(list); menu.appendChild(custom);
    wrap.appendChild(btn); wrap.appendChild(menu);
    return wrap;
  }

  function buildColorDropdown(editorContent, type) {
    var wrap = el('div', { className: 'acdc-rich-dropdown' });
    var label = type === 'color' ? 'Couleur du texte' : 'Surlignage';
    var icon = type === 'color' ? ICONS.color : ICONS.highlight;
    var btn = buildButton(type, icon, label, function () { wrap.classList.toggle('is-open'); });
    var menu = el('div', { className: 'acdc-rich-dropdown-menu' });
    var grid = el('div', { className: 'acdc-rich-color-grid' });
    COLOR_PALETTE.forEach(function (color) {
      var sw = el('button', { type: 'button', className: 'acdc-rich-color-swatch', 'aria-label': color });
      sw.style.background = color;
      sw.addEventListener('mousedown', function (e) { e.preventDefault(); });
      sw.addEventListener('click', function () {
        if (type === 'color') { applyColor(editorContent, color); }
        else { applyHighlight(editorContent, color); }
        wrap.classList.remove('is-open');
      });
      grid.appendChild(sw);
    });
    menu.appendChild(grid);
    wrap.appendChild(btn); wrap.appendChild(menu);
    return wrap;
  }

  /* =========================================================================
   * 6. Catalogue d'actions par identifiant (pour les profils)
   * ========================================================================= */

  function buildActionWidget(action, editor) {
    var ec = editor.contentEl;
    switch (action) {
      case 'paragraph': return buildParagraphSelect(ec);
      case 'bold': return buildButton('bold', '<strong>B</strong>', 'Gras (Ctrl+B)', function () { wrapInline(ec, 'strong'); });
      case 'italic': return buildButton('italic', '<em>I</em>', 'Italique (Ctrl+I)', function () { wrapInline(ec, 'em'); });
      case 'underline': return buildButton('underline', '<span style="text-decoration:underline">U</span>', 'Souligné (Ctrl+U)', function () { wrapInline(ec, 'u'); });
      case 'strike': return buildButton('strike', '<span style="text-decoration:line-through">S</span>', 'Barré', function () { wrapInline(ec, 's'); });
      case 'color': return buildColorDropdown(ec, 'color');
      case 'highlight': return buildColorDropdown(ec, 'highlight');
      case 'fontsize': return buildFontSizeDropdown(ec);
      case 'link': return buildButton('link', ICONS.link, 'Insérer un lien (Ctrl+K)', function () { showLinkModal(ec, saveSelection(ec)); });
      case 'image': return buildButton('image', ICONS.image, 'Insérer une image', function () { showImageModal(ec, saveSelection(ec)); });
      case 'specialchar': return buildButton('specialchar', ICONS.specialchar, 'Insérer un caractère spécial', function () { showSpecialCharModal(ec, saveSelection(ec)); });
      case 'variable': return buildVariableDropdown(ec, editor.node);
      case 'hr': return buildButton('hr', ICONS.hr, 'Ligne horizontale', function () { insertHTML(ec, '<hr>'); });
      case 'ul': return buildButton('ul', ICONS.ul, 'Liste à puces', function () { insertList(ec, 'ul'); });
      case 'ol': return buildButton('ol', ICONS.ol, 'Liste numérotée', function () { insertList(ec, 'ol'); });
      case 'align-left': return buildButton('align-left', ICONS.alignLeft, 'Aligner à gauche', function () { alignBlock(ec, 'left'); });
      case 'align-center': return buildButton('align-center', ICONS.alignCenter, 'Centrer', function () { alignBlock(ec, 'center'); });
      case 'align-right': return buildButton('align-right', ICONS.alignRight, 'Aligner à droite', function () { alignBlock(ec, 'right'); });
      case 'justify': return buildButton('justify', ICONS.justify, 'Justifier', function () { alignBlock(ec, 'justify'); });
      case 'outdent': return buildButton('outdent', ICONS.outdent, 'Diminuer le retrait', function () { indentSelection(ec, -1); });
      case 'indent': return buildButton('indent', ICONS.indent, 'Augmenter le retrait', function () { indentSelection(ec, +1); });
      case 'blockquote': return buildButton('blockquote', ICONS.blockquote, 'Citation', function () { setBlockFormat(ec, 'blockquote'); });
      case 'code': return buildButton('code', ICONS.code, 'Code inline', function () { wrapInline(ec, 'code'); });
      case 'codeblock': return buildButton('codeblock', ICONS.codeblock, 'Bloc de code', function () { setBlockFormat(ec, 'pre'); });
      case 'undo': return buildButton('undo', ICONS.undo, 'Annuler (Ctrl+Z)', function () { editor.undo(); });
      case 'redo': return buildButton('redo', ICONS.redo, 'Refaire (Ctrl+Shift+Z)', function () { editor.redo(); });
      case 'clear': return buildButton('clear', ICONS.clear, 'Effacer le formatage', function () { clearFormatting(ec); });
      case 'view-toggle': return buildButton('view-toggle', ICONS.viewToggle, 'Basculer Visuel / Code', function () { editor.toggleCodeMode(); });
      case 'fullscreen': return buildButton('fullscreen', ICONS.fullscreen, 'Plein écran', function () { editor.toggleFullscreen(); });
      case 'help': return buildButton('help', ICONS.help, 'Aide / Raccourcis', function () { showHelpModal(); });
      case '|': return buildSeparator();
      default: return null;
    }
  }

  /* =========================================================================
   * 7. Historique (undo/redo)
   * ========================================================================= */

  function createHistory(getValue, setValue, max) {
    max = max || 50;
    var stack = [];
    var index = -1;
    var lastSnapshot = '';
    function snapshot() {
      var v = getValue();
      if (v === lastSnapshot) { return; }
      lastSnapshot = v;
      stack = stack.slice(0, index + 1);
      stack.push(v);
      if (stack.length > max) { stack.shift(); }
      index = stack.length - 1;
    }
    return {
      record: snapshot,
      undo: function () {
        if (index <= 0) { return; }
        index--;
        var v = stack[index];
        lastSnapshot = v;
        setValue(v);
      },
      redo: function () {
        if (index >= stack.length - 1) { return; }
        index++;
        var v = stack[index];
        lastSnapshot = v;
        setValue(v);
      },
      reset: function () {
        var v = getValue();
        stack = [v]; index = 0; lastSnapshot = v;
      }
    };
  }

  /* =========================================================================
   * 8. Initialisation d'un éditeur
   * ========================================================================= */

  /* ACDC 3.24.43 — Bouton Variables dans la toolbar mail */
  function buildVariableDropdown(ec, editorNode) {
    // Lire les groupes de variables depuis data-variables sur le nœud éditeur
    var node = editorNode || ec.closest('[data-acdc-rich-editor]') || ec.parentNode;
    while (node && !node.getAttribute('data-variables')) { node = node.parentNode; }
    var raw = node ? node.getAttribute('data-variables') : null;
    var groups = [];
    if (raw) {
      try { groups = JSON.parse(raw); } catch (e) { groups = []; }
    }
    if (!groups || !groups.length) { return null; } // Pas de variables = pas de bouton

    var btn = el('button', {
      type: 'button',
      className: 'acdc-rich-btn',
      title: 'Insérer une variable',
      innerHTML: ICONS.variable
    });

    // position:fixed pour éviter le clipping par overflow:auto de la modale
    var dropdown = el('div', { className: 'acdc-rich-dropdown-menu acdc-var-dropdown', style: 'display:none;position:fixed;z-index:99999;background:#fff;border:1px solid #e0e0e0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);padding:6px 0;min-width:240px;max-height:320px;overflow-y:auto;' });
    document.body.appendChild(dropdown); // hors arbre DOM de la modale pour éviter overflow clip

    groups.forEach(function(group) {
      var header = el('div', {
        style: 'padding:6px 14px 3px;font-size:10px;font-weight:700;text-transform:uppercase;color:#8b5b23;letter-spacing:.06em;',
        textContent: group.label
      });
      dropdown.appendChild(header);
      (group.variables || []).forEach(function(v) {
        var item = el('div', {
          className: 'acdc-var-item',
          style: 'padding:6px 14px;font-size:13px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px;',
        });
        var labelSpan = el('span', { textContent: v.label, style: 'color:#0f2c52;' });
        var codeSpan  = el('span', { textContent: '{{' + v.key + '}}', style: 'color:#8b5b23;font-size:11px;font-family:monospace;background:#f7e7bf;padding:1px 5px;border-radius:4px;' });
        item.appendChild(labelSpan);
        item.appendChild(codeSpan);
        item.addEventListener('mouseenter', function() { item.style.background = '#fdf4e3'; });
        item.addEventListener('mouseleave', function() { item.style.background = ''; });
        item.addEventListener('mousedown', function(e) {
          e.preventDefault();
          insertHTML(ec, '<span data-acdc-variable="' + v.key + '">{{' + v.key + '}}</span>');
          dropdown.style.display = 'none';
          ec.dispatchEvent(new Event('input', { bubbles: true }));
        });
        dropdown.appendChild(item);
      });
    });

    var wrap = el('div', { style: 'position:relative;display:inline-block;' });
    wrap.appendChild(btn);
    // dropdown est appendé à body (hors wrap) — on retourne wrap seul

    var open = false;
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      open = !open;
      if (open) {
        var rect = btn.getBoundingClientRect();
        dropdown.style.top  = (rect.bottom + 4) + 'px';
        dropdown.style.left = rect.left + 'px';
        dropdown.style.display = 'block';
      } else {
        dropdown.style.display = 'none';
      }
    });
    document.addEventListener('click', function() {
      open = false;
      dropdown.style.display = 'none';
    });
    return wrap;
  }

  function getProfileActions(profileName) {
    var profiles = {
      minimal: ['bold', 'italic', 'link', '|', 'ul', 'ol'],
      standard: ['bold', 'italic', 'underline', 'strike', '|',
                 'link', 'blockquote', 'code', '|',
                 'ul', 'ol', '|',
                 'outdent', 'indent', '|',
                 'undo', 'redo'],
      full: ['paragraph', '|',
             'bold', 'italic', 'underline', 'strike', '|',
             'color', 'highlight', 'fontsize', '|',
             'link', 'image', 'specialchar', 'hr', '|',
             'ul', 'ol', '|',
             'align-left', 'align-center', 'align-right', 'justify', '|',
             'outdent', 'indent', '|',
             'blockquote', 'code', 'codeblock', '|',
             'undo', 'redo', 'clear', '|',
             'view-toggle', 'fullscreen', 'help'],
      mail: ['paragraph', '|',
             'bold', 'italic', 'underline', 'strike', '|',
             'color', 'highlight', 'fontsize', '|',
             'link', 'specialchar', 'hr', '|',
             'ul', 'ol', '|',
             'align-left', 'align-center', 'align-right', 'justify', '|',
             'outdent', 'indent', '|',
             'blockquote', '|',
             'variable', '|',
             'undo', 'redo', 'clear', '|',
             'view-toggle', 'fullscreen', 'help']
    };
    return profiles[profileName] || profiles.standard;
  }

  function init(node) {
    if (!node || node.dataset.acdcRichInit === '1') { return; }
    node.dataset.acdcRichInit = '1';

    var profile = node.dataset.profile || 'standard';
    var disabled = node.dataset.disabled === '1';
    var contentEl = node.querySelector('[data-acdc-rich-content]');
    var hiddenInput = node.querySelector('[data-acdc-rich-input]');
    var codeArea = node.querySelector('[data-acdc-rich-codearea]');

    if (!contentEl || !hiddenInput) { return; }

    if (disabled) { node.classList.add('is-disabled'); contentEl.setAttribute('contenteditable', 'false'); }

    var editor = {
      node: node,
      contentEl: contentEl,
      hiddenInput: hiddenInput,
      codeArea: codeArea,
      isCodeMode: false,
      history: null,
      sync: function () {
        hiddenInput.value = contentEl.innerHTML;
      },
      toggleCodeMode: function () {
        editor.isCodeMode = !editor.isCodeMode;
        if (editor.isCodeMode) {
          codeArea.value = contentEl.innerHTML;
          node.classList.add('is-code-mode');
        } else {
          contentEl.innerHTML = codeArea.value;
          node.classList.remove('is-code-mode');
          editor.sync();
        }
      },
      toggleFullscreen: function () {
        node.classList.toggle('is-fullscreen');
      },
      undo: function () { if (editor.history) { editor.history.undo(); editor.sync(); } },
      redo: function () { if (editor.history) { editor.history.redo(); editor.sync(); } }
    };

    // Construction de la toolbar
    if (!disabled) {
      var toolbarEl = node.querySelector('[data-acdc-rich-toolbar]');
      if (toolbarEl) {
        var actions = getProfileActions(profile);
        actions.forEach(function (a) {
          var widget = buildActionWidget(a, editor);
          if (widget) { toolbarEl.appendChild(widget); }
        });
      }
    }

    // Historique
    editor.history = createHistory(
      function () { return contentEl.innerHTML; },
      function (v) { contentEl.innerHTML = v; }
    );
    editor.history.reset();

    // Sync au moindre changement
    contentEl.addEventListener('input', function () {
      editor.sync();
      // Snapshot après une courte pause pour éviter de polluer la pile
      clearTimeout(contentEl._acdcSnap);
      contentEl._acdcSnap = setTimeout(function () { editor.history.record(); }, 400);
    });
    contentEl.addEventListener('blur', function () { editor.sync(); editor.history.record(); });

    // Code mode sync
    if (codeArea) {
      codeArea.addEventListener('input', function () { hiddenInput.value = codeArea.value; });
    }

    // Fermeture des dropdowns au clic extérieur
    document.addEventListener('click', function (e) {
      if (!node.contains(e.target)) {
        node.querySelectorAll('.acdc-rich-dropdown.is-open').forEach(function (d) { d.classList.remove('is-open'); });
      }
    });

    // Raccourcis clavier
    contentEl.addEventListener('keydown', function (e) {
      var ctrl = e.ctrlKey || e.metaKey;
      if (ctrl && !e.shiftKey && (e.key === 'b' || e.key === 'B')) { e.preventDefault(); wrapInline(contentEl, 'strong'); }
      else if (ctrl && !e.shiftKey && (e.key === 'i' || e.key === 'I')) { e.preventDefault(); wrapInline(contentEl, 'em'); }
      else if (ctrl && !e.shiftKey && (e.key === 'u' || e.key === 'U')) { e.preventDefault(); wrapInline(contentEl, 'u'); }
      else if (ctrl && !e.shiftKey && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); showLinkModal(contentEl, saveSelection(contentEl)); }
      else if (ctrl && !e.shiftKey && (e.key === 'z' || e.key === 'Z')) { e.preventDefault(); editor.undo(); }
      else if (ctrl && e.shiftKey && (e.key === 'z' || e.key === 'Z' || e.key === 'y' || e.key === 'Y')) { e.preventDefault(); editor.redo(); }
      else if (e.key === 'Tab') {
        var li = findParentTag(window.getSelection().focusNode, contentEl, 'LI');
        if (li) { e.preventDefault(); indentSelection(contentEl, e.shiftKey ? -1 : +1); }
      }
      else if (e.key === 'Escape' && node.classList.contains('is-fullscreen')) { editor.toggleFullscreen(); }
    });

    // Coller : nettoyer
    contentEl.addEventListener('paste', function (e) {
      e.preventDefault();
      var html = '';
      if (e.clipboardData) {
        html = e.clipboardData.getData('text/html');
        if (!html) { html = e.clipboardData.getData('text/plain').replace(/\n/g, '<br>'); }
      }
      var clean = cleanPaste(html);
      insertHTML(contentEl, clean);
      editor.sync();
    });

    // Sync initiale
    editor.sync();
  }

  function initAll() {
    document.querySelectorAll('[data-acdc-rich-editor]').forEach(init);
  }

  /* =========================================================================
   * 9. Exposition globale et bootstrap
   * ========================================================================= */

  window.ACDCRichEditor = {
    init: init,
    initAll: initAll
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
