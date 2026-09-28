<?php
if (!defined('ABSPATH')) {
  exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Required from inside a closure (Plugin's template_redirect handler), so these variables are local to it, not globals.

use Groove\Themes\Font_Loader;
use Groove\Themes\Themes_Manager;

// NOTE: Themes_Manager::register_defaults() is already called in Plugin::__construct().
// Do NOT call it again here — it would double-register all themes and re-run migrations.

$theme = Themes_Manager::create_theme_for_current_request();

?>
<?php if (!$theme):
  $password_post = Themes_Manager::get_password_protected_post_for_current_request();
  if ($password_post):
    status_header(200);

    // Theme colours come from the theme, the way its fonts do. Two hardcoded
    // ID maps used to live here — plugin source, with no filter to join, so a
    // theme shipped as a package could never be in them. It was a documented
    // requirement no third-party theme could satisfy, and the contract check
    // for it failed permanently for every one of them. A theme declares a gate
    // block in setup.php now, and the values below are what a theme that
    // declares nothing falls back to.
    $_pw_theme_id = (string) get_post_meta($password_post->ID, 'theme_id', true);
    $_pw_gate = Themes_Manager::get_theme_gate_colors($_pw_theme_id);

    $_pw_accent = $_pw_gate['accent'] ?? '#2271b1';
    $_pw_accent_hover = $_pw_gate['accent_hover'] ?? '#135e96';

    // Resolve folio fonts through the same resolver the themes use, so the gate
    // is typeset like the folio behind it. This document is rendered before
    // wp_head(), so its stylesheets are printed on their own below.
    $_pw_system_stack = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    $_pw_fonts = Font_Loader::resolve(
      (int) $password_post->ID,
      Themes_Manager::get_theme_default_fonts($_pw_theme_id)
    );

    // The stacks are from the fixed font list or Font_Loader::sanitize_css_stack()
    // (letters, digits, space , ' - _ . only); the colours are hex, validated
    // when the theme's gate block was read.
    $_pw_heading_stack = $_pw_fonts['header']['css_stack'] ?? $_pw_system_stack;
    $_pw_body_stack = $_pw_fonts['body']['css_stack'] ?? $_pw_system_stack;

    $_pw_bg = $_pw_gate['background'] ?? '#f0f0f1';

    wp_register_style('groove-password-gate', false, [], GROOVE_VERSION);
    wp_add_inline_style('groove-password-gate', implode("\n", array(
      '* { box-sizing: border-box; margin: 0; padding: 0; }',
      'body { font-family: ' . $_pw_body_stack . '; color: #1d2327; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; background: ' . $_pw_bg . '; }',
      '.groove-password-wrap { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 40px; max-width: 420px; width: 100%; text-align: left; }',
      '.groove-password-wrap h1 { font-family: ' . $_pw_heading_stack . '; font-size: 20px; font-weight: 600; margin-bottom: 24px; }',
      '.groove-password-wrap p { font-size: 14px; color: #646970; margin-bottom: 24px; }',
      '.groove-password-wrap label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }',
      '.groove-password-wrap input[type="password"] { width: 100%; padding: 8px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; margin-bottom: 16px; }',
      '.groove-password-wrap input[type="password"]:focus { border-color: ' . $_pw_accent . '; box-shadow: 0 0 0 1px ' . $_pw_accent . '; outline: none; }',
      '.groove-password-wrap input[type="submit"] { display: block; width: 100%; background: ' . $_pw_accent . '; color: #fff; border: none; border-radius: 4px; padding: 10px 24px; font-size: 14px; font-weight: 500; cursor: pointer; }',
      '.groove-password-wrap input[type="submit"]:hover { background: ' . $_pw_accent_hover . '; }',
    )));
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo esc_html($password_post->post_title); ?></title>
  <?php wp_print_styles(array_merge(Font_Loader::enqueue_files($_pw_fonts), array('groove-password-gate'))); ?>
</head>
<body>
  <div class="groove-password-wrap">
    <h1><?php echo esc_html($password_post->post_title); ?></h1>
    <?php
      // Replace WordPress's default password form with cleaner copy.
      $_pw_post_id = (int) $password_post->ID;
      $_pw_action_url = site_url('wp-login.php?action=postpass', 'login_post');
    ?>
    <form action="<?php echo esc_url($_pw_action_url); ?>" class="post-password-form" method="post">
      <p><?php echo esc_html__('Enter the password to view this folio.', 'groove-folios'); ?></p>
      <label for="pwbox-<?php echo (int) $_pw_post_id; ?>"><?php echo esc_html__('Password', 'groove-folios'); ?></label>
      <input name="post_password" id="pwbox-<?php echo (int) $_pw_post_id; ?>" type="password" spellcheck="false" />
      <input type="submit" name="Submit" value="<?php echo esc_attr__('Unlock', 'groove-folios'); ?>" />
    </form>
  </div>
</body>
</html>
<?php return;
  endif; // $password_post
?>
<?php
// Work out *why* there is no theme. "Theme not found" was reported for every
// failure here, including the common one: an unpublished folio opened without a
// session that may see it. The theme is fine in that case, so the message sent
// people looking for a broken theme instead of an unpublished folio.
$_gv_post = null;
$_gv_id = \Groove\Utils\Utils::get_groove_post_id();
if ($_gv_id) {
  $_gv_post = get_post($_gv_id);
}

if (!$_gv_post || !\Groove\Utils\Utils::is_groove_post($_gv_post)) {
  // Nothing here at all: a bad link, or the folio was deleted.
  $_gv_status = 404;
  $_gv_title = __('Folio not found', 'groove-folios');
  $_gv_message = __('This folio no longer exists, or the link is wrong.', 'groove-folios');
}
elseif (!\Groove\Utils\Utils::can_current_request_view_post($_gv_post)) {
  // It exists but this visitor may not see it — in practice, a draft folio.
  // 404 rather than 403 so an unpublished folio's existence stays private.
  $_gv_status = 404;
  $_gv_title = __('Not published yet', 'groove-folios');
  $_gv_message = is_user_logged_in()
    ? __('This folio is not published yet, and your account cannot preview it.', 'groove-folios')
    : __('This folio is not published yet. Sign in to preview it, or publish it to share the link.', 'groove-folios');
}
else {
  // Genuinely no usable theme: the folio names a theme_id nothing on this site
  // answers to, or no theme is registered at all. A folio with no theme_id is
  // not in this branch — that one defaults, in resolve_registered_theme_id().
  //
  // This used to be unreachable. create_cover_theme()/create_page_theme() fell
  // back to the first registered theme for an unrecognised ID as readily as for
  // an empty one, so a folio whose theme failed to load rendered in a different
  // theme at HTTP 200 and this notice never ran.
  $_gv_status = 404;
  $_gv_title = __('Theme not found', 'groove-folios');
  $_gv_message = __('This folio points at a theme that is not installed.', 'groove-folios');
}

status_header($_gv_status);
nocache_headers();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo esc_html($_gv_title); ?></title>
  <?php
  // A bare document with no wp_head(), so the one stylesheet it needs is
  // printed on its own.
  wp_register_style('groove-folio-notice', false, [], GROOVE_VERSION);
  wp_add_inline_style(
    'groove-folio-notice',
    "body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0f0f1; color: #1d2327; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; -webkit-font-smoothing: antialiased; }"
    . ' .groove-notice { max-width: 26rem; padding: 2rem; text-align: center; }'
    . ' .groove-notice h1 { margin: 0 0 0.5rem; font-size: 1.125rem; font-weight: 600; }'
    . ' .groove-notice p { margin: 0; font-size: 0.9375rem; line-height: 1.6; color: #50575e; }'
  );
  wp_print_styles('groove-folio-notice');
  ?>
</head>

<body>
  <div class="groove-notice">
    <h1><?php echo esc_html($_gv_title); ?></h1>
    <p><?php echo esc_html($_gv_message); ?></p>
  </div>
</body>

</html>
<?php return;
endif;

// A folio this request may see. WordPress's own main query found nothing at this
// path (there are no rewrite rules, by design) and has already set a 404, which
// every published folio was served with until this line: search engines drop a
// 404 and link previews refuse it. The page is real, so say so.
status_header(200);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php wp_head(); ?>
</head>

<body <?php body_class('groove'); ?>>
  <?php $theme->display_theme(); ?>
  <?php wp_footer(); ?>
</body>

</html>
