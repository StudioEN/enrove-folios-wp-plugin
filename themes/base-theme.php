<?php
namespace Groove\Themes;

use Groove\Modules\Assets;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

abstract class Base_Theme extends Assets
{
  public $id;
  public $post_type;
  public $title;
  /** Every cover declares its own; declared here too so the theme picker's
   *  preview can set it on a page class without a PHP 8.2 dynamic-property deprecation. */
  public $subtitle;
  public $content;
  public $feature_image;
  public $author;
  public $copyright;
  public $page;
  public $pages;
  /** Page subclasses only: the parent folio, resolved by get_folio_data(). */
  public $folio;
  public $folio_id;
  public $theme_id;
  public $theme_name;
  public $theme_cover_url;
  public $theme_logo_url;
  public $show_logo = true;
  public $is_preview_mode = false;

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

    // The theme contract: the --folio-* slots every theme fills, with WordPress
    // admin-palette fallbacks. Loaded before theme CSS so a theme's own
    // declarations win, and after 'groove' so --g-folio-* fonts are in scope.
    wp_enqueue_style(
      'groove-folio-contract',
      $this->get_css_assets_url('folio-contract'),
      ['groove'],
      GROOVE_VERSION
    );

    // Per-theme CSS (built-in or installed package).
    $theme_css_path = $this->get_theme_css_path();
    $version = file_exists($theme_css_path) ? filemtime($theme_css_path) : GROOVE_VERSION;

    wp_enqueue_style(
      'groove-theme-' . static::get_id(),
      $this->get_theme_css_url(),
      ['groove', 'groove-folio-contract'],
      $version
    );

    $this->enqueue_folio_fonts('groove-theme-' . static::get_id());

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

    $path_folio_id = (int) Utils::get_folio_id_from_current_path();
    if ($path_folio_id > 0) {
      return $path_folio_id;
    }

    $folio_id = (int) get_post_meta($post_id, 'folio_id', true);
    if ($folio_id > 0) {
      return $folio_id;
    }

