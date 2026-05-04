<?php
/**
 * 天気プラグイン管理画面設定クラス
 *
 * @package MyWeatherPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理画面の設定ページを処理
 */
class My_Weather_Admin {

	const OPTION_GROUP = 'my_weather_settings';
	const SETTINGS_PAGE = 'my_weather_settings';

	/**
	 * 管理画面設定を初期化
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * 管理画面メニューページを追加
	 */
	public static function add_menu_page() {
		add_options_page(
			__( 'Weather Settings', 'my-weather-plugin' ),
			__( 'Weather', 'my-weather-plugin' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * プラグインの設定を登録
	 */
	public static function register_settings() {
		// 設定を登録
		register_setting(
			self::OPTION_GROUP,
			'my_weather_latitude',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_coordinate' ),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'my_weather_longitude',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_coordinate' ),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'my_weather_city_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
			)
		);

		// 設定セクションを追加
		add_settings_section(
			'my_weather_location_section',
			__( 'Location Settings', 'my-weather-plugin' ),
			array( __CLASS__, 'render_section_description' ),
			self::SETTINGS_PAGE
		);

		// 設定フィールドを追加
		add_settings_field(
			'my_weather_city_name',
			__( 'City Name', 'my-weather-plugin' ),
			array( __CLASS__, 'render_city_name_field' ),
			self::SETTINGS_PAGE,
			'my_weather_location_section'
		);

		add_settings_field(
			'my_weather_latitude',
			__( 'Latitude', 'my-weather-plugin' ),
			array( __CLASS__, 'render_latitude_field' ),
			self::SETTINGS_PAGE,
			'my_weather_location_section'
		);

		add_settings_field(
			'my_weather_longitude',
			__( 'Longitude', 'my-weather-plugin' ),
			array( __CLASS__, 'render_longitude_field' ),
			self::SETTINGS_PAGE,
			'my_weather_location_section'
		);
	}

	/**
	 * 設定ページをレンダリング
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'my-weather-plugin' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::SETTINGS_PAGE );
				submit_button();
				?>
			</form>

			<div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
				<h3><?php esc_html_e( 'Usage', 'my-weather-plugin' ); ?></h3>
				<p><?php esc_html_e( 'Use the [weather] shortcode in posts or pages to display current weather:', 'my-weather-plugin' ); ?></p>
				<code style="display: block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">[weather]</code>

				<p><?php esc_html_e( 'For simple format (text only):', 'my-weather-plugin' ); ?></p>
				<code style="display: block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">[weather format="simple"]</code>

				<h3><?php esc_html_e( 'Example Coordinates', 'my-weather-plugin' ); ?></h3>
				<ul style="margin-left: 20px;">
					<li><?php esc_html_e( 'Tokyo: Latitude 35.6762, Longitude 139.6503', 'my-weather-plugin' ); ?></li>
					<li><?php esc_html_e( 'New York: Latitude 40.7128, Longitude -74.0060', 'my-weather-plugin' ); ?></li>
					<li><?php esc_html_e( 'London: Latitude 51.5074, Longitude -0.1278', 'my-weather-plugin' ); ?></li>
					<li><?php esc_html_e( 'Paris: Latitude 48.8566, Longitude 2.3522', 'my-weather-plugin' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * セクションの説明をレンダリング
	 */
	public static function render_section_description() {
		esc_html_e( 'Configure the location for weather information display. Coordinates should be in decimal format (latitude: -90 to 90, longitude: -180 to 180).', 'my-weather-plugin' );
	}

	/**
	 * 都市名フィールドをレンダリング
	 */
	public static function render_city_name_field() {
		$value = get_option( 'my_weather_city_name', 'Tokyo' );
		printf(
			'<input type="text" name="my_weather_city_name" value="%s" required style="width: 300px;" />',
			esc_attr( $value )
		);
	}

	/**
	 * 緯度フィールドをレンダリング
	 */
	public static function render_latitude_field() {
		$value = get_option( 'my_weather_latitude', '35.6762' );
		printf(
			'<input type="number" name="my_weather_latitude" value="%s" step="0.0001" min="-90" max="90" required style="width: 300px;" />',
			esc_attr( $value )
		);
	}

	/**
	 * 経度フィールドをレンダリング
	 */
	public static function render_longitude_field() {
		$value = get_option( 'my_weather_longitude', '139.6503' );
		printf(
			'<input type="number" name="my_weather_longitude" value="%s" step="0.0001" min="-180" max="180" required style="width: 300px;" />',
			esc_attr( $value )
		);
	}

	/**
	 * 座標入力値をサニタイズ
	 *
	 * @param string $value 入力値
	 * @return string|WP_Error サニタイズされた値またはエラー
	 */
	public static function sanitize_coordinate( $value ) {
		$value = trim( $value );
		$float = floatval( $value );

		// 座標の範囲を検証
		if ( abs( $float ) > 180 ) {
			add_settings_error(
				self::OPTION_GROUP,
				'invalid_coordinate',
				__( 'Invalid coordinate value. Use format like 35.6762 or -74.0060', 'my-weather-plugin' )
			);
			return get_option( 'my_weather_latitude' ) ?: '0';
		}

		// 位置情報が変更された時はキャッシュをクリア
		My_Weather_API::clear_cache();

		return $float;
	}

	/**
	 * 管理画面用のスクリプトを読み込む
	 *
	 * @param string $hook_suffix 現在のページフック
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		if ( 'settings_page_' . self::SETTINGS_PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'my-weather-admin',
			MY_WEATHER_PLUGIN_URL . 'assets/admin-style.css',
			array(),
			MY_WEATHER_PLUGIN_VERSION
		);
	}
}
