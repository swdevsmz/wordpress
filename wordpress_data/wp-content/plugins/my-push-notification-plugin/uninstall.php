<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package My_Push_Notification_Plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$subscribers = $wpdb->prefix . 'my_push_subscribers';
$wpdb->query( "DROP TABLE IF EXISTS {$subscribers}" );

delete_option( 'my_push_vapid_public_key' );
delete_option( 'my_push_vapid_private_key' );
delete_option( 'my_push_vapid_subject' );
delete_option( 'my_push_default_title' );
delete_option( 'my_push_auto_notify_posts' );
