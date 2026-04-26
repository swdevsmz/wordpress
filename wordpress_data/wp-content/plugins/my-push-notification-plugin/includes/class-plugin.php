<?php

/**
 * プラグインのメインローダー。
 *
 * 全クラスのインスタンスを保持するシングルトンとして機能し、
 * WordPress の各フックへの登録を一元管理する。
 *
 * @package My_Push_Notification_Plugin
 */

// WordPress の外部から直接アクセスされた場合は終了する。
if (! defined('ABSPATH')) {
	exit;
}

/**
 * プラグインのメインクラス。
 *
 * シングルトンパターンを採用しており、インスタンスは instance() で取得する。
 * 各サブシステム（REST・管理画面・購読者リポジトリ・Push 送信）を生成・保持する。
 */
class My_Push_Plugin
{

	// ---- WordPress オプション名定数 -------------------------------------------

	/** VAPID 公開鍵を保存する wp_options のキー。 */
	const OPTION_PUBLIC_KEY  = 'my_push_vapid_public_key';

	/** VAPID 秘密鍵を保存する wp_options のキー。 */
	const OPTION_PRIVATE_KEY = 'my_push_vapid_private_key';

	/**
	 * VAPID サブジェクトを保存する wp_options のキー。
	 * サイト URL または mailto: アドレスを格納する。
	 */
	const OPTION_SUBJECT     = 'my_push_vapid_subject';

	/** 通知のデフォルトタイトルを保存する wp_options のキー。 */
	const OPTION_TITLE       = 'my_push_default_title';

	/**
	 * 投稿公開時に自動通知を送るかどうかを保存する wp_options のキー。
	 * 値は '1'（有効）または '0'（無効）。
	 */
	const OPTION_AUTO_POSTS  = 'my_push_auto_notify_posts';

	/** REST API 名前空間。エンドポイント URL は /wp-json/my-push/v1/... の形式になる。 */
	const REST_NAMESPACE     = 'my-push/v1';

	/**
	 * REST リクエストの nonce アクション名。
	 * フロントエンドは wp_create_nonce( 'wp_rest' ) で生成し、
	 * REST コントローラーが wp_verify_nonce() で検証する。
	 */
	const NONCE_ACTION       = 'wp_rest';

	// ---- シングルトン ----------------------------------------------------------

	/**
	 * 唯一のインスタンスを保持するプロパティ。
	 *
	 * @var My_Push_Plugin|null
	 */
	private static $instance = null;

	// ---- 依存クラスのインスタンス ---------------------------------------------

	/**
	 * 購読者の DB 操作を担うリポジトリ。
	 *
	 * @var My_Push_Subscriber_Repository
	 */
	private $repository;

	/**
	 * REST API コントローラー。
	 *
	 * @var My_Push_REST
	 */
	private $rest;

	/**
	 * 管理画面コントローラー。
	 *
	 * @var My_Push_Admin
	 */
	private $admin;

	/**
	 * Web Push 送信サービス。
	 *
	 * @var My_Push_Web_Push_Service
	 */
	private $web_push;

	// ---- シングルトン取得 ------------------------------------------------------

	/**
	 * インスタンスを返す。未生成なら new self() で生成する。
	 *
	 * @return My_Push_Plugin
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// ---- コンストラクタ --------------------------------------------------------

	/**
	 * 各依存クラスをインスタンス化する。
	 * シングルトンのため private にして外部からの new を禁止する。
	 */
	private function __construct()
	{
		$this->repository = new My_Push_Subscriber_Repository();
		$this->web_push   = new My_Push_Web_Push_Service($this->repository);
		$this->rest       = new My_Push_REST($this->repository);
		$this->admin      = new My_Push_Admin($this->repository, $this->web_push);
	}

	// ---- フック登録 ------------------------------------------------------------

