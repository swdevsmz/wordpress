<?php
/**
 * My Block Theme functions.
 *
 * @package MyBlockTheme
 */

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'my-block-theme-style',
			get_stylesheet_uri(),
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
);
