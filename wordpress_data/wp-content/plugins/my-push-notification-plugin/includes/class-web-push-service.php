<?php

/**
 * Web Push 通知の送信ロジックを担うサービスクラス。
 *
 * minishlink/web-push Composer ライブラリを使用して、
 * VAPID 認証付きの暗号化 Web Push メッセージを全購読者に送信する。
 *
 * @package My_Push_Notification_Plugin
 */

// WordPress の外部から直接アクセスされた場合は終了する。
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Web Push 送信サービスクラス。
 *
 * 担当:
 *   - Composer ライブラリの依存状態チェック
 *   - 投稿公開通知・テスト通知の送信
 *   - 全購読者への一括 Push 送信
 *   - 期限切れ購読の自動無効化
 */
class My_Push_Web_Push_Service
{

	// ---- 依存プロパティ --------------------------------------------------------

	/**
	 * 購読者の DB 操作を担うリポジトリ。
	 *
	 * @var My_Push_Subscriber_Repository
	 */
	private $repository;

	// ---- コンストラクタ --------------------------------------------------------

	/**
	 * @param My_Push_Subscriber_Repository $repository 購読者リポジトリ。
	 */
	public function __construct(My_Push_Subscriber_Repository $repository)
	{
		$this->repository = $repository;
	}

	// ---- 依存ライブラリの確認 --------------------------------------------------

	/**
	 * minishlink/web-push ライブラリがインストール済みかどうかを返す。
	 *
	 * Composer の autoload により WebPush クラスと Subscription クラスが
	 * 利用可能であれば true を返す。管理画面の状態表示でも使用する。
	 *
	 * @return bool インストール済みなら true。
	 */
	public function dependency_status()
	{
		return class_exists('\Minishlink\WebPush\WebPush') && class_exists('\Minishlink\WebPush\Subscription');
	}

	// ---- 公開 API：通知送信 ----------------------------------------------------

	/**
	 * 指定した投稿の情報を含む Push 通知を全購読者に送信する。
	 *
	 * 投稿タイトルを通知本文、パーマリンクをクリック先 URL に使用する。
	 *
	 * @param int $post_id 投稿 ID。
	 * @return array{sent: int, failed: int}|WP_Error 成功時は送受信件数の配列、失敗時は WP_Error。
	 */
	public function send_post_notification($post_id)
	{
		$post = get_post($post_id);

		// 投稿が存在しない場合はエラーを返す。
		if (! $post) {
			return new WP_Error('my_push_missing_post', __('Post not found.', 'my-push-notification-plugin'));
		}

		return $this->send_to_all(
			array(
				'title' => $this->default_title(),  // 管理画面で設定したデフォルトタイトル。
				'body'  => get_the_title($post),   // 投稿タイトルを通知本文にする。
				'url'   => get_permalink($post),   // 通知クリック時の遷移先 URL。
				'icon'  => $this->site_icon_url(),   // サイトアイコン URL（通知アイコン）。
			)
		);
	}

	/**
	 * テスト Push 通知を全購読者に送信する。
	 *
	 * 管理画面の「テスト通知を送信」ボタンから呼び出される。
	 *
	 * @return array{sent: int, failed: int}|WP_Error 成功時は送受信件数の配列、失敗時は WP_Error。
	 */
	public function send_test_notification()
	{
		return $this->send_to_all(
			array(
				'title' => $this->default_title(),
				'body'  => __('This is a test push notification from WordPress.', 'my-push-notification-plugin'),
				'url'   => home_url('/'),
				'icon'  => $this->site_icon_url(),
			)
		);
	}

