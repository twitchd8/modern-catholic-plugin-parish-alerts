( function () {
	'use strict';

	const root = document.querySelector( '[data-parish-alerts]' );

	if ( ! root ) {
		return;
	}

	const storageKey = root.dataset.storageKey || 'parishAlertsAcknowledgedV1';
	const trigger = root.querySelector( '[data-parish-alerts-trigger]' );
	const closeButton = root.querySelector( '[data-parish-alerts-close]' );
	const panel = root.querySelector( '#parish-alerts-panel' );
	const countBadge = root.querySelector( '[data-alert-count]' );
	const alertItems = Array.from( root.querySelectorAll( '[data-alert-item]' ) );
	const markAllButton = root.querySelector( '[data-alert-mark-all]' );
	const modal = root.querySelector( '[data-alert-modal]' );
	const modalItems = Array.from( root.querySelectorAll( '[data-alert-modal-item]' ) );
	const modalAcknowledge = root.querySelector( '[data-alert-modal-ack]' );
	const modalDismissButtons = Array.from( root.querySelectorAll( '[data-alert-modal-dismiss]' ) );
	let acknowledged = loadAcknowledged();
	let modalDismissedThisPage = false;
	let modalPreviousFocus = null;

	if ( ! trigger || ! closeButton || ! panel || ! countBadge ) {
		return;
	}

	function loadAcknowledged() {
		try {
			const stored = JSON.parse( window.localStorage.getItem( storageKey ) || '[]' );
			return new Set( Array.isArray( stored ) ? stored.filter( value => 'string' === typeof value ) : [] );
		} catch ( error ) {
			return new Set();
		}
	}

	function saveAcknowledged() {
		try {
			const tokens = Array.from( acknowledged ).slice( -200 );
			acknowledged = new Set( tokens );
			window.localStorage.setItem( storageKey, JSON.stringify( tokens ) );
		} catch ( error ) {
			// Keep acknowledgment state in memory when browser storage is unavailable.
		}
	}

	function isAcknowledged( item ) {
		return acknowledged.has( item.dataset.alertToken );
	}

	function unseenItems( items ) {
		return items.filter( item => ! isAcknowledged( item ) );
	}

	function newAlertLabel( count ) {
		if ( 1 === count ) {
			return root.dataset.labelNewOne || '1 new alert';
		}

		return ( root.dataset.labelNewMany || '%d new alerts' ).replace( '%d', String( count ) );
	}

	function updateInterface() {
		alertItems.forEach( item => {
			const read = isAcknowledged( item );
			const button = item.querySelector( '[data-alert-ack]' );

			item.classList.toggle( 'is-unread', ! read );
			item.classList.toggle( 'is-read', read );

			if ( button ) {
				button.disabled = read;
				button.textContent = read ? root.dataset.labelRead : root.dataset.labelMarkRead;
			}
		} );

		modalItems.forEach( item => {
			item.hidden = isAcknowledged( item );
		} );

		const unseenCount = unseenItems( alertItems ).length;
		countBadge.hidden = 0 === unseenCount;
		countBadge.textContent = unseenCount ? String( unseenCount ) : '';
		countBadge.setAttribute( 'aria-label', unseenCount ? newAlertLabel( unseenCount ) : '' );
		trigger.setAttribute(
			'aria-label',
			unseenCount ? `${ root.dataset.labelButton }, ${ newAlertLabel( unseenCount ) }` : root.dataset.labelButton
		);

		if ( markAllButton ) {
			markAllButton.hidden = 0 === unseenCount;
		}

		if ( modal && 0 === unseenItems( modalItems ).length && ! modal.hidden ) {
			closeModal();
		}
	}

	function acknowledgeTokens( tokens ) {
		tokens.filter( Boolean ).forEach( token => acknowledged.add( token ) );
		saveAcknowledged();
		updateInterface();
	}

	function setPanelOpen( open, returnFocus = false ) {
		panel.hidden = ! open;
		trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		root.classList.toggle( 'is-open', open );

		if ( open ) {
			closeButton.focus();
		} else if ( returnFocus ) {
			trigger.focus();
		}
	}

	function modalFocusableElements() {
		if ( ! modal ) {
			return [];
		}

		return Array.from(
			modal.querySelectorAll( 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])' )
		).filter( element => ! element.hidden && ! element.closest( '[hidden]' ) );
	}

	function openModal() {
		if ( ! modal || modalDismissedThisPage || 0 === unseenItems( modalItems ).length ) {
			return;
		}

		setPanelOpen( false );
		modalPreviousFocus = document.activeElement;
		modal.hidden = false;
		document.body.classList.add( 'parish-alerts-modal-open' );

		const focusable = modalFocusableElements();
		if ( focusable.length ) {
			focusable[ 0 ].focus();
		}
	}

	function closeModal( returnFocus = true ) {
		if ( ! modal || modal.hidden ) {
			return;
		}

		modal.hidden = true;
		document.body.classList.remove( 'parish-alerts-modal-open' );

		if ( returnFocus && modalPreviousFocus && 'function' === typeof modalPreviousFocus.focus ) {
			modalPreviousFocus.focus();
		}
	}

	trigger.addEventListener( 'click', function () {
		setPanelOpen( panel.hidden );
	} );

	closeButton.addEventListener( 'click', function () {
		setPanelOpen( false, true );
	} );

	alertItems.forEach( item => {
		const button = item.querySelector( '[data-alert-ack]' );
		if ( button ) {
			button.addEventListener( 'click', function () {
				acknowledgeTokens( [ item.dataset.alertToken ] );
			} );
		}
	} );

	if ( markAllButton ) {
		markAllButton.addEventListener( 'click', function () {
			acknowledgeTokens( alertItems.map( item => item.dataset.alertToken ) );
		} );
	}

	if ( modalAcknowledge ) {
		modalAcknowledge.addEventListener( 'click', function () {
			acknowledgeTokens( unseenItems( modalItems ).map( item => item.dataset.alertToken ) );
			closeModal();
		} );
	}

	modalDismissButtons.forEach( button => {
		button.addEventListener( 'click', function () {
			modalDismissedThisPage = true;
			closeModal();
		} );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( modal && ! modal.hidden ) {
			if ( 'Escape' === event.key ) {
				modalDismissedThisPage = true;
				closeModal();
				return;
			}

			if ( 'Tab' === event.key ) {
				const focusable = modalFocusableElements();
				if ( ! focusable.length ) {
					return;
				}

				const first = focusable[ 0 ];
				const last = focusable[ focusable.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
			return;
		}

		if ( 'Escape' === event.key && ! panel.hidden ) {
			setPanelOpen( false, true );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( ! panel.hidden && ! root.contains( event.target ) ) {
			setPanelOpen( false );
		}
	} );

	window.addEventListener( 'storage', function ( event ) {
		if ( event.key === storageKey ) {
			acknowledged = loadAcknowledged();
			updateInterface();
		}
	} );

	updateInterface();
	window.setTimeout( openModal, 0 );
}() );
