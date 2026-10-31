<?php
namespace Enrove\Themes;

use Enrove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * A folio as one document, for the browser to print or save as a PDF.
 *
 * Nothing here makes a PDF: a browser does that, from its own print window,
 * with the site's own fonts and the theme's own CSS, so the plugin ships no PDF
 * library and calls no service. This class puts the cover, a contents page and
 * every page of the folio in one document; includes/folio-print-template.php
 * prints it; assets/css/folio-print.css lays it out on paper, and each theme's
 * assets/css/print.css finishes the job for that theme's markup.
 *
 * The URL carries no nonce, being a folio URL like any other, so this shows what
 * the request could already read one page at a time and no more.
 */
class Folio_Print
{
  /** Margins of a page, in millimetres. folio-print.css declares the same in its @page rule. */
  const MARGIN_TOP = 20;
  const MARGIN_SIDE = 18;
  const MARGIN_BOTTOM = 22;

  /** Paper sizes in millimetres, by the name @page's size descriptor knows them. */
  const PAPER_SIZES = array(
    'A4' => array(210, 297),
    'letter' => array(215.9, 279.4),
  );

  /** @var \WP_Post */
  private $folio;

  /** @var Base_Theme|null Null when the folio's cover is turned off. */
  private $cover;

  /** @var Base_Theme */
  private $page_theme;

  /** @var \WP_Post[] */
  private $pages;

  /**
   * The folio this request asks to print, if it may.
   *
   * @return \WP_Post|null Null when this is not a print request, or the folio
   *                       cannot be shown, which the normal template then explains.
   */
  public static function get_requested_folio()
  {
    if ('1' !== (string) get_query_var('enrove_print')) {
      return null;
    }

    $folio = get_post((int) Utils::get_enrove_post_id());
    if (
      !$folio instanceof \WP_Post ||
      $folio->post_type !== 'enrove_folio' ||
      !Utils::can_current_request_view_post($folio) ||
      Themes_Manager::resolve_registered_theme_id((string) get_post_meta($folio->ID, 'theme_id', true)) === null
    ) {
      return null;
    }

    return $folio;
  }

