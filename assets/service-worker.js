'use strict';

self.addEventListener( 'push', function ( event ) {
	let data = {};

	try {
		data = event.data ? event.data.json() : {};
	} catch ( error ) {
		data = {};
	}

	const title = data.title || 'Parish Alert';
	const options = {
		body: data.body || '',
		icon: data.icon || undefined,
		tag: data.tag || `parish-alert-${ data.id || 'new' }`,
		renotify: true,
		requireInteraction: 'emergency' === data.level,
		data: {
			url: data.url || '/',
		},
	};

	event.waitUntil( self.registration.showNotification( title, options ) );
} );

self.addEventListener( 'notificationclick', function ( event ) {
	event.notification.close();
	const targetUrl = new URL( event.notification.data?.url || '/', self.location.origin ).href;

	event.waitUntil(
		clients.matchAll( { type: 'window', includeUncontrolled: true } ).then( function ( windowClients ) {
			for ( const client of windowClients ) {
				if ( client.url === targetUrl && 'focus' in client ) {
					return client.focus();
				}
			}

			return clients.openWindow ? clients.openWindow( targetUrl ) : undefined;
		} )
	);
} );
