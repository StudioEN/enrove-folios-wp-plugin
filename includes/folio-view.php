<?php
function get_the_wp_query ($args) {
	$wp_query = new \WP_Query($args);
	return $wp_query;
}


function get_the_folio ($id) {
	$args = array(
		'post__in' => array($id),
		'post_status' => array('publish', 'draft', 'pending'),
		'post_type' => 'groove_folio',
	);
	
	$q = get_the_wp_query($args);
	return $q;
}

function get_the_folio_pages ($id) {
	$args = array(
		'post_type' => 'groove_folio_page',
		'post_status' => array('publish', 'draft', 'pending'),
		'meta_query' => array(
			array(
				'key' => 'folio_id',
				'value' => $id,
				'compare' => '=',
				'type' => 'NUMERIC'
			)
		)
	);
	
	$q = get_the_wp_query($args);
	return $q;
}

function get_the_block_template () {
	global $wp_embed;
	$post_type = isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : '';
	$folio_id = isset($_REQUEST['p']) ? $_REQUEST['p'] : '';

	if ($post_type !== 'groove_folio') {
		return '<h1>' . esc_html__( 'No matching template found' ) . '</h1>';
	}

	$folio = get_the_folio($folio_id)->post;
	$pages = get_the_folio_pages($folio_id) ->posts;

	$html = '<h1>'. $folio->post_title .'</h1>';

	
	foreach ($pages as $page) {
		$content = $page->post_content;
		$content = $wp_embed->autoembed( $content );
		$content = shortcode_unautop( $content );
		$content = do_blocks( $content );
		$content = wptexturize( $content );
		$content = convert_smilies( $content );
		$content = wp_filter_content_tags( $content, 'template' );
		$content = str_replace( ']]>', ']]&gt;', $content );
		$html = $html . '<div class="g-folio__blocks" >'. $content .'</div>';
	}
	
	return '<div class="g-folio__site" style="width: 650px;margin: auto">'. $html .'</div>';
}
/**
 * Template canvas file to render the current 'wp_template'.
 *
 * @package WordPress
 */

/*
 * Get the template HTML.
 * This needs to run before <head> so that blocks can add scripts and styles in wp_head().
 */
$template_html = get_the_block_template();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php echo $template_html; ?>
</body>
</html>
