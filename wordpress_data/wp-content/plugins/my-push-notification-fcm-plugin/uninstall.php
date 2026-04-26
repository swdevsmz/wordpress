<?php
/**
 * FCM プラグインのアンインストール時クリーンアップ。
 *
 * @package My_Push_Notification_FCM_Plugin
 */

// WordPress のアンインストール処理から呼ばれていない場合は何もしません。
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 保存済み FCM 登録トークンのテーブルを削除します。
$fcm_tokens = $wpdb->prefix . 'my_push_fcm_tokens';
$wpdb->query( "DROP TABLE IF EXISTS {$fcm_tokens}" );

// 管理画面で保存した設定値と OAuth キャッシュを削除します。
delete_option( 'my_push_fcm_enabled' );
delete_option( 'my_push_fcm_project_id' );
delete_option( 'my_push_fcm_service_account' );
delete_option( 'my_push_fcm_web_vapid_public' );
delete_option( 'my_push_fcm_default_title' );
delete_option( 'my_push_fcm_auto_notify_posts' );
delete_option( 'my_push_fcm_oauth_cache' );
