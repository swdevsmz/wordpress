<?php
/**
 * 天気ショートコードクラス
 *
 * @package MyWeatherPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [weather] ショートコードのレンダリングを処理
 */
class My_Weather_Shortcode {

	/**
	 * ショートコードを初期化
	 */
	public static function init() {
		add_shortcode( 'weather', array( __CLASS__, 'render' ) );
	}

	/**
	 * 天気ショートコードをレンダリング
	 *
	 * @param array  $atts ショートコード属性
	 * @param string $content ショートコードコンテンツ
	 * @return string HTML出力
	 */
	public static function render( $atts = array(), $content = '' ) {
		// 属性をパース
		$atts = shortcode_atts(
			array(
				'format' => 'full',
			),
			$atts,
			'weather'
		);

		// 天気データを取得
		$weather = My_Weather_API::get_weather();

		// エラーをチェック
		if ( is_wp_error( $weather ) ) {
			return sprintf(
				'<div class="weather-error">%s</div>',
				esc_html( $weather->get_error_message() )
			);
		}

		// 位置情報を取得
		$city_name = get_option( 'my_weather_city_name', __( 'Unknown location', 'my-weather-plugin' ) );

		// 形式に基づいて表示を準備
		if ( 'simple' === $atts['format'] ) {
			return self::render_simple( $weather, $city_name );
		}

		return self::render_full( $weather, $city_name );
	}

	/**
	 * シンプル形式の天気表示をレンダリング
	 *
	 * @param array  $weather 天気データ
	 * @param string $city_name 都市名
	 * @return string HTML出力
	 */
	private static function render_simple( $weather, $city_name ) {
		$current = $weather['current'];
		$temp    = (int) $current['temperature_2m'];
		$desc    = My_Weather_API::get_weather_description( (int) $current['weather_code'] );

		return sprintf(
			'<div class="weather-simple"><strong>%s</strong>: %d°C, %s</div>',
			esc_html( $city_name ),
			$temp,
			esc_html( $desc )
		);
	}

	/**
	 * フル形式の天気表示をレンダリング
	 *
	 * @param array  $weather 天気データ
	 * @param string $city_name 都市名
	 * @return string HTML出力
	 */
	private static function render_full( $weather, $city_name ) {
		$current = $weather['current'];
		$temp    = (float) $current['temperature_2m'];
		$code    = (int) $current['weather_code'];
		$desc    = My_Weather_API::get_weather_description( $code );
		$wind    = (float) $current['wind_speed_10m'];

		ob_start();
		?>
		<div class="weather-widget">
			<div class="weather-header">
				<h3><?php echo esc_html( $city_name ); ?></h3>
			</div>
			<div class="weather-content">
				<div class="weather-temp">
					<span class="temp-value"><?php echo esc_html( $temp ); ?></span>
					<span class="temp-unit">°C</span>
				</div>
				<div class="weather-description">
					<?php echo esc_html( $desc ); ?>
				</div>
				<div class="weather-wind">
					<?php echo esc_html( sprintf( __( 'Wind: %s km/h', 'my-weather-plugin' ), $wind ) ); ?>
				</div>
			</div>
			<div class="weather-footer">
				<?php echo esc_html( sprintf( __( 'Updated: %s', 'my-weather-plugin' ), current_time( 'M d, Y H:i' ) ) ); ?>
			</div>
		</div>
		<style>
			.weather-widget {
				border: 1px solid #ddd;
				border-radius: 8px;
				padding: 20px;
				max-width: 300px;
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: white;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			}
			.weather-header h3 {
				margin: 0 0 15px 0;
				font-size: 18px;
				font-weight: 600;
			}
			.weather-temp {
				display: flex;
				align-items: baseline;
				margin-bottom: 10px;
			}
			.weather-temp .temp-value {
				font-size: 48px;
				font-weight: bold;
			}
			.weather-temp .temp-unit {
				font-size: 24px;
				margin-left: 5px;
			}
			.weather-description {
				font-size: 16px;
				margin-bottom: 10px;
				opacity: 0.9;
			}
			.weather-wind {
				font-size: 14px;
				opacity: 0.9;
			}
			.weather-footer {
				font-size: 12px;
				margin-top: 15px;
				opacity: 0.8;
				border-top: 1px solid rgba(255, 255, 255, 0.2);
				padding-top: 10px;
			}
			.weather-error {
				color: #d32f2f;
				padding: 10px;
				border: 1px solid #d32f2f;
				border-radius: 4px;
				background-color: #ffebee;
			}
		</style>
		<?php
		return ob_get_clean();
	}
}