	/**
	 * WordPress のアクション・フィルターフックをすべて登録する。
	 * plugins_loaded 後に呼び出される。
	 */
	public function register_hooks()
	{
		// テキストドメインを読み込む（i18n 対応）。
		add_action('init', array($this, 'load_textdomain'));

		// フロントエンド用 CSS/JS をエンキューする。
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

		// フッターに購読ボタンの HTML を出力する。
		add_action('wp_footer', array($this, 'render_subscription_button'));

		// 投稿ステータスが変わったとき（下書き→公開など）に自動通知を送信する。
		add_action('transition_post_status', array($this, 'maybe_send_post_notification'), 10, 3);

		// REST API エンドポイントを登録する。
		$this->rest->register_hooks();

		// 管理画面のメニュー・設定・フォーム処理を登録する。
		$this->admin->register_hooks();
	}

	// ---- i18n -----------------------------------------------------------------

	/**
	 * プラグインのテキストドメインを読み込む。
	 * languages/ ディレクトリに .mo ファイルを置くことで翻訳できる。
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain('my-push-notification-plugin', false, dirname(plugin_basename(MY_PUSH_PLUGIN_FILE)) . '/languages');
	}

	// ---- フロントエンド資産 ----------------------------------------------------

	/**
	 * フロントエンド用の CSS と JavaScript をエンキューし、
	 * JS に必要な設定値（nonce・エンドポイント URL・文字列）を wp_localize_script() で渡す。
	 *
	 * 管理画面や購読 UI を表示しないページでは何もしない。
	 */
	public function enqueue_frontend_assets()
	{
		// 管理画面または購読 UI が不要なページでは何もしない。
		if (is_admin() || ! $this->should_render_subscribe_ui()) {
			return;
		}

		// 購読ボタン用のスタイルシートを登録・エンキューする。
		wp_enqueue_style(
			'my-push-notification-plugin',
			MY_PUSH_PLUGIN_URL . 'assets/css/push.css',
			array(),
			MY_PUSH_PLUGIN_VERSION
		);

		// 購読処理を行うメイン JavaScript を登録・エンキューする（フッターに出力）。
		wp_enqueue_script(
			'my-push-notification-plugin',
			MY_PUSH_PLUGIN_URL . 'assets/js/subscribe.js',
			array(),
			MY_PUSH_PLUGIN_VERSION,
			true  // フッターに出力することで DOM 読み込み後に実行される。
		);

		// JavaScript に渡す設定オブジェクト（window.MyPushNotifications）を生成する。
		wp_localize_script(
			'my-push-notification-plugin',
			'MyPushNotifications',
			array(
				// REST リクエストの CSRF トークン。
				'nonce'            => wp_create_nonce(self::NONCE_ACTION),
				// VAPID 公開鍵を取得する REST エンドポイント URL。
				'publicKeyUrl'     => $this->rest_route_url('/public-key'),
				// 購読登録を行う REST エンドポイント URL。
				'subscribeUrl'     => $this->rest_route_url('/subscribe'),
				// 購読解除を行う REST エンドポイント URL。
				'unsubscribeUrl'   => $this->rest_route_url('/unsubscribe'),
				// ブラウザが Service Worker として登録するスクリプトの URL。
				'serviceWorkerUrl' => esc_url_raw(MY_PUSH_PLUGIN_URL . 'assets/js/service-worker.js'),
				// JavaScript 側で表示するユーザー向けメッセージ（翻訳対応）。
				'strings'          => array(
					'unsupported'   => __('このブラウザは通知に対応していません。', 'my-push-notification-plugin'),
					'default'       => __('更新通知を受け取る', 'my-push-notification-plugin'),
					'requesting'    => __('通知の許可を確認しています...', 'my-push-notification-plugin'),
					'subscribed'    => __('通知を購読中', 'my-push-notification-plugin'),
					'unsubscribing' => __('購読を解除しています...', 'my-push-notification-plugin'),
					'denied'        => __('ブラウザ設定で通知が拒否されています。', 'my-push-notification-plugin'),
					'error'         => __('通知設定を更新できませんでした。', 'my-push-notification-plugin'),
					'missingKey'    => __('通知の公開鍵が未設定です。', 'my-push-notification-plugin'),
				),
			)
		);
	}

