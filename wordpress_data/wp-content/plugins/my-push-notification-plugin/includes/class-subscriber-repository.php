<?php

/**
 * Web Push 購読者の永続化を担うリポジトリクラス。
 *
 * カスタムテーブル {prefix}my_push_subscribers を操作し、
 * 購読情報の upsert・無効化・取得・件数カウントを提供する。
 *
 * @package My_Push_Notification_Plugin
 */

// WordPress の外部から直接アクセスされた場合は終了する。
if (! defined('ABSPATH')) {
	exit;
}

/**
 * 購読者リポジトリクラス。
 *
 * テーブルスキーマ ({prefix}my_push_subscribers):
 *   id            — 主キー（AUTO_INCREMENT）。
 *   endpoint_hash — Push エンドポイント URL の SHA-256 ハッシュ（UNIQUE）。
 *                   高速検索と重複排除のために使用する。
 *   endpoint      — Push エンドポイントの完全 URL（暗号化された通知の送信先）。
 *   public_key    — クライアント P-256 公開鍵（Base64URL）。暗号化に使用。
 *   auth_token    — 認証シークレット（Base64URL）。暗号化に使用。
 *   user_id       — 紐付く WordPress ユーザー ID（ゲストは NULL）。
 *   user_agent    — 登録時のブラウザ UA 文字列（統計目的）。
 *   status        — 'active' または 'inactive'。
 *   created_at    — レコード作成日時（UTC）。
 *   updated_at    — レコード更新日時（UTC）。
 */
class My_Push_Subscriber_Repository
{

	// ---- テーブル名ヘルパー ----------------------------------------------------

	/**
	 * テーブル名（プレフィックス付き）を返す。
	 *
	 * @return string テーブル名（例: wp_my_push_subscribers）。
	 */
	public static function table_name()
	{
		global $wpdb;

		return $wpdb->prefix . 'my_push_subscribers';
	}

	// ---- テーブル作成（有効化フック）------------------------------------------

	/**
	 * カスタムテーブルを作成する。
	 *
	 * プラグインの有効化フックから呼び出される。
	 * dbDelta() を使用するため、既存テーブルのカラム追加・変更も安全に行える。
	 * テーブルが既に存在する場合は何もしない（冪等）。
	 */
	public static function create_table()
	{
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate(); // 例: DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci

		// dbDelta() が要求する形式（PRIMARY KEY の前にスペース 2 つ）に従う。
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

		// dbDelta() は wp-admin/includes/upgrade.php で定義されている。
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	// ---- CRUD 操作 -------------------------------------------------------------

	/**
	 * 購読情報を登録または更新する（Upsert）。
	 *
	 * endpoint_hash で既存レコードを検索し、
	 *   - 存在する場合: 最新の情報に UPDATE する。
	 *   - 存在しない場合: 新規 INSERT する。
	 *
	 * @param string   $endpoint   Push エンドポイント URL。
	 * @param string   $public_key クライアント P-256 公開鍵（Base64URL）。
	 * @param string   $auth_token 認証シークレット（Base64URL）。
	 * @param string   $user_agent 登録時のブラウザ UA 文字列。
	 * @param int      $user_id    WordPress ユーザー ID（ゲストは 0）。
	 * @return bool 成功なら true、失敗なら false。
	 */
	public function upsert($endpoint, $public_key, $auth_token, $user_agent, $user_id)
	{
		global $wpdb;

		$table_name = self::table_name();

		// SHA-256 ハッシュを UNIQUE キーとして使用する（longtext は UNIQUE に設定できないため）。
		$endpoint_hash = hash('sha256', $endpoint);
		$now           = current_time('mysql'); // WordPress のタイムゾーン設定に従う。

		// 既存レコードの ID を検索する。
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE endpoint_hash = %s LIMIT 1",
				$endpoint_hash
			)
		);

		// 共通データ配列（INSERT/UPDATE どちらでも使う）。
		$data = array(
			'endpoint'     => $endpoint,
			'public_key'   => $public_key,
			'auth_token'   => $auth_token,
			'user_id'      => $user_id > 0 ? $user_id : null, // ゲストは NULL を格納する。
			'user_agent'   => $user_agent,
			'status'       => 'active',   // upsert 時は常に active に戻す。
			'updated_at'   => $now,
		);

		if ($existing_id) {
			// 既存レコードを更新する。
			return false !== $wpdb->update(
				$table_name,
				$data,
				array('id' => (int) $existing_id),
				array('%s', '%s', '%s', '%d', '%s', '%s', '%s'), // データのフォーマット。
				array('%d')                                       // WHERE 句のフォーマット。
			);
		}

		// 新規レコードを挿入する（INSERT 時のみ endpoint_hash と created_at を追加）。
		$data['endpoint_hash'] = $endpoint_hash;
		$data['created_at']    = $now;

		return false !== $wpdb->insert(
			$table_name,
			$data,
			array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
		);
	}

	/**
	 * 指定したエンドポイントの購読を無効化する。
	 *
	 * レコードは削除せず status を 'inactive' に変更する（履歴保持のため）。
	 * Push サービスから「購読切れ」が通知された場合にも呼び出される。
	 *
	 * @param string $endpoint Push エンドポイント URL。
	 * @return bool 成功なら true、失敗なら false。
	 */
	public function mark_inactive($endpoint)
	{
		global $wpdb;

		return false !== $wpdb->update(
			self::table_name(),
			array(
				'status'     => 'inactive',
				'updated_at' => current_time('mysql'),
			),
			// endpoint_hash で検索する（longtext カラムの完全一致検索を避けるため）。
			array('endpoint_hash' => hash('sha256', $endpoint)),
			array('%s', '%s'), // データのフォーマット。
			array('%s')        // WHERE 句のフォーマット。
		);
	}

	/**
	 * 有効な購読者をすべて取得する。
	 *
	 * 返却する列は Push 送信に必要な最小限（endpoint・public_key・auth_token）に限定する。
	 *
	 * @return array{endpoint: string, public_key: string, auth_token: string}[] 購読者の配列。
	 */
	public function get_active_subscribers()
	{
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT endpoint, public_key, auth_token FROM ' . self::table_name() . ' WHERE status = %s',
				'active'
			),
			ARRAY_A // 連想配列として返す。
		);
	}

	/**
	 * 有効な購読者の件数を返す。
	 *
	 * 管理画面の概要テーブルに表示するために使用する。
	 *
	 * @return int 有効な購読者数。
	 */
	public function count_active_subscribers()
	{
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE status = %s',
				'active'
			)
		);
	}
}
