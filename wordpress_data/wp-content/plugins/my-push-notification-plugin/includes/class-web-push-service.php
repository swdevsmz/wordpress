<?php
/**
 * Web Push sender.
 *
 * @package My_Push_Notification_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Push_Web_Push_Service {
	/**
	 * Subscriber repository.
	 *
	 * @var My_Push_Subscriber_Repository
	 */
	private $repository;

	public function __construct( My_Push_Subscriber_Repository $repository ) {
		$this->repository = $repository;
	}

	public function dependency_status() {
		return class_exists( '\Minishlink\WebPush\WebPush' ) && class_exists( '\Minishlink\WebPush\Subscription' );
	}

	public function send_post_notification( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'my_push_missing_post', __( 'Post not found.', 'my-push-notification-plugin' ) );
		}

		return $this->send_to_all(
			array(
				'title' => $this->default_title(),
				'body'  => get_the_title( $post ),
				'url'   => get_permalink( $post ),
				'icon'  => $this->site_icon_url(),
			)
		);
	}

	public function send_test_notification() {
		return $this->send_to_all(
			array(
				'title' => $this->default_title(),
				'body'  => __( 'This is a test push notification from WordPress.', 'my-push-notification-plugin' ),
				'url'   => home_url( '/' ),
				'icon'  => $this->site_icon_url(),
			)
		);
	}

	public function send_to_all( array $payload ) {
		if ( ! $this->dependency_status() ) {
			return new WP_Error(
				'my_push_missing_dependency',
				__( 'The minishlink/web-push Composer package is not installed.', 'my-push-notification-plugin' )
			);
		}

		$public_key  = trim( (string) get_option( My_Push_Plugin::OPTION_PUBLIC_KEY, '' ) );
		$private_key = trim( (string) get_option( My_Push_Plugin::OPTION_PRIVATE_KEY, '' ) );
		$subject     = trim( (string) get_option( My_Push_Plugin::OPTION_SUBJECT, home_url( '/' ) ) );

		if ( '' === $public_key || '' === $private_key || '' === $subject ) {
			return new WP_Error(
				'my_push_missing_vapid',
				__( 'VAPID public key, private key, and subject are required.', 'my-push-notification-plugin' )
			);
		}

		$subscribers = $this->repository->get_active_subscribers();

		if ( empty( $subscribers ) ) {
			return array(
				'sent'   => 0,
				'failed' => 0,
			);
		}

		$web_push = new \Minishlink\WebPush\WebPush(
			array(
				'VAPID' => array(
					'subject'    => $subject,
					'publicKey'  => $public_key,
					'privateKey' => $private_key,
				),
			)
		);

		$sent    = 0;
		$failed  = 0;
		$message = wp_json_encode(
			array(
				'title' => isset( $payload['title'] ) ? (string) $payload['title'] : $this->default_title(),
				'body'  => isset( $payload['body'] ) ? (string) $payload['body'] : '',
				'url'   => isset( $payload['url'] ) ? esc_url_raw( (string) $payload['url'] ) : home_url( '/' ),
				'icon'  => isset( $payload['icon'] ) ? esc_url_raw( (string) $payload['icon'] ) : '',
			)
		);

		foreach ( $subscribers as $subscriber ) {
			$subscription = \Minishlink\WebPush\Subscription::create(
				array(
					'endpoint'        => $subscriber['endpoint'],
					'publicKey'       => $subscriber['public_key'],
					'authToken'       => $subscriber['auth_token'],
					'contentEncoding' => 'aes128gcm',
				)
			);

			if ( method_exists( $web_push, 'sendOneNotification' ) ) {
				$report = $web_push->sendOneNotification( $subscription, $message );

				if ( $report->isSuccess() ) {
					$sent++;
				} else {
					$failed++;
					if ( $report->isSubscriptionExpired() ) {
						$this->repository->mark_inactive( $subscriber['endpoint'] );
					}
				}

				continue;
			}

			$web_push->queueNotification( $subscription, $message );
		}

		if ( ! method_exists( $web_push, 'sendOneNotification' ) ) {
			foreach ( $web_push->flush() as $report ) {
				if ( $report->isSuccess() ) {
					$sent++;
				} else {
					$failed++;
					if ( $report->isSubscriptionExpired() ) {
						$this->repository->mark_inactive( $report->getRequest()->getUri()->__toString() );
					}
				}
			}
		}

		return array(
			'sent'   => $sent,
			'failed' => $failed,
		);
	}

	private function default_title() {
		$title = trim( (string) get_option( My_Push_Plugin::OPTION_TITLE, '' ) );

		return '' !== $title ? $title : get_bloginfo( 'name' );
	}

	private function site_icon_url() {
		$icon = get_site_icon_url();

		return $icon ? $icon : '';
	}
}
