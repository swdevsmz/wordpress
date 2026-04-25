<?php
/**
 * Subscriber persistence.
 *
 * @package My_Push_Notification_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_Subscriber_Repository {
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'my_push_subscribers';
	}

	public static function create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			endpoint_hash char(64) NOT NULL,
			endpoint longtext NOT NULL,
			public_key longtext NOT NULL,
			auth_token longtext NOT NULL,
			user_id bigint(20) unsigned NULL,
			user_agent text NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY endpoint_hash (endpoint_hash),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function upsert( $endpoint, $public_key, $auth_token, $user_agent, $user_id ) {
		global $wpdb;

		$table_name    = self::table_name();
		$endpoint_hash = hash( 'sha256', $endpoint );
		$now           = current_time( 'mysql' );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE endpoint_hash = %s LIMIT 1",
				$endpoint_hash
			)
		);

		$data = array(
			'endpoint'     => $endpoint,
			'public_key'   => $public_key,
			'auth_token'   => $auth_token,
			'user_id'      => $user_id > 0 ? $user_id : null,
			'user_agent'   => $user_agent,
			'status'       => 'active',
			'updated_at'   => $now,
		);

		if ( $existing_id ) {
			return false !== $wpdb->update(
				$table_name,
				$data,
				array( 'id' => (int) $existing_id ),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		$data['endpoint_hash'] = $endpoint_hash;
		$data['created_at']    = $now;

		return false !== $wpdb->insert(
			$table_name,
			$data,
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function mark_inactive( $endpoint ) {
		global $wpdb;

		return false !== $wpdb->update(
			self::table_name(),
			array(
				'status'     => 'inactive',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'endpoint_hash' => hash( 'sha256', $endpoint ) ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	public function get_active_subscribers() {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT endpoint, public_key, auth_token FROM ' . self::table_name() . ' WHERE status = %s',
				'active'
			),
			ARRAY_A
		);
	}

	public function count_active_subscribers() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE status = %s',
				'active'
			)
		);
	}
}
