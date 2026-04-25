<?php
/**
 * Header template.
 *
 * @package MyClassicTheme
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-shell">
	<header class="site-header">
		<div class="site-header__inner">
			<p class="site-branding">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php bloginfo( 'name' ); ?>
				</a>
			</p>

			<nav class="site-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'my-classic-theme' ); ?>">
				<?php
				if ( has_nav_menu( 'primary' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'primary',
							'container'      => false,
							'depth'          => 1,
						)
					);
				} else {
					wp_page_menu(
						array(
							'menu_class' => '',
							'depth'      => 1,
						)
					);
				}
				?>
			</nav>
		</div>
	</header>
