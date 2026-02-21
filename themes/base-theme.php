<?php
namespace Groove\Themes;

use Groove\Modules\Assets;
use Groove\Utils\Utils;

abstract class Base_Theme extends Assets
{
  public $id;
  public $post_type;
  public $title;
  public $content;
  public $feature_image;
  public $author;
  public $page;
  public $pages;
  public $theme_id;
  public $theme_name;
  public $theme_cover_url;
  public $theme_logo_url;

  public function __construct()
  {
    $this->post_type = Utils::get_groove_post_type();
    $this->id = Utils::get_groove_post_id();

    add_action('wp_enqueue_scripts', [$this, 'ensure_script']);
  }

  public function ensure_script()
  {
    echo '<script>window.GROOVE_IS_PREVIEW = true</script>';
    show_admin_bar(false);

    wp_enqueue_style('groove', $this->get_css_assets_url('groove-main', null, 'default', true), [], GROOVE_VERSION);
    wp_enqueue_script('groove', $this->get_js_assets_url('groove-main'), ['jquery'], GROOVE_VERSION, true);
  }

  // -----------------------------------------------------------------------
  // Theme identity — subclasses define a human name and asset filenames.
  // The ID is derived automatically; no manual string needed anywhere.
  // -----------------------------------------------------------------------

  /**
   * Human-readable theme name shown in the admin picker.
   * The theme ID is auto-generated from this value (see get_id()).
   *
   * @example 'Folio Starter' -> id becomes 'folio-starter'
   *
   * @return string
   */
  abstract public static function get_name(): string;

  /**
   * Filename (not full URL) of the picker thumbnail image.
   * @example 'theme-thumb-01.png'
   * @return string
   */
  abstract protected static function get_thumbnail_filename(): string;

  /**
   * Filename (not full URL) of the cover/hero image.
   * @example 'theme-cover-01.png'
   * @return string
   */
  abstract protected static function get_cover_filename(): string;

  /**
   * Filename (not full URL) of the theme logo image.
   * @example 'theme-g-logo-01.png'
   * @return string
   */
  abstract protected static function get_logo_filename(): string;

  /**
   * Auto-generate a URL-safe theme ID from the human name.
   * "Folio Starter" -> "folio-starter"
   * "Groove eBook"  -> "groove-ebook"
   *
   * @return string
   */
  public static function get_id(): string
  {
    return sanitize_title(static::get_name());
  }

  /**
   * Build the full descriptor array used by the admin theme picker
   * and Themes_Manager. This is concrete — subclasses do NOT override it.
   *
   * @return array{ID: string, name: string, thumbnail_url: string, cover_url: string, logo_url: string}
   */
  final public static function get_theme_descriptor(): array
  {
    // Instantiate to access the Assets URL helpers.
    $instance = new static ();
    return [
      'ID' => static::get_id(),
      'name' => static::get_name(),
      'thumbnail_url' => $instance->get_images_assets_url(static::get_thumbnail_filename()),
      'cover_url' => $instance->get_images_assets_url(static::get_cover_filename()),
      'logo_url' => $instance->get_images_assets_url(static::get_logo_filename()),
    ];
  }

  // -----------------------------------------------------------------------
  // Data loading
  // -----------------------------------------------------------------------

  function get_the_wp_query($args)
  {
    return new \WP_Query($args);
  }

  function get_page_data()
  {
    $wp_query = $this->get_the_wp_query(array(
      'post__in' => array($this->id),
      'post_status' => array('publish', 'draft', 'pending', 'private'),
      'post_type' => $this->post_type,
    ));

    $page = $wp_query->post;

    $this->title = $page->post_title;
    $this->content = $page->post_content;
    $this->author = get_the_author_meta('user_login', $page->post_author);
    $this->feature_image = get_the_post_thumbnail($page->ID);
    $this->page = $page;

    return $page;
  }

  function get_pages_data($id)
  {
    $args = array(
      'post_type' => 'groove_folio_page',
      'orderby' => 'menu_order',
      'order' => 'ASC',
      'post_status' => array('publish', 'draft', 'pending', 'private'),
      'meta_query' => array(
          array(
          'key' => 'folio_id',
          'value' => $id,
          'compare' => '=',
          'type' => 'NUMERIC',
        ),
      ),
    );

    $q = $this->get_the_wp_query($args);
    $this->pages = $q->posts;
  }

  /**
   * Resolve theme display metadata via Themes_Manager (single source of truth).
   * Falls back to the first registered theme if the stored ID is not found.
   */
  function get_theme_data()
  {
    $meta = get_post_meta($this->id);
    $theme_id = $meta['theme_id'][0] ?? '';
    $all_themes = Themes_Manager::get_all_themes();

    // Fall back to first available theme when stored ID is missing or unrecognised.
    if (!empty($theme_id) && isset($all_themes[$theme_id])) {
      $theme = $all_themes[$theme_id];
      $resolved_id = $theme_id;
    }
    else {
      reset($all_themes);
      $resolved_id = key($all_themes);
      $theme = current($all_themes);
    }

    // $theme may be false when the registry is empty (no themes registered yet).
    if ($theme) {
      $this->theme_id = $resolved_id;
      $this->theme_name = $theme['name'] ?? '';
      $this->theme_cover_url = $theme['cover_url'] ?? '';
      $this->theme_logo_url = $theme['logo_url'] ?? '';
    }
  }

  function get_data()
  {
    $this->get_page_data();
    $this->get_pages_data($this->id);
    $this->get_theme_data();
  }

  function display_theme()
  {
    $this->get_data();

    if ($this->post_type !== 'groove_folio') {
      return '<h1>' . esc_html__('No matching template found') . '</h1>';
    }
  }
}