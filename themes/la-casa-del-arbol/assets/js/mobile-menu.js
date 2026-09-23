/**
 * Mobile navigation dialog (Handoff §1.7).
 *
 * The native <dialog> opened with showModal() provides focus containment,
 * Esc to close and an inert background. This script only wires the buttons,
 * locks page scroll while open, closes the menu when a link is chosen and
 * when the viewport reaches the desktop layout.
 */
( function () {
	'use strict';

	var dialog = document.getElementById( 'lcda-mobile-menu' );
	var toggle = document.querySelector( '[data-lcda-menu-open]' );

	if ( ! dialog || ! toggle || typeof dialog.showModal !== 'function' ) {
		return;
	}

	var root = document.documentElement;
	var closeButton = dialog.querySelector( '[data-lcda-menu-close]' );
	var desktop = window.matchMedia( '(min-width: 768px)' );

	function open() {
		if ( dialog.open ) {
			return;
		}
		dialog.showModal();
		root.classList.add( 'lcda-menu-open' );
		toggle.setAttribute( 'aria-expanded', 'true' );
	}

	function close() {
		if ( dialog.open ) {
			dialog.close();
		}
	}

	toggle.addEventListener( 'click', open );

	if ( closeButton ) {
		closeButton.addEventListener( 'click', close );
	}

	// Choosing a destination navigates and closes the menu.
	dialog.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( 'a[href]' ) ) {
			close();
		}
	} );

	// Runs for every way of closing: button, Esc, link, resize.
	dialog.addEventListener( 'close', function () {
		root.classList.remove( 'lcda-menu-open' );
		toggle.setAttribute( 'aria-expanded', 'false' );
		toggle.focus();
	} );

	function onViewportChange( event ) {
		if ( event.matches ) {
			close();
		}
	}

	if ( desktop.addEventListener ) {
		desktop.addEventListener( 'change', onViewportChange );
	} else if ( desktop.addListener ) {
		desktop.addListener( onViewportChange );
	}
}() );
