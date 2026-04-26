<?php
/**
 * FCM HTTP v1 API へ通知を送信するサービス。
 *
 * @package My_Push_Notification_FCM_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_FCM_Sender {
	/** Firebase Cloud Messaging HTTP v1 の送信エンドポイント。 */
	const FCM_BASE = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

	/**
	 * @var My_Push_FCM_Token_Repository
	 */
	private $tokens;

	/**
	 * @var My_Push_FCM_OAuth
	 */
	private $oauth;

	public function __construct( My_Push_FCM_Token_Repository $tokens, My_Push_FCM_OAuth $oauth ) {
		$this->tokens = $tokens;
		$this->oauth  = $oauth;
	}

	/**
	 * FCM 送信に必要な設定がそろっているか確認します。
	 */
	public function is_configured() {
		if ( '1' !== (string) get_option( My_Push_FCM_Plugin::OPTION_ENABLED, '0' ) ) {
			return false;
		}

		$project_id      = trim( (string) get_option( My_Push_FCM_Plugin::OPTION_PROJECT_ID, '' ) );
		$service_account = trim( (string) get_option( My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT, '' ) );

		return '' !== $project_id && '' !== $service_account;
	}

	/**
	 * 管理画面から送るテスト通知用のペイロードを組み立てます。
	 */
	public function send_test_notification() {
		$title = trim( (string) get_option( My_Push_FCM_Plugin::OPTION_TITLE, '' ) );
		if ( '' === $title ) {
			$title = get_bloginfo( 'name' );
		}

		return $this->send(
			array(
				'title' => $title,
				'body'  => __( 'This is a test FCM notification from WordPress.', 'my-push-notification-fcm-plugin' ),
				'url'   => home_url( '/' ),
				'icon'  => get_site_icon_url() ? get_site_icon_url() : '',
			)
		);
	}

	/**
	 * 有効な登録トークンすべてに FCM 通知を送信します。
	 *
	 * @param array $payload 通知タイトル、本文、遷移先 URL などの送信データ。
	 */
	public function send( array $payload ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'my_push_fcm_not_configured',
				__( 'FCM is not configured.', 'my-push-notification-fcm-plugin' )
			);
		}

		$project_id   = trim( (string) get_option( My_Push_FCM_Plugin::OPTION_PROJECT_ID, '' ) );
		$access_token = $this->oauth->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$tokens_rows = $this->tokens->get_active_tokens();

		if ( empty( $tokens_rows ) ) {
			return array(
				'sent'   => 0,
				'failed' => 0,
			);
		}

		$endpoint = sprintf( self::FCM_BASE, rawurlencode( $project_id ) );
		$sent     = 0;
		$failed   = 0;

		foreach ( $tokens_rows as $row ) {
			$body = wp_json_encode(
				array(
					'message' => $this->build_message( $row, $payload ),
				)
			);

			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json; charset=UTF-8',
					),
					'body'    => $body,
				)
			);

			if ( is_wp_error( $response ) ) {
				$failed++;
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );

			if ( 200 === $code ) {
				$sent++;
				continue;
			}

			$failed++;

			// FCM が恒久的な失敗を返した場合は、再送しないよう対象トークンを無効化します。
			if ( $this->is_unrecoverable_status( $code ) ) {
				$json = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( $this->indicates_invalid_token( $json ) ) {
					$this->tokens->mark_inactive_by_token( $row['token'] );
				}
			}

			// 認証エラー時はキャッシュ済みアクセストークンが古い可能性があるため破棄します。
			if ( 401 === $code ) {
				$this->oauth->clear_cache();
			}
		}

		return array(
			'sent'   => $sent,
			'failed' => $failed,
		);
	}

	/**
	 * FCM HTTP v1 API に渡すメッセージ本文を作成します。
	 */
	private function build_message( array $row, array $payload ) {
		$title = isset( $payload['title'] ) ? (string) $payload['title'] : '';
		$body  = isset( $payload['body'] ) ? (string) $payload['body'] : '';
		$url   = isset( $payload['url'] ) ? esc_url_raw( (string) $payload['url'] ) : home_url( '/' );

		return array(
			'token'        => (string) $row['token'],
			'notification' => array(
				'title' => $title,
				'body'  => $body,
			),
			'data'         => array(
				'url' => $url,
			),
			'webpush'      => array(
				'fcm_options' => array(
					'link' => $url,
				),
			),
			'android'      => array(
				'priority' => 'HIGH',
			),
			'apns'         => array(
				'headers' => array(
					'apns-priority' => '10',
				),
				'payload' => array(
					'aps' => array(
						'sound' => 'default',
					),
				),
			),
		);
	}

	/**
	 * トークン側の問題として扱える HTTP ステータスか判定します。
	 */
	private function is_unrecoverable_status( $code ) {
		return in_array( (int) $code, array( 400, 403, 404, 410 ), true );
	}

	/**
	 * FCM レスポンス本文から、登録トークンが無効かどうかを判定します。
	 */
	private function indicates_invalid_token( $json ) {
		if ( ! is_array( $json ) ) {
			return false;
		}

		$status = isset( $json['error']['status'] ) ? (string) $json['error']['status'] : '';

		if ( in_array( $status, array( 'NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT' ), true ) ) {
			return true;
		}

		if ( ! empty( $json['error']['details'] ) && is_array( $json['error']['details'] ) ) {
			foreach ( $json['error']['details'] as $detail ) {
				if ( isset( $detail['errorCode'] ) && in_array( (string) $detail['errorCode'], array( 'UNREGISTERED', 'INVALID_ARGUMENT' ), true ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
