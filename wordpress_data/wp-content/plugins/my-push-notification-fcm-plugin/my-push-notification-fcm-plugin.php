<?php
/**
 * Plugin Name:       My Push Notification FCM Plugin
 * Description:       Adds Firebase Cloud Messaging push delivery for Flutter apps and FCM-enabled browsers. Independent from the Web Push plugin.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * Text Domain:       my-push-notification-fcm-plugin
 */

/*
 * このファイルは FCM プラグインのエントリーポイントです。
 * WordPress がプラグインを読み込む際に最初に実行されます。
 *
 * 主な役割:
 *   1. プラグイン全体で使う定数を定義する。
 *   2. 必要なクラスファイルを読み込む。
 *   3. 有効化時にトークン保存用テーブルを作成する。
 *   4. plugins_loaded で各 WordPress フックの登録を開始する。
 *
 * 通常版のプッシュ通知プラグインとは独立して動作します。
 * 通常版が無効でも、このプラグインだけで FCM 経由の通知配信を扱えます。
 */

// WordPress を経由せずに直接アクセスされた場合は処理を中止します。
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- プラグイン共通定数 --------------------------------------------------------

/** プラグインのメインファイルパス。フック登録や plugin_basename() で使います。 */
define( 'MY_PUSH_FCM_PLUGIN_FILE', __FILE__ );

/** プラグインディレクトリの絶対パス。クラスファイルの読み込みに使います。 */
define( 'MY_PUSH_FCM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/** プラグインディレクトリの URL。フロントエンド用 JS の読み込みに使います。 */
define( 'MY_PUSH_FCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** プラグインのバージョン番号。JS のキャッシュバスティングに使います。 */
define( 'MY_PUSH_FCM_PLUGIN_VERSION', '0.1.0' );

// ---- クラスファイルの読み込み ------------------------------------------------

// FCM 登録トークンを保存・取得するリポジトリクラス。
require_once MY_PUSH_FCM_PLUGIN_DIR . 'includes/class-my-push-fcm-token-repository.php';

// FCM HTTP v1 API 用の OAuth2 アクセストークンを取得・キャッシュするクラス。
require_once MY_PUSH_FCM_PLUGIN_DIR . 'includes/class-my-push-fcm-oauth.php';

// FCM HTTP v1 API へ通知を送信するサービスクラス。
require_once MY_PUSH_FCM_PLUGIN_DIR . 'includes/class-my-push-fcm-sender.php';

// REST API エンドポイントを登録・処理するコントローラークラス。
require_once MY_PUSH_FCM_PLUGIN_DIR . 'includes/class-my-push-fcm-rest.php';

// 管理画面の設定ページやテスト送信を扱うクラス。
require_once MY_PUSH_FCM_PLUGIN_DIR . 'includes/class-my-push-fcm-admin.php';

// プラグイン全体を束ねるメインクラス。
require_once MY_PUSH_FCM_PLUGIN_DIR . 'includes/class-my-push-fcm-plugin.php';

// ---- 有効化フック ------------------------------------------------------------

// プラグイン有効化時に FCM トークン用テーブルを作成します。
// 既存テーブルがある場合は dbDelta() が差分に応じて調整します。
register_activation_hook(
	__FILE__,
	static function () {
		My_Push_FCM_Token_Repository::create_table();
	}
);

// ---- 初期化 ------------------------------------------------------------------

// 全プラグインの読み込み完了後に、このプラグインのフック登録を開始します。
add_action(
	'plugins_loaded',
	static function () {
		My_Push_FCM_Plugin::instance()->register_hooks();
	}
);