	/**
	 * 全有効購読者に Push 通知を送信する。
	 *
	 * 処理の流れ:
	 *   1. ライブラリの存在確認
	 *   2. VAPID 鍵・サブジェクトの設定確認
	 *   3. 購読者一覧の取得
	 *   4. WebPush インスタンスを生成して各購読者にメッセージを送信
	 *   5. 期限切れ購読を自動的に無効化
	 *   6. 送信成功・失敗件数を返す
	 *
	 * ライブラリのバージョンによって sendOneNotification() の有無が異なるため、
	 * 存在する場合は即時送信、存在しない場合はキュー後に flush() で一括送信する。
	 *
	 * @param array{title?: string, body?: string, url?: string, icon?: string} $payload 通知内容。
	 * @return array{sent: int, failed: int}|WP_Error
	 */
	public function send_to_all(array $payload)
	{
		// ライブラリが未インストールの場合はエラーを返す。
		if (! $this->dependency_status()) {
			return new WP_Error(
				'my_push_missing_dependency',
				__('The minishlink/web-push Composer package is not installed.', 'my-push-notification-plugin')
			);
		}

		// VAPID 認証情報を取得する。
		$public_key  = trim((string) get_option(My_Push_Plugin::OPTION_PUBLIC_KEY, ''));
		$private_key = trim((string) get_option(My_Push_Plugin::OPTION_PRIVATE_KEY, ''));
		$subject     = trim((string) get_option(My_Push_Plugin::OPTION_SUBJECT, home_url('/')));

		// 必須設定が欠けている場合はエラーを返す。
		if ('' === $public_key || '' === $private_key || '' === $subject) {
			return new WP_Error(
				'my_push_missing_vapid',
				__('VAPID public key, private key, and subject are required.', 'my-push-notification-plugin')
			);
		}

		// 有効な購読者一覧を取得する。
		$subscribers = $this->repository->get_active_subscribers();

		// 購読者がいない場合は送信をスキップして 0 件を返す。
		if (empty($subscribers)) {
			return array(
				'sent'   => 0,
				'failed' => 0,
			);
		}

		// VAPID 認証付きの WebPush インスタンスを生成する。
		$web_push = new \Minishlink\WebPush\WebPush(
			array(
				'VAPID' => array(
					'subject'    => $subject,     // Push サービスが送信者識別に使う。
					'publicKey'  => $public_key,  // クライアントへの配布鍵。
					'privateKey' => $private_key, // 署名に使用するサーバー秘密鍵。
				),
			)
		);

		$sent   = 0;
		$failed = 0;

		// ペイロードを JSON 文字列に変換する。
		// Service Worker 側でこの JSON を parse して通知を表示する。
		$message = wp_json_encode(
			array(
				'title' => isset($payload['title']) ? (string) $payload['title'] : $this->default_title(),
				'body'  => isset($payload['body']) ? (string) $payload['body'] : '',
				'url'   => isset($payload['url']) ? esc_url_raw((string) $payload['url']) : home_url('/'),
				'icon'  => isset($payload['icon']) ? esc_url_raw((string) $payload['icon']) : '',
			)
		);

		// 各購読者に通知を送信する。
		foreach ($subscribers as $subscriber) {
			// Subscription オブジェクトを生成する。
			// aes128gcm は RFC 8291 で定義された最新の暗号化方式。
			$subscription = \Minishlink\WebPush\Subscription::create(
				array(
					'endpoint'        => $subscriber['endpoint'],
					'publicKey'       => $subscriber['public_key'],
					'authToken'       => $subscriber['auth_token'],
					'contentEncoding' => 'aes128gcm',
				)
			);

			if (method_exists($web_push, 'sendOneNotification')) {
				// 新しい API: 即時送信して結果を取得する。
				$report = $web_push->sendOneNotification($subscription, $message);

				if ($report->isSuccess()) {
					$sent++;
				} else {
					$failed++;

					// Push サービスから「購読が無効」と返ってきた場合は DB を無効化する。
					if ($report->isSubscriptionExpired()) {
						$this->repository->mark_inactive($subscriber['endpoint']);
					}
				}

				continue;
			}

			// 古い API: キューに追加して後で一括送信する。
			$web_push->queueNotification($subscription, $message);
		}

		// 古い API（sendOneNotification が存在しない場合）: flush() で一括送信する。
		if (! method_exists($web_push, 'sendOneNotification')) {
			foreach ($web_push->flush() as $report) {
				if ($report->isSuccess()) {
					$sent++;
				} else {
					$failed++;

					// 期限切れ購読を無効化する。
					// getRequest()->getUri() からエンドポイント URL を取得する。
					if ($report->isSubscriptionExpired()) {
						$this->repository->mark_inactive($report->getRequest()->getUri()->__toString());
					}
				}
			}
		}

		return array(
			'sent'   => $sent,
			'failed' => $failed,
		);
	}

	// ---- 内部ヘルパー ----------------------------------------------------------

	/**
	 * 通知のデフォルトタイトルを返す。
	 *
	 * 管理画面で設定された値を使用し、未設定の場合はサイト名にフォールバックする。
	 *
	 * @return string 通知タイトル文字列。
	 */
	private function default_title()
	{
		$title = trim((string) get_option(My_Push_Plugin::OPTION_TITLE, ''));

		// 未設定の場合はサイト名（ブログ名）を使用する。
		return '' !== $title ? $title : get_bloginfo('name');
	}

	/**
	 * サイトアイコンの URL を返す。
	 *
	 * 通知アイコンとして使用する。アイコンが設定されていない場合は空文字を返す。
	 * Service Worker 側でアイコン URL が空の場合はデフォルトアイコンを表示する。
	 *
	 * @return string サイトアイコン URL または空文字。
	 */
	private function site_icon_url()
	{
		$icon = get_site_icon_url();

		return $icon ? $icon : '';
	}
}
