/**
 * Evolurise Webhook Delivery Recovery for WooCommerce: delivery log screen.
 * Shows or hides the payload of a delivery row.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '.evowg-toggle-body' );
		if ( ! toggle ) {
			return;
		}
		event.preventDefault();
		var panel = document.getElementById( 'evowg-body-' + toggle.dataset.id );
		if ( panel ) {
			panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
		}
	} );
}() );
