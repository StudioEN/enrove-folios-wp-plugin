<?php
namespace Groove\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Utils
{
  /**
   * Public query vars the front end routes on (registered in Plugin): a
   * folio by ID for drafts and previews, and the theme picker's preview.
   * WordPress parses them, so routing reads get_query_var(), never $_GET.
   */
  const ROUTE_QUERY_VARS = array('folio_id', 'groove_preview', 'groove_theme_preview');

  static function can_preview_unpublished_posts()
  {
    if (is_admin() || (is_user_logged_in() && current_user_can('edit_posts'))) {
      return true;
    }

    // Guard against infinite recursion: get_groove_post_id() may call
    // get_groove_post_by_post_type_and_post_name() which calls
    // get_viewable_post_statuses() which calls this method again.
    static $checking = false;
    if ($checking) {
      return false;
    }
    $checking = true;

    // Allow viewing unpublished posts when the folio's password has been
    // correctly entered (cookie set). Resolve the folio post from the
    // current request — if it's a folio page, check the parent folio.
    $result = false;
    $id = self::get_groove_post_id();
    if ($id) {
      $post = get_post($id);
      if ($post) {
        $folio_post = $post;
        if ($post->post_type === 'groove_folio_page') {
          $folio_id = (int) get_post_meta($id, 'folio_id', true);
          $folio_post = $folio_id ? get_post($folio_id) : null;
        }
        if ($folio_post && !empty($folio_post->post_password) && !post_password_required($folio_post)) {
          $result = true;
        }
      }
    }

    $checking = false;
    return $result;
  }

  static function get_viewable_post_statuses()
  {
    if (Utils::can_preview_unpublished_posts()) {
      return array('publish', 'draft', 'private', 'pending');
    }

    return array('publish');
  }

  /**
   * The statuses of the pages a folio lists: its navigation, previous and
   * next, and the first page. A published folio lists only its published
   * pages (and, to someone who may read them, private ones), whoever is
   * looking: a page taken down drops out of the folio for its editors too.
   * An unpublished folio is a preview, so a signed-in editor sees every page
   * in it, drafts included, as before.
   *
   * @param int $folio_id
   * @return string[]
   */
  static function get_listed_page_statuses($folio_id)
  {
    if (!\Groove\Contents\FolioPage\Publishing::is_folio_live($folio_id)) {
      return Utils::get_viewable_post_statuses();
    }

    return current_user_can('read_private_posts') ? array('publish', 'private') : array('publish');
  }

  static function can_current_request_view_post($post)
  {
    if (!$post instanceof \WP_Post) {
      return false;
    }

    // Logged-in users who can edit the post bypass password protection.
    if (is_user_logged_in() && current_user_can('edit_post', $post->ID)) {
      return true;
    }

    // Password-protected posts require the visitor to enter the password first.
    if (post_password_required($post)) {
      return false;
    }

    // If the post has a password and the visitor has entered it correctly,
    // allow access regardless of post status (draft, pending, etc.).
    if (!empty($post->post_password)) {
      return true;
    }

    // Folio pages inherit password protection from the parent folio.
    // If the parent folio's password has been entered, allow the page.
    if ($post->post_type === 'groove_folio_page') {
      $folio_id = (int) get_post_meta($post->ID, 'folio_id', true);
      if ($folio_id) {
        $folio_post = get_post($folio_id);
        if ($folio_post && !empty($folio_post->post_password) && !post_password_required($folio_post)) {
          return true;
        }
      }
    }

    if ($post->post_status === 'publish') {
      return true;
    }

    if (is_admin()) {
      return true;
    }

    return false;
  }

  static function get_folio_base_slug()
  {
    $slug = get_option('groove_folio_base_slug', 'folio');
    $slug = sanitize_title($slug);

    return $slug !== '' ? $slug : 'folio';
  }

