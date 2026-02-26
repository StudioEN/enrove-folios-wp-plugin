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

    // Shared plugin CSS (admin bar reset, global layout).
    wp_enqueue_style('groove', $this->get_css_assets_url('groove-main', null, 'default', true), [], GROOVE_VERSION);

    // Per-theme CSS — lives in themes/<theme-id>/assets/css/theme.css.
    $theme_css_path = trailingslashit(GROOVE_PATH) . 'themes/' . static::get_id() . '/assets/css/theme.css';
    $version = file_exists($theme_css_path) ? filemtime($theme_css_path) : GROOVE_VERSION;

    wp_enqueue_style(
      'groove-theme-' . static::get_id(),
      $this->get_theme_css_url(),
      ['groove'],
      $version
    );

    $this->enqueue_primary_font_style('groove-theme-' . static::get_id());

    wp_enqueue_script('groove', $this->get_js_assets_url('groove-main'), ['jquery'], GROOVE_VERSION, true);
  }

  protected function get_folio_id_for_customization()
  {
    $post_id = (int) $this->id;
    if ($post_id <= 0) {
      return 0;
    }

    if ($this->post_type === 'groove_folio') {
      return $post_id;
    }

    if ($this->post_type !== 'groove_folio_page') {
      return 0;
    }

    $folio_id = (int) get_post_meta($post_id, 'folio_id', true);
    if ($folio_id > 0) {
      return $folio_id;
    }

    $current_path = Utils::get_current_path();
    $base_slug = Utils::get_folio_base_slug();
    $pattern = '#^/' . preg_quote($base_slug, '#') . '/([^/]+)/page/#';
    if (preg_match($pattern, $current_path, $matches)) {
      $folio_slug = rtrim($matches[1], '/');
      $folio_post = Utils::get_groove_post_by_post_type_and_post_name('groove_folio', $folio_slug);
      if ($folio_post) {
        return (int) $folio_post->ID;
      }
    }

    return 0;
  }

  protected function get_selected_primary_font_data()
  {
    $folio_id = $this->get_folio_id_for_customization();
    if ($folio_id <= 0) {
      return null;
    }

    $font_key = Utils::normalize_primary_font_key((string) get_post_meta($folio_id, 'fonts', true));
    if ($font_key === '') {
      return null;
    }

    return Utils::get_primary_font_data($font_key);
  }

  protected function enqueue_primary_font_style($theme_handle)
  {
    $font = $this->get_selected_primary_font_data();
    if (!$font || empty($font['google_url']) || empty($font['css_stack'])) {
      return;
    }

    $font_handle = 'groove-folio-font-' . $font['key'];
    wp_enqueue_style($font_handle, $font['google_url'], [], GROOVE_VERSION);

    $font_stack = $font['css_stack'];
    $inline_css = '.g-folio__theme-cover, body.groove [class*="g-folio__theme-"][class$="-page"] { --g-folio-primary-font: ' . $font_stack . '; font-family: var(--g-folio-primary-font); }';
    wp_add_inline_style($theme_handle, $inline_css);
  }

  /**
   * URL to this theme's folder inside the plugin.
   * e.g. https://example.com/wp-content/plugins/groove/themes/folio-starter/
   *
   * @return string
   */
  public function get_theme_folder_url(): string
  {
    return GROOVE_URL . 'themes/' . static::get_id() . '/';
  }

  /**
   * URL to this theme's assets/ folder.
   * e.g. .../themes/folio-starter/assets/
   *
   * @return string
   */
  public function get_theme_assets_url(): string
  {
    return $this->get_theme_folder_url() . 'assets/';
  }

  /**
   * URL to this theme's compiled CSS file.
   *
   * @return string
   */
  public function get_theme_css_url(): string
  {
    return $this->get_theme_assets_url() . 'css/theme.css';
  }

  // -----------------------------------------------------------------------
  // Theme identity
  // -----------------------------------------------------------------------

  protected static $setup_data = [];

  public static function get_setup_data(): array
  {
    $class = static::class;
    if (!isset(self::$setup_data[$class])) {
      $reflector = new \ReflectionClass($class);
      $dir = dirname($reflector->getFileName());
      $setup_file = $dir . '/setup.php';
      if (file_exists($setup_file)) {
        self::$setup_data[$class] = include $setup_file;
      } else {
        self::$setup_data[$class] = [];
      }
    }
    return self::$setup_data[$class];
  }

  /**
   * Human-readable theme name shown in the admin picker.
   *
   * @return string
   */
  public static function get_name(): string
  {
    return static::get_setup_data()['name'] ?? '';
  }

  /**
   * Filename (not full URL) of the picker thumbnail image.
   *
   * @return string
   */
  protected static function get_thumbnail_filename(): string
  {
    return static::get_setup_data()['thumbnail'] ?? '';
  }

  /**
   * Filename (not full URL) of the cover/hero image.
   *
   * @return string
   */
  protected static function get_cover_filename(): string
  {
    return static::get_setup_data()['cover'] ?? '';
  }

  /**
   * Filename (not full URL) of the theme logo image.
   *
   * @return string
   */
  protected static function get_logo_filename(): string
  {
    return static::get_setup_data()['logo'] ?? '';
  }

  /**
   * Theme description text.
   *
   * @return string
   */
  public static function get_description(): string
  {
    return static::get_setup_data()['description'] ?? '';
  }

  /**
   * Theme author.
   *
   * @return string
   */
  public static function get_author(): string
  {
    return static::get_setup_data()['author'] ?? '';
  }

  /**
   * Theme last updated date.
   *
   * @return string
   */
  public static function get_last_updated(): string
  {
    return static::get_setup_data()['last_updated'] ?? '';
  }

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
   * @return array{ID: string, name: string, thumbnail_url: string, cover_url: string, logo_url: string, description: string, author: string, last_updated: string}
   */
  final public static function get_theme_descriptor(): array
  {
    // Build URLs purely statically — no instantiation, no constructor side-effects.
    $theme_assets_url = GROOVE_URL . 'themes/' . static::get_id() . '/assets/';
    return [
      'ID' => static::get_id(),
      'name' => static::get_name(),
      'thumbnail_url' => $theme_assets_url . 'images/' . static::get_thumbnail_filename(),
      'cover_url' => $theme_assets_url . 'images/' . static::get_cover_filename(),
      'logo_url' => $theme_assets_url . 'images/' . static::get_logo_filename(),
      'description' => static::get_description(),
      'author' => static::get_author(),
      'last_updated' => static::get_last_updated(),
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
      'post_status' => Utils::get_viewable_post_statuses(),
      'post_type' => $this->post_type,
    ));

    $page = $wp_query->post;

    if ($page) {
      $this->title = $page->post_title;
      $this->content = $page->post_content;
      $this->author = get_the_author_meta('user_login', $page->post_author);
      $this->feature_image = get_the_post_thumbnail($page->ID);
      $this->page = $page;
    } else {
      $this->title = esc_html__('Not Found', 'groove');
      $this->content = '';
      $this->author = '';
      $this->feature_image = '';
      $this->page = null;
    }

    return $page;
  }

  function get_pages_data($id)
  {
    $args = array(
      'post_type' => 'groove_folio_page',
      'orderby' => 'menu_order',
      'order' => 'ASC',
      'post_status' => Utils::get_viewable_post_statuses(),
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
    } else {
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

      $folio_id = $this->get_folio_id_for_customization();
      if ($folio_id > 0) {
        $custom_logo_id = (int) get_post_meta($folio_id, 'logo_id', true);
        if ($custom_logo_id > 0) {
          $custom_logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
          if (!empty($custom_logo_url)) {
            $this->theme_logo_url = $custom_logo_url;
          }
        }
      }
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
    // Themes_Manager ensures the right class is used for the right post type.
    // No guard needed here — child classes control their own output.
    return true;
  }
}
