<?php
/**
 * Article card template part.
 *
 * @package MyClassicTheme
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'article-card' ); ?>>
	<div class="article-card__terms">
		<?php
		$categories = get_the_category_list( '' );

		if ( $categories ) {
			echo wp_kses_post( $categories );
		}
		?>
	</div>

	<h2 class="article-card__title">
		<a href="<?php the_permalink(); ?>">
			<?php the_title(); ?>
		</a>
	</h2>

	<div class="article-card__meta">
		<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
			<?php echo esc_html( get_the_date() ); ?>
		</time>
	</div>

	<div class="article-card__excerpt">
		<?php the_excerpt(); ?>
		<a class="article-card__more" href="<?php the_permalink(); ?>">
			<?php esc_html_e( 'Read more', 'my-classic-theme' ); ?>
		</a>
	</div>
</article>
