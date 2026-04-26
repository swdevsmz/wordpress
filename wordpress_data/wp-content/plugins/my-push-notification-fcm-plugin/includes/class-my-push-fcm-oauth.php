<?php
/**
 * FCM HTTP v1 API 用の OAuth2 アクセストークン取得サービス。
 *
 * @package My_Push_Notification_FCM_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_FCM_OAuth {
	/** FCM 送信に必要な OAuth スコープ。 */
	const SCOPE         = 'https://www.googleapis.com/auth/firebase.messaging';
	const TOKEN_URL     = 'https://oauth2.googleapis.com/token';
	const CACHE_OPTION  = 'my_push_fcm_oauth_cache';
	const CACHE_TTL_PAD = 120;

	/**
	 * 有効なアクセストークンを取得します。キャッシュがあればそれを優先します。
	 */
	public function get_access_token() {
		$cached = $this->get_cached_token();
		if ( $cached ) {
			return $cached;
		}

		$service_account = $this->load_service_account();
		if ( is_wp_error( $service_account ) ) {
			return $service_account;
		}

		$jwt = $this->build_signed_jwt( $service_account );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] )
				? (string) $body['error_description']
				: ( isset( $body['error'] ) ? (string) $body['error'] : 'unknown error' );

			return new WP_Error(
				'my_push_fcm_oauth_failed',
				sprintf( 'FCM OAuth2 token request failed (%d): %s', (int) $code, $message )
			);
		}

		$access_token = (string) $body['access_token'];
		$expires_in   = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;
		$expires_at   = time() + max( 60, $expires_in - self::CACHE_TTL_PAD );

		update_option(
			self::CACHE_OPTION,
			array(
				'access_token' => $access_token,
				'expires_at'   => $expires_at,
			),
			false
		);

		return $access_token;
	}

	/**
	 * 保存済みアクセストークンを破棄します。
	 */
	public function clear_cache() {
		delete_option( self::CACHE_OPTION );
	}

	/**
	 * 有効期限内のアクセストークンキャッシュを取得します。
	 */
	private function get_cached_token() {
		$cached = get_option( self::CACHE_OPTION, array() );

		if ( is_array( $cached )
			&& ! empty( $cached['access_token'] )
			&& ! empty( $cached['expires_at'] )
			&& (int) $cached['expires_at'] > time()
		) {
			return (string) $cached['access_token'];
		}

		return null;
	}

	/**
	 * 管理画面で保存された Firebase サービスアカウント JSON を読み込みます。
	 */
	private function load_service_account() {
		$raw = (string) get_option( My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT, '' );

		if ( '' === trim( $raw ) ) {
			return new WP_Error(
				'my_push_fcm_missing_service_account',
				__( 'Firebase service account JSON is not configured.', 'my-push-notification-fcm-plugin' )
			);
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded )
			|| empty( $decoded['client_email'] )
			|| empty( $decoded['private_key'] )
			|| empty( $decoded['token_uri'] )
		) {
			return new WP_Error(
				'my_push_fcm_invalid_service_account',
				__( 'Service account JSON is missing client_email / private_key / token_uri.', 'my-push-notification-fcm-plugin' )
			);
		}

		return $decoded;
	}

	/**
	 * サービスアカウントの秘密鍵で署名した JWT を作成します。
	 */
	private function build_signed_jwt( array $service_account ) {
		$now = time();

		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$claims = array(
			'iss'   => $service_account['client_email'],
			'scope' => self::SCOPE,
			'aud'   => $service_account['token_uri'],
			'iat'   => $now,
			'exp'   => $now + 3600,
		);

		$segments = array(
			$this->base64url_encode( wp_json_encode( $header ) ),
			$this->base64url_encode( wp_json_encode( $claims ) ),
		);

		$signing_input = implode( '.', $segments );
		$private_key   = (string) $service_account['private_key'];

		$key_resource = openssl_pkey_get_private( $private_key );
		if ( ! $key_resource ) {
			return new WP_Error(
				'my_push_fcm_invalid_private_key',
				__( 'Service account private key could not be parsed.', 'my-push-notification-fcm-plugin' )
			);
		}

		$signature = '';
		$success   = openssl_sign( $signing_input, $signature, $key_resource, 'sha256WithRSAEncryption' );

		if ( PHP_VERSION_ID < 80000 ) {
			openssl_free_key( $key_resource );
		}

		if ( ! $success ) {
			return new WP_Error(
				'my_push_fcm_signature_failed',
				__( 'Could not sign the JWT for FCM OAuth2.', 'my-push-notification-fcm-plugin' )
			);
		}

		$segments[] = $this->base64url_encode( $signature );

		return implode( '.', $segments );
	}

	/**
	 * JWT で利用する Base64 URL セーフ形式へ変換します。
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
