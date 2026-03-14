<?php
use Groove\Themes\Themes_Manager;

// NOTE: Themes_Manager::register_defaults() is already called in Plugin::__construct().
// Do NOT call it again here — it would double-register all themes and re-run migrations.

$theme = Themes_Manager::create_theme_for_current_request();

?>
<?php if (!$theme): ?>
<?php
status_header(404);
nocache_headers();
?>
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
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php wp_head(); ?>
</head>

<body <?php body_class('groove'); ?>>
  <?php
// ── DEBUG: remove once preview is confirmed working ──────────────────────
if (defined('WP_DEBUG') && WP_DEBUG) {
  echo '<pre style="position:fixed;top:0;left:0;z-index:99999;background:#000;color:#0f0;font-size:11px;padding:8px;max-width:400px;opacity:0.9;overflow:auto;max-height:50vh">';
  echo 'Theme class: ' . esc_html(get_class($theme)) . "\n";
  echo 'post_type:   ' . esc_html($theme->post_type ?? 'N/A') . "\n";
  echo 'id:          ' . esc_html($theme->id ?? 'N/A') . "\n";
  echo 'theme_id:    ' . esc_html($theme->theme_id ?? 'N/A') . "\n";
  echo '</pre>';
}
// ── END DEBUG ─────────────────────────────────────────────────────────────
?>
  <?php $theme->display_theme(); ?>
  <?php wp_footer(); ?>
  <style media="screen">
    html {
      margin-top: 0px !important;
    }
  </style>
</body>

</html>
