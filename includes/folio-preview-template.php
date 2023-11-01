<?php
use Groove\Themes\Theme_1;
use Groove\Themes\Theme_2;
use Groove\Themes\Theme_Page_1;
use Groove\Themes\Theme_Page_2;
	function create_theme () {
    $post_type = isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : 'groove_folio';
    $folio_id = isset($_REQUEST['folio_id']) 
      ? $_REQUEST['folio_id'] 
      : '';

    $id = isset($_REQUEST['p']) 
      ? $_REQUEST['p'] 
      : '';

    if ($post_type === 'groove_folio_page') {
      $meta = get_post_meta($id);
      $folio_id = $meta['folio_id'][0];
    } else {
      $folio_id = $id;
    }

    $meta = get_post_meta($folio_id);
    $theme_id = $meta['theme_id'][0];

    
    if ($post_type == 'groove_folio') {      
      if ($theme_id == 'theme-1') {
        return new Theme_1();
      } else if ($theme_id == 'theme-2') {
        return new Theme_2();
      }
    } else if ($post_type == 'groove_folio_page') {
      
      if ($theme_id == 'theme-1') {
        return new Theme_Page_1();
      } else if ($theme_id == 'theme-2') {
        return new Theme_Page_2();
      }
    }
  }

	$theme = create_theme();
  
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php wp_head();  ?>
</head>

<body <?php body_class(); ?>>
	<?php $theme->display_theme(); ?>
	<?php wp_footer(); ?>
</body>
</html>
