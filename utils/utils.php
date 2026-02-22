<?php
namespace Groove\Utils;

class Utils
{
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

      if (Utils::is_groove_folio_post($post)) {
        return home_url('folio/' . ($prefix ? $prefix . '/' : '') . $title);
      }

      return home_url('folio/' . ($prefix ? $prefix . '/' : '') . (Utils::is_groove_folio_page_post($post) ? 'page/' : '') . $title);
    }

    return null;
  }

  static function get_current_path()
  {
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $current_path = $url_parts['path'];

    return $current_path;
  }

  static function is_groove_post_name_url()
  {
    $current_path = Utils::get_current_path();
    $result = preg_match('/^\/folio\//', $current_path);

    return $result;
  }

  static function get_groove_post_type()
  {
    $current_path = Utils::get_current_path();
    $pattern = '/^\/folio\/.+\/page\//';


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
      return isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : 'groove_folio';
    }
  }

  static function get_groove_post_id()
  {
    if (Utils::is_groove_post_name_url()) {
      $post = Utils::get_groove_post_by_post_type_and_post_name();
      return $post ? $post->ID : null;
    }

    // Use !empty() — a bare ?folio_id (no value) sets the key to '' which
    // would be cast to 0 and cause the lookup to silently fail.
    if (!empty($_REQUEST['folio_id'])) {
      return (int)$_REQUEST['folio_id'];
    }

    if (!empty($_REQUEST['p'])) {
      return (int)$_REQUEST['p'];
    }

    return null;
  }

  static function get_groove_post_name()
  {
    $current_path = Utils::get_current_path();
    $post_type = Utils::get_groove_post_type();

    if ($post_type == 'groove_folio') {
      $post_type = 'groove_folio';
      $substring = strstr($current_path, '/folio/');
      $post_name = substr($substring, strlen('/folio/'));
      return rtrim($post_name, '/');
    }
    else {
      $post_type = 'groove_folio_page';
      $substring = strstr($current_path, '/page/');
      $post_name = substr($substring, strlen('/page/'));

      return rtrim($post_name, '/');
    }
  }

  static function get_groove_post_by_post_type_and_post_name()
  {
    $post_type = Utils::get_groove_post_type();
    $post_name = Utils::get_groove_post_name();



    $wp_query = new \WP_Query(array(
      'post_type' => $post_type,
      'name' => $post_name,
      'posts_per_page' => 1,
      'post_status' => array('draft', 'publish', 'private', 'pending'),
    ));

    $post = $wp_query->post;

    // Fallback: Drafts generated before our fix may have an empty post_name in the DB.
    // WP_Query fails to find them by 'name'. We scan all drafts and match against our slugifier logic.
    if (!$post) {
      $fallback_query = new \WP_Query(array(
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => array('draft', 'pending'),
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