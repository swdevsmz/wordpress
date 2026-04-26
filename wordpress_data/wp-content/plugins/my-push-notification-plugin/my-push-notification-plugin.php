<?php

/**
 * Plugin Name:       My Push Notification Plugin
 * Description:       Adds browser Web Push subscription and notification delivery for WordPress posts.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * Text Domain:       my-push-notification-plugin
 */

/*
 * このファイルはプラグインのエントリーポイントです。
 * WordPress がプラグインを読み込む際に最初に実行されます。
 * 役割:
 *   1. 定数の定義
 *   2. Composer オートローダーの読み込み
 *   3. 各クラスファイルのインクルード
 *   4. 有効化フック・初期化フックの登録
 */

// WordPress の外部から直接このファイルへアクセスされた場合は即座に終了する。
// ABSPATH は wp-load.php が定義する定数で、これが未定義なら WordPress 経由の呼び出しではない。
if (! defined('ABSPATH')) {
	exit;
}

// ---- プラグイン共通定数 --------------------------------------------------------

/** プラグインメインファイルの絶対パス（有効化フックなどで使用）。 */
define('MY_PUSH_PLUGIN_FILE', __FILE__);

/** プラグインディレクトリの絶対パス（末尾スラッシュあり）。 */
define('MY_PUSH_PLUGIN_DIR', plugin_dir_path(__FILE__));

/** プラグインディレクトリの URL（末尾スラッシュあり）。CSS/JS の読み込みに使用。 */
define('MY_PUSH_PLUGIN_URL', plugin_dir_url(__FILE__));

/** プラグインのバージョン番号。スクリプトキャッシュバスティングに使用。 */
define('MY_PUSH_PLUGIN_VERSION', '0.1.0');

// ---- Composer オートローダー --------------------------------------------------

// minishlink/web-push などの Composer パッケージを使用するためにオートローダーを読み込む。
// vendor ディレクトリが存在しない場合（未インストール）はスキップし、後続処理で
// dependency_status() によってエラー表示に切り替わる。
$my_push_vendor_autoload = MY_PUSH_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($my_push_vendor_autoload)) {
	require_once $my_push_vendor_autoload;
}

// ---- クラスファイルのインクルード ---------------------------------------------

// 購読者データの DB 操作を担うリポジトリクラス。
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-subscriber-repository.php';

// Web Push 通知の送信ロジックを担うサービスクラス。
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-web-push-service.php';

// REST API エンドポイントを登録・処理するコントローラークラス。
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-rest.php';

// 管理画面（設定ページ）を担うクラス。
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-admin.php';

// プラグイン全体を束ねるメインクラス（シングルトン）。
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-plugin.php';

// ---- 有効化フック -------------------------------------------------------------

// プラグインが有効化されたときに購読者テーブルを作成する。
register_activation_hook(__FILE__, array('My_Push_Subscriber_Repository', 'create_table'));

// ---- 初期化 -------------------------------------------------------------------

// 全プラグインの読み込みが完了した後（plugins_loaded）にフックを登録する。
// これにより、他のプラグインが定義したフックとの競合を防ぐ。
add_action(
	'plugins_loaded',
	static function () {
		My_Push_Plugin::instance()->register_hooks();
	}
);
