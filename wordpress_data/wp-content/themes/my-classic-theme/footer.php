<?php
/**
 * Footer template.
 *
 * @package MyClassicTheme
 */

?>
	<footer class="site-footer">
		<div class="site-footer__inner">
			<p class="site-footer__note">
				<?php esc_html_e( 'Built with My Classic Theme.', 'my-classic-theme' ); ?>
			</p>
			<p class="site-footer__note">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			</p>
		</div>
	</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
