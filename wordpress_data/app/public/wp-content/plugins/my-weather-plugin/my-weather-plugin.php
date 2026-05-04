<?php
/**
 * My Weather Plugin（天気情報プラグイン）
 *
 * @package MyWeatherPlugin
 * @version 1.0.0
 */

/**
 * Plugin Name: My Weather Plugin
 * Plugin URI: https://example.com/my-weather-plugin
 * Description: Open-Meteo APIから天気情報を取得してショートコードで表示します
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-weather-plugin
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MY_WEATHER_PLUGIN_VERSION', '1.0.0' );
define( 'MY_WEATHER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_WEATHER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// プラグインクラスを読み込む
require_once MY_WEATHER_PLUGIN_PATH . 'includes/class-weather-api.php';
require_once MY_WEATHER_PLUGIN_PATH . 'includes/class-weather-admin.php';
require_once MY_WEATHER_PLUGIN_PATH . 'includes/class-weather-shortcode.php';

/**
 * プラグインを初期化
 */
function my_weather_plugin_init() {
	// 管理画面の設定を初期化
	My_Weather_Admin::init();

	// ショートコードを登録
	My_Weather_Shortcode::init();

	// 翻訳テキストドメインを読み込む
	load_plugin_textdomain( 'my-weather-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'my_weather_plugin_init' );

/**
 * 有効化フック
 */
register_activation_hook( __FILE__, function() {
	// デフォルトオプションを設定
	if ( ! get_option( 'my_weather_latitude' ) ) {
		update_option( 'my_weather_latitude', '35.6762' );  // 東京
	}
	if ( ! get_option( 'my_weather_longitude' ) ) {
		update_option( 'my_weather_longitude', '139.6503' ); // 東京
	}
	if ( ! get_option( 'my_weather_city_name' ) ) {
		update_option( 'my_weather_city_name', 'Tokyo' );
	}
	flush_rewrite_rules();
} );

/**
 * 無効化フック
 */
register_deactivation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );

/**
 * アンインストールフック
 */
register_uninstall_hook( __FILE__, 'my_weather_plugin_uninstall' );

/**
 * アンインストール処理
 */
function my_weather_plugin_uninstall() {
	delete_option( 'my_weather_latitude' );
	delete_option( 'my_weather_longitude' );
	delete_option( 'my_weather_city_name' );
	delete_transient( 'my_weather_cache' );
}
