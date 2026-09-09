/**
 * ACDC Formation SAAS — JS de la page publique de passation.
 * 3.21.03.1 — Vanilla JS, pas de dépendance jQuery, mobile-friendly.
 */
( function() {
    'use strict';

    function init() {
        var form = document.querySelector( '.acdc-qz-public-form' );
        if ( ! form ) { return; }

        var questions = form.querySelectorAll( '.acdc-qz-public-question' );
        var totalEl = form.querySelector( '.acdc-qz-public-progress span:last-child' );
        var currentEl = form.querySelector( '#acdc-qz-current' );

        var currentIndex = 0;

        function showQuestion( idx ) {
            for ( var i = 0; i < questions.length; i++ ) {
                if ( i === idx ) {
                    questions[i].removeAttribute( 'hidden' );
                } else {
                    questions[i].setAttribute( 'hidden', 'hidden' );
                }
            }
            currentIndex = idx;
            if ( currentEl ) {
                currentEl.textContent = ( idx + 1 ).toString();
            }
            // Scroll en haut pour mobile
            window.scrollTo( { top: form.offsetTop - 20, behavior: 'smooth' } );
        }

        /* ACDC 3.25.157 — Message d'erreur ANCRÉ à la question concernée.
           L'alert() précédente n'était jamais atteinte (la validation native bloquait
           en amont) et, même atteinte, ne désignait pas la question fautive. */
        function clearQuestionError( q ) {
            var box = q.querySelector( '.acdc-qz-public-q-error' );
            if ( box ) { box.parentNode.removeChild( box ); }
            q.classList.remove( 'acdc-qz-public-q-invalid' );
        }

        function showQuestionError( q, message ) {
            clearQuestionError( q );
            var box = document.createElement( 'p' );
            box.className = 'acdc-qz-public-q-error';
            box.setAttribute( 'role', 'alert' );
            box.style.cssText = 'margin:12px 0 0;padding:10px 12px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:14px;font-weight:600;';
            box.textContent = message;
            var nav = q.querySelector( '.acdc-qz-public-q-nav' );
            if ( nav ) { q.insertBefore( box, nav ); } else { q.appendChild( box ); }
            q.classList.add( 'acdc-qz-public-q-invalid' );
            box.scrollIntoView( { behavior: 'smooth', block: 'center' } );
        }

        function validateCurrentQuestion() {
            var q = questions[ currentIndex ];
            if ( ! q ) { return true; }
            clearQuestionError( q );
            // Cherche un input requis non rempli
            var radios = q.querySelectorAll( 'input[type="radio"][required]' );
            if ( radios.length > 0 ) {
                var name = radios[0].name;
                var checked = q.querySelector( 'input[type="radio"][name="' + CSS.escape( name ) + '"]:checked' );
                if ( ! checked ) {
                    showQuestionError( q, 'Sélectionnez une réponse avant de continuer.' );
                    return false;
                }
            }
            var textareas = q.querySelectorAll( 'textarea[required]' );
            for ( var i = 0; i < textareas.length; i++ ) {
                if ( ! textareas[i].value.trim() ) {
                    showQuestionError( q, 'Saisissez votre réponse avant de continuer.' );
                    textareas[i].focus();
                    return false;
                }
            }
            return true;
        }

        // Bind boutons « Suivante »
        form.addEventListener( 'click', function( e ) {
            if ( e.target.matches( '[data-next-question]' ) ) {
                e.preventDefault();
                if ( ! validateCurrentQuestion() ) { return; }
                if ( currentIndex < questions.length - 1 ) {
                    showQuestion( currentIndex + 1 );
                }
            } else if ( e.target.matches( '[data-prev-question]' ) ) {
                e.preventDefault();
                if ( currentIndex > 0 ) {
                    showQuestion( currentIndex - 1 );
                }
            }
        } );

        // Empêche la soumission accidentelle par Entrée sur un input
        form.addEventListener( 'keydown', function( e ) {
            if ( e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit' ) {
                e.preventDefault();
            }
        } );

        // Validation finale avant soumission
        form.addEventListener( 'submit', function( e ) {
            // Vérifie qu'on est bien sur la dernière question
            if ( currentIndex !== questions.length - 1 ) {
                e.preventDefault();
                showQuestionError( questions[ currentIndex ], 'Répondez aux questions suivantes avant de valider.' );
                return false;
            }
            if ( ! validateCurrentQuestion() ) {
                e.preventDefault();
                return false;
            }
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    /* ============================================================
     * 3.21.03.1-hotfix7 — Puzzle (remise en ordre)
     * Gère les flèches ↑↓, met à jour les positions et l'input hidden.
     * ============================================================ */
    function initPuzzles() {
        var puzzles = document.querySelectorAll( '.acdc-qz-public-puzzle' );
        for ( var p = 0; p < puzzles.length; p++ ) {
            initPuzzle( puzzles[p] );
        }
    }

    function initPuzzle( puzzle ) {
        var list = puzzle.querySelector( '.acdc-qz-public-puzzle-list' );
        var hidden = puzzle.querySelector( '.acdc-qz-public-puzzle-order' );
        if ( ! list || ! hidden ) { return; }

        function refresh() {
            var items = list.querySelectorAll( '.acdc-qz-public-puzzle-item' );
            var ids = [];
            for ( var i = 0; i < items.length; i++ ) {
                // Met à jour la numérotation
                var pos = items[i].querySelector( '.acdc-qz-public-puzzle-position' );
                if ( pos ) { pos.textContent = ( i + 1 ).toString(); }
                ids.push( items[i].getAttribute( 'data-answer-id' ) );
                // Active/désactive les flèches selon position
                var upBtn = items[i].querySelector( '[data-direction="up"]' );
                var downBtn = items[i].querySelector( '[data-direction="down"]' );
                if ( upBtn )   { upBtn.disabled = ( i === 0 ); }
                if ( downBtn ) { downBtn.disabled = ( i === items.length - 1 ); }
            }
            hidden.value = ids.join( ',' );
        }

        list.addEventListener( 'click', function( e ) {
            var btn = e.target.closest( '[data-direction]' );
            if ( ! btn || btn.disabled ) { return; }
            var item = btn.closest( '.acdc-qz-public-puzzle-item' );
            if ( ! item ) { return; }
            var direction = btn.getAttribute( 'data-direction' );

            if ( direction === 'up' ) {
                var prev = item.previousElementSibling;
                if ( prev ) {
                    list.insertBefore( item, prev );
                }
            } else if ( direction === 'down' ) {
                var next = item.nextElementSibling;
                if ( next ) {
                    list.insertBefore( next, item );
                }
            }

            // Animation flash visuel
            item.classList.add( 'is-moving' );
            setTimeout( function() {
                item.classList.remove( 'is-moving' );
            }, 250 );

            refresh();
        } );

        // Initialisation : positions et désactivations
        refresh();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initPuzzles );
    } else {
        initPuzzles();
    }
} )();
