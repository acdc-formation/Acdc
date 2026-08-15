/**
 * ACDC 3.25.273 — Ce que le CSS seul ne peut pas faire.
 *
 * Deux gestes, et deux seulement.
 *
 * LE TIROIR DU MENU. Sur un téléphone, le menu latéral occuperait tout l'écran
 * s'il restait déplié : il a beaucoup d'entrées. Il se replie donc derrière un
 * bouton — le choix de David. Le bouton est posé ici, pas dans le HTML, parce
 * que trois portails partagent la même structure : l'apprenant, le formateur et
 * la gestion. Une seule pièce, trois écrans servis.
 *
 * LES LIBELLÉS DES TABLEAUX. Sur mobile, chaque ligne devient une fiche et
 * chaque cellule doit rappeler de quelle colonne elle vient — sans quoi on lit
 * une suite de valeurs sans savoir ce qu'elles désignent. Plutôt que d'ajouter
 * un attribut à la main dans les centaines de cellules du plugin, on recopie
 * l'en-tête au chargement. La règle vit à un seul endroit, et un tableau écrit
 * demain en profitera sans qu'on y pense.
 *
 * SI CE SCRIPT NE S'EXÉCUTE PAS, rien n'est cassé : la classe `acdc-js-ready`
 * n'est pas posée, le menu reste empilé au-dessus du contenu et les tableaux
 * gardent leur défilement horizontal. Moins confortable, parfaitement
 * utilisable. Une interface dont le menu ne s'ouvre plus quand un script échoue
 * serait un très mauvais échange.
 */
( function () {
	'use strict';

	var MOBILE_MAX = 1024;   /* Fiches jusqu’à la tablette : sur un iPad en portrait, un tableau de
	                          douze colonnes s’écrase au point que « Statut » se lit lettre par
	                          lettre, à la verticale. Vérifié en capture avant de trancher. */

	function each( liste, fn ) {
		Array.prototype.forEach.call( liste, fn );
	}

	/* ── Le tiroir ───────────────────────────────────────────────────────── */

	function equipeMenu( layout ) {
		var nav = layout.querySelector( '.acdc-portal-nav' );
		var contenu = layout.querySelector( '.acdc-portal-content' );
		if ( ! nav || ! contenu || layout.getAttribute( 'data-acdc-nav' ) === 'pret' ) {
			return;
		}
		layout.setAttribute( 'data-acdc-nav', 'pret' );

		var bouton = document.createElement( 'button' );
		bouton.type = 'button';
		bouton.className = 'acdc-nav-toggle';
		bouton.setAttribute( 'aria-label', 'Ouvrir le menu' );
		bouton.setAttribute( 'aria-expanded', 'false' );
		bouton.innerHTML = '<span class="acdc-nav-toggle-bars" aria-hidden="true">'
			+ '<span></span><span></span><span></span></span><span>Menu</span>';

		var support = document.createElement( 'div' );
		support.className = 'acdc-nav-toggle-bar-holder';
		support.appendChild( bouton );
		contenu.insertBefore( support, contenu.firstChild );

		var voile = document.createElement( 'div' );
		voile.className = 'acdc-nav-backdrop';
		layout.appendChild( voile );

		function ferme() {
			layout.classList.remove( 'is-nav-open' );
			bouton.setAttribute( 'aria-expanded', 'false' );
			bouton.setAttribute( 'aria-label', 'Ouvrir le menu' );
		}

		bouton.addEventListener( 'click', function () {
			var ouvert = layout.classList.toggle( 'is-nav-open' );
			bouton.setAttribute( 'aria-expanded', ouvert ? 'true' : 'false' );
			bouton.setAttribute( 'aria-label', ouvert ? 'Fermer le menu' : 'Ouvrir le menu' );
		} );

		voile.addEventListener( 'click', ferme );

		/* Choisir une entrée referme le tiroir : sur un téléphone, la page
		   change et le menu resté ouvert masquerait le résultat. */
		each( nav.querySelectorAll( 'a' ), function ( lien ) {
			lien.addEventListener( 'click', ferme );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) { ferme(); }
		} );
	}

	/* ── Les libellés des tableaux ───────────────────────────────────────── */

	function equipeTableau( table ) {
		if ( table.getAttribute( 'data-acdc-cards' ) === 'pret' ) {
			return;
		}

		var entetes = table.querySelectorAll( 'thead tr th' );
		if ( ! entetes.length ) {
			/* Sans en-tête, une fiche n'aurait aucun libellé à afficher : ce
			   tableau garde le défilement horizontal, qui reste lisible. */
			return;
		}

		var titres = [];
		each( entetes, function ( th ) {
			titres.push( ( th.textContent || '' ).replace( /\s+/g, ' ' ).trim() );
		} );

		var lignes = table.querySelectorAll( 'tbody tr' );
		var utile = false;
		each( lignes, function ( tr ) {
			var cellules = tr.children;
			/* Une ligne « aucune donnée » occupe toute la largeur : la
			   transformer en fiche produirait une carte vide et trompeuse. */
			if ( 1 === cellules.length && cellules[ 0 ].hasAttribute( 'colspan' ) ) {
				return;
			}
			each( cellules, function ( td, i ) {
				td.setAttribute( 'data-acdc-label', titres[ i ] !== undefined ? titres[ i ] : '' );
			} );
			utile = true;
		} );

		if ( utile ) {
			table.classList.add( 'acdc-table-cards' );
		}
		table.setAttribute( 'data-acdc-cards', 'pret' );
	}

	function equipeTableaux( racine ) {
		each( ( racine || document ).querySelectorAll( 'table.acdc-table, .acdc-table-wrap table' ), equipeTableau );
	}

	/* ── Mise en route ───────────────────────────────────────────────────── */

	function demarre() {
		each( document.querySelectorAll( '.acdc-portal-layout' ), function ( layout ) {
			layout.classList.add( 'acdc-js-ready' );
			equipeMenu( layout );
		} );

		if ( window.innerWidth <= MOBILE_MAX ) {
			equipeTableaux( document );
		}

		/* Un tableau chargé après coup — une liste filtrée, un onglet ouvert —
		   doit être équipé lui aussi, sinon ses fiches n'auraient pas de
		   libellés et l'on ne saurait plus ce qu'on lit. */
		if ( window.MutationObserver ) {
			var observateur = new MutationObserver( function ( mutations ) {
				if ( window.innerWidth > MOBILE_MAX ) { return; }
				mutations.forEach( function ( m ) {
					each( m.addedNodes, function ( node ) {
						if ( 1 !== node.nodeType ) { return; }
						if ( 'TABLE' === node.tagName ) { equipeTableau( node ); }
						else { equipeTableaux( node ); }
					} );
				} );
			} );
			observateur.observe( document.body, { childList: true, subtree: true } );
		}

		/* Une rotation d'écran fait passer un iPad de 1024 à 768 : les tableaux
		   doivent alors être équipés, ce qu'ils n'étaient pas au chargement. */
		window.addEventListener( 'resize', function () {
			if ( window.innerWidth <= MOBILE_MAX ) {
				equipeTableaux( document );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarre );
	} else {
		demarre();
	}
} )();