  /**
   * The fixed list of typefaces a folio can choose from, per role.
   *
   * `google_family` is a `family=` fragment for the css2 API; themes declare
   * their own defaults in the same shape. Everything is requested and injected
   * by Groove\Themes\Font_Loader — never build a font URL anywhere else.
   *
   * @return array
   */
  static function get_supported_primary_fonts()
  {
    return array(
      'dm-sans' => array(
        'label' => 'DM Sans',
        'css_stack' => "'DM Sans', sans-serif",
        'google_family' => 'DM+Sans:wght@400;700',
      ),
      'inter' => array(
        'label' => 'Inter',
        'css_stack' => "'Inter', sans-serif",
        'google_family' => 'Inter:wght@400;700',
      ),
      'lato' => array(
        'label' => 'Lato',
        'css_stack' => "'Lato', sans-serif",
        'google_family' => 'Lato:wght@400;700',
      ),
      'merriweather' => array(
        'label' => 'Merriweather',
        'css_stack' => "'Merriweather', serif",
        'google_family' => 'Merriweather:wght@400;700',
      ),
      'montserrat' => array(
        'label' => 'Montserrat',
        'css_stack' => "'Montserrat', sans-serif",
        'google_family' => 'Montserrat:wght@400;700',
      ),
      'noto-sans' => array(
        'label' => 'Noto Sans',
        'css_stack' => "'Noto Sans', sans-serif",
        'google_family' => 'Noto+Sans:wght@400;700',
      ),
      'noto-serif' => array(
        'label' => 'Noto Serif',
        'css_stack' => "'Noto Serif', serif",
        'google_family' => 'Noto+Serif:wght@400;700',
      ),
      'nunito-sans' => array(
        'label' => 'Nunito Sans',
        'css_stack' => "'Nunito Sans', sans-serif",
        'google_family' => 'Nunito+Sans:wght@400;700',
      ),
      'poppins' => array(
        'label' => 'Poppins',
        'css_stack' => "'Poppins', sans-serif",
        'google_family' => 'Poppins:wght@400;700',
      ),
      'roboto' => array(
        'label' => 'Roboto',
        'css_stack' => "'Roboto', sans-serif",
        'google_family' => 'Roboto:wght@400;700',
      ),
    );
  }

  static function normalize_primary_font_key($font_key)
  {
    $font_key = is_string($font_key) ? sanitize_key($font_key) : '';
    $supported_fonts = Utils::get_supported_primary_fonts();

    return isset($supported_fonts[$font_key]) ? $font_key : '';
  }

  static function get_primary_font_data($font_key)
  {
    $font_key = Utils::normalize_primary_font_key($font_key);
    if ($font_key === '') {
      return null;
    }

    $supported_fonts = Utils::get_supported_primary_fonts();
    $font = $supported_fonts[$font_key];
    $font['key'] = $font_key;

    return $font;
  }

  static function get_folio_font_keys($folio_id)
  {
    $folio_id = (int) $folio_id;
    if ($folio_id <= 0) {
      return array(
        'header' => '',
        'body' => '',
      );
    }

    $legacy_font_key = Utils::normalize_primary_font_key((string) get_post_meta($folio_id, 'fonts', true));
    $header_font_key = Utils::normalize_primary_font_key((string) get_post_meta($folio_id, 'header_font', true));
    $body_font_key = Utils::normalize_primary_font_key((string) get_post_meta($folio_id, 'body_font', true));

    if ($header_font_key === '' && $legacy_font_key !== '') {
      $header_font_key = $legacy_font_key;
    }

    if ($body_font_key === '' && $legacy_font_key !== '') {
      $body_font_key = $legacy_font_key;
    }

    return array(
      'header' => $header_font_key,
      'body' => $body_font_key,
    );
  }

  static function sanitize_on_this_page_label($label)
  {
    if (!is_string($label)) {
      return '';
    }

    return trim(sanitize_text_field($label));
  }

  static function get_default_on_this_page_label()
  {
    return __('On this page', 'groove-folios');
  }

  static function get_folio_on_this_page_label($folio_id)
  {
    $folio_id = (int) $folio_id;
    if ($folio_id <= 0) {
      return Utils::get_default_on_this_page_label();
    }

    $label = get_post_meta($folio_id, 'on_this_page_label', true);
    $label = Utils::sanitize_on_this_page_label($label);

    return $label !== '' ? $label : Utils::get_default_on_this_page_label();
  }

