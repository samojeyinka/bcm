<?php

if ( ! function_exists( 'bcm_replace_ad_markers' ) ) {
function bcm_replace_ad_markers( string $html, int &$counter ): string {
	// ============== why the regex has two alternatives ==============
	// The <p>...</p> alternative swallows a paragraph that holds only [ad],
	// so the whole <p> is replaced and no <p><div> nesting is left behind.
	// The bare [ad] alternative still catches markers inside a sentence.
	$out = preg_replace_callback(
		'#<p>\s*\[ad\]\s*</p>|\[ad\]#',
		function ( array $m ) use ( &$counter ): string {
			$counter++;
			return '<div class="ad ad-' . $counter . '" data-ad-slot="' . $counter . '"></div>';
		},
		$html
	);
	return $out ?? $html;
}
}


// ============== read the query string ==============
// 'paged' below is the WP_Query argument name; 'page' is only the URL
// parameter we read here, so the two spellings deliberately differ.
$page   = isset( $_GET['page'] ) && is_scalar( $_GET['page'] ) ? max( 1, absint( $_GET['page'] ) ) : 1;
$search = isset( $_GET['s'] ) && is_scalar( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$q = new WP_Query( [
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => 10,
	'paged'               => $page,
	's'                   => $search,
	'ignore_sticky_posts' => true,
] );

$pages      = max( 1, (int) $q->max_num_pages );
$ad_counter = 0;
?>
<h1>
	Page <?= (int) $page ?> of <?= (int) $pages ?>
	<?php if ( $search !== '' ) : ?>: Search for &ldquo;<?= esc_html( $search ) ?>&rdquo;<?php endif; ?>
</h1>

<?php if ( ! $q->have_posts() ) : ?>
	<p>
		<?= $search !== ''
			? 'No results found for &ldquo;' . esc_html( $search ) . '&rdquo;.'
			: 'No posts found on this page.' ?>
	</p>
<?php endif; ?>

<?php
while ( $q->have_posts() ) {
	$q->the_post();
	$content = apply_filters( 'the_content', get_the_content() );
	$content = bcm_replace_ad_markers( $content, $ad_counter );
	?>
	<h2><?= esc_html( get_the_title() ) ?></h2>
	<div class="entry"><?= $content  ?></div>
	<?php
}
wp_reset_postdata();

$args = $search !== '' ? [ 's' => $search ] : [];
if ( $page > 1 ) : ?>
	<a rel="prev" href="<?= esc_url( '?' . http_build_query( $args + [ 'page' => $page - 1 ] ) ) ?>">Previous</a>
<?php endif;
if ( $page < $pages ) : ?>
	<a rel="next" href="<?= esc_url( '?' . http_build_query( $args + [ 'page' => $page + 1 ] ) ) ?>">Next</a>
<?php endif;
