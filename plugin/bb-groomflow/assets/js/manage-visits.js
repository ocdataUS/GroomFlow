/* global document */
( function () {
	'use strict';

	function handleToggle( button ) {
		const targetId = button.getAttribute( 'aria-controls' );
		if ( ! targetId ) {
			return;
		}

		const detailsRow = document.getElementById( targetId );
		const isOpen = button.getAttribute( 'aria-expanded' ) === 'true';
		const openText = button.getAttribute( 'data-open-text' ) || 'Details';
		const closeText = button.getAttribute( 'data-close-text' ) || 'Hide Details';

		button.setAttribute( 'aria-expanded', isOpen ? 'false' : 'true' );
		button.textContent = isOpen ? openText : closeText;

		if ( detailsRow ) {
			detailsRow.hidden = isOpen;
			detailsRow.classList.toggle( 'is-open', ! isOpen );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.bbgf-visit-toggle' );
		if ( ! button ) {
			return;
		}

		event.preventDefault();
		handleToggle( button );
	} );
}() );
