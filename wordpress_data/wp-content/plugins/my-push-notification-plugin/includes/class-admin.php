<?php

/**
 * 管理画面の設定ページを担うクラス。
 *
 * WordPress Settings API を使って VAPID 鍵・通知タイトルなどの
 * オプションを登録・保存し、テスト通知の送信・VAPID 鍵生成の
 * フォーム処理も行う。
 *
 * @package My_Push_Notification_Plugin
 */

// WordPress の外部から直接アクセスされた場合は終了する。
if (! defined('ABSPATH')) {
	exit;
}

/**
 * 管理画面コントローラークラス。
 *
 * 担当:
 *   - 設定ページのメニュー登録
 *   - WordPress Settings API によるオプション登録とバリデーション
 *   - テスト通知送信フォームの処理
 *   - VAPID 鍵自動生成フォームの処理
 *   - プラグイン一覧ページへの「設定」リンク追加
 */
class My_Push_Admin
{

	/** 設定ページのスラッグ。add_options_page() と admin_url() で共用する。 */
	const PAGE_SLUG = 'my-push-notification-plugin';

	// ---- 依存プロパティ --------------------------------------------------------

	/**
	 * 購読者の DB 操作を担うリポジトリ。
	 *
	 * @var My_Push_Subscriber_Repository
	 */
	private $repository;

	/**
	 * Web Push 送信サービス。テスト通知の送信に使用する。
	 *
	 * @var My_Push_Web_Push_Service
	 */
	private $web_push;

	// ---- コンストラクタ --------------------------------------------------------

	/**
	 * @param My_Push_Subscriber_Repository $repository 購読者リポジトリ。
	 * @param My_Push_Web_Push_Service      $web_push   Web Push 送信サービス。
	 */
	public function __construct(
		My_Push_Subscriber_Repository $repository,
		My_Push_Web_Push_Service $web_push
	) {
		$this->repository = $repository;
		$this->web_push   = $web_push;
	}

	// ---- フック登録 ------------------------------------------------------------

	/**
	 * 管理画面に必要な WordPress フックをすべて登録する。
	 */
	public function register_hooks()
	{
		// 管理メニューに設定ページを追加する。
		add_action('admin_menu', array($this, 'add_settings_page'));

		// Settings API でオプションフィールドを登録する。
		add_action('admin_init', array($this, 'register_settings'));

		// admin-post.php 経由でテスト通知フォームの POST を処理する。
		add_action('admin_post_my_push_send_test', array($this, 'handle_test_notification'));

		// admin-post.php 経由で VAPID 鍵生成フォームの POST を処理する。
		add_action('admin_post_my_push_generate_vapid', array($this, 'handle_generate_vapid'));

		// プラグイン一覧ページに「設定」リンクを追加する。
		add_filter('plugin_action_links_' . plugin_basename(MY_PUSH_PLUGIN_FILE), array($this, 'add_action_links'));
	}

	// ---- プラグイン一覧ページへのリンク追加 ------------------------------------

	/**
	 * プラグイン一覧の「有効化」ボタン横に「設定」リンクを追加する。
	 *
	 * @param string[] $links 既存のアクションリンクの配列。
	 * @return string[] 「設定」リンクを先頭に追加した配列。
	 */
	public function add_action_links($links)
	{
		$settings_url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
		$settings     = '<a href="' . esc_url($settings_url) . '">' . esc_html__('設定', 'my-push-notification-plugin') . '</a>';
		array_unshift($links, $settings);

		return $links;
	}

	// ---- 設定ページ登録 --------------------------------------------------------

	/**
	 * 「設定」メニュー配下に Push 通知設定ページを追加する。
	 * 権限: manage_options（管理者のみ）。
	 */
	public function add_settings_page()
	{
		add_options_page(
			__('Push Notifications', 'my-push-notification-plugin'), // ページタイトル（<title> タグ）。
			__('Push 通知', 'my-push-notification-plugin'),           // メニュー表示名。
			'manage_options',                                            // 必要な権限。
			self::PAGE_SLUG,                                             // ページスラッグ。
			array($this, 'render_settings_page')                      // 描画コールバック。
		);
	}

	// ---- Settings API オプション登録 ------------------------------------------

