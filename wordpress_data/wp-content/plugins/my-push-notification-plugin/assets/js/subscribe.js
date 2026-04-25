(function () {
	'use strict';

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}

		callback();
	}

	function urlBase64ToUint8Array(base64String) {
		var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
		var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
		var rawData = window.atob(base64);
		var outputArray = new Uint8Array(rawData.length);

		for (var i = 0; i < rawData.length; i++) {
			outputArray[i] = rawData.charCodeAt(i);
		}

		return outputArray;
	}

	function requestJson(url, options) {
		options = options || {};
		options.headers = Object.assign(
			{
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.MyPushNotifications.nonce
			},
			options.headers || {}
		);

		return window.fetch(url, options).then(function (response) {
			if (!response.ok) {
				throw new Error('Request failed: ' + response.status);
			}

			return response.json();
		});
	}

	function setState(elements, label, message, disabled) {
		elements.button.textContent = label;
		elements.button.disabled = Boolean(disabled);
		elements.message.textContent = message || '';
	}

	ready(function () {
		var config = window.MyPushNotifications;
		var root = document.querySelector('[data-my-push-subscribe]');

		if (!config || !root) {
			return;
		}

		var elements = {
			button: root.querySelector('[data-my-push-button]'),
			message: root.querySelector('[data-my-push-message]')
		};

		root.hidden = false;

		if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
			setState(elements, config.strings.default, config.strings.unsupported, true);
			return;
		}

		if (window.Notification.permission === 'denied') {
			setState(elements, config.strings.default, config.strings.denied, true);
			return;
		}

		var publicKeyPromise = window.fetch(config.publicKeyUrl)
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Public key unavailable.');
				}

				return response.json();
			})
			.then(function (data) {
				if (!data.publicKey) {
					throw new Error('Public key missing.');
				}

				return data.publicKey;
			});

		var registrationPromise = navigator.serviceWorker.register(config.serviceWorkerUrl);

		Promise.all([publicKeyPromise, registrationPromise])
			.then(function (results) {
				var publicKey = results[0];
				var registration = results[1];

				return registration.pushManager.getSubscription().then(function (subscription) {
					if (subscription) {
						setState(elements, config.strings.subscribed, '', false);
					} else {
						setState(elements, config.strings.default, '', false);
					}

					elements.button.addEventListener('click', function () {
						if (elements.button.disabled) {
							return;
						}

						if (subscription) {
							setState(elements, config.strings.unsubscribing, '', true);
							subscription.unsubscribe()
								.then(function () {
									return requestJson(config.unsubscribeUrl, {
										method: 'POST',
										body: JSON.stringify({ endpoint: subscription.endpoint })
									});
								})
								.then(function () {
									subscription = null;
									setState(elements, config.strings.default, '', false);
								})
								.catch(function () {
									setState(elements, config.strings.subscribed, config.strings.error, false);
								});
							return;
						}

						setState(elements, config.strings.requesting, '', true);

						window.Notification.requestPermission()
							.then(function (permission) {
								if (permission !== 'granted') {
									throw new Error('Notification permission not granted.');
								}

								return registration.pushManager.subscribe({
									userVisibleOnly: true,
									applicationServerKey: urlBase64ToUint8Array(publicKey)
								});
							})
							.then(function (newSubscription) {
								subscription = newSubscription;
								return requestJson(config.subscribeUrl, {
									method: 'POST',
									body: JSON.stringify(newSubscription.toJSON())
								});
							})
							.then(function () {
								setState(elements, config.strings.subscribed, '', false);
							})
							.catch(function () {
								setState(elements, config.strings.default, config.strings.error, false);
							});
					});
				});
			})
			.catch(function () {
				setState(elements, config.strings.default, config.strings.missingKey, true);
			});
	});
})();
