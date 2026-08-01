/**
 * ACDC Formation SAAS — Module Quizzes (JS éditeur)
 *
 * Couvre :
 *  - Drag & drop de la liste des questions (jQuery UI Sortable).
 *  - Sauvegarde AJAX au fil de l'eau (debounced) à chaque changement.
 *  - Ouverture/fermeture des modales (paramètres globaux, objectifs,
 *    création, dupliquer-depuis).
 *  - Intégration de la bibliothèque média WordPress.
 *  - Menus contextuels ⋯ sur les cartes de la liste.
 *  - Confirmations destructives (supprimer, archiver).
 *
 * Données injectées par wp_localize_script via la variable acdcQzEditor :
 *   { ajaxUrl, nonce, i18n }
 *
 * @since 3.21.01
 */

( function( $ ) {
    'use strict';

    var QzEditor = {

        /**
         * Délai de debounce de l'autosave en ms.
         */
        AUTOSAVE_DELAY: 800,

        /**
         * Map question_id => timer de debounce.
         */
        _saveTimers: {},

        /**
         * Initialise les écouteurs sur le document.
         */
        init: function() {
            this.bindModals();
            this.bindCardMenus();
            this.bindCardActions();
            this.bindTabSwitching();
            this.bindEditor();
            this.bindObjectives();
            this.bindMediaPicker();
            // Ouverture automatique modale d'envoi si redirigé depuis le menu 3 points
            var urlParams = new URLSearchParams( window.location.search );
            if ( urlParams.get( 'qz_action' ) === 'open_send' ) {
                setTimeout( function() {
                    var $modal = $( '#acdc-qz-modal-send-async' );
                    if ( $modal.length ) {
                        $modal.attr( 'hidden', null ).removeAttr( 'hidden' );
                        $modal.find( 'input, select, textarea, button' ).first().focus();
                    }
                }, 200 );
            }
        },

        /* --------------------------------------------------------- */
        /*  Modales                                                  */
        /* --------------------------------------------------------- */

        bindModals: function() {
            // Bouton qui ouvre une modale (data-target="acdc-qz-modal-...")
            $( document ).on( 'click', '[data-target^="acdc-qz-modal-"]', function( e ) {
                e.preventDefault();
                var target = $( this ).data( 'target' );
                var $modal = $( '#' + target );
                if ( $modal.length ) {
                    $modal.attr( 'hidden', null ).removeAttr( 'hidden' );
                    // Focus sur le premier champ.
                    setTimeout( function() {
                        $modal.find( 'input, select, textarea, button' ).first().focus();
                    }, 50 );
                }
            } );

            // Fermeture par overlay ou bouton "Annuler" ou "X" (data-close).
            $( document ).on( 'click', '.acdc-qz-modal [data-close]', function( e ) {
                e.preventDefault();
                $( this ).closest( '.acdc-qz-modal' ).attr( 'hidden', 'hidden' );
            } );

            // Fermeture par touche Échap.
            $( document ).on( 'keydown', function( e ) {
                if ( e.key === 'Escape' ) {
                    $( '.acdc-qz-modal:not([hidden])' ).attr( 'hidden', 'hidden' );
                }
            } );
        },

        bindTabSwitching: function() {
            $( document ).on( 'click', '.acdc-qz-tab-link', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var tab = $btn.data( 'tab' );
                var $form = $btn.closest( '.acdc-qz-tabs' );
                $form.find( '.acdc-qz-tab-link' ).removeClass( 'is-active' );
                $btn.addClass( 'is-active' );
                $form.find( '.acdc-qz-tab-pane' ).removeClass( 'is-active' );
                $form.find( '[data-tab-pane="' + tab + '"]' ).addClass( 'is-active' );
            } );
        },

        /* --------------------------------------------------------- */
        /*  Menus contextuels des cartes (⋯)                         */
        /* --------------------------------------------------------- */

        bindCardMenus: function() {
            $( document ).on( 'click', '.acdc-qz-card-menu-toggle', function( e ) {
                e.preventDefault();
                e.stopPropagation();
                var $menu = $( this ).siblings( '.acdc-qz-card-menu' );
                // Ferme tous les autres menus.
                $( '.acdc-qz-card-menu' ).not( $menu ).attr( 'hidden', 'hidden' );
                if ( $menu.attr( 'hidden' ) ) {
                    $menu.attr( 'hidden', null ).removeAttr( 'hidden' );
                } else {
                    $menu.attr( 'hidden', 'hidden' );
                }
            } );

            // ACDC 3.21.06 — Bouton "Lancer en live" : enregistré ICI, avant le handler
            // "ferme tous les menus", pour être exécuté en premier.
            $( document ).on( 'click', '.acdc-qz-live-launch-btn', function( e ) {
                e.preventDefault();
                e.stopPropagation();
                var btn    = this;
                var modal  = document.getElementById( 'acdc-qz-live-launch-modal' );
                if ( ! modal ) { return; }
                var quizId      = btn.getAttribute( 'data-quiz-id' );
                var quizTitle   = btn.getAttribute( 'data-quiz-title' ) || '';
                var launchNonce = btn.getAttribute( 'data-launch-nonce' ) || '';
                var actionNonce = btn.getAttribute( 'data-launch-action-nonce' ) || '';
                var launchBase  = btn.getAttribute( 'data-launch-url' ) ||
                    ( typeof acdcQzEditor !== 'undefined' ? acdcQzEditor.ajaxUrl : '' ).replace( 'admin-ajax.php', 'admin-post.php' );
                // Stocker le contexte dans le modal pour doLiveLaunch
                modal.dataset.currentQuizId      = quizId;
                modal.dataset.currentActionNonce = actionNonce;
                modal.dataset.currentLaunchBase  = launchBase;
                // Titre du quiz
                var nameEl = modal.querySelector( '#acdc-qz-live-launch-quiz-name' );
                if ( nameEl ) { nameEl.textContent = quizTitle; }
                // Reset UI
                var listEl    = modal.querySelector( '#acdc-qz-live-launch-sessions-list' );
                var noSessEl  = modal.querySelector( '#acdc-qz-live-launch-no-sessions' );
                var loadingEl = modal.querySelector( '#acdc-qz-live-launch-loading' );
                var wrapEl    = modal.querySelector( '#acdc-qz-live-launch-sessions-wrap' );
                if ( listEl )    { listEl.innerHTML = ''; }
                if ( noSessEl )  { noSessEl.style.display = 'none'; }
                if ( loadingEl ) { loadingEl.style.display = 'block'; }
                if ( wrapEl )    { wrapEl.style.display = 'none'; }
                // Fermer le menu 3 points puis ouvrir le modal
                $( '.acdc-qz-card-menu' ).attr( 'hidden', 'hidden' );
                modal.removeAttribute( 'hidden' );
                // Charger les séances du jour
                $.ajax( {
                    url:  typeof acdcQzEditor !== 'undefined' ? acdcQzEditor.ajaxUrl : '',
                    type: 'POST',
                    data: { action: 'acdc_of_qz_get_live_launch_sessions', quiz_id: quizId, _wpnonce: launchNonce },
                    success: function( resp ) {
                        if ( loadingEl ) { loadingEl.style.display = 'none'; }
                        if ( wrapEl )    { wrapEl.style.display = ''; }
                        if ( ! resp.success || ! resp.data.sessions || ! resp.data.sessions.length ) {
                            if ( noSessEl ) { noSessEl.style.display = ''; }
                            return;
                        }
                        resp.data.sessions.forEach( function( s ) {
                            var $b = $( '<button type="button" class="acdc-button acdc-button-soft"></button>' )
                                .css( { 'text-align': 'left', 'justify-content': 'flex-start', 'font-size': '13px', 'padding': '10px 14px', 'margin-bottom': '4px' } )
                                .text( s.label )
                                .on( 'click', function() { QzEditor.doLiveLaunch( s.id ); } );
                            $( listEl ).append( $b );
                        } );
                    },
                    error: function() {
                        if ( loadingEl ) { loadingEl.style.display = 'none'; }
                        if ( wrapEl )    { wrapEl.style.display = ''; }
                        if ( noSessEl )  { noSessEl.style.display = ''; }
                    }
                } );
            } );

            // Clic ailleurs : ferme tous les menus.
            // Exclure les clics sur .acdc-qz-live-launch-btn (géré ci-dessus).
            $( document ).on( 'click', function( e ) {
                if ( $( e.target ).closest( '.acdc-qz-live-launch-btn' ).length ) { return; }
                $( '.acdc-qz-card-menu' ).attr( 'hidden', 'hidden' );
            } );

            // Fermeture du modal live-launch (overlay, bouton ✕, touche Échap)
            $( document ).on( 'click', '#acdc-qz-live-launch-modal .acdc-qz-modal-overlay, #acdc-qz-live-launch-modal [data-close]', function( e ) {
                e.stopPropagation();
                var m = document.getElementById( 'acdc-qz-live-launch-modal' );
                if ( m ) { m.setAttribute( 'hidden', '' ); }
            } );
            $( document ).on( 'click', '#acdc-qz-live-launch-no-session-btn', function( e ) {
                e.preventDefault();
                e.stopPropagation();
                QzEditor.doLiveLaunch( 0 );
            } );
            $( document ).on( 'keydown', function( e ) {
                if ( e.key === 'Escape' ) {
                    var m = document.getElementById( 'acdc-qz-live-launch-modal' );
                    if ( m && ! m.hidden ) { m.setAttribute( 'hidden', '' ); }
                }
            } );
        },

        bindCardActions: function() {
            // Action "Dupliquer dans la même formation".
            $( document ).on( 'click', '.acdc-qz-action-duplicate', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var quizId = $btn.data( 'quiz-id' );
                var nonce  = $btn.data( 'nonce' );
                if ( ! quizId || ! nonce ) { return; }
                QzEditor.postAdminForm( {
                    action: 'acdc_of_qz_duplicate_quiz',
                    source_quiz_id: quizId,
                    target_formation_id: 0,
                    _acdc_qz_nonce: nonce
                } );
            } );

            // Duplication directe (sans modale, conserve la même formation / formation_id=0)
            $( document ).on( 'click', '.acdc-qz-action-duplicate-direct', function( e ) {
                e.preventDefault();
                var $btn   = $( this );
                var quizId = $btn.data( 'quiz-id' );
                var nonce  = $btn.data( 'nonce' );
                if ( ! quizId || ! nonce ) { return; }
                QzEditor.postAdminForm( {
                    action:        'acdc_of_qz_duplicate_quiz_direct',
                    quiz_id:       quizId,
                    _acdc_qz_nonce: nonce
                } );
            } );

            // Action "Archiver".
            $( document ).on( 'click', '.acdc-qz-action-archive', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var quizId = $btn.data( 'quiz-id' );
                var nonce  = $btn.data( 'nonce' );
                if ( ! quizId || ! nonce ) { return; }
                if ( ! confirm( 'Archiver ce quiz ?' ) ) { return; }
                QzEditor.postAdminForm( {
                    action: 'acdc_of_qz_archive_quiz',
                    quiz_id: quizId,
                    _acdc_qz_nonce: nonce
                } );
            } );

            // Action "Supprimer".
            $( document ).on( 'click', '.acdc-qz-action-delete', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var quizId = $btn.data( 'quiz-id' );
                var nonce  = $btn.data( 'nonce' );
                if ( ! quizId || ! nonce ) { return; }
                if ( ! confirm( 'Supprimer définitivement ce quiz ? Cette action est irréversible.' ) ) { return; }
                function qzDoDelete( force ) {
                    $.ajax( {
                        url: acdcQzEditor.ajaxUrl,
                        type: 'POST',
                        data: { action: 'acdc_of_qz_delete_quiz', quiz_id: quizId, _acdc_qz_nonce: nonce, force: force ? 1 : 0 },
                        success: function( resp ) {
                            if ( resp && resp.success && resp.data && resp.data.redirect ) {
                                window.location.href = resp.data.redirect;
                            } else if ( resp && resp.success ) {
                                window.location.reload();
                            } else if ( resp && resp.data && resp.data.locked ) {
                                var warn = ( resp.data.message ? resp.data.message + '\n\n' : '' ) + 'Forcer la suppression effacera DÉFINITIVEMENT ce test ainsi que toutes les réponses et résultats associés (preuve Qualiopi). Continuer ?';
                                if ( confirm( warn ) ) { qzDoDelete( true ); }
                            } else {
                                alert( ( resp && resp.data && resp.data.message ) ? resp.data.message : 'Une erreur est survenue.' );
                            }
                        },
                        error: function() { alert( 'Erreur réseau. Veuillez réessayer.' ); }
                    } );
                }
                qzDoDelete( false );
            } );

            // 3.21.02 — Action "Activer ce quiz" (draft → active).
            $( document ).on( 'click', '.acdc-qz-action-activate', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var quizId = $btn.data( 'quiz-id' );
                var nonce  = $btn.data( 'nonce' );
                if ( ! quizId || ! nonce ) { return; }
                QzEditor.postAdminForm( {
                    action: 'acdc_of_qz_activate_quiz',
                    quiz_id: quizId,
                    _acdc_qz_nonce: nonce
                } );
            } );

            // 3.21.02 — Action "Repasser en brouillon" (active → draft).
            $( document ).on( 'click', '.acdc-qz-action-unpublish', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var quizId = $btn.data( 'quiz-id' );
                var nonce  = $btn.data( 'nonce' );
                if ( ! quizId || ! nonce ) { return; }
                if ( ! confirm( 'Repasser ce quiz en brouillon ? Il ne pourra plus être lancé en session tant qu\'il n\'aura pas été ré-activé.' ) ) { return; }
                QzEditor.postAdminForm( {
                    action: 'acdc_of_qz_unpublish_quiz',
                    quiz_id: quizId,
                    _acdc_qz_nonce: nonce
                } );
            } );

            // 3.21.02 — Action "Verrouiller manuellement" (active + locked).
            $( document ).on( 'click', '.acdc-qz-action-lock', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var quizId = $btn.data( 'quiz-id' );
                var nonce  = $btn.data( 'nonce' );
                if ( ! quizId || ! nonce ) { return; }
                if ( ! confirm( 'Verrouiller manuellement ce quiz ?\n\nCette action est IRRÉVERSIBLE : plus aucune modification ne sera possible. Pour évoluer, il faudra créer une nouvelle version.\n\nContinuer ?' ) ) { return; }
                QzEditor.postAdminForm( {
                    action: 'acdc_of_qz_lock_quiz',
                    quiz_id: quizId,
                    _acdc_qz_nonce: nonce
                } );
            } );

            // 3.21.02 — Action "Créer une nouvelle version".
            $( document ).on( 'click', '.acdc-qz-action-new-version', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var quizId = $btn.data( 'quiz-id' );
                var nonce  = $btn.data( 'nonce' );
                if ( ! quizId || ! nonce ) { return; }
                if ( ! confirm( 'Créer une nouvelle version de ce quiz ? La version actuelle restera consultable en lecture seule, et vous éditerez la nouvelle version.' ) ) { return; }
                QzEditor.postAdminForm( {
                    action: 'acdc_of_qz_create_new_version',
                    quiz_id: quizId,
                    _acdc_qz_nonce: nonce
                } );
            } );

        },

        /**
         * Construit un formulaire HTML caché et le soumet à admin-post.php.
         * Utilisé pour les actions Dupliquer / Archiver / Supprimer depuis
         * la liste, qui réclament une nonce admin (et non AJAX).
         *
         * @param {Object} fields  Champs du formulaire (action, payload, _acdc_qz_nonce).
         */
        /**
         * ACDC 3.21.06 — Lance la session live avec ou sans séance OF.
         * @param {number} formationSessionId  0 = sans séance.
         */
        doLiveLaunch: function( formationSessionId ) {
            var modal = document.getElementById( 'acdc-qz-live-launch-modal' );
            if ( ! modal ) { return; }
            var quizId      = modal.dataset.currentQuizId || 0;
            var actionNonce = modal.dataset.currentActionNonce || '';
            var launchBase  = modal.dataset.currentLaunchBase || '';
            if ( ! launchBase ) {
                launchBase = ( acdcQzEditor.ajaxUrl || '' ).replace( 'admin-ajax.php', 'admin-post.php' );
            }
            modal.setAttribute( 'hidden', '' );
            var url = launchBase
                + '?action=acdc_of_qz_launch_live'
                + '&quiz_id=' + encodeURIComponent( quizId )
                + '&_wpnonce=' + encodeURIComponent( actionNonce )
                + ( formationSessionId ? '&formation_session_id=' + encodeURIComponent( formationSessionId ) : '' );
            window.open( url, '_blank', 'noopener' );
        },

        postAdminForm: function( fields ) {
            // ACDC 3.25.78 — Utiliser admin-ajax.php (wp_ajax_) au lieu de admin-post.php (bloqué par WAF PlanetHoster)
            var ajaxUrl = acdcQzEditor.ajaxUrl; // déjà = admin-ajax.php
            $.ajax( {
                url: ajaxUrl,
                type: 'POST',
                data: fields,
                success: function( resp ) {
                    if ( resp && resp.success && resp.data && resp.data.redirect ) {
                        window.location.href = resp.data.redirect;
                    } else if ( resp && resp.success ) {
                        window.location.reload();
                    } else {
                        var msg = ( resp && resp.data && resp.data.message ) ? resp.data.message : 'Une erreur est survenue.';
                        alert( msg );
                    }
                },
                error: function() {
                    alert( 'Erreur réseau. Veuillez réessayer.' );
                }
            } );
        },

        /* --------------------------------------------------------- */
        /*  Éditeur — drag&drop + autosave                           */
        /* --------------------------------------------------------- */

        bindEditor: function() {
            var $editor = $( '.acdc-qz-editor' );
            if ( ! $editor.length ) { return; }
            var quizId = $editor.data( 'quiz-id' );
            var locked = parseInt( $editor.data( 'quiz-locked' ), 10 ) === 1;
            if ( locked ) {
                return; // Pas d'éditeur interactif en mode verrouillé.
            }

            // 1. Drag & drop des questions.
            var $list = $( '#acdc-qz-question-list' );
            if ( $list.length && $.fn.sortable ) {
                $list.sortable( {
                    handle: '.acdc-qz-question-thumb-handle',
                    placeholder: 'acdc-qz-question-thumb-placeholder',
                    axis: 'y',
                    update: function() {
                        var orderedIds = $list.children( '[data-question-id]' ).map( function() {
                            return parseInt( $( this ).data( 'question-id' ), 10 );
                        } ).get();
                        QzEditor.ajaxReorderQuestions( quizId, orderedIds );
                    }
                } );
            }

            // 2. Suppression de question (sur la vignette).
            $( document ).on( 'click', '.acdc-qz-question-thumb-delete', function( e ) {
                e.preventDefault();
                e.stopPropagation();
                if ( ! confirm( acdcQzEditor.i18n.confirmDel ) ) { return; }
                var $thumb = $( this ).closest( '.acdc-qz-question-thumb' );
                var qid = parseInt( $thumb.data( 'question-id' ), 10 );
                QzEditor.ajaxDeleteQuestion( quizId, qid, function() {
                    $thumb.fadeOut( 150, function() {
                        $( this ).remove();
                        // Si on vient de supprimer la question courante, on recharge la page.
                        if ( $thumb.hasClass( 'is-current' ) ) {
                            window.location.reload();
                        }
                    } );
                } );
            } );

            // 3. Clic sur une vignette pour changer de question courante.
            $( document ).on( 'click', '.acdc-qz-question-thumb', function( e ) {
                if ( $( e.target ).closest( '.acdc-qz-question-thumb-delete, .acdc-qz-question-thumb-handle' ).length ) {
                    return;
                }
                var qid = $( this ).data( 'question-id' );
                if ( ! qid ) { return; }
                var url = window.location.href.replace( /([&?])question_id=\d+/, '' );
                url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'question_id=' + qid;
                window.location.href = url;
            } );

            // 4. Bouton "+ Ajouter une question".
            $( document ).on( 'click', '.acdc-qz-btn-add-question', function( e ) {
                e.preventDefault();
                QzEditor.ajaxCreateQuestion( quizId );
            } );

            // 5. Autosave au fil de l'eau sur les changements de la question courante.
            $( document ).on( 'input change', '.acdc-qz-question-form, .acdc-qz-settings-form, .acdc-qz-answers-zone', function() {
                QzEditor.scheduleAutosave( quizId );
            } );

            // 6. Bouton "+ Ajouter une proposition" / "+ Ajouter un élément".
            $( document ).on( 'click', '.acdc-qz-answer-add', function( e ) {
                e.preventDefault();
                QzEditor.appendEmptyAnswer( $( this ) );
            } );

            // 7. Bouton "×" supprimer une proposition.
            $( document ).on( 'click', '.acdc-qz-answer-delete', function( e ) {
                e.preventDefault();
                $( this ).closest( '.acdc-qz-answer-row' ).remove();
                QzEditor.scheduleAutosave( quizId );
            } );
        },

        appendEmptyAnswer: function( $btn ) {
            var $list = $btn.siblings( '.acdc-qz-answers-list' ).first();
            var $tpl = $list.children( '.acdc-qz-answer-row' ).first().clone();
            $tpl.attr( 'data-answer-id', '0' );
            $tpl.find( 'input.acdc-qz-answer-text' ).val( '' );
            $tpl.find( 'input.acdc-qz-answer-correct' ).prop( 'checked', false );
            $list.append( $tpl );
        },

        scheduleAutosave: function( quizId ) {
            // Indique "saving"
            QzEditor.setAutosaveStatus( 'saving' );

            if ( QzEditor._saveTimers[ quizId ] ) {
                clearTimeout( QzEditor._saveTimers[ quizId ] );
            }
            QzEditor._saveTimers[ quizId ] = setTimeout( function() {
                QzEditor.runAutosave( quizId );
            }, QzEditor.AUTOSAVE_DELAY );
        },

        runAutosave: function( quizId ) {
            var $form = $( '.acdc-qz-question-form' );
            if ( ! $form.length ) { return; }
            var qid = parseInt( $form.data( 'question-id' ), 10 ) || 0;
            var type = $form.data( 'type' );

            // Récupérer le panneau droit (paramètres).
            var $settings = $( '.acdc-qz-settings-form' );
            // Si le type a été changé dans le panneau droit, on l'utilise.
            var newType = $settings.find( '[name="type"]' ).val();
            if ( newType ) { type = newType; }

            // Construire la liste des réponses.
            var answers = [];
            $( '.acdc-qz-answer-row' ).each( function( idx ) {
                var $row = $( this );
                var aid = parseInt( $row.attr( 'data-answer-id' ), 10 ) || 0;
                var text = $row.find( 'input.acdc-qz-answer-text' ).val() || $row.find( 'input[name="acdc_qz_answer_text[]"]' ).val() || '';
                var isCorrect = false;
                var $correct = $row.find( '.acdc-qz-answer-correct' );
                if ( $correct.is( 'input[type="checkbox"]' ) || $correct.is( 'input[type="radio"]' ) ) {
                    isCorrect = $correct.prop( 'checked' );
                } else {
                    var hidden = $row.find( 'input[name="acdc_qz_correct[]"]' ).val();
                    isCorrect = ( hidden === '1' );
                }
                answers.push( {
                    id: aid,
                    text: text,
                    is_correct: isCorrect ? 1 : 0,
                    sort_order: idx,
                    feedback_text: '',
                    media_id: 0
                } );
            } );

            // Cas Vrai/Faux : la coche radio donne directement la position correcte.
            if ( type === 'true_false' ) {
                var correctIdx = parseInt( $( 'input[name="acdc_qz_tf_correct"]:checked' ).val() || '0', 10 );
                answers.forEach( function( a, idx ) {
                    a.is_correct = ( idx === correctIdx ) ? 1 : 0;
                } );
            }

            var data = {
                action: 'acdc_of_qz_editor_autosave_question',
                nonce: acdcQzEditor.nonce,
                quiz_id: quizId,
                question_id: qid,
                type: type,
                title: $form.find( '[name="title"]' ).val() || '',
                description: $form.find( '[name="description"]' ).val() || '',
                time_limit: parseInt( $settings.find( '[name="time_limit"]' ).val(), 10 ) || 20,
                points_type: $settings.find( '[name="points_type"]' ).val() || 'standard',
                points_value: parseInt( $settings.find( '[name="points_value"]' ).val(), 10 ) || 1000,
                is_scored: $settings.find( '[name="is_scored"]' ).is( ':checked' ) ? 1 : 0,
                objective_id: parseInt( $settings.find( '[name="objective_id"]' ).val(), 10 ) || 0,
                media_id: parseInt( $form.find( '[name="media_id"]' ).val(), 10 ) || 0,
                answers: answers
            };

            $.post( acdcQzEditor.ajaxUrl, data )
                .done( function( resp ) {
                    if ( resp && resp.success ) {
                        QzEditor.setAutosaveStatus( 'saved' );
                        if ( resp.data && resp.data.question_id && qid === 0 ) {
                            // Première sauvegarde d'une nouvelle question : on stocke l'ID.
                            $form.attr( 'data-question-id', resp.data.question_id );
                            $form.data( 'question-id', resp.data.question_id );
                        }
                    } else {
                        QzEditor.setAutosaveStatus( 'error', resp && resp.data ? resp.data.message : '' );
                    }
                } )
                .fail( function() {
                    QzEditor.setAutosaveStatus( 'error' );
                } );
        },

        setAutosaveStatus: function( state, msg ) {
            var $ind = $( '.acdc-qz-autosave-indicator' );
            $ind.attr( 'hidden', null ).removeAttr( 'hidden' );
            $ind.find( '> span' ).attr( 'hidden', 'hidden' );
            $ind.find( '.acdc-qz-autosave-' + state ).attr( 'hidden', null ).removeAttr( 'hidden' );
            if ( state === 'saved' ) {
                setTimeout( function() {
                    $ind.attr( 'hidden', 'hidden' );
                }, 1500 );
            }
        },

        ajaxCreateQuestion: function( quizId ) {
            $.post( acdcQzEditor.ajaxUrl, {
                action: 'acdc_of_qz_editor_autosave_question',
                nonce: acdcQzEditor.nonce,
                quiz_id: quizId,
                question_id: 0,
                type: 'qcm_single',
                title: '',
                time_limit: 20,
                points_type: 'standard',
                points_value: 1000,
                is_scored: 1,
                // 3.21.01.2 — Aucune réponse pré-remplie : l'utilisateur saisit
                // ses propres réponses dans l'éditeur, qui les persiste via l'autosave.
                // Le serveur filtre déjà les réponses vides à la persistance.
                answers: []
            } ).done( function( resp ) {
                if ( resp && resp.success && resp.data && resp.data.question_id ) {
                    var url = window.location.href.replace( /([&?])question_id=\d+/, '' );
                    url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'question_id=' + resp.data.question_id;
                    window.location.href = url;
                } else {
                    alert( acdcQzEditor.i18n.error + ( resp && resp.data ? ': ' + resp.data.message : '' ) );
                }
            } ).fail( function() {
                alert( acdcQzEditor.i18n.error );
            } );
        },

        ajaxDeleteQuestion: function( quizId, questionId, onSuccess ) {
            $.post( acdcQzEditor.ajaxUrl, {
                action: 'acdc_of_qz_editor_delete_question',
                nonce: acdcQzEditor.nonce,
                quiz_id: quizId,
                question_id: questionId
            } ).done( function( resp ) {
                if ( resp && resp.success ) {
                    if ( typeof onSuccess === 'function' ) { onSuccess(); }
                } else {
                    alert( acdcQzEditor.i18n.error + ( resp && resp.data ? ': ' + resp.data.message : '' ) );
                }
            } ).fail( function() {
                alert( acdcQzEditor.i18n.error );
            } );
        },

        ajaxReorderQuestions: function( quizId, orderedIds ) {
            $.post( acdcQzEditor.ajaxUrl, {
                action: 'acdc_of_qz_editor_reorder_questions',
                nonce: acdcQzEditor.nonce,
                quiz_id: quizId,
                ordered_ids: orderedIds
            } );
        },

        /* --------------------------------------------------------- */
        /*  Modale Objectifs                                         */
        /* --------------------------------------------------------- */

        bindObjectives: function() {
            // Ajouter un objectif (ligne vide).
            $( document ).on( 'click', '.acdc-qz-objective-add', function( e ) {
                e.preventDefault();
                var $list = $( '#acdc-qz-objectives-list' );
                $list.find( '.acdc-qz-objectives-empty' ).remove();
                var html = '' +
                    '<li class="acdc-qz-objective-row" data-objective-id="0">' +
                        '<div class="acdc-qz-objective-fields">' +
                            '<input type="text" class="acdc-qz-input acdc-qz-objective-label" placeholder="Intitulé" maxlength="255" />' +
                            '<input type="number" class="acdc-qz-input acdc-qz-objective-pass" min="0" max="100" step="0.5" placeholder="Seuil %" />' +
                        '</div>' +
                        '<textarea class="acdc-qz-input acdc-qz-objective-description" rows="2" placeholder="Description (optionnelle)"></textarea>' +
                        '<div class="acdc-qz-objective-actions">' +
                            '<button type="button" class="acdc-button acdc-button-soft acdc-qz-objective-save">Enregistrer</button>' +
                            '<button type="button" class="acdc-button acdc-button-link acdc-qz-objective-delete">Supprimer</button>' +
                        '</div>' +
                    '</li>';
                $list.append( html );
            } );

            // Sauvegarder un objectif.
            $( document ).on( 'click', '.acdc-qz-objective-save', function( e ) {
                e.preventDefault();
                var $row = $( this ).closest( '.acdc-qz-objective-row' );
                var quizId = $( this ).closest( '.acdc-qz-modal-body' ).data( 'quiz-id' );
                var oid = parseInt( $row.attr( 'data-objective-id' ), 10 ) || 0;
                var label = $row.find( '.acdc-qz-objective-label' ).val();
                var description = $row.find( '.acdc-qz-objective-description' ).val();
                var passVal = $row.find( '.acdc-qz-objective-pass' ).val();

                $.post( acdcQzEditor.ajaxUrl, {
                    action: 'acdc_of_qz_editor_save_objective',
                    nonce: acdcQzEditor.nonce,
                    quiz_id: quizId,
                    objective_id: oid,
                    label: label,
                    description: description,
                    pass_threshold: passVal
                } ).done( function( resp ) {
                    if ( resp && resp.success ) {
                        if ( resp.data && resp.data.objective_id ) {
                            $row.attr( 'data-objective-id', resp.data.objective_id );
                        }
                        QzEditor.flashSaved( $row );
                    } else {
                        alert( acdcQzEditor.i18n.error + ( resp && resp.data ? ': ' + resp.data.message : '' ) );
                    }
                } );
            } );

            // Supprimer un objectif.
            $( document ).on( 'click', '.acdc-qz-objective-delete', function( e ) {
                e.preventDefault();
                if ( ! confirm( acdcQzEditor.i18n.confirmDelObjective ) ) { return; }
                var $row = $( this ).closest( '.acdc-qz-objective-row' );
                var quizId = $( this ).closest( '.acdc-qz-modal-body' ).data( 'quiz-id' );
                var oid = parseInt( $row.attr( 'data-objective-id' ), 10 ) || 0;

                if ( oid === 0 ) {
                    // Pas encore enregistré côté serveur, on retire juste la ligne.
                    $row.remove();
                    return;
                }
                $.post( acdcQzEditor.ajaxUrl, {
                    action: 'acdc_of_qz_editor_delete_objective',
                    nonce: acdcQzEditor.nonce,
                    quiz_id: quizId,
                    objective_id: oid
                } ).done( function( resp ) {
                    if ( resp && resp.success ) {
                        $row.fadeOut( 150, function() { $( this ).remove(); } );
                    } else {
                        alert( acdcQzEditor.i18n.error );
                    }
                } );
            } );
        },

        flashSaved: function( $el ) {
            $el.css( 'background', '#dff5e6' );
            setTimeout( function() {
                $el.css( 'background', '' );
            }, 800 );
        },

        /* --------------------------------------------------------- */
        /*  Bibliothèque média WordPress                             */
        /* --------------------------------------------------------- */

        bindMediaPicker: function() {
            $( document ).on( 'click', '.acdc-qz-media-pick', function( e ) {
                e.preventDefault();
                var $btn = $( this );
                var $picker = $btn.closest( '.acdc-qz-media-picker' );
                if ( typeof wp === 'undefined' || ! wp.media ) {
                    alert( 'Bibliothèque média WordPress non disponible.' );
                    return;
                }
                var frame = wp.media( {
                    title: acdcQzEditor.i18n.addImage,
                    button: { text: acdcQzEditor.i18n.addImage },
                    library: { type: 'image' },
                    multiple: false
                } );
                frame.on( 'select', function() {
                    var attachment = frame.state().get( 'selection' ).first().toJSON();
                    $picker.find( 'input[name="media_id"]' ).val( attachment.id ).trigger( 'change' );
                    var $preview = $picker.find( '.acdc-qz-media-preview' );
                    $preview.html( '<img src="' + attachment.url + '" alt="" />' );
                    $btn.text( acdcQzEditor.i18n.replaceImage );
                } );
                frame.open();
            } );

            $( document ).on( 'click', '.acdc-qz-media-clear', function( e ) {
                e.preventDefault();
                var $picker = $( this ).closest( '.acdc-qz-media-picker' );
                $picker.find( 'input[name="media_id"]' ).val( '0' ).trigger( 'change' );
                $picker.find( '.acdc-qz-media-preview' ).empty();
                $picker.find( '.acdc-qz-media-pick' ).text( acdcQzEditor.i18n.addImage );
                $( this ).remove();
            } );
        }
    };

    function ajaxUrl() {
        return acdcQzEditor.ajaxUrl;
    }

    $( function() {
        if ( typeof acdcQzEditor === 'undefined' ) {
            return;
        }
        QzEditor.init();
    } );

    /* ============================================================
     * 3.21.03.1 — Handlers pour la modale d'envoi asynchrone
     * ============================================================ */
    $( function() {
        // Onglets de sélection des destinataires
        $( document ).on( 'click', '.acdc-qz-send-tab', function( e ) {
            e.preventDefault();
            var $tab = $( this );
            var mode = $tab.data( 'mode' );
            var $modal = $tab.closest( '.acdc-qz-modal' );

            $modal.find( '.acdc-qz-send-tab' ).removeClass( 'is-active' );
            $tab.addClass( 'is-active' );
            $modal.find( '.acdc-qz-send-pane' ).attr( 'hidden', 'hidden' ).removeClass( 'is-active' );
            $modal.find( '[data-mode-pane="' + mode + '"]' ).removeAttr( 'hidden' ).addClass( 'is-active' );
            // Met à jour l'input hidden recipients_mode
            $modal.find( 'input[name="recipients_mode"]' ).val( mode );
        } );

        // Liens Tout cocher / Tout décocher
        $( document ).on( 'click', '.acdc-qz-select-all', function( e ) {
            e.preventDefault();
            $( this ).closest( '.acdc-qz-send-pane' )
                .find( '.acdc-qz-learners-list input[type="checkbox"]' )
                .prop( 'checked', true );
        } );
        $( document ).on( 'click', '.acdc-qz-select-none', function( e ) {
            e.preventDefault();
            $( this ).closest( '.acdc-qz-send-pane' )
                .find( '.acdc-qz-learners-list input[type="checkbox"]' )
                .prop( 'checked', false );
        } );

        // Validation du formulaire d'envoi : vérifie qu'il y a au moins un destinataire avant submit
        $( document ).on( 'submit', '.acdc-qz-send-async-form', function( e ) {
            var $form = $( this );
            var mode = $form.find( 'input[name="recipients_mode"]' ).val();

            if ( 'formation' === mode ) {
                var checked = $form.find( '.acdc-qz-learners-list input[type="checkbox"]:checked' ).length;
                if ( 0 === checked ) {
                    e.preventDefault();
                    alert( 'Veuillez sélectionner au moins un apprenant.' );
                    return false;
                }
            } else if ( 'session' === mode ) {
                var sid = parseInt( $form.find( 'select[name="formation_session_id"]' ).val() || '0', 10 );
                if ( sid <= 0 ) {
                    e.preventDefault();
                    alert( 'Veuillez sélectionner une session.' );
                    return false;
                }
            } else if ( 'custom' === mode ) {
                var raw = $form.find( 'textarea[name="custom_emails"]' ).val() || '';
                if ( '' === raw.trim() ) {
                    e.preventDefault();
                    alert( 'Veuillez saisir au moins une adresse e-mail.' );
                    return false;
                }
            }
            // Confirmation finale
            return confirm( "Confirmer l'envoi des e-mails ? Le quiz se verrouillera automatiquement au premier clic d'apprenant." );
        } );
    } );

    /* ================================================================
     * ACDC 3.21.06 — Fonctions globales pour le modal "Lancer en live"
     * Définies dans le scope du module jQuery mais exposées sur window
     * pour être appelables via onclick="..." dans le HTML PHP.
     * ================================================================ */
    window._acdcLiveLaunch = {};

    window.acdcQzLiveLaunchOpen = function( btn, e ) {
        if ( e ) { e.stopPropagation(); e.preventDefault(); }
        var modal = document.getElementById( 'acdc-qz-live-launch-modal' );
        if ( ! modal ) { return; }
        window._acdcLiveLaunch = {
            quizId:      btn.getAttribute( 'data-quiz-id' ),
            actionNonce: btn.getAttribute( 'data-launch-action-nonce' ),
            launchBase:  btn.getAttribute( 'data-launch-url' ),
            ajaxUrl:     btn.getAttribute( 'data-ajax-url' ),
            launchNonce: btn.getAttribute( 'data-launch-nonce' )
        };
        var nameEl = document.getElementById( 'acdc-qz-live-launch-quiz-name' );
        if ( nameEl ) { nameEl.textContent = btn.getAttribute( 'data-quiz-title' ) || ''; }
        document.getElementById( 'acdc-qz-live-launch-sessions-list' ).innerHTML = '';
        document.getElementById( 'acdc-qz-live-launch-no-sessions' ).style.display = 'none';
        document.getElementById( 'acdc-qz-live-launch-loading' ).style.display = 'block';
        document.getElementById( 'acdc-qz-live-launch-sessions-wrap' ).style.display = 'none';
        /* Forcer display:flex en plus de removeAttribute pour contourner tout override CSS */
        modal.removeAttribute( 'hidden' );
        modal.style.display = 'flex';
        /* Fermer le menu 3 points */
        document.querySelectorAll( '.acdc-qz-card-menu' ).forEach( function( m ) {
            m.setAttribute( 'hidden', '' );
        } );
        /* Charger les séances via fetch */
        var body = new URLSearchParams();
        body.append( 'action', 'acdc_of_qz_get_live_launch_sessions' );
        body.append( 'quiz_id', window._acdcLiveLaunch.quizId );
        body.append( '_wpnonce', window._acdcLiveLaunch.launchNonce );
        fetch( window._acdcLiveLaunch.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
            .then( function( r ) { return r.json(); } )
            .then( function( j ) {
                document.getElementById( 'acdc-qz-live-launch-loading' ).style.display = 'none';
                document.getElementById( 'acdc-qz-live-launch-sessions-wrap' ).style.display = '';
                if ( ! j.success || ! j.data.sessions || ! j.data.sessions.length ) {
                    document.getElementById( 'acdc-qz-live-launch-no-sessions' ).style.display = '';
                    return;
                }
                var list = document.getElementById( 'acdc-qz-live-launch-sessions-list' );
                j.data.sessions.forEach( function( s ) {
                    var b = document.createElement( 'button' );
                    b.type = 'button';
                    b.className = 'acdc-button acdc-button-soft';
                    b.style.cssText = 'text-align:left;justify-content:flex-start;font-size:13px;padding:10px 14px;margin-bottom:4px;';
                    b.textContent = s.label;
                    b.onclick = ( function( sid ) {
                        return function() { window.acdcQzLiveLaunchDo( sid ); };
                    } )( parseInt( s.id, 10 ) );
                    list.appendChild( b );
                } );
            } )
            .catch( function() {
                document.getElementById( 'acdc-qz-live-launch-loading' ).style.display = 'none';
                document.getElementById( 'acdc-qz-live-launch-sessions-wrap' ).style.display = '';
                document.getElementById( 'acdc-qz-live-launch-no-sessions' ).style.display = '';
            } );
    };

    window.acdcQzLiveLaunchDo = function( sessionId ) {
        var modal = document.getElementById( 'acdc-qz-live-launch-modal' );
        if ( modal ) { modal.setAttribute( 'hidden', '' ); modal.style.display = ''; }
        var d = window._acdcLiveLaunch;
        var url = d.launchBase
            + '?action=acdc_of_qz_launch_live'
            + '&quiz_id=' + encodeURIComponent( d.quizId )
            + '&_wpnonce=' + encodeURIComponent( d.actionNonce )
            + ( sessionId ? '&formation_session_id=' + encodeURIComponent( sessionId ) : '' );
        window.open( url, '_blank', 'noopener' );
    };

    document.addEventListener( 'keydown', function( e ) {
        if ( e.key === 'Escape' ) {
            var m = document.getElementById( 'acdc-qz-live-launch-modal' );
            if ( m && ! m.hidden ) { m.setAttribute( 'hidden', '' ); m.style.display = ''; }
        }
    } );

} )( jQuery );
