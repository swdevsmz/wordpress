<?php
/**
 * FCM プラグインのメインローダー。
 *
 * @package My_Push_Notification_FCM_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_FCM_Plugin {

	/** FCM 経由の通知配信を有効化するかどうか。値は '1' または '0'。 */
	const OPTION_ENABLED = 'my_push_fcm_enabled';

	/** Firebase プロジェクト ID。 */
	const OPTION_PROJECT_ID = 'my_push_fcm_project_id';

	/** Firebase サービスアカウント JSON。autoload=no で保存します。 */
	const OPTION_SERVICE_ACCOUNT = 'my_push_fcm_service_account';

	/** Web クライアント向け FCM VAPID 公開鍵。ブラウザーへ渡して使います。 */
	const OPTION_WEB_VAPID = 'my_push_fcm_web_vapid_public';

	/** 通知タイトルの初期値。 */
	const OPTION_TITLE = 'my_push_fcm_default_title';

	/** 投稿公開時に自動通知を送るかどうか。値は '1' または '0'。 */
	const OPTION_AUTO_POSTS = 'my_push_fcm_auto_notify_posts';

	/** REST API 名前空間。wp-json/my-push-fcm/v1/... の形で利用されます。 */
	const REST_NAMESPACE = 'my-push-fcm/v1';

	/** REST リクエストで使う nonce アクション名。 */
	const NONCE_ACTION = 'wp_rest';

	/**
	 * @var My_Push_FCM_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var My_Push_FCM_Token_Repository
	 */
	private $tokens;

	/**
	 * @var My_Push_FCM_OAuth
	 */
	private $oauth;

	/**
	 * @var My_Push_FCM_Sender
	 */
	private $sender;

	/**
	 * @var My_Push_FCM_REST
	 */
	private $rest;

	/**
	 * @var My_Push_FCM_Admin
	 */
	private $admin;

	/**
	 * プラグイン全体で共有するインスタンスを返します。
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * 各責務のクラスを生成し、依存関係を渡します。
	 */
	private function __construct() {
		$this->tokens = new My_Push_FCM_Token_Repository();
		$this->oauth  = new My_Push_FCM_OAuth();
		$this->sender = new My_Push_FCM_Sender( $this->tokens, $this->oauth );
		$this->rest   = new My_Push_FCM_REST( $this->tokens );
		$this->admin  = new My_Push_FCM_Admin( $this->tokens, $this->sender, $this->oauth );
	}

	/**
	 * WordPress のフックへ各機能を登録します。
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'transition_post_status', array( $this, 'maybe_send_post_notification' ), 10, 3 );

		$this->rest->register_hooks();
		$this->admin->register_hooks();
	}

	/**
	 * 翻訳ファイルを読み込みます。
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'my-push-notification-fcm-plugin', false, dirname( plugin_basename( MY_PUSH_FCM_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * フロントエンドに FCM クライアント用設定を渡す軽量スクリプトを出力します。
	 *
	 * 実際の Firebase JS SDK の読み込みや messaging.getToken() の呼び出しは、
	 * テーマまたはアプリ側で行う想定です。ここでは window.MyPushFCM に
	 * REST エンドポイント URL と nonce を渡します。
	 */
	public function enqueue_frontend_assets() {
		if ( is_admin() ) {
			return;
		}

		if ( '1' !== (string) get_option( self::OPTION_ENABLED, '0' ) ) {
			return;
		}

		wp_register_script(
			'my-push-notification-fcm-plugin',
			MY_PUSH_FCM_PLUGIN_URL . 'assets/js/fcm-config.js',
			array(),
			MY_PUSH_FCM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'my-push-notification-fcm-plugin',
			'MyPushFCM',
			array(
				'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
				'webConfigUrl'  => $this->rest_route_url( '/web-config' ),
				'registerUrl'   => $this->rest_route_url( '/register' ),
				'unregisterUrl' => $this->rest_route_url( '/unregister' ),
			)
		);

		wp_enqueue_script( 'my-push-notification-fcm-plugin' );
	}

	/**
	 * パーマリンク設定に依存しない REST API URL を組み立てます。
	 */
	private function rest_route_url( $route ) {
		return esc_url_raw(
			add_query_arg(
				'rest_route',
				'/' . self::REST_NAMESPACE . $route,
				home_url( '/' )
			)
		);
	}

	/**
	 * 投稿が新規公開されたら、購読中の FCM トークンへ通知を送ります。
	 *
	 * @param string  $new_status 新しい投稿ステータス。
	 * @param string  $old_status 変更前の投稿ステータス。
	 * @param WP_Post $post       対象投稿。
	 */
	public function maybe_send_post_notification( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		if ( '1' !== (string) get_option( self::OPTION_AUTO_POSTS, '0' ) ) {
			return;
		}

		if ( ! $this->sender->is_configured() ) {
			return;
		}

		$title = trim( (string) get_option( self::OPTION_TITLE, '' ) );
		if ( '' === $title ) {
			$title = get_bloginfo( 'name' );
		}

		$icon = get_site_icon_url();

		$this->sender->send(
			array(
				'title' => $title,
				'body'  => get_the_title( $post ),
				'url'   => get_permalink( $post ),
				'icon'  => $icon ? $icon : '',
			)
		);
	}
}
