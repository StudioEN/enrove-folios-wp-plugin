<?php
use Groove\Themes\Themes_Manager;

// NOTE: Themes_Manager::register_defaults() is already called in Plugin::__construct().
// Do NOT call it again here — it would double-register all themes and re-run migrations.

$theme = Themes_Manager::create_theme_for_current_request();

?>
<?php if (!$theme):
  $password_post = Themes_Manager::get_password_protected_post_for_current_request();
  if ($password_post):
    status_header(200);

    // Resolve theme accent color.
    $_pw_theme_id = (string) get_post_meta($password_post->ID, 'theme_id', true);
    $_pw_accent_map = array(
      'groove-proposal'   => array('#27498c', '#1a4173'),
      'groove-magazine'   => array('#2563EB', '#1d4ed8'),
      'groove-newsletter' => array('#6f3115', '#5a2710'),
      'groove-ebook'      => array('#1D35B4', '#162a90'),
      'folio-starter'     => array('#3858E9', '#2c47ba'),
    );
    $_pw_accent = isset($_pw_accent_map[$_pw_theme_id]) ? $_pw_accent_map[$_pw_theme_id][0] : '#2271b1';
    $_pw_accent_hover = isset($_pw_accent_map[$_pw_theme_id]) ? $_pw_accent_map[$_pw_theme_id][1] : '#135e96';

    // Resolve folio fonts.
    $_pw_font_keys = \Groove\Utils\Utils::get_folio_font_keys($password_post->ID);
    $_pw_header_font = \Groove\Utils\Utils::get_primary_font_data($_pw_font_keys['header']);
    $_pw_body_font = \Groove\Utils\Utils::get_primary_font_data($_pw_font_keys['body']);

    $_pw_heading_stack = $_pw_header_font ? $_pw_header_font['css_stack'] : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    $_pw_body_stack = $_pw_body_font ? $_pw_body_font['css_stack'] : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

    // Resolve theme background color.
    $_pw_bg_map = array(
      'groove-proposal'   => '#ededeb',
      'groove-magazine'   => '#FAFAFA',
      'groove-newsletter' => '#eee7db',
      'groove-ebook'      => '#1D35B4',
      'folio-starter'     => '#F0F6FC',
    );
    $_pw_bg = isset($_pw_bg_map[$_pw_theme_id]) ? $_pw_bg_map[$_pw_theme_id] : '#f0f0f1';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo esc_html($password_post->post_title); ?></title>
  <?php if ($_pw_header_font): ?>
    <link rel="stylesheet" href="<?php echo esc_url($_pw_header_font['google_url']); ?>" />
  <?php endif; ?>
  <?php if ($_pw_body_font && (!$_pw_header_font || $_pw_body_font['key'] !== $_pw_header_font['key'])): ?>
    <link rel="stylesheet" href="<?php echo esc_url($_pw_body_font['google_url']); ?>" />
  <?php endif; ?>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: <?php echo $_pw_body_stack; ?>;
      color: #1d2327;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; padding: 20px;
      background: <?php echo esc_attr($_pw_bg); ?>;
    }
    .groove-password-wrap {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0,0,0,.1);
      padding: 40px; max-width: 420px; width: 100%; text-align: left;
    }
    .groove-password-wrap h1 { font-family: <?php echo $_pw_heading_stack; ?>; font-size: 20px; font-weight: 600; margin-bottom: 24px; }
    .groove-password-wrap p { font-size: 14px; color: #646970; margin-bottom: 24px; }
    .groove-password-wrap label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
    .groove-password-wrap input[type="password"] { width: 100%; padding: 8px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; margin-bottom: 16px; }
    .groove-password-wrap input[type="password"]:focus { border-color: <?php echo esc_attr($_pw_accent); ?>; box-shadow: 0 0 0 1px <?php echo esc_attr($_pw_accent); ?>; outline: none; }
    .groove-password-wrap input[type="submit"] { display: block; width: 100%; background: <?php echo esc_attr($_pw_accent); ?>; color: #fff; border: none; border-radius: 4px; padding: 10px 24px; font-size: 14px; font-weight: 500; cursor: pointer; }
    .groove-password-wrap input[type="submit"]:hover { background: <?php echo esc_attr($_pw_accent_hover); ?>; }
  </style>
</head>
<body>
  <div class="groove-password-wrap">
    <h1><?php echo esc_html($password_post->post_title); ?></h1>
    <?php
      // Replace WordPress's default password form with cleaner copy.
      $_pw_post_id = $password_post->ID;
      $_pw_action_url = esc_url(site_url('wp-login.php?action=postpass', 'login_post'));
    ?>
    <form action="<?php echo $_pw_action_url; ?>" class="post-password-form" method="post">
      <p><?php echo esc_html__('Enter the password to view this folio.', 'groove'); ?></p>
      <label for="pwbox-<?php echo $_pw_post_id; ?>"><?php echo esc_html__('Password', 'groove'); ?></label>
      <input name="post_password" id="pwbox-<?php echo $_pw_post_id; ?>" type="password" spellcheck="false" />
      <input type="submit" name="Submit" value="<?php echo esc_attr__('Unlock', 'groove'); ?>" />
    </form>
  </div>
</body>
</html>
<?php return;
  endif; // $password_post
?>
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
  <?php $theme->display_theme(); ?>
  <?php wp_footer(); ?>
  <style media="screen">
    html {
      margin-top: 0px !important;
    }
  </style>
</body>

</html>
