<?php
/**
 * REST API endpoints.
 *
 * @package My_Push_Notification_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_REST {
	/**
	 * Subscriber repository.
	 *
	 * @var My_Push_Subscriber_Repository
	 */
	private $repository;

	public function __construct( My_Push_Subscriber_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			My_Push_Plugin::REST_NAMESPACE,
			'/public-key',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_public_key' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			My_Push_Plugin::REST_NAMESPACE,
			'/subscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'subscribe' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
			)
		);

		register_rest_route(
			My_Push_Plugin::REST_NAMESPACE,
			'/unsubscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'unsubscribe' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
				'args'                => array(
					'endpoint' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	public function get_public_key() {
		$public_key = trim( (string) get_option( My_Push_Plugin::OPTION_PUBLIC_KEY, '' ) );

		if ( '' === $public_key ) {
			return new WP_Error(
				'my_push_missing_public_key',
				__( 'VAPID public key is not configured.', 'my-push-notification-plugin' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'publicKey' => $public_key,
			)
		);
	}

	public function verify_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		if ( wp_verify_nonce( $nonce, My_Push_Plugin::NONCE_ACTION ) ) {
			return true;
		}

		return new WP_Error(
			'my_push_invalid_nonce',
			__( 'Invalid request token.', 'my-push-notification-plugin' ),
			array( 'status' => 403 )
		);
	}

	public function subscribe( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$endpoint = isset( $params['endpoint'] ) ? esc_url_raw( (string) $params['endpoint'] ) : '';
		$keys     = isset( $params['keys'] ) && is_array( $params['keys'] ) ? $params['keys'] : array();
		$p256dh   = isset( $keys['p256dh'] ) ? sanitize_text_field( (string) $keys['p256dh'] ) : '';
		$auth     = isset( $keys['auth'] ) ? sanitize_text_field( (string) $keys['auth'] ) : '';

		if ( ! $this->is_valid_subscription( $endpoint, $p256dh, $auth ) ) {
			return new WP_Error(
				'my_push_invalid_subscription',
				__( 'Invalid push subscription.', 'my-push-notification-plugin' ),
				array( 'status' => 400 )
			);
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$saved      = $this->repository->upsert( $endpoint, $p256dh, $auth, $user_agent, get_current_user_id() );

		if ( ! $saved ) {
			return new WP_Error(
				'my_push_subscribe_failed',
				__( 'Could not save push subscription.', 'my-push-notification-plugin' ),
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

	public function unsubscribe( WP_REST_Request $request ) {
		$endpoint = esc_url_raw( (string) $request->get_param( 'endpoint' ) );

		if ( ! wp_http_validate_url( $endpoint ) ) {
			return new WP_Error(
				'my_push_invalid_endpoint',
				__( 'Invalid push endpoint.', 'my-push-notification-plugin' ),
				array( 'status' => 400 )
			);
		}

		$this->repository->mark_inactive( $endpoint );

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'inactive',
			)
		);
	}

	private function is_valid_subscription( $endpoint, $public_key, $auth_token ) {
		return wp_http_validate_url( $endpoint )
			&& '' !== $public_key
			&& '' !== $auth_token;
	}
}
