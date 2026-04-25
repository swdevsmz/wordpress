self.addEventListener('push', function (event) {
	'use strict';

	var payload = {};

	if (event.data) {
		try {
			payload = event.data.json();
		} catch (error) {
			payload = {
				title: 'Notification',
				body: event.data.text()
			};
		}
	}

	var title = payload.title || 'Notification';
	var options = {
		body: payload.body || '',
		icon: payload.icon || undefined,
		badge: payload.icon || undefined,
		data: {
			url: payload.url || '/'
		}
	};

	event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
	'use strict';

	var targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

	event.notification.close();

	event.waitUntil(
		clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
			for (var i = 0; i < clientList.length; i++) {
				var client = clientList[i];

				if ('focus' in client && client.url === targetUrl) {
					return client.focus();
				}
			}

			if (clients.openWindow) {
				return clients.openWindow(targetUrl);
			}

			return null;
		})
	);
});
