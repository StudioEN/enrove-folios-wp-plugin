<?php
use Groove\Themes\Themes_Manager;

// Boot the theme registry before attempting to create a theme.
Themes_Manager::register_defaults();

$theme = Themes_Manager::create_theme_for_current_request();

?>
<?php if (!$theme): ?>
<!DOCTYPE html>
<html>

<head>
  <title>Theme not found</title>
</head>

<body>
  <h1>
    <?php echo esc_html__('Theme not found.', 'groove'); ?>
  </h1>
</body>

</html>
<?php return;
endif; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <?php wp_head(); ?>
</head>

<body <?php body_class('groove'); ?>>
  <?php $theme->display_theme(); ?>
  <?php wp_footer(); ?>
  <style media="screen">
    html {
      margin-top: 0px !important;
    }
  </style>
</body>

</html>