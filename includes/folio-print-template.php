<?php
if (!defined('ABSPATH')) {
  exit;
}

// Reads no request data: it is handed $print_folio, which Folio_Print checked.

$enrove_print_document = new \Enrove\Themes\Folio_Print($print_folio);

// As in folio-preview-template.php: WordPress found nothing at this path and has
// set a 404 already, and core's title tag would title the document after that.
// The title is also what a browser offers as the PDF's file name.
status_header(200);
$GLOBALS['wp_query']->is_404 = false;
remove_action('wp_head', '_wp_render_title_tag', 1);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="folio-scheme-light">

<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo esc_html($print_folio->post_title); ?></title>
  <?php wp_head(); ?>
</head>

<body <?php body_class('enrove g-folio-print'); ?>>
  <?php $enrove_print_document->display(); ?>
  <?php wp_footer(); ?>
</body>

</html>
