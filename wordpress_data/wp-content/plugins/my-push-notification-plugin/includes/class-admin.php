<?php
/**
 * Admin settings page.
 *
 * @package My_Push_Notification_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_Admin {
	const PAGE_SLUG = 'my-push-notification-plugin';

	/**
	 * Subscriber repository.
	 *
	 * @var My_Push_Subscriber_Repository
	 */
	private $repository;

	/**
	 * Push sender.
	 *
	 * @var My_Push_Web_Push_Service
	 */
	private $web_push;

	public function __construct( My_Push_Subscriber_Repository $repository, My_Push_Web_Push_Service $web_push ) {
		$this->repository = $repository;
		$this->web_push   = $web_push;
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_my_push_send_test', array( $this, 'handle_test_notification' ) );
		add_action( 'admin_post_my_push_generate_vapid', array( $this, 'handle_generate_vapid' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MY_PUSH_PLUGIN_FILE ), array( $this, 'add_action_links' ) );
	}

	public function add_action_links( $links ) {
		$settings_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$settings     = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( '設定', 'my-push-notification-plugin' ) . '</a>';
		array_unshift( $links, $settings );

		return $links;
	}

	public function add_settings_page() {
		add_options_page(
			__( 'Push Notifications', 'my-push-notification-plugin' ),
			__( 'Push 通知', 'my-push-notification-plugin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_PUBLIC_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_PRIVATE_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_private_key' ),
				'default'           => '',
			)
		);

		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_SUBJECT,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => home_url( '/' ),
			)
		);

		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_TITLE,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			)
		);

		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_AUTO_POSTS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);
	}

	public function sanitize_private_key( $value ) {
		$value = sanitize_text_field( (string) $value );

		if ( '' === $value ) {
			return (string) get_option( My_Push_Plugin::OPTION_PRIVATE_KEY, '' );
		}

		return $value;
	}

	public function sanitize_checkbox( $value ) {
		return '1' === (string) $value ? '1' : '0';
	}

	public function handle_test_notification() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to send test notifications.', 'my-push-notification-plugin' ), 403 );
		}

		check_admin_referer( 'my_push_send_test' );

		$result = $this->web_push->send_test_notification();
		$user   = get_current_user_id();

		if ( is_wp_error( $result ) ) {
			set_transient( 'my_push_notice_' . $user, array( 'error', $result->get_error_message() ), 60 );
		} else {
			set_transient(
				'my_push_notice_' . $user,
				array(
					'success',
					sprintf(
						/* translators: 1: sent count, 2: failed count. */
						__( 'Test notification finished. Sent: %1$d, Failed: %2$d.', 'my-push-notification-plugin' ),
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

	public function handle_generate_vapid() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate VAPID keys.', 'my-push-notification-plugin' ), 403 );
		}

		check_admin_referer( 'my_push_generate_vapid' );

		if ( ! class_exists( '\Minishlink\WebPush\VAPID' ) ) {
			set_transient(
				'my_push_notice_' . get_current_user_id(),
				array( 'error', __( 'The minishlink/web-push Composer package is not installed.', 'my-push-notification-plugin' ) ),
				60
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
			exit;
		}

		$keys = \Minishlink\WebPush\VAPID::createVapidKeys();

		if ( empty( $keys['publicKey'] ) || empty( $keys['privateKey'] ) ) {
			set_transient(
				'my_push_notice_' . get_current_user_id(),
				array( 'error', __( 'Could not generate VAPID keys.', 'my-push-notification-plugin' ) ),
				60
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
			exit;
		}

		update_option( My_Push_Plugin::OPTION_PUBLIC_KEY, sanitize_text_field( $keys['publicKey'] ) );
		update_option( My_Push_Plugin::OPTION_PRIVATE_KEY, sanitize_text_field( $keys['privateKey'] ) );

		if ( '' === trim( (string) get_option( My_Push_Plugin::OPTION_SUBJECT, '' ) ) ) {
			update_option( My_Push_Plugin::OPTION_SUBJECT, home_url( '/' ) );
		}

		set_transient(
			'my_push_notice_' . get_current_user_id(),
			array( 'success', __( 'VAPID keys generated and saved.', 'my-push-notification-plugin' ) ),
			60
		);

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_transient( 'my_push_notice_' . get_current_user_id() );
		delete_transient( 'my_push_notice_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Push 通知', 'my-push-notification-plugin' ); ?></h1>

			<?php if ( is_array( $notice ) && 2 === count( $notice ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice[1] ); ?></p>
				</div>
			<?php endif; ?>

			<?php settings_errors(); ?>

			<table class="widefat striped" style="max-width: 760px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Active subscribers', 'my-push-notification-plugin' ); ?></th>
						<td><?php echo esc_html( (string) $this->repository->count_active_subscribers() ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Web Push library', 'my-push-notification-plugin' ); ?></th>
						<td>
							<?php if ( $this->web_push->dependency_status() ) : ?>
								<?php echo esc_html__( 'Installed', 'my-push-notification-plugin' ); ?>
							<?php else : ?>
								<?php echo esc_html__( 'Not installed. Run composer install in this plugin directory before sending notifications.', 'my-push-notification-plugin' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<form method="post" action="options.php" style="max-width: 760px; margin-top: 24px;">
				<?php settings_fields( 'my_push_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="my_push_vapid_public_key"><?php echo esc_html__( 'VAPID public key', 'my-push-notification-plugin' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="my_push_vapid_public_key" name="<?php echo esc_attr( My_Push_Plugin::OPTION_PUBLIC_KEY ); ?>" value="<?php echo esc_attr( get_option( My_Push_Plugin::OPTION_PUBLIC_KEY, '' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_vapid_private_key"><?php echo esc_html__( 'VAPID private key', 'my-push-notification-plugin' ); ?></label></th>
						<td>
							<input type="password" class="regular-text code" id="my_push_vapid_private_key" name="<?php echo esc_attr( My_Push_Plugin::OPTION_PRIVATE_KEY ); ?>" value="" autocomplete="new-password">
							<?php if ( get_option( My_Push_Plugin::OPTION_PRIVATE_KEY, '' ) ) : ?>
								<p class="description"><?php echo esc_html__( 'Private key is configured. Enter a new value only when replacing it.', 'my-push-notification-plugin' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_vapid_subject"><?php echo esc_html__( 'VAPID subject', 'my-push-notification-plugin' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="my_push_vapid_subject" name="<?php echo esc_attr( My_Push_Plugin::OPTION_SUBJECT ); ?>" value="<?php echo esc_attr( get_option( My_Push_Plugin::OPTION_SUBJECT, home_url( '/' ) ) ); ?>">
							<p class="description"><?php echo esc_html__( 'Use a site URL or a mailto: address.', 'my-push-notification-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_default_title"><?php echo esc_html__( 'Default notification title', 'my-push-notification-plugin' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="my_push_default_title" name="<?php echo esc_attr( My_Push_Plugin::OPTION_TITLE ); ?>" value="<?php echo esc_attr( get_option( My_Push_Plugin::OPTION_TITLE, get_bloginfo( 'name' ) ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Automatic post notifications', 'my-push-notification-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( My_Push_Plugin::OPTION_AUTO_POSTS ); ?>" value="1" <?php checked( '1', get_option( My_Push_Plugin::OPTION_AUTO_POSTS, '0' ) ); ?>>
								<?php echo esc_html__( 'Send a notification when a post is newly published.', 'my-push-notification-plugin' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
				<input type="hidden" name="action" value="my_push_generate_vapid">
				<?php wp_nonce_field( 'my_push_generate_vapid' ); ?>
				<?php submit_button( __( 'Generate VAPID keys', 'my-push-notification-plugin' ), 'secondary' ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
				<input type="hidden" name="action" value="my_push_send_test">
				<?php wp_nonce_field( 'my_push_send_test' ); ?>
				<?php submit_button( __( 'Send test notification', 'my-push-notification-plugin' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
