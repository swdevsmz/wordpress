<?php
/**
 * Main plugin loader.
 *
 * @package My_Push_Notification_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_Plugin {
	const OPTION_PUBLIC_KEY  = 'my_push_vapid_public_key';
	const OPTION_PRIVATE_KEY = 'my_push_vapid_private_key';
	const OPTION_SUBJECT     = 'my_push_vapid_subject';
	const OPTION_TITLE       = 'my_push_default_title';
	const OPTION_AUTO_POSTS  = 'my_push_auto_notify_posts';
	const REST_NAMESPACE     = 'my-push/v1';
	const NONCE_ACTION       = 'wp_rest';

	/**
	 * Singleton instance.
	 *
	 * @var My_Push_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Subscriber repository.
	 *
	 * @var My_Push_Subscriber_Repository
	 */
	private $repository;

	/**
	 * REST controller.
	 *
	 * @var My_Push_REST
	 */
	private $rest;

	/**
	 * Admin controller.
	 *
	 * @var My_Push_Admin
	 */
	private $admin;

	/**
	 * Push sender.
	 *
	 * @var My_Push_Web_Push_Service
	 */
	private $web_push;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->repository = new My_Push_Subscriber_Repository();
		$this->web_push   = new My_Push_Web_Push_Service( $this->repository );
		$this->rest       = new My_Push_REST( $this->repository );
		$this->admin      = new My_Push_Admin( $this->repository, $this->web_push );
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_subscription_button' ) );
		add_action( 'transition_post_status', array( $this, 'maybe_send_post_notification' ), 10, 3 );

		$this->rest->register_hooks();
		$this->admin->register_hooks();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'my-push-notification-plugin', false, dirname( plugin_basename( MY_PUSH_PLUGIN_FILE ) ) . '/languages' );
	}

	public function enqueue_frontend_assets() {
		if ( is_admin() || ! $this->should_render_subscribe_ui() ) {
			return;
		}

		wp_enqueue_style(
			'my-push-notification-plugin',
			MY_PUSH_PLUGIN_URL . 'assets/css/push.css',
			array(),
			MY_PUSH_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'my-push-notification-plugin',
			MY_PUSH_PLUGIN_URL . 'assets/js/subscribe.js',
			array(),
			MY_PUSH_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'my-push-notification-plugin',
			'MyPushNotifications',
			array(
				'nonce'            => wp_create_nonce( self::NONCE_ACTION ),
				'publicKeyUrl'     => $this->rest_route_url( '/public-key' ),
				'subscribeUrl'     => $this->rest_route_url( '/subscribe' ),
				'unsubscribeUrl'   => $this->rest_route_url( '/unsubscribe' ),
				'serviceWorkerUrl' => esc_url_raw( MY_PUSH_PLUGIN_URL . 'assets/js/service-worker.js' ),
				'strings'          => array(
					'unsupported'   => __( 'このブラウザは通知に対応していません。', 'my-push-notification-plugin' ),
					'default'       => __( '更新通知を受け取る', 'my-push-notification-plugin' ),
					'requesting'    => __( '通知の許可を確認しています...', 'my-push-notification-plugin' ),
					'subscribed'    => __( '通知を購読中', 'my-push-notification-plugin' ),
					'unsubscribing' => __( '購読を解除しています...', 'my-push-notification-plugin' ),
					'denied'        => __( 'ブラウザ設定で通知が拒否されています。', 'my-push-notification-plugin' ),
					'error'         => __( '通知設定を更新できませんでした。', 'my-push-notification-plugin' ),
					'missingKey'    => __( '通知の公開鍵が未設定です。', 'my-push-notification-plugin' ),
				),
			)
		);
	}

	public function render_subscription_button() {
		if ( is_admin() || ! $this->should_render_subscribe_ui() ) {
			return;
		}

		echo '<div class="my-push-subscribe" data-my-push-subscribe hidden>';
		echo '<button type="button" class="my-push-subscribe__button" data-my-push-button>';
		echo esc_html__( '更新通知を受け取る', 'my-push-notification-plugin' );
		echo '</button>';
		echo '<p class="my-push-subscribe__message" data-my-push-message></p>';
		echo '</div>';
	}

	/**
	 * 仕様: 投稿一覧 (home/archive) と投稿詳細 (singular post) のみ表示する。
	 */
	private function should_render_subscribe_ui() {
		return is_home() || is_front_page() || is_archive() || is_singular( 'post' );
	}

	private function rest_route_url( $route ) {
		return esc_url_raw(
			add_query_arg(
				'rest_route',
				'/' . My_Push_Plugin::REST_NAMESPACE . $route,
				home_url( '/' )
			)
		);
	}

	public function maybe_send_post_notification( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		if ( '1' !== get_option( self::OPTION_AUTO_POSTS, '0' ) ) {
			return;
		}

		$this->web_push->send_post_notification( (int) $post->ID );
	}
}
