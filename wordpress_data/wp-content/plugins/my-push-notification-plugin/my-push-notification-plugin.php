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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MY_PUSH_PLUGIN_FILE', __FILE__ );
define( 'MY_PUSH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_PUSH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MY_PUSH_PLUGIN_VERSION', '0.1.0' );

$my_push_vendor_autoload = MY_PUSH_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $my_push_vendor_autoload ) ) {
	require_once $my_push_vendor_autoload;
}

require_once MY_PUSH_PLUGIN_DIR . 'includes/class-subscriber-repository.php';
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-web-push-service.php';
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-rest.php';
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-admin.php';
require_once MY_PUSH_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'My_Push_Subscriber_Repository', 'create_table' ) );

add_action(
	'plugins_loaded',
	static function () {
		My_Push_Plugin::instance()->register_hooks();
	}
);
