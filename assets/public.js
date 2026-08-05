( function () {
	'use strict';

	const root = document.querySelector( '[data-parish-alerts]' );

	if ( ! root ) {
		return;
	}

	const trigger = root.querySelector( '[data-parish-alerts-trigger]' );
	const closeButton = root.querySelector( '[data-parish-alerts-close]' );
	const panel = root.querySelector( '#parish-alerts-panel' );

	if ( ! trigger || ! closeButton || ! panel ) {
		return;
	}

	function setOpen( open, returnFocus = false ) {
		panel.hidden = ! open;
		trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		root.classList.toggle( 'is-open', open );

		if ( open ) {
			closeButton.focus();
		} else if ( returnFocus ) {
			trigger.focus();
		}
	}

	trigger.addEventListener( 'click', function () {
		setOpen( panel.hidden );
	} );

	closeButton.addEventListener( 'click', function () {
		setOpen( false, true );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! panel.hidden ) {
			setOpen( false, true );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( ! panel.hidden && ! root.contains( event.target ) ) {
			setOpen( false );
		}
	} );
}() );
