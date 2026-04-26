<?php
/**
 * FCM プラグインの管理画面コントローラー。
 *
 * @package My_Push_Notification_FCM_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_FCM_Admin {
	/** 設定ページの URL クエリで使うスラッグ。 */
	const PAGE_SLUG = 'my-push-notification-fcm-plugin';

	/**
	 * @var My_Push_FCM_Token_Repository
	 */
	private $tokens;

	/**
	 * @var My_Push_FCM_Sender
	 */
	private $sender;

	/**
	 * @var My_Push_FCM_OAuth
	 */
	private $oauth;

	public function __construct( My_Push_FCM_Token_Repository $tokens, My_Push_FCM_Sender $sender, My_Push_FCM_OAuth $oauth ) {
		$this->tokens = $tokens;
		$this->sender = $sender;
		$this->oauth  = $oauth;
	}

	/**
	 * 管理画面で必要なメニュー、設定、テスト送信処理を登録します。
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_my_push_fcm_send_test', array( $this, 'handle_test_notification' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MY_PUSH_FCM_PLUGIN_FILE ), array( $this, 'add_action_links' ) );
	}

	/**
	 * プラグイン一覧に設定画面へのショートカットを追加します。
	 */
	public function add_action_links( $links ) {
		$settings_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$settings     = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( '設定', 'my-push-notification-fcm-plugin' ) . '</a>';
		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * 「設定」メニュー配下に FCM 設定ページを追加します。
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Push Notifications (FCM)', 'my-push-notification-fcm-plugin' ),
			__( 'Push 通知 (FCM)', 'my-push-notification-fcm-plugin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * FCM 関連オプションとサニタイズ処理を Settings API に登録します。
	 */
	public function register_settings() {
		register_setting(
			'my_push_fcm_settings',
			My_Push_FCM_Plugin::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);

		register_setting(
			'my_push_fcm_settings',
			My_Push_FCM_Plugin::OPTION_PROJECT_ID,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'my_push_fcm_settings',
			My_Push_FCM_Plugin::OPTION_WEB_VAPID,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'my_push_fcm_settings',
			My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_service_account' ),
				'default'           => '',
			)
		);

		register_setting(
			'my_push_fcm_settings',
			My_Push_FCM_Plugin::OPTION_TITLE,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			)
		);

		register_setting(
			'my_push_fcm_settings',
			My_Push_FCM_Plugin::OPTION_AUTO_POSTS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);

		// サービスアカウント JSON は大きく機密性も高いため、autoload=no で保存します。
		add_filter(
			'pre_update_option_' . My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT,
			static function ( $value, $old_value ) {
				if ( $value !== $old_value ) {
					update_option( My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT, $value, false );
				}
				return $value;
			},
			10,
			2
		);
	}

	/**
	 * チェックボックス値を '1' または '0' に正規化します。
	 */
	public function sanitize_checkbox( $value ) {
		return '1' === (string) $value ? '1' : '0';
	}

	/**
	 * サービスアカウント JSON の必須キーを確認し、正常なら OAuth キャッシュを破棄します。
	 */
	public function sanitize_service_account( $value ) {
		$value = trim( (string) wp_unslash( $value ) );

		if ( '' === $value ) {
			return (string) get_option( My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT, '' );
		}

		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded )
			|| empty( $decoded['client_email'] )
			|| empty( $decoded['private_key'] )
			|| empty( $decoded['token_uri'] )
		) {
			add_settings_error(
				'my_push_fcm_settings',
				'my_push_fcm_invalid_service_account',
				__( 'サービスアカウント JSON の形式が正しくありません (client_email / private_key / token_uri が必要です)。', 'my-push-notification-fcm-plugin' )
			);

			return (string) get_option( My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT, '' );
		}

		$this->oauth->clear_cache();

		return $value;
	}

	/**
	 * 管理画面のテスト送信フォームから FCM テスト通知を送信します。
	 */
	public function handle_test_notification() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to send test notifications.', 'my-push-notification-fcm-plugin' ), 403 );
		}

		check_admin_referer( 'my_push_fcm_send_test' );

		$result = $this->sender->send_test_notification();
		$user   = get_current_user_id();

		if ( is_wp_error( $result ) ) {
			set_transient( 'my_push_fcm_notice_' . $user, array( 'error', $result->get_error_message() ), 60 );
		} else {
			set_transient(
				'my_push_fcm_notice_' . $user,
				array(
					'success',
					sprintf(
						/* translators: 1: sent count, 2: failed count. */
						__( 'FCM test finished. Sent: %1$d, Failed: %2$d.', 'my-push-notification-fcm-plugin' ),
						(int) $result['sent'],
						(int) $result['failed']
					),
				),
				60
			);
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * FCM 設定ページを描画します。
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_transient( 'my_push_fcm_notice_' . get_current_user_id() );
		delete_transient( 'my_push_fcm_notice_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Push 通知 (FCM)', 'my-push-notification-fcm-plugin' ); ?></h1>

			<?php if ( is_array( $notice ) && 2 === count( $notice ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice[1] ); ?></p>
				</div>
			<?php endif; ?>

			<?php settings_errors(); ?>

			<table class="widefat striped" style="max-width: 760px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Active FCM tokens', 'my-push-notification-fcm-plugin' ); ?></th>
						<td><?php echo esc_html( (string) $this->tokens->count_active_tokens() ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'OpenSSL extension', 'my-push-notification-fcm-plugin' ); ?></th>
						<td>
							<?php if ( extension_loaded( 'openssl' ) ) : ?>
								<?php echo esc_html__( 'Available', 'my-push-notification-fcm-plugin' ); ?>
							<?php else : ?>
								<?php echo esc_html__( 'Not available. PHP OpenSSL extension is required for FCM HTTP v1 OAuth2.', 'my-push-notification-fcm-plugin' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<form method="post" action="options.php" style="max-width: 760px; margin-top: 24px;">
				<?php settings_fields( 'my_push_fcm_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'FCM transport', 'my-push-notification-fcm-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( My_Push_FCM_Plugin::OPTION_ENABLED ); ?>" value="1" <?php checked( '1', get_option( My_Push_FCM_Plugin::OPTION_ENABLED, '0' ) ); ?>>
								<?php echo esc_html__( 'FCM 経由の通知配信を有効にする。', 'my-push-notification-fcm-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_fcm_project_id"><?php echo esc_html__( 'Firebase project ID', 'my-push-notification-fcm-plugin' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="my_push_fcm_project_id" name="<?php echo esc_attr( My_Push_FCM_Plugin::OPTION_PROJECT_ID ); ?>" value="<?php echo esc_attr( get_option( My_Push_FCM_Plugin::OPTION_PROJECT_ID, '' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_fcm_web_vapid"><?php echo esc_html__( 'FCM Web VAPID public key', 'my-push-notification-fcm-plugin' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="my_push_fcm_web_vapid" name="<?php echo esc_attr( My_Push_FCM_Plugin::OPTION_WEB_VAPID ); ?>" value="<?php echo esc_attr( get_option( My_Push_FCM_Plugin::OPTION_WEB_VAPID, '' ) ); ?>">
							<p class="description"><?php echo esc_html__( 'Firebase コンソールの Web 設定にある公開鍵を入力します。', 'my-push-notification-fcm-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_fcm_service_account"><?php echo esc_html__( 'Service account JSON', 'my-push-notification-fcm-plugin' ); ?></label></th>
						<td>
							<textarea id="my_push_fcm_service_account" name="<?php echo esc_attr( My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT ); ?>" rows="6" cols="60" class="large-text code" autocomplete="off" placeholder='{"type":"service_account",...}'></textarea>
							<p class="description">
								<?php
								if ( '' !== trim( (string) get_option( My_Push_FCM_Plugin::OPTION_SERVICE_ACCOUNT, '' ) ) ) {
									echo esc_html__( 'サービスアカウントは設定済みです。空欄で保存すると既存値を維持します。', 'my-push-notification-fcm-plugin' );
								} else {
									echo esc_html__( 'サービスアカウントは未設定です。Firebase コンソールで JSON を作成して貼り付けてください。', 'my-push-notification-fcm-plugin' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_fcm_default_title"><?php echo esc_html__( 'Default notification title', 'my-push-notification-fcm-plugin' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="my_push_fcm_default_title" name="<?php echo esc_attr( My_Push_FCM_Plugin::OPTION_TITLE ); ?>" value="<?php echo esc_attr( get_option( My_Push_FCM_Plugin::OPTION_TITLE, get_bloginfo( 'name' ) ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Automatic post notifications', 'my-push-notification-fcm-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( My_Push_FCM_Plugin::OPTION_AUTO_POSTS ); ?>" value="1" <?php checked( '1', get_option( My_Push_FCM_Plugin::OPTION_AUTO_POSTS, '0' ) ); ?>>
								<?php echo esc_html__( 'Send a notification when a post is newly published.', 'my-push-notification-fcm-plugin' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
				<input type="hidden" name="action" value="my_push_fcm_send_test">
				<?php wp_nonce_field( 'my_push_fcm_send_test' ); ?>
				<?php submit_button( __( 'Send FCM test notification', 'my-push-notification-fcm-plugin' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
