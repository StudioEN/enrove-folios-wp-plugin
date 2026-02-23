<?php
namespace Groove\Utils;

class Utils
{
  static function can_preview_unpublished_posts()
  {
    return is_admin() || (is_user_logged_in() && current_user_can('edit_posts'));
  }

  static function get_viewable_post_statuses()
  {
    if (Utils::can_preview_unpublished_posts()) {
      return array('publish', 'draft', 'private', 'pending');
    }

    return array('publish');
  }

  static function can_current_request_view_post($post)
  {
    if (!$post instanceof \WP_Post) {
      return false;
    }

    if ($post->post_status === 'publish') {
      return true;
    }

    if (is_admin()) {
      return true;
    }

    return is_user_logged_in() && current_user_can('edit_post', $post->ID);
  }

  static function get_folio_base_slug()
  {
    $slug = get_option('groove_folio_base_slug', 'folio');
    $slug = sanitize_title($slug);

    return $slug !== '' ? $slug : 'folio';
  }

  static function get_folio_id()
  {
    return Utils::get_groove_post_id();
  }

  static function get_folios_page_id()
  {
    return Utils::get_groove_post_id();
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

  static function get_folio_permalink_by_id($post_id)
  {
    if (is_admin()) {
      return Utils::get_folio_preview_query_url_by_id($post_id);
    }

    $post = get_post($post_id);
    $prefix = '';

    if ($post && isset($post->post_type) && $post->post_type === 'groove_folio_page') {
      $folio_id = get_post_meta($post_id, 'folio_id');
      $folio_post = get_post($folio_id[0]);
      if ($folio_post) {
        $prefix = Utils::get_post_slug($folio_post);
      }
    }

    return Utils::get_folio_permalink(get_post($post_id), $prefix);
  }

  static function get_folio_preview_query_url_by_id($post_id)
  {
    $post = get_post($post_id);
    if (!$post || !Utils::is_groove_post($post)) {
      return null;
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
      : preg_replace('/\s+/', '-', strtolower($post->post_title ?? ''));
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
    $home_path = parse_url(home_url(), PHP_URL_PATH) ?? '/';
    $home_path = rtrim($home_path, '/');

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $url_parts = parse_url($request_uri);
    $current_path = isset($url_parts['path']) ? $url_parts['path'] : '/';

    if ($home_path && strpos($current_path, $home_path) === 0) {
      $current_path = substr($current_path, strlen($home_path));
    }

    return $current_path;
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
      return isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : 'groove_folio';
    }
  }

  static function get_groove_post_id()
  {
    // Prefer explicit ID params over slug-based lookup so ?folio_id=N URLs
    // work reliably for drafts (which have no post_name in the DB).
    if (!empty($_GET['folio_id'])) {
      return (int) wp_unslash($_GET['folio_id']);
    }

    if (!empty($_GET['p'])) {
      return (int) wp_unslash($_GET['p']);
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



    $wp_query = new \WP_Query(array(
      'post_type' => $post_type,
      'name' => $post_name,
      'posts_per_page' => 1,
      'post_status' => Utils::get_viewable_post_statuses(),
    ));

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
        'post_status' => $fallback_statuses
      ));
      foreach ($fallback_query->posts as $p) {
        if (empty($p->post_name)) {
          $slug = preg_replace('/\s+/', '-', strtolower($p->post_title ?? ''));
          if ($slug === $post_name) {
            $post = $p;
            break;
          }
        }
      }
    }

    return $post;
  }
}
?>