	// ---- 購読ボタン HTML 出力 --------------------------------------------------

	/**
	 * フッターに購読ボタンのマークアップを出力する。
	 *
	 * hidden 属性を付与しておき、JavaScript が Push API 対応状況を確認してから
	 * 表示するかどうかを制御する（段階的強化）。
	 */
	public function render_subscription_button()
	{
		// 管理画面または購読 UI が不要なページでは何もしない。
		if (is_admin() || ! $this->should_render_subscribe_ui()) {
			return;
		}

		// data-my-push-subscribe: JavaScript がルート要素として参照する。
		// hidden: JS が Push 対応を確認するまで非表示にしておく。
		echo '<div class="my-push-subscribe" data-my-push-subscribe hidden>';
		// data-my-push-button: JavaScript がクリックイベントを付与するターゲット。
		echo '<button type="button" class="my-push-subscribe__button" data-my-push-button>';
		echo esc_html__('更新通知を受け取る', 'my-push-notification-plugin');
		echo '</button>';
		// data-my-push-message: JavaScript が状態メッセージを書き込む領域。
		echo '<p class="my-push-subscribe__message" data-my-push-message></p>';
		echo '</div>';
	}

	// ---- 内部ヘルパー ----------------------------------------------------------

	/**
	 * 購読 UI を表示すべきページかどうかを返す。
	 *
	 * 表示対象: トップページ・投稿一覧（アーカイブ）・個別投稿ページ。
	 * 固定ページや商品ページなどには表示しない。
	 *
	 * @return bool
	 */
	private function should_render_subscribe_ui()
	{
		return is_home() || is_front_page() || is_archive() || is_singular('post');
	}

	/**
	 * プラグインの REST エンドポイントの完全 URL を生成して返す。
	 *
	 * パーマリンクが有効な場合は /wp-json/my-push/v1{$route} 形式になるが、
	 * パーマリンクが無効な場合は add_query_arg() でフォールバックする。
	 *
	 * @param string $route スラッシュで始まるルートパス（例: '/subscribe'）。
	 * @return string エスケープ済みの URL 文字列。
	 */
	private function rest_route_url($route)
	{
		return esc_url_raw(
			add_query_arg(
				'rest_route',
				'/' . My_Push_Plugin::REST_NAMESPACE . $route,
				home_url('/')
			)
		);
	}

	// ---- 投稿公開時の自動通知 --------------------------------------------------

	/**
	 * 投稿ステータスが「公開」に変わったときに Push 通知を送信する。
	 *
	 * transition_post_status フックで呼ばれる。以下の条件をすべて満たす場合のみ送信する:
	 *   - 新しいステータスが 'publish'
	 *   - 以前のステータスが 'publish' でない（再保存ではなく新規公開）
	 *   - 投稿タイプが 'post'
	 *   - 管理画面の自動通知オプションが有効
	 *
	 * @param string  $new_status 変更後のステータス。
	 * @param string  $old_status 変更前のステータス。
	 * @param WP_Post $post       対象の投稿オブジェクト。
	 */
	public function maybe_send_post_notification($new_status, $old_status, $post)
	{
		// 公開に変わった場合のみ処理する（既に公開済みの再保存は除外）。
		if ('publish' !== $new_status || 'publish' === $old_status) {
			return;
		}

		// WP_Post オブジェクトかつ通常の投稿タイプのみ対象にする。
		if (! $post instanceof WP_Post || 'post' !== $post->post_type) {
			return;
		}

		// 管理画面で「投稿公開時に自動送信」が無効なら何もしない。
		if ('1' !== get_option(self::OPTION_AUTO_POSTS, '0')) {
			return;
		}

		// 全購読者に投稿通知を送信する。
		$this->web_push->send_post_notification((int) $post->ID);
	}
}
