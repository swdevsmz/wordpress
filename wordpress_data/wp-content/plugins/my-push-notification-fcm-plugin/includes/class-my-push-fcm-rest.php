<?php
/**
 * FCM 用 REST API コントローラー。
 *
 * エンドポイント:
 *   GET  /wp-json/my-push-fcm/v1/web-config   FCM Web 用 VAPID 公開鍵を返す。
 *   POST /wp-json/my-push-fcm/v1/register     FCM 登録トークンを保存する。
 *   POST /wp-json/my-push-fcm/v1/unregister   FCM 登録トークンを無効化する。
 *
 * @package My_Push_Notification_FCM_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_FCM_REST {

	/**
	 * @var My_Push_FCM_Token_Repository
	 */
	private $tokens;

	public function __construct( My_Push_FCM_Token_Repository $tokens ) {
		$this->tokens = $tokens;
	}

	/**
	 * REST API 初期化タイミングでルート登録を行います。
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * FCM クライアント設定取得・トークン登録・解除の REST ルートを登録します。
	 */
	public function register_routes() {
		register_rest_route(
			My_Push_FCM_Plugin::REST_NAMESPACE,
			'/web-config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_web_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			My_Push_FCM_Plugin::REST_NAMESPACE,
			'/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_token' ),
				'permission_callback' => array( $this, 'verify_register_permission' ),
			)
		);

		register_rest_route(
			My_Push_FCM_Plugin::REST_NAMESPACE,
			'/unregister',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'unregister_token' ),
				'permission_callback' => array( $this, 'verify_register_permission' ),
				'args'                => array(
					'token' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * ブラウザーが FCM トークン取得に使う VAPID 公開鍵を返します。
	 */
	public function get_web_config() {
		if ( '1' !== (string) get_option( My_Push_FCM_Plugin::OPTION_ENABLED, '0' ) ) {
			return new WP_Error(
				'my_push_fcm_disabled',
				__( 'FCM transport is not enabled.', 'my-push-notification-fcm-plugin' ),
				array( 'status' => 404 )
			);
		}

		$vapid = trim( (string) get_option( My_Push_FCM_Plugin::OPTION_WEB_VAPID, '' ) );

		if ( '' === $vapid ) {
			return new WP_Error(
				'my_push_fcm_missing_web_vapid',
				__( 'FCM web VAPID public key is not configured.', 'my-push-notification-fcm-plugin' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'vapidPublicKey' => $vapid,
			)
		);
	}

	/**
	 * ログインユーザーまたは有効な REST nonce を持つリクエストだけ許可します。
	 */
	public function verify_register_permission( WP_REST_Request $request ) {
		if ( is_user_logged_in() ) {
			return true;
		}

		return $this->verify_nonce( $request );
	}

	/**
	 * ヘッダーまたはリクエストパラメータから nonce を取り出して検証します。
	 */
	public function verify_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		if ( wp_verify_nonce( $nonce, My_Push_FCM_Plugin::NONCE_ACTION ) ) {
			return true;
		}

		return new WP_Error(
			'my_push_fcm_invalid_nonce',
			__( 'Invalid request token.', 'my-push-notification-fcm-plugin' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * クライアントから送られた FCM 登録トークンを保存します。
	 */
	public function register_token( WP_REST_Request $request ) {
		if ( '1' !== (string) get_option( My_Push_FCM_Plugin::OPTION_ENABLED, '0' ) ) {
			return new WP_Error(
				'my_push_fcm_disabled',
				__( 'FCM transport is not enabled.', 'my-push-notification-fcm-plugin' ),
				array( 'status' => 404 )
			);
		}

		$rate_limit = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$token        = isset( $params['token'] ) ? sanitize_text_field( (string) $params['token'] ) : '';
		$platform     = isset( $params['platform'] ) ? sanitize_text_field( (string) $params['platform'] ) : 'unknown';
		$app_id       = isset( $params['app_id'] ) ? sanitize_text_field( (string) $params['app_id'] ) : '';
		$device_label = isset( $params['device_label'] ) ? sanitize_text_field( (string) $params['device_label'] ) : '';

		if ( '' === $token || strlen( $token ) > 4096 ) {
			return new WP_Error(
				'my_push_fcm_invalid_token',
				__( 'Invalid FCM registration token.', 'my-push-notification-fcm-plugin' ),
				array( 'status' => 400 )
			);
		}

		$saved = $this->tokens->upsert( $token, $platform, $app_id, $device_label, get_current_user_id() );

		if ( ! $saved ) {
			return new WP_Error(
				'my_push_fcm_register_failed',
				__( 'Could not save FCM registration token.', 'my-push-notification-fcm-plugin' ),
				array( 'status' => 500 )
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
	 * 指定された FCM 登録トークンを無効化します。
	 */
	public function unregister_token( WP_REST_Request $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );

		if ( '' === $token ) {
			return new WP_Error(
				'my_push_fcm_invalid_token',
				__( 'Invalid FCM registration token.', 'my-push-notification-fcm-plugin' ),
				array( 'status' => 400 )
			);
		}

		$this->tokens->mark_inactive_by_token( $token );

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'inactive',
			)
		);
	}

	/**
	 * トークン登録 API への短時間の連続アクセスを抑制します。
	 */
	private function check_rate_limit() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$id = is_user_logged_in() ? 'u' . get_current_user_id() : 'ip-' . md5( $ip );
		$tk = 'my_push_fcm_rl_' . $id;

		$count = (int) get_transient( $tk );
		if ( $count >= 30 ) {
			return new WP_Error(
				'my_push_fcm_rate_limited',
				__( 'Too many registration attempts. Please try again later.', 'my-push-notification-fcm-plugin' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $tk, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}
}
