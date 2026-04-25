<?php
/**
 * Dev helper: generate VAPID keys and seed options via WP-CLI eval-file.
 *
 * Usage (inside the wordpress container):
 *   php wp-cli.phar eval-file \
 *     /var/www/html/wp-content/plugins/my-push-notification-plugin/bin/generate-vapid-cli.php \
 *     --path=/var/www/html --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$autoload = ABSPATH . 'wp-content/plugins/my-push-notification-plugin/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "vendor/autoload.php not found\n" );
	exit( 1 );
}

require_once $autoload;

$keys = \Minishlink\WebPush\VAPID::createVapidKeys();

update_option( 'my_push_vapid_public_key', $keys['publicKey'] );
update_option( 'my_push_vapid_private_key', $keys['privateKey'] );
update_option( 'my_push_vapid_subject', home_url( '/' ) );

if ( '' === trim( (string) get_option( 'my_push_default_title', '' ) ) ) {
	update_option( 'my_push_default_title', get_bloginfo( 'name' ) );
}

update_option( 'my_push_auto_notify_posts', '1' );

printf( "public_key_len=%d\n", strlen( $keys['publicKey'] ) );
printf( "private_key_len=%d\n", strlen( $keys['privateKey'] ) );
printf( "subject=%s\n", get_option( 'my_push_vapid_subject' ) );