  public function __construct(\WP_Post $folio)
  {
    $this->folio = $folio;
    $this->pages = self::get_pages($folio->ID);

    $theme_id = (string) get_post_meta($folio->ID, 'theme_id', true);

    if (Utils::is_folio_cover_enabled($folio->ID)) {
      $this->cover = Themes_Manager::create_cover_theme($theme_id);
      if ($this->cover) {
        $this->cover->is_print_mode = true;
        $this->cover->post_type = 'enrove_folio';
        $this->cover->id = $folio->ID;
      }
    }

    $this->page_theme = Themes_Manager::create_page_theme($theme_id);

    add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 100);
    add_filter('wp_robots', 'wp_robots_no_robots');
  }

  /**
   * The pages the folio lists to this request, in order, leaving out any it may
   * not read at their own address.
   *
   * @return \WP_Post[]
   */
  public static function get_pages($folio_id)
  {
    $query = new \WP_Query(array(
      'post_type' => 'enrove_folio_page',
      'orderby' => 'menu_order',
      'order' => 'ASC',
      'posts_per_page' => -1,
      'post_status' => Utils::get_listed_page_statuses((int) $folio_id),
      'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- folio_id meta is the only link from a page to its folio; the query is bounded to one folio's pages.
        array(
          'key' => 'folio_id',
          'value' => (int) $folio_id,
          'compare' => '=',
          'type' => 'NUMERIC',
        ),
      ),
    ));

    return array_values(array_filter($query->posts, array(Utils::class, 'can_current_request_view_post')));
  }

  /**
   * The paper a folio prints on: the one the reader picked in the bar, or the site's.
   *
   * @return string A key of PAPER_SIZES.
   */
  public static function get_paper_size()
  {
    $requested = (string) get_query_var('enrove_print_paper');

    return isset(self::PAPER_SIZES[$requested]) ? $requested : self::get_default_paper_size();
  }

  /**
   * The site's paper: US Letter where that is the usual paper, A4 everywhere else.
   *
   * @return string A key of PAPER_SIZES.
   */
  private static function get_default_paper_size()
  {
    $locale = get_locale();
    $default = in_array($locale, array('en_US', 'en_CA', 'es_MX', 'fr_CA'), true) ? 'letter' : 'A4';

    /**
     * Filters the paper a folio is printed on.
     *
     * @param string $paper 'A4' or 'letter'.
     */
    $paper = (string) apply_filters('enrove_folios_print_paper_size', $default);

    return isset(self::PAPER_SIZES[$paper]) ? $paper : $default;
  }

  /**
   * Plain text as a CSS string, for a `content` declaration.
   *
   * @return string With its quotes.
   */
  public static function to_css_string($text)
  {
    $text = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', wp_strip_all_tags((string) $text)));

    return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $text) . '"';
  }

  /**
   * Swap the screen's scripts for the print document's own. A theme's script
   * drives navigation this document does not have, and its bootstrap would put
   * a reader's dark mode on paper.
   */
  public function enqueue_assets()
  {
    $scripts = wp_scripts();
    foreach ($scripts->queue as $handle) {
      wp_dequeue_script($handle);
    }

    $theme = $this->page_theme;
    $theme_handle = 'enrove-theme-' . $theme::get_id();

    wp_enqueue_style(
      'enrove-folio-print',
      $theme->get_css_assets_url('folio-print'),
      array($theme_handle),
      (string) filemtime(ENROVE_ASSETS_PATH . 'css/folio-print.css')
    );
    wp_add_inline_style('enrove-folio-print', wp_strip_all_tags($this->get_inline_css()));

    $theme_print_css = $theme->get_theme_assets_path() . 'css/print.css';
    if (file_exists($theme_print_css)) {
      wp_enqueue_style(
        'enrove-theme-print-' . $theme::get_id(),
        $theme->get_theme_assets_url() . 'css/print.css',
        array('enrove-folio-print'),
        (string) filemtime($theme_print_css)
      );
    }

    wp_enqueue_script(
      'enrove-folio-print',
      $theme->get_js_assets_url('enrove-print'),
      array(),
      (string) filemtime(ENROVE_ASSETS_PATH . 'js/enrove-print.js'),
      true
    );
  }

  /**
   * What the stylesheet cannot know: the paper, and the folio's title and font
   * in the running footer. A margin box is outside the document, so it inherits
   * neither the theme's custom properties nor the folio's font.
   */
  private function get_inline_css()
  {
    $paper = self::get_paper_size();
    list($width, $height) = self::PAPER_SIZES[$paper];

    $fonts = Font_Loader::resolve((int) $this->folio->ID, $this->page_theme::get_default_fonts());
    $stack = Font_Loader::sanitize_css_stack($fonts['body']['css_stack'] ?? '');
    $font = $stack === '' ? '' : 'font-family: ' . $stack . ';';

    $content_width = $width - 2 * self::MARGIN_SIDE;
    $content_height = $height - self::MARGIN_TOP - self::MARGIN_BOTTOM;

    return implode("\n", array(
      '@page {',
      '  size: ' . $paper . ';',
      '  @bottom-left { content: ' . self::to_css_string($this->folio->post_title) . '; ' . $font . ' }',
      '  @bottom-right { ' . $font . ' }',
      '}',
      ':root {',
      '  --g-folio-print-paper-height: ' . (float) $height . 'mm;',
      '  --g-folio-print-width: ' . (float) $content_width . 'mm;',
      '  --g-folio-print-height: ' . (float) $content_height . 'mm;',
      '}',
    ));
  }

  /** A contents page only helps when there is more than one page to find. */
  private function has_contents()
  {
    return count($this->pages) > 1;
  }

  /**
   * The bar above the document on screen, which never prints. The print window
   * opens by itself; the button is there for when it is closed, or was blocked.
   */
  private function display_bar()
  {
    // Whether the script may pick the reader's paper: not over a reader's own
    // choice, and not on a site that has settled on a paper with the filter.
    $paper_is_open = (string) get_query_var('enrove_print_paper') === '' && !has_filter('enrove_folios_print_paper_size');
    ?>
    <div class="g-folio-print__bar"<?php if ($paper_is_open) { echo ' data-enrove-paper="' . esc_attr(self::get_paper_size()) . '"'; } ?>>
      <div class="g-folio-print__bar-text">
        <p class="g-folio-print__bar-title"><?php esc_html_e('Save this folio as a PDF', 'enrove-folios'); ?></p>
        <p class="g-folio-print__bar-hint"><?php esc_html_e('In the print window, choose Save as PDF, and turn off Headers and footers for clean pages. White strips at the edges of the cover mean the paper differs from the one in the print window.', 'enrove-folios'); ?></p>
        <p class="g-folio-print__bar-hint g-folio-print__bar-hint--safari"><?php esc_html_e('Safari keeps a white margin around the cover and leaves out page numbers. For the full layout, open this page in Chrome or Edge.', 'enrove-folios'); ?></p>
      </div>
      <div class="g-folio-print__bar-actions">
        <?php $this->display_paper_choice(); ?>
        <button type="button" class="g-folio-print__bar-button" data-enrove-print><?php esc_html_e('Print or save as PDF', 'enrove-folios'); ?></button>
      </div>
    </div>
    <?php
  }

  /**
   * The print window fits a sheet to its own paper, and no script can read that
   * paper, so a reader whose paper differs from this document's has to say so.
   */
  private function display_paper_choice()
  {
    $url = Utils::get_folio_print_url($this->folio->ID);
    $current = self::get_paper_size();
    $papers = array(
      'A4' => __('A4', 'enrove-folios'),
      'letter' => __('US Letter', 'enrove-folios'),
    );
    ?>
    <div class="g-folio-print__bar-paper" role="group" aria-label="<?php esc_attr_e('Paper', 'enrove-folios'); ?>">
      <?php foreach ($papers as $paper => $label): ?>
        <a href="<?php echo esc_url(add_query_arg('enrove_print_paper', $paper, $url)); ?>"<?php if ($paper === $current) { echo ' aria-current="true"'; } ?>><?php echo esc_html($label); ?></a>
      <?php endforeach; ?>
    </div>
    <?php
  }

  private function display_contents()
  {
    ?>
    <nav class="g-folio-print__contents" aria-labelledby="g-folio-print-contents-title">
      <h2 class="g-folio-print__contents-title" id="g-folio-print-contents-title"><?php esc_html_e('Contents', 'enrove-folios'); ?></h2>
      <ol class="g-folio-print__contents-list">
        <?php foreach ($this->pages as $index => $page): ?>
          <li class="g-folio-print__contents-item">
            <a href="<?php echo esc_url('#g-folio-print-page-' . ($index + 1)); ?>"><?php echo esc_html(get_the_title($page)); ?></a>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>
    <?php
  }

  /** Everything inside <body>. */
  public function display()
  {
    $this->display_bar();

    if ($this->cover) {
      ?>
      <section class="g-folio-print__cover"><?php $this->cover->display_theme(); ?></section>
      <?php
    }

    if (!$this->pages) {
      return;
    }
    ?>
    <div class="g-folio-print__flow <?php echo esc_attr($this->page_theme->get_print_root_class()); ?>">
      <?php if ($this->has_contents()) { $this->display_contents(); } ?>
      <?php foreach ($this->pages as $index => $page): ?>
        <section class="g-folio-print__page" id="<?php echo esc_attr('g-folio-print-page-' . ($index + 1)); ?>">
          <?php $this->page_theme->display_print_page($page, (int) $this->folio->ID); ?>
        </section>
      <?php endforeach; ?>
    </div>
    <?php
  }
}