  static function get_newsletter_theme_color_presets()
  {
    return array(
      'coastal-slate' => array(
        'label' => __('Coastal Slate', 'groove-folios'),
        'seed' => '#2E5F7B',
      ),
      'evergreen-ink' => array(
        'label' => __('Evergreen Ink', 'groove-folios'),
        'seed' => '#2C6650',
      ),
      'clay-signal' => array(
        'label' => __('Clay Signal', 'groove-folios'),
        'seed' => '#A3553D',
      ),
      'berry-graphite' => array(
        'label' => __('Berry Graphite', 'groove-folios'),
        'seed' => '#6E4969',
      ),
      'deep-ultramarine' => array(
        'label' => __('Deep Ultramarine', 'groove-folios'),
        'seed' => '#355E9D',
      ),
    );
  }

  static function get_default_newsletter_theme_color_preset()
  {
    $presets = Utils::get_newsletter_theme_color_presets();
    $default = (string) array_key_first($presets);

    return $default !== '' ? $default : 'coastal-slate';
  }

  static function sanitize_newsletter_theme_color_preset($preset_key)
  {
    $preset_key = is_string($preset_key) ? sanitize_key($preset_key) : '';
    $presets = Utils::get_newsletter_theme_color_presets();

    if (!isset($presets[$preset_key])) {
      return Utils::get_default_newsletter_theme_color_preset();
    }

    return $preset_key;
  }

  static function get_folio_newsletter_theme_color_preset($folio_id)
  {
    $folio_id = (int) $folio_id;
    if ($folio_id <= 0) {
      return Utils::get_default_newsletter_theme_color_preset();
    }

    $saved = (string) get_post_meta($folio_id, 'newsletter_theme_preset', true);
    return Utils::sanitize_newsletter_theme_color_preset($saved);
  }

  static function get_folio_id()
  {
    return Utils::get_groove_post_id();
  }

  static function get_folios_page_id()
  {
    return Utils::get_groove_post_id();
  }

  static function is_folio_cover_enabled($folio_id)
  {
    $folio_id = (int) $folio_id;
    if ($folio_id <= 0) {
      return true;
    }

    $folio = get_post($folio_id);
    if (!$folio || !Utils::is_groove_folio_post($folio)) {
      return true;
    }

    $use_folio = get_post_meta($folio_id, 'use_folio', true);
    if ($use_folio === '') {
      // Backward-compat: existing folios default to using the cover.
      return true;
    }

    return in_array((string) $use_folio, array('1', 'on', 'true', 'yes'), true);
  }

  static function get_first_folio_page_id($folio_id)
  {
    $folio_id = (int) $folio_id;
    if ($folio_id <= 0) {
      return 0;
    }

    $query = new \WP_Query(array(
      'post_type' => 'groove_folio_page',
      'posts_per_page' => 1,
      'post_status' => Utils::get_listed_page_statuses($folio_id),
      'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- folio_id meta is the page-to-folio link; the query is limited to one folio's pages.
        array(
          'key' => 'folio_id',
          'value' => $folio_id,
          'compare' => '=',
          'type' => 'NUMERIC',
        ),
      ),
      'orderby' => array(
        'menu_order' => 'ASC',
        'ID' => 'ASC',
      ),
      'ignore_sticky_posts' => true,
      'no_found_rows' => true,
    ));

    if (!empty($query->posts) && !empty($query->posts[0]->ID)) {
      return (int) $query->posts[0]->ID;
    }

