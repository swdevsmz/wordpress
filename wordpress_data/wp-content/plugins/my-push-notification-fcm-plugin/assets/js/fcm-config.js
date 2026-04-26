/**
 * FCM クライアント設定を window.MyPushFCM 経由で扱う補助スクリプト。
 *
 * Firebase JS SDK の読み込みや messaging.getToken() の呼び出しは、
 * テーマまたはアプリ側で行う想定です。このファイルでは REST API へ
 * FCM トークンを登録・解除するための関数だけを公開します。
 */
(function () {
	'use strict';

	if (!window.MyPushFCM) {
		return;
	}

	/**
	 * FCM 登録トークンを WordPress 側に保存します。
	 *
	 * @param {string} token FCM から取得した登録トークン。
	 * @param {Object} extra platform や app_id などの追加情報。
	 * @return {Promise<Object>} REST API のレスポンス。
	 */
	window.MyPushFCM.registerToken = function (token, extra) {
		extra = extra || {};
		var payload = Object.assign(
			{ token: token, platform: extra.platform || 'web' },
			extra
		);
		return window.fetch(window.MyPushFCM.registerUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.MyPushFCM.nonce
			},
			body: JSON.stringify(payload)
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('FCM register failed: ' + response.status);
			}
			return response.json();
		});
	};

	/**
	 * FCM 登録トークンを WordPress 側で無効化します。
	 *
	 * @param {string} token 無効化する FCM 登録トークン。
	 * @return {Promise<Object>} REST API のレスポンス。
	 */
	window.MyPushFCM.unregisterToken = function (token) {
		return window.fetch(window.MyPushFCM.unregisterUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.MyPushFCM.nonce
			},
			body: JSON.stringify({ token: token })
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('FCM unregister failed: ' + response.status);
			}
			return response.json();
		});
	};
})();