    return 0;
  }

  /**
   * Resolve the parent folio ID for a folio-page request.
   *
   * Canonical resolution order, matching Themes_Manager::create_theme_for_current_request():
   *   1. The folio slug in the URL path — always correct, even when the page's
   *      folio_id meta is stale (e.g. after duplication + deletion of the source folio).
   *   2. The folio_id meta stored on the page.
   *   3. A folio_id request parameter (admin previews and query-param URLs).
   *
   * When meta is missing entirely, it is backfilled from the URL so admin screens
   * (which have no folio path to read) resolve the folio too. Meta that merely
   * disagrees with the URL is left alone: page lookup in
   * Utils::get_groove_post_by_post_type_and_post_name() is itself scoped by
   * folio_id, so a page that resolved from a folio path already matches it.
   *
   * Page themes should call this from their constructor rather than re-implementing it.
   *
   * @return int Folio ID, or 0 when it cannot be resolved.
   */
  protected function resolve_page_folio_id()
  {
    $post_id = (int) $this->id;

    if ($this->post_type === 'groove_folio_page') {
      $meta_folio_id = $post_id > 0 ? (int) get_post_meta($post_id, 'folio_id', true) : 0;
      $path_folio_id = (int) Utils::get_folio_id_from_current_path();

      if ($path_folio_id > 0) {
        if ($post_id > 0 && $meta_folio_id <= 0) {
          update_post_meta($post_id, 'folio_id', $path_folio_id);
        }

        return $path_folio_id;
      }

      if ($meta_folio_id > 0) {
        return $meta_folio_id;
      }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter naming the folio to render; nothing is changed, and get_folio_data() only loads a folio this visitor may view.
    return isset($_REQUEST['folio_id']) ? intval(wp_unslash($_REQUEST['folio_id'])) : 0;
  }

  /**
   * The theme's own typeface defaults, declared in its setup.php `fonts` block.
   * Used whenever the folio has not chosen a font for that role.
   *
   * @return array Role => ['css_stack' => string, 'google_family' => string]
   */
  public static function get_default_fonts(): array
  {
    return Font_Loader::normalize_theme_fonts(static::get_setup_data()['fonts'] ?? []);
  }

  /**
   * Colours the password gate borrows so it looks like the folio behind it.
   *
   * Declared in setup.php, like the fonts above, because the gate used to key
   * off two hardcoded ID maps in includes/folio-preview-template.php. Those
   * maps were plugin source, with no filter to join, so a theme shipped as a
   * package could never appear in them: the contract check for it failed
   * permanently for every third-party theme, and the only fix was to patch
   * plugin core, which an update then overwrote.
   *
   * Every value is run through sanitize_hex_color() because all three are
   * interpolated straight into the gate's inline CSS, and an installed package
   * is not trusted input. Anything that is not a hex colour is dropped, and the
   * gate falls back to its own neutral default for that slot.
   *
   * @return array{accent?:string, accent_hover?:string, background?:string}
   */
  public static function get_gate_colors(): array
  {
    $declared = static::get_setup_data()['gate'] ?? [];

    if (!is_array($declared)) {
      return [];
    }

    $out = [];
    foreach (['accent', 'accent_hover', 'background'] as $slot) {
      if (empty($declared[$slot])) {
        continue;
      }

      $color = sanitize_hex_color((string) $declared[$slot]);
      if ($color) {
        $out[$slot] = $color;
      }
    }

    return $out;
  }

  /**
   * Load this render's fonts. Delegates everything — resolution, the single
   * combined request, the preconnect and the CSS variables — to Font_Loader,
   * so themes never enqueue a font stylesheet of their own.
   *
   * @param string $theme_handle Handle of this theme's stylesheet.
   */
  protected function enqueue_folio_fonts($theme_handle)
  {
    $resolved = Font_Loader::resolve(
      $this->get_folio_id_for_customization(),
      static::get_default_fonts()
    );

    Font_Loader::enqueue($resolved, $theme_handle, Font_Loader::FRONTEND_SELECTOR);
  }

  /**
   * Absolute path to this theme's folder.
   *
   * @return string
   */
  public static function get_theme_folder_path(): string
  {
    $class = static::class;
    if (!isset(self::$theme_folder_paths[$class])) {
      $reflector = new \ReflectionClass($class);
      self::$theme_folder_paths[$class] = trailingslashit(dirname((string) $reflector->getFileName()));
    }

    return self::$theme_folder_paths[$class];
  }

  /**
   * URL to this theme's folder (built-in or installed package).
   *
   * @return string
   */
  public function get_theme_folder_url(): string
  {
    return static::resolve_theme_folder_url();
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
   * Absolute path to this theme's assets/ folder.
   *
   * @return string
   */
  public function get_theme_assets_path(): string
  {
    return static::get_theme_folder_path() . 'assets/';
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

  /**
   * Absolute path to this theme's compiled CSS file.
   *
   * @return string
   */
  public function get_theme_css_path(): string
  {
    return $this->get_theme_assets_path() . 'css/theme.css';
  }

  // -----------------------------------------------------------------------
  // Theme identity
  // -----------------------------------------------------------------------

  protected static $setup_data = [];
  protected static $theme_folder_paths = [];
  protected static $theme_folder_urls = [];
  protected static $sample_content_data = [];

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
   * The handful of capabilities this theme wants named in the picker.
   *
   * Declared in setup.php so a theme's own file is the only place that says
   * what the theme can do — including an installed package the plugin knows
   * nothing else about. `Themes_Manager::feature_label()` turns a key from the
   * shared vocabulary into wording, so five themes cannot invent five phrases
   * for the same capability; anything outside that vocabulary is shown as the
   * theme wrote it, so a theme is never limited to the list.
   *
   * A claim the plugin can check, it checks: 'blocks' is dropped from a theme
   * that ships no blocks.php, because a chip promising blocks that are not in
   * the inserter is worse than no chip. Four is the ceiling — this is a row of
   * highlights, not a feature matrix, and an installed package does not get to
   * push the dialog's actions off the screen.
   *
   * @return string[] Feature keys, in the order the theme declared them.
   */
  public static function get_features(): array
  {
    $declared = static::get_setup_data()['features'] ?? [];
    if (!is_array($declared)) {
      return [];
    }

    $features = [];
    foreach ($declared as $feature) {
      if (!is_string($feature)) {
        continue;
      }

      $feature = trim($feature);
      if ($feature === '' || in_array($feature, $features, true)) {
        continue;
      }

      if ($feature === 'blocks' && !static::ships_blocks()) {
        continue;
      }

      $features[] = $feature;
      if (count($features) === 4) {
        break;
      }
    }

    return $features;
  }

  /**
   * Whether this theme registers blocks of its own.
   *
   * blocks.php is the file that registers them, and a theme that ships one
   * must list it in `dependencies` for it to be required at all — so either
   * answer being yes is enough to believe the theme.
   *
   * @return bool
   */
  protected static function ships_blocks(): bool
  {
    $dependencies = static::get_setup_data()['dependencies'] ?? [];
    if (is_array($dependencies)) {
      foreach ($dependencies as $dependency) {
        if (is_string($dependency) && basename($dependency) === 'blocks.php') {
          return true;
        }
      }
    }

    return file_exists(static::get_theme_folder_path() . 'blocks.php');
  }

  /**
   * Title a new folio takes when the site has no explicit default of its own.
   *
   * Lets each theme name its output in its own terms — an issue, a proposal, an
   * eBook — instead of every folio starting life as "A New Folio". Themes that
   * omit the key fall back to that generic title in Themes_Manager.
   *
   * @return string  Empty when the theme states no preference.
   */
  public static function get_default_folio_title(): string
  {
    return static::get_setup_data()['default_title'] ?? '';
  }

  /**
   * Imagery set this theme seeds placeholder content from.
   *
   * A set is a photographic register defined in pexels/sets.php, not a folder
   * of images owned by this theme — several themes may name the same one when
   * the look already fits. Blank means the theme falls back to the shared pool.
   *
   * @return string
   */
  public static function get_image_set(): string
  {
    return static::get_setup_data()['image_set'] ?? '';
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
   * Raw sample-content definition for this theme, if it ships one.
   *
   * A theme opts in by placing sample-content.php next to cover.php; the file
   * returns an array of label/description/subtitle/folio_meta/pages. Themes
   * without the file simply return null and the "Add New" seed toggle is not
   * offered for them.
   *
   * Consumers should prefer Themes_Manager::get_sample_content(), which
   * normalises the shape. This accessor deliberately returns the file verbatim.
   *
   * @return array|null
   */
  public static function get_sample_content_data(): ?array
  {
    $class = static::class;
    if (!array_key_exists($class, self::$sample_content_data)) {
      $file = static::get_theme_folder_path() . 'sample-content.php';
      $data = is_readable($file) ? include $file : null;
      self::$sample_content_data[$class] = is_array($data) ? $data : null;
    }

    return self::$sample_content_data[$class];
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
   * @return array{ID: string, name: string, thumbnail_url: string, cover_url: string, logo_url: string, description: string, features: string[], author: string, last_updated: string}
   */
  final public static function get_theme_descriptor(): array
  {
    // Build URLs purely statically — no instantiation, no constructor side-effects.
    $theme_assets_url = static::resolve_theme_folder_url() . 'assets/';
    return [
      'ID' => static::get_id(),
      'name' => static::get_name(),
      'thumbnail_url' => $theme_assets_url . 'images/' . static::get_thumbnail_filename(),
      'cover_url' => static::resolve_cover_url($theme_assets_url),
      'logo_url' => $theme_assets_url . 'images/' . static::get_logo_filename(),
      'description' => static::get_description(),
      'features' => static::get_features(),
      'default_title' => static::get_default_folio_title(),
      'image_set' => static::get_image_set(),
      'author' => static::get_author(),
      'last_updated' => static::get_last_updated(),
    ];
  }

  /**
   * Resolve the public URL for this theme directory from the class file path.
   * Supports both plugin-bundled themes and installed themes in wp-content.
   *
   * @return string
   */
  /**
   * URL of the theme's cover, or '' when there is no cover to show.
   *
   * A theme package carries its own cover. The built-in themes' covers are
   * Pexels photos, which the WordPress.org package cannot carry, so when the
   * file is not in the theme folder the downloaded copy is used instead (see
   * \Groove\Pexels\Library) — and when that is not there either, '' rather
   * than a URL to nothing, so each theme falls back to its own background.
   *
   * @param string $theme_assets_url The theme's assets/ URL, trailing slash.
   * @return string
   */
  protected static function resolve_cover_url(string $theme_assets_url): string
  {
    $filename = static::get_cover_filename();
    if ($filename === '') {
      return '';
    }

    if (is_readable(trailingslashit(static::get_theme_folder_path()) . 'assets/images/' . $filename)) {
      return $theme_assets_url . 'images/' . $filename;
    }

    if (class_exists('\\Groove\\Pexels\\Library')) {
      return \Groove\Pexels\Library::existing_url('cover-' . static::get_id());
    }

    return '';
  }

  protected static function resolve_theme_folder_url(): string
  {
    $class = static::class;
    if (isset(self::$theme_folder_urls[$class])) {
      return self::$theme_folder_urls[$class];
    }

    $theme_folder_path = wp_normalize_path(static::get_theme_folder_path());
    $content_dir = wp_normalize_path(trailingslashit(WP_CONTENT_DIR));

    if (strpos($theme_folder_path, $content_dir) === 0) {
      $relative_path = ltrim(substr($theme_folder_path, strlen($content_dir)), '/');
      self::$theme_folder_urls[$class] = trailingslashit(WP_CONTENT_URL) . $relative_path;
      return self::$theme_folder_urls[$class];
    }

    self::$theme_folder_urls[$class] = trailingslashit(GROOVE_URL) . 'themes/' . static::get_id() . '/';
    return self::$theme_folder_urls[$class];
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
      $this->title = esc_html__('Not Found', 'groove-folios');
      $this->content = '';
      $this->author = '';
      $this->feature_image = '';
      $this->page = null;
    }

    return $page;
  }

  /**
   * Apply core embed transforms to rendered page HTML.
   *
   * Folio page themes render blocks manually and bypass `the_content`,
   * so bare oEmbed URLs (for example Spotify links on their own line)
   * need explicit processing here.
   */
  protected function apply_embed_processing($content)
  {
    $content = (string) $content;
    if ($content === '') {
      return '';
    }

    global $wp_embed;
    if ($wp_embed instanceof \WP_Embed) {
      $content = $wp_embed->run_shortcode($content);
      $content = $wp_embed->autoembed($content);
    }

    return $content;
  }

  function get_pages_data($id)
  {
    $args = array(
      'post_type' => 'groove_folio_page',
      'orderby' => 'menu_order',
      'order' => 'ASC',
      'post_status' => Utils::get_viewable_post_statuses(),
      'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- folio_id meta is the only link from a page to its folio; the query is bounded to one folio's pages.
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

  // -----------------------------------------------------------------------
  // Folio page helpers
  //
  // Every Page subclass needs the same four things: its parent folio, where it
  // sits in the folio's page order, its neighbours, and an anchor name for a
  // heading. These lived as byte-identical copies in all five themes until the
  // copies drifted — groove-proposal hardened two of them and the fix never
  // travelled. They are here so the next theme inherits the fixed versions.
  //
  // A theme is still free to override any of them; magazine reorders $pages in
  // get_data() before these ever read it.
  // -----------------------------------------------------------------------

  /**
   * Load the parent folio post into $this->folio.
   *
   * Page subclasses resolve $this->folio_id in their constructor via
   * resolve_page_folio_id(), then call this from get_data().
   *
   * @return \WP_Post|null
   */
  function get_folio_data()
  {
    $wp_query = $this->get_the_wp_query(array(
      'post__in' => array((int) $this->folio_id),
      'post_status' => Utils::get_viewable_post_statuses(),
      'post_type' => 'groove_folio',
    ));

    $folio = $wp_query->post;

    // Direct fallback: WP_Query may miss the folio in some status-filtering
    // edge cases. Viewability is re-checked by hand because we are bypassing
    // the post_status arg that would otherwise have enforced it.
    if (!$folio && (int) $this->folio_id > 0) {
      $direct = get_post((int) $this->folio_id);
      if ($direct && $direct->post_type === 'groove_folio' && Utils::can_current_request_view_post($direct)) {
        $folio = $direct;
      }
    }

    $this->folio = $folio;

    return $folio;
  }

  /**
   * The page list as an ordered, 0-indexed list.
   *
   * A theme is invited to reorder $this->pages in get_data() (groove-magazine
   * does), and a reorder that filters rather than sorts leaves the original keys
   * behind. Position and key would then disagree, and prev/next — which step by
   * one from the current index — would walk to the wrong page or to nothing.
   * Normalising here means any array a theme hands back works.
   *
   * @return array
   */
  protected function get_ordered_pages()
  {
    return is_array($this->pages) ? array_values($this->pages) : array();
  }

  /**
   * This page's position in $this->pages, or -1 when it is not among them
   * (a preview's fabricated post, or a page whose folio_id went stale).
   *
   * @return int
   */
  function get_current_index()
  {
    foreach ($this->get_ordered_pages() as $index => $page) {
      if (isset($page->ID) && (int) $page->ID === (int) $this->id) {
        return $index;
      }
    }

    return -1;
  }

  /**
   * @return \WP_Post|null The page before this one, or null at the start.
   */
  function get_prev_page()
  {
    $pages = $this->get_ordered_pages();
    $index = $this->get_current_index();

    return $index > 0 ? $pages[$index - 1] : null;
  }

  /**
   * @return \WP_Post|null The page after this one, or null at the end.
   */
  function get_next_page()
  {
    $pages = $this->get_ordered_pages();
    $index = $this->get_current_index();

    return ($index > -1 && $index < count($pages) - 1) ? $pages[$index + 1] : null;
  }

  /**
   * Slugify a heading into an anchor name — lowercased, hyphenated.
   * Consumed by the "On this page" rails and the mobile nav.
   *
   * @param string $string
   * @return string
   */
  function to_anchor_name($string)
  {
    $dstr = preg_replace_callback('/([A-Z]+)/', function ($matches) {
      return '-' . strtolower($matches[0]);
    }, (string) $string);

    $dst = preg_replace_callback('/([\s]+)/', function ($matches) {
      return '-';
    }, (string) $dstr);

    return trim((string) preg_replace('/_{2,}/', '-', (string) $dst), '-');
  }

  /**
   * A heading's anchor: the author's own id when it has one, else a slug of
   * its text.
   *
   * @param \DOMElement $element
   * @return string
   */
  function get_html_id($element)
  {
    $id = $element->getAttribute('id');

    return $id ? $id : $this->to_anchor_name($element->textContent);
  }

  /**
   * Resolve navigation context with shared fallbacks.
   *
   * Themes can consume this context and still render fully custom nav markup.
   */
  protected function get_navigation_context($args = array())
  {
    $folio_id = isset($args['folio_id']) ? (int) $args['folio_id'] : $this->get_folio_id_for_customization();
    $current_page_id = isset($args['current_page_id'])
      ? (int) $args['current_page_id']
      : (($this->post_type === 'groove_folio_page') ? (int) $this->id : 0);

    $pages = array();
    if (isset($args['pages']) && is_array($args['pages'])) {
      $pages = $args['pages'];
    } elseif (is_array($this->pages) && !empty($this->pages)) {
      $pages = $this->pages;
    } elseif ($folio_id > 0) {
      $this->get_pages_data($folio_id);
      $pages = is_array($this->pages) ? $this->pages : array();
    }

    $title = array_key_exists('title', $args) ? (string) $args['title'] : '';
    if ($title === '') {
      if ($this->post_type === 'groove_folio') {
        $title = !empty($this->title) ? (string) $this->title : (string) $this->theme_name;
      } else {
        if (property_exists($this, 'folio') && isset($this->folio) && isset($this->folio->post_title)) {
          $title = (string) $this->folio->post_title;
        } elseif ($folio_id > 0) {
          $folio_post = get_post($folio_id);
          if ($folio_post && isset($folio_post->post_title)) {
            $title = (string) $folio_post->post_title;
          }
        }
      }
    }

    $title_url = array_key_exists('title_url', $args) ? (string) $args['title_url'] : '';
    if ($title_url === '' && $folio_id > 0 && Utils::is_folio_cover_enabled($folio_id)) {
      $title_url = (string) Utils::get_folio_permalink_by_id($folio_id);
    }

    return array(
      'folio_id' => $folio_id,
      'title' => $title,
      'title_url' => $title_url,
      'pages' => $pages,
      'current_page_id' => $current_page_id,
    );
  }

  /**
   * Resolve theme display metadata via Themes_Manager (single source of truth).
   * Falls back to the first registered theme if the stored ID is not found.
   */
  function get_theme_data()
  {
    $theme_meta_post_id = $this->get_folio_id_for_customization();
    if ($theme_meta_post_id <= 0) {
      $theme_meta_post_id = (int) $this->id;
    }

    $meta = get_post_meta($theme_meta_post_id);
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
      $this->show_logo = true;

      $folio_id = $this->get_folio_id_for_customization();
      if ($folio_id > 0) {
        $show_logo_meta = get_post_meta($folio_id, 'show_logo', true);
        $this->show_logo = !in_array((string) $show_logo_meta, array('0', 'false', 'off', 'no'), true);

        $custom_logo_id = (int) get_post_meta($folio_id, 'logo_id', true);
        if ($custom_logo_id > 0) {
          $custom_logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
          if (!empty($custom_logo_url)) {
            $this->theme_logo_url = $custom_logo_url;
          }
        }
      }

      if (!$this->show_logo) {
        $this->theme_logo_url = '';
      }
    }
  }

  function get_data()
  {
    if ($this->is_preview_mode) { return; }
    $this->get_page_data();
    $this->get_pages_data($this->id);
    $this->get_theme_data();
    $this->copyright = '';

    $folio_id = $this->get_folio_id_for_customization();
    if ($folio_id > 0) {
      $this->copyright = trim((string) get_post_meta($folio_id, 'copyright', true));

      $show_byline = (string) get_post_meta($folio_id, 'show_byline', true);
      if ($show_byline === '0') {
        $this->author = '';
      } else {
        $byline_user_id = (int) get_post_meta($folio_id, 'byline', true);
        if ($byline_user_id <= 0) {
          $byline_user_id = (int) get_post_field('post_author', $folio_id);
        }

        if ($byline_user_id > 0) {
          $byline_user = get_userdata($byline_user_id);
          $this->author = $byline_user ? $byline_user->display_name : '';
        }
      }
    }
  }

  function display_theme()
  {
    $this->get_data();
    // Themes_Manager ensures the right class is used for the right post type.
    // No guard needed here — child classes control their own output.
    return true;
  }
}
