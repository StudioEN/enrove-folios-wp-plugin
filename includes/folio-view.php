<?php
use Groove\Themes\Default_Themes;
use Groove\Themes\Theme_2;
use Groove\Themes\Theme_Data;
use Groove\Themes\Theme_1;
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
	$theme_id = isset($_REQUEST['theme_id']) ? $_REQUEST['theme_id'] : 'theme-1';

	if ($post_type !== 'groove_folio') {
		return '<h1>' . esc_html__( 'No matching template found' ) . '</h1>';
	}

	$folio = get_the_folio($folio_id)->post;
	$meta = get_post_meta($folio_id);
	$theme_id = $meta['theme_id'][0];
	$pages = get_the_folio_pages($folio_id) ->posts;

	$default_themes = new Default_Themes();
	$themes_data = $default_themes->get_themes();
	$current_theme_data = $themes_data[$theme_id];

	$data = new Theme_Data(
		$theme_id,
		$folio->post_title,
		$folio->subtitle,
		$pages,
		$current_theme_data['cover_url'],
		$current_theme_data['logo_url'],
		$current_theme_data['name'],
		get_the_author_meta('user_login', $folio->post_author)
	);

	if ($theme_id == 'theme-1') {
		$theme = new Theme_1($data);
		$theme->display_theme();
	} else {
		$theme = new Theme_2($data);
		$theme->display_theme();
	}

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
	<?php wp_head();  ?>
</head>

<body <?php body_class(); ?>>
<?php echo $template_html; ?>
</body>
</html>