    return 0;
  }

  static function is_groove_folio_post($post)
  {
    return isset($post->post_type) && $post->post_type == 'groove_folio';
  }

  static function is_groove_folio_page_post($post)
  {
    return isset($post->post_type) && $post->post_type == 'groove_folio_page';
  }

  static function is_groove_post($post)
  {
    return Utils::is_groove_folio_post($post) || Utils::is_groove_folio_page_post($post);
  }

  /**
   * Public URL of a folio or folio page, or '' when there is none.
   *
   * '' rather than null: nearly every caller hands the result straight to
   * esc_url(), and theme previews render sample pages that have no post
   * behind them, so null reached ltrim() inside esc_url() and raised a PHP 8.1
   * deprecation on every preview. Callers only ever test the result for truth.
   *
   * @param int $post_id
   * @return string
   */
  static function get_folio_permalink_by_id($post_id)
  {
    if (is_admin()) {
      return Utils::get_folio_preview_query_url_by_id($post_id);
    }

    $post = get_post($post_id);
    if (!$post) {
      return '';
    }

    // Resolve the parent folio for folio pages.
    $folio_post = $post;
    if ($post->post_type === 'groove_folio_page') {
      $folio_id = get_post_meta($post_id, 'folio_id');
      $folio_post = !empty($folio_id) ? get_post($folio_id[0]) : null;
    }

    // Non-published folios use query-param URLs because slug resolution
    // doesn't work reliably for drafts.
    if (!$folio_post || $folio_post->post_status !== 'publish') {
      return Utils::get_folio_preview_query_url_by_id($post_id);
    }

    $prefix = '';
    if ($post->post_type === 'groove_folio_page' && $folio_post) {
      $prefix = Utils::get_post_slug($folio_post);
    }

    return Utils::get_folio_permalink($post, $prefix);
  }

  static function get_folio_preview_query_url_by_id($post_id)
  {
    $post = get_post($post_id);
    if (!$post || !Utils::is_groove_post($post)) {
      return '';
    }

    $query_args = array(
      'groove_preview' => 1,
    );

    if (Utils::is_groove_folio_post($post)) {
      $query_args['folio_id'] = $post->ID;
    } else {
      $query_args['p'] = $post->ID;
      $query_args['post_type'] = 'groove_folio_page';
    }

    return add_query_arg($query_args, home_url('/'));
  }

  static function get_post_slug($post)
  {
    if (!$post)
      return '';
    return $post->post_name
      ? $post->post_name
      : sanitize_title((string) ($post->post_title ?? ''));
  }

  static function get_folio_permalink($post, $prefix)
  {
    if (Utils::is_groove_post($post)) {
      $title = Utils::get_post_slug($post);
      $base_slug = Utils::get_folio_base_slug();
      $pretty_permalinks_enabled = (bool) get_option('permalink_structure');

      if (!$pretty_permalinks_enabled) {
        $query_args = array(
          'groove_preview' => 1,
        );

        if (Utils::is_groove_folio_post($post)) {
          $query_args['folio_id'] = $post->ID;
        } else {
          $query_args['p'] = $post->ID;
          $query_args['post_type'] = 'groove_folio_page';
        }

        return add_query_arg($query_args, home_url('/'));
      }

      if (Utils::is_groove_folio_post($post)) {
        return home_url($base_slug . '/' . ($prefix ? $prefix . '/' : '') . $title);
      }

      return home_url($base_slug . '/' . ($prefix ? $prefix . '/' : '') . (Utils::is_groove_folio_page_post($post) ? 'page/' : '') . $title);
    }

    return null;
  }

  static function get_current_path()
  {
    $home_path = wp_parse_url(home_url(), PHP_URL_PATH) ?? '/';
    $home_path = rtrim($home_path, '/');

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the path is taken from it, and only to regex-match against the folio base slug; it is never output or stored, and sanitize_text_field() would strip %-encoded octets from the path.
    $url_parts = wp_parse_url($request_uri);
    $current_path = isset($url_parts['path']) ? $url_parts['path'] : '/';

    if ($home_path && strpos($current_path, $home_path) === 0) {
      $current_path = substr($current_path, strlen($home_path));
    }

    return $current_path;
  }

  static function get_folio_slug_from_current_path()
  {
    $current_path = Utils::get_current_path();
    $base_slug = Utils::get_folio_base_slug();
    $pattern = '#^/' . preg_quote($base_slug, '#') . '/([^/]+)(?:/page/.*)?/?$#';

    if (!preg_match($pattern, $current_path, $matches)) {
      return '';
    }

    return isset($matches[1]) ? rtrim((string) $matches[1], '/') : '';
  }

  static function get_folio_id_from_current_path()
  {
    $folio_slug = Utils::get_folio_slug_from_current_path();
    if ($folio_slug === '') {
      return 0;
    }

    $folio_post = Utils::get_groove_post_by_post_type_and_post_name('groove_folio', $folio_slug);
    return $folio_post ? (int) $folio_post->ID : 0;
  }

  static function is_groove_post_name_url()
  {
    $current_path = Utils::get_current_path();
    $base_slug = Utils::get_folio_base_slug();
    $result = preg_match('#^/' . preg_quote($base_slug, '#') . '/#', $current_path);

    return $result;
  }

  static function get_groove_post_type()
  {
    $current_path = Utils::get_current_path();
    $base_slug = Utils::get_folio_base_slug();
    $pattern = '#^/' . preg_quote($base_slug, '#') . '/.+/page/#';


    if (Utils::is_groove_post_name_url()) {
      $result = preg_match($pattern, $current_path);

      if ($result) {
        return 'groove_folio_page';
      }
      else {
        return 'groove_folio';
      }
    }
    else {
      $post_type = get_query_var('post_type');
      return is_string($post_type) && $post_type !== '' ? sanitize_key($post_type) : 'groove_folio';
    }
  }

  static function get_groove_post_id()
  {
    // Prefer explicit ID params over slug-based lookup so ?folio_id=N URLs
    // work reliably for drafts (which have no post_name in the DB).
    $query_folio_id = absint(get_query_var('folio_id'));
    if ($query_folio_id > 0) {
      return $query_folio_id;
    }

    $query_post_id = absint(get_query_var('p'));
    if ($query_post_id > 0) {
      return $query_post_id;
    }

    if (Utils::is_groove_post_name_url()) {
      $post = Utils::get_groove_post_by_post_type_and_post_name();
      return $post ? $post->ID : null;
    }

    return null;
  }

  static function get_groove_post_name()
  {
    $current_path = Utils::get_current_path();
    $post_type = Utils::get_groove_post_type();
    $base_slug = Utils::get_folio_base_slug();
    $base_prefix = '/' . $base_slug . '/';

    if ($post_type == 'groove_folio') {
      $post_type = 'groove_folio';
      $substring = strstr($current_path, $base_prefix);
      $post_name = substr($substring, strlen($base_prefix));
      return rtrim($post_name, '/');
    }
    else {
      $post_type = 'groove_folio_page';
      $substring = strstr($current_path, '/page/');
      $post_name = substr($substring, strlen('/page/'));

      return rtrim($post_name, '/');
    }
  }

  static function get_groove_post_by_post_type_and_post_name($post_type = null, $post_name = null)
  {
    if (empty($post_type)) {
      $post_type = Utils::get_groove_post_type();
    }

    if (empty($post_name)) {
      $post_name = Utils::get_groove_post_name();
    }
    $query_args = array(
      'post_type' => $post_type,
      'name' => $post_name,
      'posts_per_page' => 1,
      'post_status' => Utils::get_viewable_post_statuses(),
    );

    $scoped_folio_id = 0;
    if ($post_type === 'groove_folio_page') {
      $scoped_folio_id = Utils::get_folio_id_from_current_path();
      if ($scoped_folio_id > 0) {
        $query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- folio_id meta is the page-to-folio link; the query is limited to one folio's pages.
          array(
            'key' => 'folio_id',
            'value' => $scoped_folio_id,
            'compare' => '=',
            'type' => 'NUMERIC',
          ),
        );
      }
    }

    $wp_query = new \WP_Query($query_args);

    $post = $wp_query->post;

    // Fallback: Drafts don't have a 'post_name' saved in the DB, so WP_Query fails.
    // We synthesize the slug from the title in get_post_slug(), so we must reverse
    // that check here to resolve the draft.
    if (!$post && $post_name) {
      $allowed_statuses = Utils::get_viewable_post_statuses();
      $fallback_statuses = array_values(array_diff($allowed_statuses, array('publish')));
      if (empty($fallback_statuses)) {
        return null;
      }

      $fallback_query = new \WP_Query(array(
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => $fallback_statuses,
        'meta_query' => $scoped_folio_id > 0 // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- folio_id meta is the page-to-folio link; the query is limited to one folio's pages.
          ? array(
            array(
              'key' => 'folio_id',
              'value' => $scoped_folio_id,
              'compare' => '=',
              'type' => 'NUMERIC',
            ),
          )
          : array(),
      ));
      foreach ($fallback_query->posts as $p) {
        $slug = Utils::get_post_slug($p);
        if ($slug === $post_name) {
          $post = $p;
          break;
        }
      }
    }

    return $post;
  }
}
?>
