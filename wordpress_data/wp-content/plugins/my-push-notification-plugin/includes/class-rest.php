<?php

/**
 * REST API エンドポイントを担うコントローラークラス。
 *
 * 登録するエンドポイント:
 *   GET  /my-push/v1/public-key   — VAPID 公開鍵を返す（認証不要）。
 *   POST /my-push/v1/subscribe    — Web Push 購読情報を登録する。
 *   POST /my-push/v1/unsubscribe  — Web Push 購読を解除する。
 *
 * @package My_Push_Notification_Plugin
 */

// WordPress の外部から直接アクセスされた場合は終了する。
if (! defined('ABSPATH')) {
	exit;
}

/**
 * REST API コントローラークラス。
 *
 * WP_REST_Controller を継承せず、シンプルな register_rest_route() で
 * エンドポイントを定義する軽量な実装。
 */
class My_Push_REST
{

	/**
	 * Web Push 購読者の DB 操作を担うリポジトリ。
	 *
	 * @var My_Push_Subscriber_Repository
	 */
	private $repository;

	// ---- コンストラクタ --------------------------------------------------------

	/**
	 * @param My_Push_Subscriber_Repository $repository  Web Push 購読者リポジトリ。
	 */
	public function __construct(My_Push_Subscriber_Repository $repository)
	{
		$this->repository = $repository;
	}

	// ---- フック登録 ------------------------------------------------------------

	/**
	 * rest_api_init フックにルート登録コールバックを追加する。
	 */
	public function register_hooks()
	{
		add_action('rest_api_init', array($this, 'register_routes'));
	}

	// ---- ルート定義 ------------------------------------------------------------

	/**
	 * REST ルートをすべて登録する。
	 *
	 * 名前空間 my-push/v1 を使用するため、
	 * エンドポイント URL は /wp-json/my-push/v1/{route} となる。
	 */
	public function register_routes()
	{
		// ---- Web Push エンドポイント -------------------------------------------

		// GET /my-push/v1/public-key
		// フロントエンドが VAPID 公開鍵を取得するためのエンドポイント。
		// 認証不要（__return_true）で誰でも読み取れる。
		register_rest_route(
			My_Push_Plugin::REST_NAMESPACE,
			'/public-key',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_public_key'),
				'permission_callback' => '__return_true',
			)
		);

