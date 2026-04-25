<?php
/**
 * Main index template.
 *
 * @package MyClassicTheme
 */

get_header();
?>

<main id="primary" class="site-main">
	<section class="index-hero" aria-labelledby="recent-posts-title">
		<p class="index-hero__label"><?php esc_html_e( 'Journal', 'my-classic-theme' ); ?></p>
		<h1 id="recent-posts-title" class="index-hero__title"><?php esc_html_e( '最近の記事', 'my-classic-theme' ); ?></h1>
		<p class="index-hero__lead"><?php esc_html_e( '更新情報、読みもの、制作メモを落ち着いて読める一覧です。', 'my-classic-theme' ); ?></p>
	</section>

	<?php if ( have_posts() ) : ?>
		<section class="article-list" aria-label="<?php esc_attr_e( 'Article list', 'my-classic-theme' ); ?>">
			<?php
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content', 'card' );
			endwhile;
			?>
		</section>

		<?php
		the_posts_pagination(
			array(
				'class'     => 'pagination',
				'mid_size'  => 1,
				'prev_text' => __( 'Newer', 'my-classic-theme' ),
				'next_text' => __( 'Older', 'my-classic-theme' ),
			)
		);
		?>
	<?php else : ?>
		<p class="no-posts"><?php esc_html_e( 'No posts are available yet.', 'my-classic-theme' ); ?></p>
	<?php endif; ?>
</main>

<?php
get_footer();
