<?php
/**
 * FCM 登録トークンの永続化を担当するリポジトリ。
 *
 * @package My_Push_Notification_FCM_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_FCM_Token_Repository {
	/** REST API から受け付ける端末種別。想定外の値は unknown に丸めます。 */
	const ALLOWED_PLATFORMS = array( 'android', 'ios', 'web', 'unknown' );

	/**
	 * WordPress のテーブル接頭辞を使って、FCM トークン保存テーブル名を返します。
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'my_push_fcm_tokens';
	}

	/**
	 * プラグイン有効化時に FCM トークン保存テーブルを作成・更新します。
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			token longtext NOT NULL,
			platform varchar(20) NOT NULL DEFAULT 'unknown',
			app_id varchar(190) NULL,
			user_id bigint(20) unsigned NULL,
			device_label text NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY status (status),
			KEY platform (platform)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * FCM トークンを新規登録または更新します。
	 *
	 * トークン本文は送信時に必要なため保存しつつ、検索・重複判定には SHA-256 ハッシュを使います。
	 */
	public function upsert( $token, $platform, $app_id, $device_label, $user_id ) {
		global $wpdb;

		$table_name = self::table_name();
		$token_hash = hash( 'sha256', $token );
		$now        = current_time( 'mysql' );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE token_hash = %s LIMIT 1",
				$token_hash
			)
		);

		$data = array(
			'token'        => $token,
			'platform'     => self::normalize_platform( $platform ),
			'app_id'       => '' !== $app_id ? $app_id : null,
			'user_id'      => $user_id > 0 ? $user_id : null,
			'device_label' => '' !== $device_label ? $device_label : null,
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

		$data['token_hash'] = $token_hash;
		$data['created_at'] = $now;

		return false !== $wpdb->insert(
			$table_name,
			$data,
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * トークン本文からハッシュを作り、該当トークンを無効化します。
	 */
	public function mark_inactive_by_token( $token ) {
		return $this->mark_inactive_by_hash( hash( 'sha256', $token ) );
	}

	/**
	 * FCM から無効判定されたトークンを論理削除します。
	 */
	public function mark_inactive_by_hash( $token_hash ) {
		global $wpdb;

		return false !== $wpdb->update(
			self::table_name(),
			array(
				'status'     => 'inactive',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'token_hash' => $token_hash ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * 通知送信対象になる有効なトークン一覧を取得します。
	 */
	public function get_active_tokens() {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, token, platform FROM ' . self::table_name() . ' WHERE status = %s',
				'active'
			),
			ARRAY_A
		);
	}

	/**
	 * 管理画面の状態表示用に、有効トークン数を返します。
	 */
	public function count_active_tokens() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE status = %s',
				'active'
			)
		);
	}

	/**
	 * 受信した端末種別を保存可能な値に正規化します。
	 */
	private static function normalize_platform( $platform ) {
		$platform = is_string( $platform ) ? strtolower( trim( $platform ) ) : '';

		return in_array( $platform, self::ALLOWED_PLATFORMS, true ) ? $platform : 'unknown';
	}
}
