(function(){
    var acdcKernel = window.AcdcUiKernel || null;
    function closeMenu(menu){
        if(!menu){return;}
        menu.classList.remove('is-open');
        var button = menu.querySelector('.acdc-user-menu-toggle');
        if(button){
            button.setAttribute('aria-expanded', 'false');
        }
    }

    document.addEventListener('click', function(event){
        document.querySelectorAll('[data-acdc-user-menu]').forEach(function(menu){
            var button = menu.querySelector('.acdc-user-menu-toggle');
            if(!button){
                return;
            }
            if(button.contains(event.target)){
                var open = !menu.classList.contains('is-open');
                document.querySelectorAll('[data-acdc-user-menu]').forEach(function(other){
                    if(other !== menu){
                        closeMenu(other);
                    }
                });
                menu.classList.toggle('is-open', open);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                return;
            }
            if(!menu.contains(event.target)){
                closeMenu(menu);
            }
        });
    });

    document.addEventListener('keydown', function(event){
        if(event.key === 'Escape'){
            document.querySelectorAll('[data-acdc-user-menu]').forEach(closeMenu);
        }
    });
})();


(function(){
    function acdcEscapeHtml(value){
        return String(value || '').replace(/[&<>"']/g, function(ch){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch] || ch;
        });
    }

    function acdcIconSvg(type){
        if(type === 'more'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="5" cy="12" r="1.8" fill="currentColor"></circle><circle cx="12" cy="12" r="1.8" fill="currentColor"></circle><circle cx="19" cy="12" r="1.8" fill="currentColor"></circle></svg>';
        }
        if(type === 'view'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }
        if(type === 'edit'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path></svg>';
        }
        if(type === 'phone'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 3.08 5.18 2 2 0 0 1 5.05 3h3a2 2 0 0 1 2 1.72l.35 2.47a2 2 0 0 1-.57 1.71L8.1 10.63a16 16 0 0 0 5.27 5.27l1.73-1.73a2 2 0 0 1 1.71-.57l2.47.35A2 2 0 0 1 22 16.92z"></path></svg>';
        }
        if(type === 'trash'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>';
        }
        if(type === 'copy'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
        }
        if(type === 'refresh'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15.55-6.36L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15.55 6.36L3 16"></path></svg>';
        }
        if(type === 'toggle-on'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="10" rx="5"></rect><circle cx="16" cy="12" r="3"></circle></svg>';
        }
        if(type === 'toggle-off'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="10" rx="5"></rect><circle cx="8" cy="12" r="3"></circle></svg>';
        }
        if(type === 'download'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"></path><path d="m7 10-7 7-7-7"></path><path d="M5 21h14"></path></svg>';
        }
        if(type === 'document'){
            return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"></path><path d="M14 2v5h5"></path><path d="M9 13h6"></path><path d="M9 17h6"></path></svg>';
        }
        return '';
    }

    function acdcAppendParam(url, key, value){
        try {
            var parsed = new URL(url, window.location.origin);
            parsed.searchParams.set(key, value);
            return parsed.toString();
        } catch(error){
            return url + (url.indexOf('?') > -1 ? '&' : '?') + encodeURIComponent(key) + '=' + encodeURIComponent(value);
        }
    }

    function acdcReplaceAction(url, actionValue){
        try {
            var parsed = new URL(url, window.location.origin);
            parsed.searchParams.set('action', actionValue);
            return parsed.toString();
        } catch(error){
            return url;
        }
    }

    function acdcGetItemId(url){
        try {
            var parsed = new URL(url, window.location.origin);
            return parsed.searchParams.get('item_id') || parsed.searchParams.get('prospect_id') || '';
        } catch(error){
            return '';
        }
    }

    function acdcSetFrontTabUrl(url, tab, params){
        try {
            var parsed = new URL(url || window.location.href, window.location.origin);
            parsed.searchParams.set('tab', tab);
            ['action','item_id','focus_field','focus_section','open_rdv','duplicate_id','prospect_id'].forEach(function(key){ parsed.searchParams.delete(key); });
            Object.keys(params || {}).forEach(function(key){ parsed.searchParams.set(key, params[key]); });
            return parsed.toString();
        } catch(error){
            return '#';
        }
    }

    function acdcBuildMenuUrls(mapping){
        var itemId = acdcGetItemId(mapping.view || mapping.edit || mapping.follow || mapping.delete || '');
        var isAdmin = window.location.href.indexOf('/wp-admin/') > -1;
        var root = window.location.origin;
        var adminBase = root + '/wp-admin/admin.php';
        var followBase = mapping.follow || (isAdmin ? (adminBase + '?page=acdc-of-prospect-followup&action=view&item_id=' + encodeURIComponent(itemId)) : window.location.href);
        var editBase = mapping.edit || (isAdmin ? adminBase + '?page=acdc-of-prospects&action=edit&item_id=' + encodeURIComponent(itemId) : window.location.href);
        if (isAdmin) {
            return [
                ['Ajouter un rendez-vous', acdcAppendParam(followBase, 'open_rdv', '1')],
                ['Recueil des besoins', adminBase + '?page=acdc-of-needs&action=new&prospect_id=' + encodeURIComponent(itemId)],
                ['Devis', adminBase + '?page=acdc-of-quotes&prospect_id=' + encodeURIComponent(itemId)],
                ['Convention/Contrat', adminBase + '?page=acdc-of-registration-contract&action=new&prospect_id=' + encodeURIComponent(itemId)],
                ['Inscrire en formation', adminBase + '?page=acdc-of-register-training&action=new&prospect_id=' + encodeURIComponent(itemId)]
            ];
        }
        return [
            ['Ajouter un rendez-vous', acdcSetFrontTabUrl(followBase, 'prospect_followup', { action:'view', item_id:itemId, open_rdv:'1' })],
            ['Recueil des besoins', acdcSetFrontTabUrl(editBase, 'needs', { action:'new', prospect_id:itemId })],
            ['Devis', acdcSetFrontTabUrl(editBase, 'quotes', { prospect_id:itemId })],
            ['Convention/Contrat', acdcSetFrontTabUrl(editBase, 'registration_contract', { action:'new', prospect_id:itemId })],
            ['Inscrire en formation', acdcSetFrontTabUrl(editBase, 'register_training', { action:'new', prospect_id:itemId })]
        ];
    }

    function acdcCreateIconLink(href, title, iconType, deleteConfirm){
        var a = document.createElement('a');
        a.href = href;
        a.title = title;
        a.setAttribute('aria-label', title);
        a.className = 'acdc-prospect-patch-icon';
        a.setAttribute('style', 'display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;color:#e9c77c;text-decoration:none;line-height:1;');
        a.innerHTML = acdcIconSvg(iconType);
        if(deleteConfirm){
            a.addEventListener('click', function(event){
                if(!window.confirm(deleteConfirm)){
                    event.preventDefault();
                }
            });
        }
        return a;
    }

    function acdcPositionProspectMenu(menu, trigger){
        if(!menu || !trigger){ return; }
        /* ACDC 3.20.66 — Sécurisation du test acdcKernel (cf. acdcCloseAllProspectMenus) */
        if(typeof acdcKernel !== 'undefined' && acdcKernel && typeof acdcKernel.positionFloatingElement === 'function'){
            acdcKernel.positionFloatingElement(menu, trigger, { align:'left', gap:10, minWidth:250 });
            return;
        }
        var rect = trigger.getBoundingClientRect();
        var gap = 10;
        var menuWidth = menu.offsetWidth || 250;
        var menuHeight = menu.offsetHeight || 0;
        var left = rect.left;
        var top = rect.bottom + gap;

        if((left + menuWidth) > (window.innerWidth - 12)){
            left = Math.max(12, window.innerWidth - menuWidth - 12);
        }
        if((top + menuHeight) > (window.innerHeight - 12)){
            var aboveTop = rect.top - menuHeight - gap;
            if(aboveTop >= 12){
                top = aboveTop;
            } else {
                top = Math.max(12, window.innerHeight - menuHeight - 12);
            }
        }

        menu.style.left = Math.round(left) + 'px';
        menu.style.top = Math.round(top) + 'px';
    }

    function acdcCloseAllProspectMenus(except){
        /* ACDC 3.20.66 — Sécurisation du test de présence de acdcKernel.
         * Avant : if (acdcKernel && ...) → lançait ReferenceError si acdcKernel
         * n'existait pas dans le scope. Le test typeof !== 'undefined' est
         * sécurisé et permet de retomber proprement sur la branche else.
         */
        if(typeof acdcKernel !== 'undefined' && acdcKernel && typeof acdcKernel.hideElements === 'function'){
            acdcKernel.hideElements(Array.prototype.slice.call(document.querySelectorAll('.acdc-prospect-patch-menu')).filter(function(menu){ return menu !== except; }));
        } else {
            document.querySelectorAll('.acdc-prospect-patch-menu').forEach(function(menu){
                if(menu !== except){
                    menu.style.display = 'none';
                    menu.setAttribute('aria-hidden', 'true');
                }
            });
        }
        document.querySelectorAll('.acdc-prospect-patch-trigger').forEach(function(btn){
            if(!except || btn._acdcProspectMenu !== except){ btn.setAttribute('aria-expanded', 'false'); }
        });
    }

    function acdcBuildActionWrapper(mapping){
        var wrapper = document.createElement('div');
        wrapper.className = 'acdc-prospect-patch-actions';
        wrapper.setAttribute('style', 'display:flex;align-items:center;gap:12px;white-space:nowrap;position:relative;');

        var menuHost = document.createElement('div');
        menuHost.setAttribute('style', 'display:inline-flex;align-items:center;');
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'acdc-prospect-patch-trigger';
        trigger.setAttribute('aria-label', 'Actions complémentaires');
        trigger.setAttribute('aria-haspopup', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('style', 'display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;padding:0;border:none;background:transparent;color:#e9c77c;cursor:pointer;');
        trigger.innerHTML = acdcIconSvg('more');

        var menu = document.createElement('div');
        menu.className = 'acdc-prospect-patch-menu';
        menu.setAttribute('aria-hidden', 'true');
        menu.setAttribute('style', 'display:none;position:fixed;top:0;left:0;min-width:250px;background:#fff;border:1px solid #dce4ec;border-radius:10px;box-shadow:0 10px 30px rgba(28,44,64,.12);padding:14px 0;z-index:999999;');
        var acdcInlineMenuLabels = ['attribuer à', 'statut dossier', 'relancer'];
        acdcBuildMenuUrls(mapping).forEach(function(item){
            var link = document.createElement('a');
            link.href = item[1];
            link.textContent = item[0];
            link.setAttribute('style', 'display:block;padding:12px 26px;color:#1E4777;text-decoration:none;font-size:16px;line-height:1.35;white-space:nowrap;');
            link.addEventListener('mouseenter', function(){ this.style.background = '#F6F8FB'; this.style.color = '#1e4777'; });
            link.addEventListener('mouseleave', function(){ this.style.background = 'transparent'; this.style.color = '#1E4777'; });
            if(acdcInlineMenuLabels.indexOf(item[0].toLowerCase()) === -1){
                link.addEventListener('click', function(){ acdcCloseAllProspectMenus(); });
            }
            menu.appendChild(link);
        });

        if(document.body){
            document.body.appendChild(menu);
        }
        trigger._acdcProspectMenu = menu;
        menu._acdcProspectTrigger = trigger;
        menu._acdcTriggerButton = trigger;
        var acdcMenuItemId = (function(){
            var src = mapping.edit || mapping.view || mapping.follow || mapping.delete || '';
            if(!src){ return ''; }
            try { var u = new URL(src, window.location.origin); return u.searchParams.get('item_id') || u.searchParams.get('prospect_id') || ''; } catch(e){ return ''; }
        })();
        if(acdcMenuItemId){ menu.setAttribute('data-acdc-prospect-id', acdcMenuItemId); }

        trigger.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            var willOpen = menu.style.display === 'none';
            acdcCloseAllProspectMenus();
            if(willOpen){
                menu.style.display = 'block';
                menu.setAttribute('aria-hidden', 'false');
                acdcPositionProspectMenu(menu, trigger);
            } else {
                menu.style.display = 'none';
                menu.setAttribute('aria-hidden', 'true');
            }
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
        menuHost.appendChild(trigger);
        wrapper.appendChild(menuHost);

        if(mapping.view){ wrapper.appendChild(acdcCreateIconLink(mapping.view, 'Voir', 'view')); }
        if(mapping.edit){ wrapper.appendChild(acdcCreateIconLink(mapping.edit, 'Modifier', 'edit')); }
        if(mapping.follow){ wrapper.appendChild(acdcCreateIconLink(mapping.follow, 'Suivi commercial', 'clipboard')); }
        if(mapping.delete){ wrapper.appendChild(acdcCreateIconLink(mapping.delete, 'Supprimer', 'trash', 'Supprimer ce prospect ?')); }

        return wrapper;
    }

    function acdcExtractMapping(cell){
        var mapping = {};
        var links = Array.prototype.slice.call(cell.querySelectorAll('a'));
        links.forEach(function(link){
            var text = (link.textContent || '').trim().toLowerCase();
            if(text === 'voir'){ mapping.view = link.href; }
            else if(text === 'modifier'){ mapping.edit = link.href; }
            else if(text === 'suivi' || text === 'suivi commercial'){ mapping.follow = link.href; }
            else if(text === 'supprimer'){ mapping.delete = link.href; }
            else {
                var label = (link.getAttribute('aria-label') || link.getAttribute('title') || '').trim().toLowerCase();
                if(label === 'voir'){ mapping.view = link.href; }
                else if(label === 'modifier'){ mapping.edit = link.href; }
                else if(label === 'suivi commercial'){ mapping.follow = link.href; }
                else if(label === 'supprimer'){ mapping.delete = link.href; }
            }
        });
        return mapping;
    }

    function acdcPatchProspectsActions(){
        document.querySelectorAll('table.acdc-table-prospects tbody tr').forEach(function(row){
            var cell = row.querySelector('td:last-child');
            if(!cell){ return; }
            if(cell.querySelector('.acdc-prospect-patch-actions') || cell.querySelector('.acdc-prospect-actions')){ return; }
            var mapping = acdcExtractMapping(cell);
            if(!mapping.view && !mapping.edit && !mapping.follow && !mapping.delete){ return; }
            cell.innerHTML = '';
            cell.appendChild(acdcBuildActionWrapper(mapping));
        });
    }


    function acdcNormalizeActionLabel(text){
        return (text || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function acdcGetActionIconType(label){
        if(label === 'voir' || label === 'voir le détail' || label === 'voir détail' || label === 'ouvrir les résultats' || label === 'résultats') return 'view';
        if(label === 'modifier' || label.indexOf('modifier ') === 0) return 'edit';
        if(label === 'supprimer' || label.indexOf('supprimer ') === 0) return 'trash';
        if(label === 'dupliquer' || label === 'répliquer') return 'copy';
        if(label === 'relancer' || label === 'renvoyer' || label === 'envoyer les accès' || label === 'préparer un e-mail' || label === 'suivi commercial') return 'refresh';
        if(label === 'activer') return 'toggle-on';
        if(label === 'désactiver') return 'toggle-off';
        if(label === 'télécharger') return 'download';
        if(label === 'voir le document' || label === 'page apprenant' || label === 'ouvrir') return 'document';
        if(label === 'animer' || label === 'attribuer à' || label === 'statut dossier') return 'edit';
        return '';
    }

    function acdcCleanupActionCellText(cell){
        Array.prototype.slice.call(cell.childNodes).forEach(function(node){
            if(node.nodeType === 3 && /^(\s*\|\s*|\s+)$/.test(node.textContent || '')){
                node.parentNode.removeChild(node);
            }
        });
    }

    function acdcPatchGenericActionLinks(scope){
        var root = scope && scope.querySelectorAll ? scope : document;
        root.querySelectorAll('table tbody td a').forEach(function(link){
            if(link.closest('.acdc-prospect-patch-actions') || link.closest('.acdc-inline-action-icons') || link.closest('.acdc-row-actions-menu') || link.closest('.acdc-contract-doc-topbar') || link.closest('.acdc-modal') || link.closest('.acdc-modal-shell') || link.closest('.acdc-modal-dialog')){
                return;
            }
            if(link.classList.contains('acdc-button') || link.classList.contains('acdc-icon-link') || link.querySelector('svg')){
                return;
            }
            var cell = link.closest('td');
            if(!cell){ return; }
            var label = acdcNormalizeActionLabel(link.textContent || link.getAttribute('aria-label') || link.getAttribute('title') || '');
            var iconType = acdcGetActionIconType(label);
            if(!iconType){ return; }
            link.classList.add('acdc-inline-action-icon');
            link.setAttribute('title', link.getAttribute('title') || (link.textContent || '').trim());
            link.setAttribute('aria-label', link.getAttribute('aria-label') || (link.textContent || '').trim());
            link.innerHTML = acdcIconSvg(iconType) + '<span class="screen-reader-text">' + acdcEscapeHtml((link.textContent || '').trim()) + '</span>';
            link.style.display = 'inline-flex';
            link.style.alignItems = 'center';
            link.style.justifyContent = 'center';
            link.style.width = '18px';
            link.style.height = '18px';
            link.style.color = '#C5A253';
            link.style.textDecoration = 'none';
            link.style.verticalAlign = 'middle';
            link.style.margin = '0';
            link.style.padding = '0';
            cell.classList.add('acdc-inline-action-icons');
            cell.style.whiteSpace = 'nowrap';
        });
        root.querySelectorAll('table tbody td.acdc-inline-action-icons').forEach(function(cell){
            acdcCleanupActionCellText(cell);
            cell.style.display = 'flex';
            cell.style.alignItems = 'center';
            cell.style.gap = '12px';
        });
    }
    function acdcFindRdvModal(){
        return document.getElementById('acdc-prospect-rdv-modal') || document.getElementById('acdc-prospect-inline-rdv-modal') || Array.prototype.find.call(document.querySelectorAll('.acdc-modal-shell, .acdc-modal-dialog, .acdc-modal'), function(node){
            return /ajouter rdv/i.test(node.textContent || '');
        }) || null;
    }

    function acdcPatchRdvModal(){
        return;
    }

    function acdcNormalizeActionLabel(label){
        return String(label || '')
            .toLowerCase()
            .replace(/[’']/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function acdcActionIconType(label){
        var normalized = acdcNormalizeActionLabel(label);
        if(normalized === 'voir' || normalized === 'ouvrir'){ return 'view'; }
        if(normalized === 'modifier' || normalized === 'editer' || normalized === 'éditer'){ return 'edit'; }
        if(normalized === 'supprimer'){ return 'trash'; }
        if(normalized === 'dupliquer' || normalized === 'repliquer' || normalized === 'répliquer'){ return 'copy'; }
        if(normalized === 'relancer' || normalized === 'renvoyer'){ return 'refresh'; }
        if(normalized === 'activer'){ return 'toggle-on'; }
        if(normalized === 'desactiver' || normalized === 'désactiver'){ return 'toggle-off'; }
        if(normalized === 'telecharger' || normalized === 'télécharger'){ return 'download'; }
        if(normalized === 'voir le document'){ return 'document'; }
        return '';
    }

    function acdcInjectGenericIconActionStyles(){
        if(document.getElementById('acdc-generic-icon-actions-style')){ return; }
        // ACDC 3.20.57 — neutralisation phase 1 : remplacement des valeurs
        // en dur (#E2B54B, #C99A2D, 18px) par les variables CSS du back office.
        // Le rôle de la fonction reste identique : poser un style minimal sur
        // les éléments .acdc-inline-icon-action générés par acdcPatchGenericActionContainers.
        var style = document.createElement('style');
        style.id = 'acdc-generic-icon-actions-style';
        style.textContent = '' +
            '.acdc-inline-icon-actions{display:flex !important;align-items:center;gap:var(--acdc-action-icon-gap, 14px);flex-wrap:wrap}' +
            '.acdc-inline-icon-action{display:inline-flex !important;align-items:center;justify-content:center;width:var(--acdc-action-icon-frame-size, 30px);height:var(--acdc-action-icon-frame-size, 30px);padding:0 !important;border:none !important;background:transparent !important;color:var(--acdc-action-icon-color, var(--acdc-icon-color, var(--acdc-primary, #8b5b23))) !important;text-decoration:none !important;line-height:1 !important;min-width:var(--acdc-action-icon-frame-size, 30px);box-shadow:none !important}' +
            '.acdc-inline-icon-action:hover{color:var(--acdc-action-icon-color, var(--acdc-icon-color, var(--acdc-primary, #8b5b23))) !important;background:transparent !important;opacity:.85}' +
            '.acdc-inline-icon-action svg{display:block;width:var(--acdc-action-icon-glyph-size, 16px);height:var(--acdc-action-icon-glyph-size, 16px)}' +
            '.acdc-inline-icon-action .acdc-inline-icon-label{position:absolute !important;width:1px !important;height:1px !important;padding:0 !important;margin:-1px !important;overflow:hidden !important;clip:rect(0,0,0,0) !important;white-space:nowrap !important;border:0 !important}' +
            '.acdc-inline-icon-separator{display:none !important}';
        document.head.appendChild(style);
    }

    function acdcElementActionLabel(el){
        if(!el){ return ''; }
        var label = (el.getAttribute('data-acdc-original-label') || el.getAttribute('aria-label') || el.getAttribute('title') || el.textContent || '');
        return String(label).trim();
    }

    function acdcPatchGenericActionElement(el){
        if(!el || el.dataset.acdcIconized === '1'){ return false; }
        var label = acdcElementActionLabel(el);
        var iconType = acdcActionIconType(label);
        if(!iconType){ return false; }
        el.dataset.acdcIconized = '1';
        el.setAttribute('data-acdc-original-label', label);
        el.setAttribute('aria-label', label);
        el.setAttribute('title', label);
        el.classList.add('acdc-inline-icon-action');
        el.innerHTML = acdcIconSvg(iconType) + '<span class="acdc-inline-icon-label">' + acdcEscapeHtml(label) + '</span>';
        return true;
    }

    function acdcContainerEligibleChildren(container){
        var nodes = Array.prototype.slice.call(container.childNodes || []);
        var actionElements = [];
        for(var i = 0; i < nodes.length; i++){
            var node = nodes[i];
            if(node.nodeType === 3){
                if((node.textContent || '').replace(/[|·•]/g, '').trim() !== ''){ return null; }
                continue;
            }
            if(node.nodeType !== 1){ return null; }
            var tag = (node.tagName || '').toLowerCase();
            if(tag !== 'a' && tag !== 'button'){ return null; }
            if(node.classList.contains('acdc-row-view-link') || node.classList.contains('acdc-row-edit-link') || node.classList.contains('acdc-row-delete-link') || node.classList.contains('acdc-row-menu-toggle') || node.classList.contains('acdc-table-action-trigger')){ return null; }
            if(!acdcActionIconType(acdcElementActionLabel(node))){ return null; }
            actionElements.push(node);
        }
        return actionElements.length ? actionElements : null;
    }

    function acdcPatchGenericActionContainers(){
        acdcInjectGenericIconActionStyles();
        document.querySelectorAll('table td').forEach(function(cell){
            if(cell.querySelector('.acdc-prospect-patch-actions') || cell.querySelector('.acdc-prospect-actions')){ return; }
            var candidates = [cell].concat(Array.prototype.slice.call(cell.querySelectorAll(':scope > div, :scope > p, :scope > span')));
            candidates.forEach(function(container){
                if(!container || container.dataset.acdcGenericIconsPatched === '1'){ return; }
                var actions = acdcContainerEligibleChildren(container);
                if(!actions){ return; }
                container.dataset.acdcGenericIconsPatched = '1';
                container.classList.add('acdc-inline-icon-actions');
                Array.prototype.slice.call(container.childNodes || []).forEach(function(node){
                    if(node.nodeType === 3 && (node.textContent || '').trim() !== ''){
                        var separator = document.createElement('span');
                        separator.className = 'acdc-inline-icon-separator';
                        separator.textContent = node.textContent;
                        container.replaceChild(separator, node);
                    }
                });
                actions.forEach(acdcPatchGenericActionElement);
            });
        });
    }

    function acdcInitProspectUiPatches(){
        acdcPatchProspectsActions();
        acdcPatchRdvModal();
        acdcPatchGenericActionContainers();
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', acdcInitProspectUiPatches);
    } else {
        acdcInitProspectUiPatches();
    }
    document.addEventListener('click', function(event){
        if(!event.target.closest('.acdc-prospect-patch-menu') && !event.target.closest('.acdc-prospect-patch-trigger')){
            acdcCloseAllProspectMenus();
        }
    });
    document.addEventListener('keydown', function(event){
        if(event.key === 'Escape'){ acdcCloseAllProspectMenus(); }
    });

    window.addEventListener('resize', function(){
        document.querySelectorAll('.acdc-prospect-patch-menu').forEach(function(menu){
            if(menu.style.display !== 'none' && menu._acdcProspectTrigger){
                acdcPositionProspectMenu(menu, menu._acdcProspectTrigger);
            }
        });
    });
    window.addEventListener('scroll', function(){
        document.querySelectorAll('.acdc-prospect-patch-menu').forEach(function(menu){
            if(menu.style.display !== 'none' && menu._acdcProspectTrigger){
                acdcPositionProspectMenu(menu, menu._acdcProspectTrigger);
            }
        });
    }, true);
    window.setTimeout(acdcInitProspectUiPatches, 150);
})();


(function(){
    var acdcKernel = window.AcdcUiKernel || null;
    function acdcGetFloatingMenus(){
        return Array.prototype.slice.call(document.querySelectorAll('[data-acdc-row-menu-dropdown], .acdc-row-menu-dropdown, [data-acdc-bpf-row-menu-dropdown], .acdc-bpf-action-menu, .acdc-prospect-action-dropdown, [data-acdc-table-action-menu], .acdc-table-action-menu'));
    }

    function acdcIsDropdownNode(node){
        return !!(node && node.nodeType === 1 && (node.matches('[data-acdc-row-menu-dropdown]') || node.matches('.acdc-row-menu-dropdown') || node.matches('[data-acdc-bpf-row-menu-dropdown]') || node.matches('.acdc-bpf-action-menu') || node.matches('.acdc-prospect-action-dropdown') || node.matches('[data-acdc-table-action-menu]') || node.matches('.acdc-table-action-menu')));
    }

    function acdcCloseFloatingRowMenus(except){
        document.querySelectorAll('[data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, [data-acdc-prospect-menu-toggle], .acdc-prospect-patch-trigger, .acdc-table-action-trigger').forEach(function(btn){
            var menu = btn._acdcFloatingMenu || null;
            if(!except || menu !== except){ btn.setAttribute('aria-expanded','false'); }
        });
        var menus = acdcGetFloatingMenus().filter(function(menu){ return menu !== except && acdcIsDropdownNode(menu); });
        if(acdcKernel && typeof acdcKernel.hideElements === 'function'){
            acdcKernel.hideElements(menus);
            return;
        }
        menus.forEach(function(menu){
            menu.hidden = true;
            menu.style.display = 'none';
            menu.setAttribute('aria-hidden','true');
        });
    }

    function acdcEnsureFloatingMenu(menu){
        if(!menu || !acdcIsDropdownNode(menu) || menu.dataset.acdcFloatingReady === '1'){ return; }
        menu.dataset.acdcFloatingReady = '1';
        menu.classList.add('acdc-row-menu-dropdown-floating');
        menu._acdcOriginalParent = menu.parentNode;
        menu._acdcOriginalNext = menu.nextSibling;
        document.body.appendChild(menu);
        menu.hidden = true;
        menu.style.display = 'none';
        menu.setAttribute('aria-hidden','true');
    }

    function acdcPositionFloatingMenu(menu, trigger){
        if(!menu || !trigger){ return; }
        if(acdcKernel && typeof acdcKernel.positionFloatingElement === 'function'){
            acdcKernel.positionFloatingElement(menu, trigger, { align:'right', gap:8, minWidth:220 });
            return;
        }
        var rect = trigger.getBoundingClientRect();
        var gap = 8;
        menu.hidden = false;
        menu.style.display = 'block';
        var width = Math.max(menu.offsetWidth || 220, 220);
        var height = menu.offsetHeight || 0;
        var left = rect.right - width;
        var top = rect.bottom + gap;
        if(left < 12){ left = 12; }
        if(left + width > window.innerWidth - 12){ left = Math.max(12, window.innerWidth - width - 12); }
        if(top + height > window.innerHeight - 12){
            var above = rect.top - height - gap;
            top = above >= 12 ? above : Math.max(12, window.innerHeight - height - 12);
        }
        menu.style.left = Math.round(left) + 'px';
        menu.style.top = Math.round(top) + 'px';
        menu.setAttribute('aria-hidden','false');
    }

    function acdcBindFloatingTrigger(trigger){
        if(!trigger || trigger.dataset.acdcFloatingBound === '1'){ return; }
        var wrapper = trigger.closest('[data-acdc-row-menu], .acdc-row-menu, [data-acdc-bpf-row-menu], .acdc-row-actions-menu-cell, .acdc-bpf-actions, .acdc-table-actions, .acdc-prospect-action-menu');
        var menu = null;
        if(wrapper){
            menu = wrapper.querySelector('[data-acdc-row-menu-dropdown], .acdc-row-menu-dropdown, [data-acdc-bpf-row-menu-dropdown], .acdc-bpf-action-menu, .acdc-prospect-action-dropdown, [data-acdc-table-action-menu], .acdc-table-action-menu');
        }
        if((!menu || !acdcIsDropdownNode(menu)) && trigger.matches('.acdc-table-action-trigger')){
            var targetId = trigger.getAttribute('data-acdc-menu-target');
            if(targetId){
                menu = document.getElementById(targetId);
            }
        }
        if(!menu || !acdcIsDropdownNode(menu)){ return; }
        trigger.dataset.acdcFloatingBound = '1';
        acdcEnsureFloatingMenu(menu);
        trigger._acdcFloatingMenu = menu;
        menu._acdcFloatingTrigger = trigger;
        trigger.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            var open = trigger.getAttribute('aria-expanded') === 'true';
            acdcCloseFloatingRowMenus(open ? null : menu);
            if(open){
                menu.hidden = true;
                menu.style.display = 'none';
                menu.setAttribute('aria-hidden','true');
                trigger.setAttribute('aria-expanded','false');
                return;
            }
            acdcPositionFloatingMenu(menu, trigger);
            trigger.setAttribute('aria-expanded','true');
        });
        menu.querySelectorAll('a, button').forEach(function(el){
            el.addEventListener('click', function(){ acdcCloseFloatingRowMenus(); });
        });
    }

    function acdcInitFloatingRowMenus(){
        document.querySelectorAll('[data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, [data-acdc-prospect-menu-toggle], .acdc-table-action-trigger').forEach(acdcBindFloatingTrigger);
    }



    function acdcCreateActionIconSvg(type){
        var paths = {
            more: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5" cy="12" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="19" cy="12" r="1.5"></circle></svg>',
            view: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
            edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path></svg>',
            delete: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>'
        };
        return paths[type] || '';
    }

    function acdcNormalizeActionLabel(text){
        return (text || '')
            .replace(/\s+/g, ' ')
            .replace(/[|·•]+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function acdcInferActionType(node){
        if(!node){ return ''; }
        var text = acdcNormalizeActionLabel(node.getAttribute('aria-label') || node.getAttribute('title') || node.textContent || '');
        var href = ((node.getAttribute('href') || '') + ' ' + (node.getAttribute('onclick') || '')).toLowerCase();
        var cls = (node.className || '').toLowerCase();
        if(text.indexOf('supprim') !== -1 || href.indexOf('delete') !== -1 || cls.indexOf('delete') !== -1 || href.indexOf('supprimer') !== -1){ return 'delete'; }
        if(text.indexOf('modifi') !== -1 || href.indexOf('action=edit') !== -1 || cls.indexOf('edit') !== -1){ return 'edit'; }
        if(text.indexOf('voir') !== -1 || text.indexOf('consulter') !== -1 || href.indexOf('action=view') !== -1 || cls.indexOf('view') !== -1){ return 'view'; }
        return '';
    }

    function acdcDecorateActionControl(node, forcedType){
        if(!node || node.dataset.acdcIconized === '1'){ return; }
        /* ACDC 3.20.62 — Exclusion Prospects.
         * Le module Prospects rend ses propres SVG via le PHP CRM (qui consulte
         * ACDC_ACTION_HUB_CONFIG depuis la 3.20.61). Si ce moteur écrase le
         * contenu, le presse-papier du Suivi commercial est remplacé par
         * un œil parce que acdcInferActionType voit "action=view" dans l'URL.
         * On préserve donc le rendu Prospects en sortant silencieusement.
         */
        if(node.closest && node.closest('.acdc-prospect-actions, .acdc-prospect-action-menu, .acdc-prospect-action-dropdown, .acdc-prospect-patch-actions')){ return; }
        var type = forcedType || acdcInferActionType(node);
        if(!type){ return; }
        var label = node.getAttribute('aria-label') || node.getAttribute('title') || node.textContent || '';
        node.dataset.acdcIconized = '1';
        node.classList.add('acdc-row-action-icon');
        if(type === 'view'){ node.classList.add('acdc-row-view-link'); }
        if(type === 'edit'){ node.classList.add('acdc-row-edit-link'); }
        if(type === 'delete'){ node.classList.add('acdc-row-delete-link'); }
        node.setAttribute('title', label.trim() || (type === 'view' ? 'Voir' : type === 'edit' ? 'Modifier' : 'Supprimer'));
        node.setAttribute('aria-label', node.getAttribute('title'));
        node.innerHTML = '<span class="acdc-row-action-icon-svg" aria-hidden="true">' + acdcCreateActionIconSvg(type) + '</span><span class="screen-reader-text">' + node.getAttribute('title') + '</span>';
    }

    function acdcDecorateMenuTrigger(node){
        if(!node || node.dataset.acdcIconized === '1'){ return; }
        /* ACDC 3.20.62 — Exclusion Prospects (cf. acdcDecorateActionControl). */
        if(node.closest && node.closest('.acdc-prospect-actions, .acdc-prospect-action-menu, .acdc-prospect-action-dropdown, .acdc-prospect-patch-actions')){ return; }
        node.dataset.acdcIconized = '1';
        node.classList.add('acdc-row-action-icon','acdc-row-menu-toggle');
        node.setAttribute('title', node.getAttribute('title') || 'Actions');
        node.setAttribute('aria-label', node.getAttribute('aria-label') || 'Actions');
        node.innerHTML = '<span class="acdc-row-action-icon-svg" aria-hidden="true">' + acdcCreateActionIconSvg('more') + '</span><span class="screen-reader-text">Actions</span>';
    }

    function acdcFindActionColumnIndexes(table){
        var indexes = [];
        if(!table){ return indexes; }
        var headers = table.querySelectorAll('thead th');
        headers.forEach(function(th, idx){
            var txt = acdcNormalizeActionLabel(th.textContent || '');
            if(txt === 'actions' || txt === 'action'){
                indexes.push(idx);
            }
        });
        return indexes;
    }

    function acdcIconizeTextActions(root){
        root = root || document;

        root.querySelectorAll('[data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, .acdc-table-action-trigger').forEach(acdcDecorateMenuTrigger);
        root.querySelectorAll('.acdc-row-view-link, .acdc-row-edit-link, .acdc-row-delete-link').forEach(function(node){
            acdcDecorateActionControl(node, '');
        });

        root.querySelectorAll('table').forEach(function(table){
            var actionIndexes = acdcFindActionColumnIndexes(table);
            if(!actionIndexes.length){ return; }
            table.querySelectorAll('tbody tr').forEach(function(tr){
                actionIndexes.forEach(function(index){
                    var cell = tr.children[index];
                    if(!cell){ return; }
                    cell.classList.add('acdc-actions-cell-icons');
                    cell.querySelectorAll('a, button').forEach(function(node){
                        if(node.matches('[data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, .acdc-table-action-trigger')){
                            acdcDecorateMenuTrigger(node);
                            return;
                        }
                        // FIX v3.19.70 — ne jamais décorer en icône les liens situés
                        // à l'intérieur d'un dropdown de menu. Ces liens sont des items
                        // textuels (ex: "Ajouter un rendez-vous", "Recueil des besoins", "Devis")
                        // et leur URL peut contenir "action=view" ou "action=edit"
                        // pour des raisons fonctionnelles (pré-sélection de prospect)
                        // sans que l'intention sémantique soit "Voir"/"Modifier".
                        // Les transformer en icône fait apparaître un œil bronze
                        // à la place du label textuel.
                        if(node.closest('.acdc-row-menu-dropdown, .acdc-bpf-action-menu, .acdc-prospect-action-dropdown, .acdc-table-action-menu, .acdc-row-menu, .acdc-prospect-patch-menu, .acdc-followup-menu-panel')){
                            return;
                        }
                        acdcDecorateActionControl(node, '');
                    });
                    Array.prototype.slice.call(cell.childNodes).forEach(function(child){
                        if(child.nodeType === 3 && child.textContent && child.textContent.replace(/[\s|·•]+/g, '') === ''){
                            child.textContent = '';
                        }
                    });
                });
            });
        });
    }

    function acdcObserveActionIconization(){
        if(!window.MutationObserver){ return; }
        var observer = new MutationObserver(function(mutations){
            var shouldRefresh = false;
            mutations.forEach(function(mutation){
                if(shouldRefresh){ return; }
                mutation.addedNodes.forEach(function(node){
                    if(shouldRefresh || !node || node.nodeType !== 1){ return; }
                    if(node.matches && (node.matches('table, tr, td, a, button, [data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, .acdc-table-action-trigger') || node.querySelector('table, tr, td, a, button, [data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, .acdc-table-action-trigger'))){
                        shouldRefresh = true;
                    }
                });
            });
            if(shouldRefresh){
                acdcIconizeTextActions(document);
                acdcInitFloatingRowMenus();
            }
        });
        observer.observe(document.body, {childList:true, subtree:true});
    }

    document.addEventListener('click', function(event){
        var trigger = event.target.closest('[data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, [data-acdc-prospect-menu-toggle], .acdc-table-action-trigger');
        if(trigger && trigger.dataset.acdcFloatingBound !== '1'){
            acdcBindFloatingTrigger(trigger);
        }
    }, true);
    document.addEventListener('DOMContentLoaded', function(){ acdcIconizeTextActions(document); acdcInitFloatingRowMenus(); acdcObserveActionIconization(); });
    document.addEventListener('click', function(event){
        if(!event.target.closest('[data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, [data-acdc-prospect-menu-toggle], .acdc-prospect-patch-trigger, .acdc-table-action-trigger, .acdc-row-menu-dropdown-floating, .acdc-table-action-menu')){
            acdcCloseFloatingRowMenus();
        }
    });
    document.addEventListener('keydown', function(event){ if(event.key === 'Escape'){ acdcCloseFloatingRowMenus(); } });
    window.addEventListener('scroll', function(){
        acdcGetFloatingMenus().forEach(function(menu){
            if(!menu.hidden && menu._acdcFloatingTrigger){ acdcPositionFloatingMenu(menu, menu._acdcFloatingTrigger); }
        });
    }, true);
    window.addEventListener('resize', function(){
        acdcGetFloatingMenus().forEach(function(menu){
            if(!menu.hidden && menu._acdcFloatingTrigger){ acdcPositionFloatingMenu(menu, menu._acdcFloatingTrigger); }
        });
    });

    var acdcUniversalMenuTriggerSelector = '[data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, [data-acdc-prospect-menu-toggle], .acdc-prospect-patch-trigger, .acdc-table-action-trigger';
    var acdcUniversalMenuWrapperSelector = '[data-acdc-row-menu], .acdc-row-actions-menu-cell, .acdc-row-menu, [data-acdc-bpf-row-menu], .acdc-bpf-actions, .acdc-table-actions, .acdc-prospect-action-menu';
    var acdcUniversalMenuDropdownSelector = '[data-acdc-row-menu-dropdown], .acdc-row-menu-dropdown, [data-acdc-bpf-row-menu-dropdown], .acdc-bpf-action-menu, .acdc-prospect-action-dropdown, [data-acdc-table-action-menu], .acdc-table-action-menu';

    function acdcUniversalHideMenu(menu){
        if(!menu){ return; }
        menu.hidden = true;
        if(menu.classList.contains('acdc-row-menu') || menu.classList.contains('acdc-bpf-action-menu') || menu.classList.contains('acdc-table-action-menu')){
            menu.style.display = '';
        }
    }

    function acdcUniversalShowMenu(menu, trigger){
        if(!menu){ return; }
        menu.hidden = false;
        if(menu.classList.contains('acdc-row-menu') || menu.classList.contains('acdc-bpf-action-menu') || menu.classList.contains('acdc-table-action-menu')){
            menu.style.display = 'block';
        }
        if(menu.classList.contains('acdc-row-menu-dropdown-floating')){
            acdcPositionFloatingMenu(menu, trigger);
        }
    }

    function acdcUniversalCloseAllMenus(){
        document.querySelectorAll(acdcUniversalMenuWrapperSelector).forEach(function(wrapper){ wrapper.classList.remove('is-open'); });
        document.querySelectorAll(acdcUniversalMenuDropdownSelector).forEach(acdcUniversalHideMenu);
        document.querySelectorAll(acdcUniversalMenuTriggerSelector).forEach(function(btn){ btn.setAttribute('aria-expanded', 'false'); });
    }

    function acdcUniversalFindMenu(trigger){
        if(!trigger){ return null; }
        // FIX v3.19.70 — quand le dropdown a été déplacé vers document.body par
        // acdcEnsureFloatingMenu (règle permanente "menu 3 points hors tableau en
        // position fixed"), les fallbacks DOM-relatifs ci-dessous retournent null
        // car le dropdown n'est plus ni dans le wrapper, ni sibling, ni parent du
        // trigger. La référence directe posée par acdcBindFloatingTrigger doit
        // être consultée EN PREMIER. Sans ce fallback, le clic sur l'icône 3 points
        // n'ouvrait plus aucun menu nulle part dans le plugin.
        if(trigger._acdcFloatingMenu && acdcIsDropdownNode(trigger._acdcFloatingMenu)){
            return trigger._acdcFloatingMenu;
        }
        var targetId = trigger.getAttribute('data-acdc-menu-target');
        if(targetId){
            var byId = document.getElementById(targetId);
            if(byId){ return byId; }
        }
        var wrapper = trigger.closest(acdcUniversalMenuWrapperSelector);
        if(wrapper){
            var inside = wrapper.querySelector(acdcUniversalMenuDropdownSelector);
            if(inside && inside !== trigger){ return inside; }
        }
        var sibling = trigger.nextElementSibling;
        if(sibling && sibling.matches(acdcUniversalMenuDropdownSelector)){
            return sibling;
        }
        if(trigger.parentElement){
            var nearby = trigger.parentElement.querySelector(acdcUniversalMenuDropdownSelector);
            if(nearby && nearby !== trigger){ return nearby; }
        }
        return null;
    }

    document.addEventListener('click', function(event){
        var trigger = event.target.closest(acdcUniversalMenuTriggerSelector);
        if(trigger){
            // FIX v3.19.70 — ne capturer le clic QUE si on sait gérer ce menu.
            // Certains traits (ex: documents-billing) utilisent des dropdowns en
            // <div class="acdc-row-menu"> gérés par leur propre JS local avec le
            // pattern is-open sur le td parent. Ces menus ne sont pas reconnus par
            // acdcIsDropdownNode et retournaient null via acdcUniversalFindMenu.
            // Avant ce fix, on faisait quand même stopImmediatePropagation →
            // le JS local ne se déclenchait jamais → menu jamais ouvert.
            var menu = acdcUniversalFindMenu(trigger);
            if(!menu){
                // Aucun menu géré par le système universel : on laisse le JS local
                // du trait prendre le relais (pas de preventDefault, pas de stop).
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            if(typeof event.stopImmediatePropagation === 'function'){
                event.stopImmediatePropagation();
            }
            var wrapper = trigger.closest(acdcUniversalMenuWrapperSelector);
            var isOpen = !!(menu && !menu.hidden && (menu.style.display === 'block' || !menu.classList.contains('acdc-row-menu')));
            acdcUniversalCloseAllMenus();
            if(menu && !isOpen){
                if(wrapper){ wrapper.classList.add('is-open'); }
                trigger.setAttribute('aria-expanded', 'true');
                acdcUniversalShowMenu(menu, trigger);
            }
            return;
        }
        if(!event.target.closest(acdcUniversalMenuDropdownSelector)){
            acdcUniversalCloseAllMenus();
        }
    }, true);

    setTimeout(function(){ acdcIconizeTextActions(document); acdcInitFloatingRowMenus(); }, 150);
})();

/* ACDC 3.20.56 — moteur unique transverse des actions de listes.
 * Objectif : appliquer le même rendu d'actions sur toutes les pages ACDC,
 * y compris les listes qui n'avaient que des liens texte ou des boutons sans icône.
 */
(function(window, document){
  'use strict';
  if (!window || !document) { return; }

  var RUN_LOCK = false;
  var ROOT_SELECTOR = '.acdc-admin-page, .acdc-front-page, .acdc-saas-front, .wrap';
  var ACTION_HEADER_RE = /^(actions?|opérations?|gestion)$/i;
  var ACTION_LABEL_RE = /(voir|visualiser|consulter|ouvrir|modifier|éditer|corriger|supprimer|effacer|retirer|analyse du besoin|besoin|devis|convention|contrat|inscrire|inscription|relancer|rendez-vous|rdv|envoyer|mail|email|courriel|pdf|document|télécharger|telecharger|imprimer|dupliquer|copier|répliquer|repliquer|activer|désactiver|desactiver|archiver|restaurer|clôturer|cloturer|valider|annuler|répondre|repondre|résultat|resultat|attestation|certificat|convocation|programme|quiz|test|enquête|enquete|évaluation|evaluation)/i;

  var ICONS = {
    view: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.8"></circle></svg>',
    edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4.5L19 9.5a2.1 2.1 0 0 0 0-3L17.5 5a2.1 2.1 0 0 0-3 0L4 15.5V20Z"></path><path d="M13.5 6.5l4 4"></path></svg>',
    delete: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"></path><path d="M9 7V5h6v2"></path><path d="M7 7l1 13h8l1-13"></path><path d="M10 11v5"></path><path d="M14 11v5"></path></svg>',
    need: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5Z"></path><path d="M8 8h8"></path><path d="M8 12h8"></path><path d="M8 16h5"></path></svg>',
    quote: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6Z"></path><path d="M15 3v4h4"></path><path d="M8.5 12h7"></path><path d="M8.5 16h5"></path><path d="M10 9h1.5"></path></svg>',
    contract: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6Z"></path><path d="M15 3v4h4"></path><path d="M9 11h6"></path><path d="M9 15h4"></path><path d="M14 18l2 2 4-5"></path></svg>',
    signup: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path><path d="M4 21a8 8 0 0 1 11-7.4"></path><path d="M18 14v6"></path><path d="M15 17h6"></path></svg>',
    followup: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v10H8l-3 3Z"></path><path d="M8 9h8"></path><path d="M8 12h5"></path></svg>',
    calendar: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v15H5Z"></path><path d="M8 3v4"></path><path d="M16 3v4"></path><path d="M5 9h14"></path><path d="M8 13h3"></path><path d="M13 13h3"></path></svg>',
    send: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 21 4l-7.5 18-3-7.5Z"></path><path d="M21 4 10.5 14.5"></path></svg>',
    document: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6Z"></path><path d="M15 3v4h4"></path><path d="M9 12h6"></path><path d="M9 16h6"></path></svg>',
    print: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V4h10v4"></path><path d="M7 17H5a2 2 0 0 1-2-2v-4h18v4a2 2 0 0 1-2 2h-2"></path><path d="M7 14h10v7H7Z"></path></svg>',
    copy: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h11v13H8Z"></path><path d="M5 16H4V3h11v1"></path></svg>',
    toggle: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12h8"></path><path d="M12 8v8"></path><circle cx="12" cy="12" r="9"></circle></svg>',
    archive: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v14H4Z"></path><path d="M3 3h18v4H3Z"></path><path d="M9 12h6"></path></svg>',
    validate: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>',
    cancel: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M8 8l8 8"></path><path d="M16 8l-8 8"></path></svg>',
    more: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.7"></circle><circle cx="12" cy="12" r="1.7"></circle><circle cx="12" cy="19" r="1.7"></circle></svg>'
  };

  function textOf(el){
    if (!el) { return ''; }
    var value = '';
    if (el.getAttribute) {
      value = el.getAttribute('data-acdc-action-label') || el.getAttribute('data-acdc-original-label') || el.getAttribute('aria-label') || el.getAttribute('title') || '';
    }
    if (!value && (el.tagName === 'INPUT' || el.tagName === 'BUTTON')) { value = el.value || ''; }
    if (!value) { value = (el.textContent || '').replace(/\s+/g, ' ').trim(); }
    return value.replace(/\s+/g, ' ').trim();
  }
  function cleanLabel(label){
    label = (label || '').replace(/\s+/g, ' ').trim();
    if (!label || label === '…' || label === '...' || label === '⋮') { return 'Actions complémentaires'; }
    return label.charAt(0).toUpperCase() + label.slice(1);
  }
  function escapeHtml(value){
    return String(value || '').replace(/[&<>"]/g, function(chr){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[chr]; });
  }
  function actionType(label, el){
    var t = (label || '').toLowerCase();
    var href = el && el.getAttribute ? (el.getAttribute('href') || '').toLowerCase() : '';
    var cls = el && el.className ? String(el.className).toLowerCase() : '';
    var haystack = t + ' ' + href + ' ' + cls;
    /* ACDC 3.20.60 — les tests sémantiques précis (followup, need, quote, etc.)
     * doivent passer AVANT le test générique 'view' qui matche 'action=view'
     * dans presque toutes les URLs. Sans ce réordonnancement, le label
     * "Suivi commercial" était classé en 'view' parce que son URL contient
     * action=view, et donc affichait l'œil au lieu du presse-papier. */
    if (/supprimer|effacer|retirer|delete|trash|remove/.test(haystack)) { return 'delete'; }
    if (/modifier|éditer|editer|edit|corriger/.test(haystack)) { return 'edit'; }
    /* Tests sémantiques précis prioritaires : ils s'appuient sur le LABEL
     * (mot précis présent dans le texte du bouton ou son aria-label). */
    if (/relancer|relance|follow|suivi commercial|suivi-commercial|\bsuivi\b/.test(t)) { return 'followup'; }
    if (/analyse du besoin|analyse-des-besoins|besoin|needs?/.test(t)) { return 'need'; }
    if (/devis|quote|proposal/.test(t)) { return 'quote'; }
    if (/convention|contrat|contract/.test(t)) { return 'contract'; }
    if (/inscrire|inscription|en cours d'inscription|signup|register/.test(t)) { return 'signup'; }
    if (/rendez-vous|rendez vous|rdv|calendar|calendrier|planning/.test(t)) { return 'calendar'; }
    /* Test générique view : APRÈS les tests sémantiques. */
    if (/voir|visualiser|consulter|ouvrir|fiche|view|show|open/.test(haystack)) { return 'view'; }
    /* Filets de sécurité haystack (ancien comportement) si le label seul
     * n'a pas suffi : on ratisse aussi href et cls pour ces types. */
    if (/relancer|relance|follow|suivi-commercial/.test(haystack)) { return 'followup'; }
    if (/analyse-des-besoins|\bbesoin\b|\bneed\b/.test(haystack)) { return 'need'; }
    if (/\bdevis\b|\bquote\b/.test(haystack)) { return 'quote'; }
    if (/\bconvention\b|\bcontrat\b|\bcontract\b/.test(haystack)) { return 'contract'; }
    if (/\binscrire\b|\binscription\b|\bsignup\b|\bregister\b/.test(haystack)) { return 'signup'; }
    if (/\brdv\b|calendrier|planning/.test(haystack)) { return 'calendar'; }
    if (/envoyer|email|mail|courriel|send/.test(haystack)) { return 'send'; }
    if (/pdf|document|télécharger|telecharger|download|attestation|certificat|convocation|programme/.test(haystack)) { return 'document'; }
    if (/imprimer|print/.test(haystack)) { return 'print'; }
    if (/dupliquer|copier|répliquer|repliquer|copy|duplicate/.test(haystack)) { return 'copy'; }
    if (/activer|désactiver|desactiver|enable|disable/.test(haystack)) { return 'toggle'; }
    if (/archiver|archive|restaurer|restore/.test(haystack)) { return 'archive'; }
    if (/valider|clôturer|cloturer|terminer|confirmer|check|done/.test(haystack)) { return 'validate'; }
    if (/annuler|cancel/.test(haystack)) { return 'cancel'; }
    return 'more';
  }
  function isVisible(el){
    if (!el || el.nodeType !== 1) { return false; }
    if (el.hidden || el.getAttribute('aria-hidden') === 'true') { return false; }
    var style = window.getComputedStyle ? window.getComputedStyle(el) : null;
    if (style && (style.display === 'none' || style.visibility === 'hidden')) { return false; }
    return true;
  }
  function isActionElement(el){
    if (!el || el.nodeType !== 1 || !isVisible(el)) { return false; }
    if (el.closest('.acdc-no-action-hub, .tablenav, .pagination-links, .nav-tab-wrapper, .subsubsub, .notice, .acdc-admin-sidebar')) { return false; }
    if (el.closest('.acdc-action-hub-menu, .acdc-row-menu')) { return false; }
    /* ACDC 3.20.59 — exclusion explicite des Prospects et de leur menu d'actions
     * complémentaires. Le module Prospects rend déjà ses propres SVG via le PHP
     * (render_inline_icon) avec son CSS dédié. Le passage par AcdcActionHub
     * réécrit les boutons avec un menu trois points vertical et remplace le
     * clipboard du Suivi commercial par un œil (parce que l'URL contient
     * action=view). L'exclusion préserve l'intention du rendu PHP.
     */
    if (el.closest('.acdc-prospect-actions, .acdc-prospect-action-menu, .acdc-prospect-action-dropdown, .acdc-prospect-patch-actions')) { return false; }
    if (el.hasAttribute('data-acdc-no-iconize')) { return false; }
    if (el.classList.contains('acdc-row-menu-toggle')) { return true; }
    var tag = el.tagName;
    if (tag !== 'A' && tag !== 'BUTTON' && tag !== 'INPUT') { return false; }
    if (tag === 'INPUT') {
      var type = (el.getAttribute('type') || '').toLowerCase();
      if (type !== 'submit' && type !== 'button') { return false; }
    }
    var label = textOf(el);
    var href = el.getAttribute ? (el.getAttribute('href') || '') : '';
    return ACTION_LABEL_RE.test(label) || ACTION_LABEL_RE.test(href) || /page-title-action|acdc-.*action|delete|edit|view/.test(String(el.className || '').toLowerCase());
  }
  /* ACDC 3.20.60 — résolveur de SVG piloté par le back office.
   * Lit ACDC_ACTION_HUB_CONFIG si disponible (injecté par wp_localize_script
   * depuis get_action_hub_config()). Si la config fournit un glyphe choisi
   * pour ce type, on l'utilise. Sinon on retombe sur le SVG en dur ICONS[type].
   * Cette résolution garantit que toute défaillance de la config (config
   * absente, type inconnu, glyphe invalide) ne casse rien : le moteur
   * continue de fonctionner avec son comportement antérieur.
   */
  function resolveSvg(type){
    var cfg = (typeof window !== 'undefined') ? window.ACDC_ACTION_HUB_CONFIG : null;
    if (cfg && cfg.icons && cfg.glyphs) {
      var glyphName = cfg.icons[type];
      if (glyphName && cfg.glyphs[glyphName]) {
        return cfg.glyphs[glyphName];
      }
    }
    return ICONS[type] || ICONS.more;
  }

  function iconize(el){
    if (!el || el.nodeType !== 1 || !isActionElement(el)) { return; }
    var label = cleanLabel(textOf(el));
    var type = actionType(label, el);
    var svg = resolveSvg(type);
    el.classList.add('acdc-action-hub-btn', 'acdc-action-hub-btn--' + type);
    el.setAttribute('data-acdc-action-label', label);
    el.setAttribute('aria-label', label);
    el.setAttribute('title', label);
    if (el.tagName === 'INPUT') { el.classList.add('acdc-action-hub-input'); return; }
    var currentHtml = el.innerHTML || '';
    if (currentHtml.indexOf('acdc-action-hub-sr') === -1 || !el.querySelector('svg')) {
      el.innerHTML = svg + '<span class="acdc-action-hub-sr">' + escapeHtml(label) + '</span>';
    }
  }
  function actionIndexForTable(table){
    var headers = Array.prototype.slice.call(table.querySelectorAll('thead tr:last-child th, thead tr:last-child td'));
    var indexes = [];
    headers.forEach(function(th, index){
      var txt = (th.textContent || '').replace(/\s+/g, ' ').trim();
      if (ACTION_HEADER_RE.test(txt)) { indexes.push(index); }
    });
    return indexes;
  }
  function ensureCellWrapper(cell){
    if (!cell || cell.querySelector(':scope > .acdc-action-hub-wrap')) { return; }
    if (cell.querySelector('form')) { return; }
    var children = Array.prototype.slice.call(cell.childNodes);
    if (!children.some(function(n){ return n.nodeType === 1; })) { return; }
    var wrap = document.createElement('div');
    wrap.className = 'acdc-action-hub-wrap';
    children.forEach(function(node){ wrap.appendChild(node); });
    cell.appendChild(wrap);
  }
  function processCell(cell){
    if (!cell || cell.nodeType !== 1 || cell.closest('thead, tfoot')) { return; }
    var candidates = Array.prototype.slice.call(cell.querySelectorAll('a, button, input[type="submit"], input[type="button"]')).filter(isActionElement);
    if (!candidates.length) { return; }
    cell.classList.add('acdc-action-hub-cell');
    ensureCellWrapper(cell);
    candidates.forEach(function(el){
      var form = el.closest('form');
      if (form && cell.contains(form)) { form.classList.add('acdc-action-hub-form'); }
      iconize(el);
    });
  }
  function looksLikeActionCell(cell){
    if (!cell || cell.nodeType !== 1 || cell.closest('thead, tfoot')) { return false; }
    if (cell.classList.contains('acdc-actions-cell') || cell.classList.contains('acdc-action-hub-cell')) { return true; }
    var cls = String(cell.className || '').toLowerCase();
    if (/(actions?|operation)/.test(cls)) { return true; }
    var candidates = Array.prototype.slice.call(cell.querySelectorAll('a, button, input[type="submit"], input[type="button"]')).filter(isActionElement);
    if (!candidates.length) { return false; }
    var text = (cell.textContent || '').replace(/\s+/g, ' ').trim();
    return ACTION_LABEL_RE.test(text) || candidates.length >= 2;
  }
  function processTable(table){
    if (!table || table.nodeType !== 1) { return; }
    var indexes = actionIndexForTable(table);
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
    if (indexes.length) {
      rows.forEach(function(row){
        var cells = Array.prototype.slice.call(row.children);
        indexes.forEach(function(index){ if (cells[index]) { processCell(cells[index]); } });
      });
      return;
    }
    rows.forEach(function(row){
      var cells = Array.prototype.slice.call(row.children);
      if (cells.length < 2) { return; }
      var last = cells[cells.length - 1];
      if (looksLikeActionCell(last)) { processCell(last); }
    });
  }
  function processLooseActionZones(root){
    var selectors = ['.acdc-actions-cell', '.acdc-record-actions', '.acdc-page-actions', '.acdc-toolbar-actions', '.acdc-card-actions', '.acdc-form-actions'];
    Array.prototype.slice.call(root.querySelectorAll(selectors.join(','))).forEach(function(zone){
      if (zone.getAttribute('data-acdc-no-iconize')) { return; }
      if (zone.tagName === 'TD' || zone.tagName === 'TH') { processCell(zone); return; }
      var elements = Array.prototype.slice.call(zone.querySelectorAll('a, button, input[type="submit"], input[type="button"]')).filter(isActionElement);
      if (!elements.length) { return; }
      zone.classList.add('acdc-action-hub-zone');
      elements.forEach(iconize);
    });
  }
  function normalize(root){
    if (RUN_LOCK) { return; }
    RUN_LOCK = true;
    try {
      root = root || document;
      var scope = root;
      if (root.nodeType === 1 && !root.matches(ROOT_SELECTOR) && !root.querySelector(ROOT_SELECTOR)) { scope = document; }
      Array.prototype.slice.call(scope.querySelectorAll('table')).forEach(processTable);
      processLooseActionZones(scope);
    } finally { RUN_LOCK = false; }
  }
  function schedule(root){
    window.clearTimeout(window.__acdcActionHubTimer);
    window.__acdcActionHubTimer = window.setTimeout(function(){ normalize(root || document); }, 80);
  }
  window.AcdcActionHub = window.AcdcActionHub || {};
  window.AcdcActionHub.normalize = normalize;
  window.AcdcActionHub.schedule = schedule;
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function(){ schedule(document); }); }
  else { schedule(document); }
  window.addEventListener('load', function(){ schedule(document); });
  document.addEventListener('acdc:content-updated', function(e){ schedule(e && e.target ? e.target : document); });
  if (window.MutationObserver) {
    var observer = new MutationObserver(function(mutations){
      var shouldRun = mutations.some(function(m){
        return Array.prototype.slice.call(m.addedNodes || []).some(function(node){
          return node.nodeType === 1 && node.matches && (node.matches('table, .acdc-actions-cell, .acdc-record-actions, .acdc-page-actions') || node.querySelector('table, .acdc-actions-cell, .acdc-record-actions, .acdc-page-actions'));
        });
      });
      if (shouldRun) { schedule(document); }
    });
    observer.observe(document.documentElement || document.body, { childList: true, subtree: true });
  }
})(window, document);
