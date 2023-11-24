<?php
use Groove\Themes\Theme_1;
use Groove\Themes\Theme_2;
use Groove\Themes\Theme_Page_1;
use Groove\Themes\Theme_Page_2;
use Groove\Utils\Utils;

	function create_theme () {
    $id = Utils::get_groove_post_id();
    $post_type = Utils::get_groove_post_type();

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
      
      $post_password_required = post_password_required( $folio_id );

      if ($post_password_required) {
        wp_redirect(Utils::get_folio_permalink_by_id($folio_id), 301);
        exit;
      }

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

<body <?php body_class('groove'); ?>>
	<?php $theme->display_theme(); ?>
	<?php wp_footer(); ?>
  <style media="screen">html { margin-top: 0px !important;}</style>
</body>
</html>