	/**
	 * WordPress Settings API を使って各オプションを登録する。
	 *
	 * register_setting() はオプションを options.php（フォームの action）に
	 * 紐付けるとともに、sanitize_callback でサニタイズ処理を定義する。
	 */
	public function register_settings()
	{
		// VAPID 公開鍵（Base64URL 文字列）。
		register_setting(
			'my_push_settings',                     // フォームグループ名（settings_fields() と一致させる）。
			My_Push_Plugin::OPTION_PUBLIC_KEY,       // wp_options のキー名。
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field', // HTML タグや余分な空白を除去する。
				'default'           => '',
			)
		);

		// VAPID 秘密鍵（Base64URL 文字列）。
		// 空欄で送信された場合は既存の値を維持する特別なサニタイズを使用する。
		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_PRIVATE_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array($this, 'sanitize_private_key'),
				'default'           => '',
			)
		);

		// VAPID サブジェクト（サイト URL または mailto: アドレス）。
		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_SUBJECT,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => home_url('/'),
			)
		);

		// 通知のデフォルトタイトル。
		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_TITLE,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo('name'),
			)
		);

		// 投稿公開時の自動通知フラグ（'1' または '0'）。
		register_setting(
			'my_push_settings',
			My_Push_Plugin::OPTION_AUTO_POSTS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array($this, 'sanitize_checkbox'),
				'default'           => '0',
			)
		);

	}

	// ---- サニタイズコールバック ------------------------------------------------

	/**
	 * VAPID 秘密鍵のサニタイズ処理。
	 *
	 * 管理画面のパスワードフィールドは空で送信される場合があるため、
	 * 空文字が送られた場合は DB の既存値をそのまま返して上書きを防ぐ。
	 *
	 * @param mixed $value フォームから送られた生の値。
	 * @return string サニタイズ済みの秘密鍵文字列。
	 */
	public function sanitize_private_key($value)
	{
		$value = sanitize_text_field((string) $value);

		// 空欄の場合は既存値を維持する（誤って鍵を消去しないための保護）。
		if ('' === $value) {
			return (string) get_option(My_Push_Plugin::OPTION_PRIVATE_KEY, '');
		}

		return $value;
	}

	/**
	 * チェックボックスのサニタイズ処理。
	 *
	 * チェックが入っていれば '1'、外れていれば '0' を返す。
	 * フォームの値は文字列として受け取る。
	 *
	 * @param mixed $value フォームから送られた生の値。
	 * @return string '1' または '0'。
	 */
	public function sanitize_checkbox($value)
	{
		return '1' === (string) $value ? '1' : '0';
	}

	// ---- フォーム処理（admin-post.php） ----------------------------------------

	/**
	 * テスト通知の送信フォームを処理する。
	 *
	 * admin-post.php に action=my_push_send_test で POST されたときに呼ばれる。
	 * 処理後は設定ページにリダイレクトし、結果を transient で通知する。
	 */
	public function handle_test_notification()
	{
		// 権限チェック：管理者のみ許可する。
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to send test notifications.', 'my-push-notification-plugin'), 403);
		}

		// nonce 検証：CSRF 攻撃を防ぐ。
		check_admin_referer('my_push_send_test');

		// テスト通知を全購読者に送信する。
		$result = $this->web_push->send_test_notification();
		$user   = get_current_user_id();

		if (is_wp_error($result)) {
			// エラー内容を transient に保存し、設定ページで表示する。
			set_transient('my_push_notice_' . $user, array('error', $result->get_error_message()), 60);
		} else {
			// 送信成功・失敗件数を transient に保存し、設定ページで表示する。
			set_transient(
				'my_push_notice_' . $user,
				array(
					'success',
					sprintf(
						/* translators: 1: sent count, 2: failed count. */
						__('Test notification finished. Sent: %1$d, Failed: %2$d.', 'my-push-notification-plugin'),
						(int) $result['sent'],
						(int) $result['failed']
					),
				),
				60
			);
		}

		// 設定ページにリダイレクトする（PRG パターン）。
		wp_safe_redirect(admin_url('options-general.php?page=' . self::PAGE_SLUG));
		exit;
	}

	/**
	 * VAPID 鍵ペアの自動生成フォームを処理する。
	 *
	 * admin-post.php に action=my_push_generate_vapid で POST されたときに呼ばれる。
	 * minishlink/web-push ライブラリを使って公開鍵・秘密鍵を生成し DB に保存する。
	 */
	public function handle_generate_vapid()
	{
		// 権限チェック。
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to generate VAPID keys.', 'my-push-notification-plugin'), 403);
		}

		// nonce 検証。
		check_admin_referer('my_push_generate_vapid');

		// minishlink/web-push ライブラリが Composer でインストールされているか確認する。
		if (! class_exists('\Minishlink\WebPush\VAPID')) {
			set_transient(
				'my_push_notice_' . get_current_user_id(),
				array('error', __('The minishlink/web-push Composer package is not installed.', 'my-push-notification-plugin')),
				60
			);
			wp_safe_redirect(admin_url('options-general.php?page=' . self::PAGE_SLUG));
			exit;
		}

		// VAPID 鍵ペアを生成する（公開鍵・秘密鍵ともに Base64URL 形式）。
		$keys = \Minishlink\WebPush\VAPID::createVapidKeys();

		// 鍵が正常に生成されなかった場合はエラーを返す。
		if (empty($keys['publicKey']) || empty($keys['privateKey'])) {
			set_transient(
				'my_push_notice_' . get_current_user_id(),
				array('error', __('Could not generate VAPID keys.', 'my-push-notification-plugin')),
				60
			);
			wp_safe_redirect(admin_url('options-general.php?page=' . self::PAGE_SLUG));
			exit;
		}

		// 生成した鍵を DB に保存する。
		update_option(My_Push_Plugin::OPTION_PUBLIC_KEY, sanitize_text_field($keys['publicKey']));
		update_option(My_Push_Plugin::OPTION_PRIVATE_KEY, sanitize_text_field($keys['privateKey']));

		// サブジェクトが未設定の場合はサイトのトップページ URL をデフォルトにする。
		if ('' === trim((string) get_option(My_Push_Plugin::OPTION_SUBJECT, ''))) {
			update_option(My_Push_Plugin::OPTION_SUBJECT, home_url('/'));
		}

		set_transient(
			'my_push_notice_' . get_current_user_id(),
			array('success', __('VAPID keys generated and saved.', 'my-push-notification-plugin')),
			60
		);

		wp_safe_redirect(admin_url('options-general.php?page=' . self::PAGE_SLUG));
		exit;
	}

	// ---- 設定ページ描画 --------------------------------------------------------

	/**
	 * 設定ページ全体の HTML を出力する。
	 *
	 * 出力内容:
	 *   - 一時通知メッセージ（transient から取得）
	 *   - 購読者数・ライブラリ状態の概要テーブル
	 *   - VAPID 鍵・通知タイトル・自動通知の設定フォーム（Settings API）
	 *   - VAPID 鍵生成ボタン
	 *   - テスト通知送信ボタン
	 */
	public function render_settings_page()
	{
		// 管理者権限のない場合は何も表示しない。
		if (! current_user_can('manage_options')) {
			return;
		}

		// 一時通知メッセージを取得し、表示後に削除する（一度だけ表示）。
		$notice = get_transient('my_push_notice_' . get_current_user_id());
		delete_transient('my_push_notice_' . get_current_user_id());
?>
		<div class="wrap">
			<h1><?php echo esc_html__('Push 通知', 'my-push-notification-plugin'); ?></h1>

			<?php
			// 一時通知メッセージを表示する（成功・エラーどちらでも対応）。
			if (is_array($notice) && 2 === count($notice)) :
			?>
				<div class="notice notice-<?php echo esc_attr($notice[0]); ?> is-dismissible">
					<p><?php echo esc_html($notice[1]); ?></p>
				</div>
			<?php endif; ?>

			<?php
			// Settings API のエラーメッセージを表示する（バリデーション失敗時など）。
			settings_errors();
			?>

			<?php /* 概要テーブル：購読者数とライブラリインストール状況を表示する。 */ ?>
			<table class="widefat striped" style="max-width: 760px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__('Active subscribers', 'my-push-notification-plugin'); ?></th>
						<td><?php echo esc_html((string) $this->repository->count_active_subscribers()); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Web Push library', 'my-push-notification-plugin'); ?></th>
						<td>
							<?php if ($this->web_push->dependency_status()) : ?>
								<?php echo esc_html__('Installed', 'my-push-notification-plugin'); ?>
							<?php else : ?>
								<?php echo esc_html__('Not installed. Run composer install in this plugin directory before sending notifications.', 'my-push-notification-plugin'); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php /* メイン設定フォーム：Settings API の options.php に POST する。 */ ?>
			<form method="post" action="options.php" style="max-width: 760px; margin-top: 24px;">
				<?php
				// 隠しフィールド（nonce・action・option_page）を出力する。
				settings_fields('my_push_settings');
				?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="my_push_vapid_public_key"><?php echo esc_html__('VAPID public key', 'my-push-notification-plugin'); ?></label></th>
						<td>
							<?php /* VAPID 公開鍵の入力フィールド。フロントエンドの JS に渡され購読処理で使用される。 */ ?>
							<input type="text" class="regular-text code" id="my_push_vapid_public_key" name="<?php echo esc_attr(My_Push_Plugin::OPTION_PUBLIC_KEY); ?>" value="<?php echo esc_attr(get_option(My_Push_Plugin::OPTION_PUBLIC_KEY, '')); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_vapid_private_key"><?php echo esc_html__('VAPID private key', 'my-push-notification-plugin'); ?></label></th>
						<td>
							<?php
							/*
							 * VAPID 秘密鍵はパスワードフィールドとして表示する。
							 * 常に空欄で表示し、変更時のみ新しい値を入力してもらう。
							 * autocomplete="new-password" でブラウザの自動入力を無効化する。
							 */
							?>
							<input type="password" class="regular-text code" id="my_push_vapid_private_key" name="<?php echo esc_attr(My_Push_Plugin::OPTION_PRIVATE_KEY); ?>" value="" autocomplete="new-password">
							<?php if (get_option(My_Push_Plugin::OPTION_PRIVATE_KEY, '')) : ?>
								<p class="description"><?php echo esc_html__('Private key is configured. Enter a new value only when replacing it.', 'my-push-notification-plugin'); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_vapid_subject"><?php echo esc_html__('VAPID subject', 'my-push-notification-plugin'); ?></label></th>
						<td>
							<?php /* VAPID サブジェクト：Push サービスが送信者を識別するための識別子。 */ ?>
							<input type="text" class="regular-text" id="my_push_vapid_subject" name="<?php echo esc_attr(My_Push_Plugin::OPTION_SUBJECT); ?>" value="<?php echo esc_attr(get_option(My_Push_Plugin::OPTION_SUBJECT, home_url('/'))); ?>">
							<p class="description"><?php echo esc_html__('Use a site URL or a mailto: address.', 'my-push-notification-plugin'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="my_push_default_title"><?php echo esc_html__('Default notification title', 'my-push-notification-plugin'); ?></label></th>
						<td>
							<?php /* 通知のタイトル。未設定の場合はサイト名が使用される。 */ ?>
							<input type="text" class="regular-text" id="my_push_default_title" name="<?php echo esc_attr(My_Push_Plugin::OPTION_TITLE); ?>" value="<?php echo esc_attr(get_option(My_Push_Plugin::OPTION_TITLE, get_bloginfo('name'))); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Automatic post notifications', 'my-push-notification-plugin'); ?></th>
						<td>
							<label>
								<?php /* 新規投稿公開時に自動で Push 通知を送るかどうかの設定。 */ ?>
								<input type="checkbox" name="<?php echo esc_attr(My_Push_Plugin::OPTION_AUTO_POSTS); ?>" value="1" <?php checked('1', get_option(My_Push_Plugin::OPTION_AUTO_POSTS, '0')); ?>>
								<?php echo esc_html__('Send a notification when a post is newly published.', 'my-push-notification-plugin'); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php /* VAPID 鍵自動生成フォーム：admin-post.php に POST してサーバー側で鍵を生成する。 */ ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 20px;">
				<input type="hidden" name="action" value="my_push_generate_vapid">
				<?php wp_nonce_field('my_push_generate_vapid'); ?>
				<?php submit_button(__('Generate VAPID keys', 'my-push-notification-plugin'), 'secondary'); ?>
			</form>

			<?php /* テスト通知送信フォーム：全購読者にテスト通知を送信する。 */ ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 20px;">
				<input type="hidden" name="action" value="my_push_send_test">
				<?php wp_nonce_field('my_push_send_test'); ?>
				<?php submit_button(__('Send test notification', 'my-push-notification-plugin'), 'secondary'); ?>
			</form>
		</div>
<?php
	}
}
