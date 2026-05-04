<?php
/**
 * 天気API統合クラス
 *
 * @package MyWeatherPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Open-Meteo APIの呼び出しとキャッシング機能を提供
 */
class My_Weather_API {

	const API_URL     = 'https://api.open-meteo.com/v1/forecast';
	const CACHE_KEY   = 'my_weather_cache';
	const CACHE_TIME  = 3600; // 1時間

	/**
	 * 設定された位置の天気データを取得
	 *
	 * @return array|WP_Error 天気データまたはエラー
	 */
	public static function get_weather() {
		// まずキャッシュを確認
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		// オプションから位置情報を取得
		$latitude  = get_option( 'my_weather_latitude' );
		$longitude = get_option( 'my_weather_longitude' );

		if ( ! $latitude || ! $longitude ) {
			return new WP_Error( 'invalid_location', __( 'Weather location not configured', 'my-weather-plugin' ) );
		}

		// パラメータを含むAPI URLを構築
		$url = add_query_arg(
			array(
				'latitude'             => floatval( $latitude ),
				'longitude'            => floatval( $longitude ),
				'current'              => 'temperature_2m,weather_code,wind_speed_10m',
				'temperature_unit'     => 'celsius',
				'wind_speed_unit'      => 'kmh',
				'forecast_days'        => 1,
			),
			self::API_URL
		);

		// APIリクエストを実行
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'My-Weather-Plugin/1.0',
			)
		);

		// リクエストエラーを処理
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', __( 'Failed to fetch weather data', 'my-weather-plugin' ) );
		}

		// レスポンスコードを確認
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'api_error', sprintf( __( 'Weather API error: %d', 'my-weather-plugin' ), $code ) );
		}

		// レスポンスをパース
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['current'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid weather data received', 'my-weather-plugin' ) );
		}

		// 結果をキャッシュ
		set_transient( self::CACHE_KEY, $data, self::CACHE_TIME );

		return $data;
	}

	/**
	 * WMO天気コードを人間が読める形式に変換
	 *
	 * @param int $code WMO天気コード
	 * @return string 天気の説明
	 */
	public static function get_weather_description( $code ) {
		$descriptions = array(
			0  => __( 'Clear sky', 'my-weather-plugin' ),
			1  => __( 'Mainly clear', 'my-weather-plugin' ),
			2  => __( 'Partly cloudy', 'my-weather-plugin' ),
			3  => __( 'Overcast', 'my-weather-plugin' ),
			45 => __( 'Foggy', 'my-weather-plugin' ),
			48 => __( 'Depositing rime fog', 'my-weather-plugin' ),
			51 => __( 'Light drizzle', 'my-weather-plugin' ),
			53 => __( 'Moderate drizzle', 'my-weather-plugin' ),
			55 => __( 'Dense drizzle', 'my-weather-plugin' ),
			61 => __( 'Slight rain', 'my-weather-plugin' ),
			63 => __( 'Moderate rain', 'my-weather-plugin' ),
			65 => __( 'Heavy rain', 'my-weather-plugin' ),
			71 => __( 'Slight snow', 'my-weather-plugin' ),
			73 => __( 'Moderate snow', 'my-weather-plugin' ),
			75 => __( 'Heavy snow', 'my-weather-plugin' ),
			77 => __( 'Snow grains', 'my-weather-plugin' ),
			80 => __( 'Slight rain showers', 'my-weather-plugin' ),
			81 => __( 'Moderate rain showers', 'my-weather-plugin' ),
			82 => __( 'Violent rain showers', 'my-weather-plugin' ),
			85 => __( 'Slight snow showers', 'my-weather-plugin' ),
			86 => __( 'Heavy snow showers', 'my-weather-plugin' ),
			95 => __( 'Thunderstorm', 'my-weather-plugin' ),
			96 => __( 'Thunderstorm with slight hail', 'my-weather-plugin' ),
			99 => __( 'Thunderstorm with heavy hail', 'my-weather-plugin' ),
		);

		return isset( $descriptions[ $code ] ) ? $descriptions[ $code ] : __( 'Unknown', 'my-weather-plugin' );
	}

	/**
	 * キャッシュされた天気データをクリア
	 */
	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}
}
