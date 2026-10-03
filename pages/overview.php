<?php
namespace Enrove\Pages;

use Enrove\Pages\Page;
use Enrove\Setup\First_Run;
use Enrove\Utils\Request;


if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Overview extends Page
{
  const PAGE_ID = 'enrove-overview';
  const MENU_PRIORITY = 10;

  public function get_title()
  {
    return 'Overview';
  }

  public function create_tabs()
  {
    return array();
  }

  protected function shows_setup_entry()
  {
    return false;
  }

  public function register_admin_menu()
  {
    add_menu_page(
      esc_html__('Enrove', 'enrove-folios'),
      esc_html__('Enrove Folios', 'enrove-folios'),
      'edit_posts',
      self::PAGE_ID,
    [$this, 'display_page'],
      $this->get_images_assets_url('logo.svg'),
      '58.5'
    );

    add_action('admin_menu', [$this, 'change_overview_menu_name'], 200);
  }

  public function change_overview_menu_name()
  {
    global $submenu;

    $menu_slug = 'enrove-overview';
    $new_label = esc_html__('Overview', 'enrove-folios');

    if (isset($submenu[$menu_slug])) {
      $submenu[$menu_slug][0][0] = $new_label;
    }
  }

  public function __construct()
  {
    add_action('admin_menu', [$this, 'register_admin_menu'], 20);
    add_action('admin_enqueue_scripts', [$this, 'normalize_menu_icon_spacing']);
  }

  /**
   * Size the menu icon like core's dashicons. On every admin screen, since the
   * menu is, so it rides on a handle of its own rather than enrove-main's.
   */
  public function normalize_menu_icon_spacing()
  {
    wp_register_style('enrove-admin-menu', false, [], ENROVE_VERSION);
    wp_enqueue_style('enrove-admin-menu');
    wp_add_inline_style(
      'enrove-admin-menu',
      '#adminmenu #toplevel_page_enrove-overview .wp-menu-image img { width: 16px; height: 16px; padding: 9px 10px; box-sizing: content-box; }'
    );
  }

  public function display_glance()
  {
    // Aggregate all non-trash statuses for each post type.
    $folio_counts = wp_count_posts('enrove_folio');

    $folio_total = (int)($folio_counts->publish ?? 0)
      + (int)($folio_counts->draft ?? 0)
      + (int)($folio_counts->private ?? 0)
      + (int)($folio_counts->pending ?? 0);

    // Count collection tags that are actually assigned to folios.
    $collection_total = wp_count_terms(array(
      'taxonomy' => 'enrove_collection_tag',
      'hide_empty' => false,
    ));
    if (is_wp_error($collection_total)) {
      $collection_total = 0;
    }
    $collection_total = (int) $collection_total;

    $folios_url = admin_url('admin.php?page=enrove-all-folios');
    ?>
<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-sm font-semibold text-gray-800 m-0"><?php esc_html_e('At a Glance', 'enrove-folios'); ?></h2>
    <a class="button button-primary" href="<?php echo esc_url(Request::admin_url(All_Folios::PAGE_ID, array('open_add_new' => 1))); ?>">
      <?php esc_html_e('Add New Folio', 'enrove-folios'); ?>
    </a>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <a href="<?php echo esc_url($folios_url); ?>" class="block rounded-lg border border-gray-200 bg-gray-50/50 p-4 hover:bg-gray-100">
      <div class="text-xs font-medium text-gray-500 uppercase tracking-wide"><?php esc_html_e('Folios', 'enrove-folios'); ?></div>
      <div class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html(number_format_i18n($folio_total)); ?></div>
      <div class="mt-1 text-xs text-gray-600">
        <?php
        /* translators: %s: number of folios */
        printf(esc_html(_n('%s item', '%s items', $folio_total, 'enrove-folios')), esc_html(number_format_i18n($folio_total)));
        ?>
      </div>
    </a>

    <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4">
      <div class="text-xs font-medium text-gray-500 uppercase tracking-wide"><?php esc_html_e('Collections', 'enrove-folios'); ?></div>
      <div class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html(number_format_i18n($collection_total)); ?></div>
      <div class="mt-1 text-xs text-gray-600">
        <?php
        /* translators: %s: number of collection tags */
        printf(esc_html(_n('%s tag', '%s tags', $collection_total, 'enrove-folios')), esc_html(number_format_i18n($collection_total)));
        ?>
      </div>
    </div>
  </div>
</section>
<?php
  }

  public function display_activity()
  {
    $recent_posts = get_posts(array(
      'post_type' => 'enrove_folio',
      'post_status' => 'any',
      'posts_per_page' => 5,
      'orderby' => 'modified',
      'order' => 'DESC'
    ));
    ?>
<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-sm font-semibold text-gray-800 m-0"><?php esc_html_e('Recent Activity', 'enrove-folios'); ?></h2>
    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=enrove-all-folios')); ?>">
      <?php esc_html_e('View All Folios', 'enrove-folios'); ?>
    </a>
  </div>

  <?php if (!empty($recent_posts)): ?>
  <div class="overflow-x-auto">
    <table class="min-w-full border-collapse">
      <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e('Folio', 'enrove-folios'); ?></th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e('Status', 'enrove-folios'); ?></th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e('Updated', 'enrove-folios'); ?></th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-100">
        <?php foreach ($recent_posts as $post): ?>
        <?php
          $title = get_the_title($post);
          $title = $title ? $title : esc_html__('(no title)', 'enrove-folios');
          $link = Folio::get_edit_url((int) $post->ID);
          $status_object = get_post_status_object($post->post_status);
          $status_label = $status_object ? $status_object->label : ucfirst($post->post_status);
          ?>
        <tr class="hover:bg-gray-50/50">
          <td class="px-4 py-3"><a href="<?php echo esc_url($link); ?>" class="text-indigo-600 hover:text-indigo-500"><?php echo esc_html($title); ?></a></td>
          <td class="px-4 py-3 text-gray-700"><?php echo esc_html($status_label); ?></td>
          <td class="px-4 py-3 text-gray-700 whitespace-nowrap"><?php echo esc_html(get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $post)); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="text-sm text-gray-600"><?php esc_html_e('No recent activity found.', 'enrove-folios'); ?></p>
  <?php endif; ?>
</section>
<?php
  }

  /**
   * The outcome of a reset (Settings → Reset), which lands here: a fresh
   * install starts on Overview.
   */
  private function toast_reset_outcome()
  {
    // Set by the reset handler's own signed redirect (Utils\Request).
    $outcome = Request::key('enrove_reset');
    $folios = Request::int('enrove_reset_folios');
    $pages = Request::int('enrove_reset_pages');
    $tags = Request::int('enrove_reset_tags');

    $consumed = array('enrove_reset', 'enrove_reset_folios', 'enrove_reset_pages', 'enrove_reset_tags');

    if ($outcome === 'none') {
      \Enrove\Toast::success(__('Enrove Folios was reset.', 'enrove-folios'), $consumed);
    } elseif ($outcome === 'kept') {
      \Enrove\Toast::success(__('Enrove Folios was reset. Your folios were kept.', 'enrove-folios'), $consumed);
    } elseif ($outcome === 'deleted') {
      \Enrove\Toast::success(sprintf(
        /* translators: 1: number of folios, 2: number of folio pages, 3: number of collection tags — each already a phrase such as "3 folios". */
        __('Enrove Folios was reset. Deleted %1$s, %2$s and %3$s.', 'enrove-folios'),
        /* translators: %s: number of folios. */
        sprintf(_n('%s folio', '%s folios', $folios, 'enrove-folios'), number_format_i18n($folios)),
        /* translators: %s: number of folio pages. */
        sprintf(_n('%s page', '%s pages', $pages, 'enrove-folios'), number_format_i18n($pages)),
        /* translators: %s: number of collection tags. */
        sprintf(_n('%s collection tag', '%s collection tags', $tags, 'enrove-folios'), number_format_i18n($tags))
      ), $consumed);
    }
  }

  public function display_content()
  {
    $this->toast_reset_outcome();
    ?>
<div class="space-y-6">
  <?php if (First_Run::should_offer()): ?>
  <?php First_Run::display_overview_panel(); ?>
  <?php elseif (First_Run::should_offer_return()): ?>
  <?php First_Run::display_return_panel(); ?>
  <?php else: ?>
  <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
    <p class="m-0 text-sm text-indigo-900">
      <?php esc_html_e('Welcome to Enrove Folios. Manage your folios, review recent changes, and create new work from here.', 'enrove-folios'); ?>
    </p>
  </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
      <?php $this->display_glance(); ?>
    </div>
    <div>
      <?php $this->display_activity(); ?>
    </div>
  </div>
</div>
<?php
  }
}