		// POST /my-push/v1/subscribe
		// ブラウザから送られた PushSubscription オブジェクトを DB に保存する。
		// nonce 検証（verify_nonce）でログイン不要だが CSRF を防ぐ。
		register_rest_route(
			My_Push_Plugin::REST_NAMESPACE,
			'/subscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'subscribe'),
				'permission_callback' => array($this, 'verify_nonce'),
			)
		);

		// POST /my-push/v1/unsubscribe
		// 指定したエンドポイントの購読状態を inactive に変更する。
		register_rest_route(
			My_Push_Plugin::REST_NAMESPACE,
			'/unsubscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'unsubscribe'),
				'permission_callback' => array($this, 'verify_nonce'),
				'args'                => array(
					// endpoint は必須パラメーター。URL 形式でサニタイズする。
					'endpoint' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	// ---- Web Push エンドポイントコールバック ------------------------------------

	/**
	 * VAPID 公開鍵を JSON で返す。
	 *
	 * 公開鍵が未設定の場合は 404 エラーを返す。
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_public_key()
	{
		$public_key = trim((string) get_option(My_Push_Plugin::OPTION_PUBLIC_KEY, ''));

		// 公開鍵が未設定の場合は 404 を返す。
		if ('' === $public_key) {
			return new WP_Error(
				'my_push_missing_public_key',
				__('VAPID public key is not configured.', 'my-push-notification-plugin'),
				array('status' => 404)
			);
		}

		return rest_ensure_response(
			array(
				'publicKey' => $public_key,
			)
		);
	}

	/**
	 * REST リクエストの nonce を検証する（permission_callback として使用）。
	 *
	 * X-WP-Nonce ヘッダーまたは _wpnonce クエリパラメーターを確認する。
	 * これにより認証なし（ゲスト）でも CSRF を防止できる。
	 *
	 * @param WP_REST_Request $request REST リクエストオブジェクト。
	 * @return true|WP_Error 検証成功なら true、失敗なら 403 WP_Error。
	 */
	public function verify_nonce(WP_REST_Request $request)
	{
		// ヘッダーから nonce を取得する（JavaScript の fetch() が送る標準的な方法）。
		$nonce = $request->get_header('X-WP-Nonce');

		// ヘッダーにない場合はクエリパラメーターから取得する。
		if (! $nonce) {
			$nonce = (string) $request->get_param('_wpnonce');
		}

		// wp_verify_nonce() で CSRF トークンを検証する。
		if (wp_verify_nonce($nonce, My_Push_Plugin::NONCE_ACTION)) {
			return true;
		}

		// 検証失敗：403 Forbidden を返す。
		return new WP_Error(
			'my_push_invalid_nonce',
			__('Invalid request token.', 'my-push-notification-plugin'),
			array('status' => 403)
		);
	}

	/**
	 * ブラウザの PushSubscription オブジェクトを受け取り DB に保存する。
	 *
	 * リクエストボディ（JSON）の期待する形式:
	 * {
	 *   "endpoint": "https://...",
	 *   "keys": {
	 *     "p256dh": "Base64URL 公開鍵",
	 *     "auth":   "Base64URL 認証シークレット"
	 *   }
	 * }
	 *
	 * @param WP_REST_Request $request REST リクエストオブジェクト。
	 * @return WP_REST_Response|WP_Error
	 */
	public function subscribe(WP_REST_Request $request)
	{
		// JSON ボディを優先し、フォームエンコードにもフォールバックする。
		$params = $request->get_json_params();

		if (! is_array($params)) {
			$params = $request->get_params();
		}

		// 各フィールドを取り出してサニタイズする。
		$endpoint = isset($params['endpoint']) ? esc_url_raw((string) $params['endpoint']) : '';
		$keys     = isset($params['keys']) && is_array($params['keys']) ? $params['keys'] : array();
		$p256dh   = isset($keys['p256dh']) ? sanitize_text_field((string) $keys['p256dh']) : '';
		$auth     = isset($keys['auth']) ? sanitize_text_field((string) $keys['auth']) : '';

		// 購読情報のバリデーション：URL が有効かつ鍵が両方存在すること。
		if (! $this->is_valid_subscription($endpoint, $p256dh, $auth)) {
			return new WP_Error(
				'my_push_invalid_subscription',
				__('Invalid push subscription.', 'my-push-notification-plugin'),
				array('status' => 400)
			);
		}

		// UA 文字列を取得してサニタイズする（統計目的のみ）。
		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

		// DB に upsert する（既存なら更新、なければ新規追加）。
		$saved = $this->repository->upsert($endpoint, $p256dh, $auth, $user_agent, get_current_user_id());

		if (! $saved) {
			return new WP_Error(
				'my_push_subscribe_failed',
				__('Could not save push subscription.', 'my-push-notification-plugin'),
				array('status' => 500)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'active',
			)
		);
	}

	/**
	 * 指定したエンドポイントの購読を解除する。
	 *
	 * DB のレコードは削除せず status を 'inactive' に変更する（履歴保持）。
	 *
	 * @param WP_REST_Request $request REST リクエストオブジェクト。
	 * @return WP_REST_Response|WP_Error
	 */
	public function unsubscribe(WP_REST_Request $request)
	{
		// register_routes() の args で sanitize_callback が適用済み。
		$endpoint = esc_url_raw((string) $request->get_param('endpoint'));

		// URL の形式を再確認する（二重チェック）。
		if (! wp_http_validate_url($endpoint)) {
			return new WP_Error(
				'my_push_invalid_endpoint',
				__('Invalid push endpoint.', 'my-push-notification-plugin'),
				array('status' => 400)
			);
		}

		// DB のステータスを 'inactive' に更新する。
		$this->repository->mark_inactive($endpoint);

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'inactive',
			)
		);
	}

	/**
	 * Push 購読情報が有効かどうかを検証する（内部ヘルパー）。
	 *
	 * @param string $endpoint   Push エンドポイント URL。
	 * @param string $public_key クライアント P-256 公開鍵（Base64URL）。
	 * @param string $auth_token 認証シークレット（Base64URL）。
	 * @return bool すべて揃っており URL が有効なら true。
	 */
	private function is_valid_subscription($endpoint, $public_key, $auth_token)
	{
		return wp_http_validate_url($endpoint)
			&& '' !== $public_key
			&& '' !== $auth_token;
	}
}
