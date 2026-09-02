<?php
namespace Groove\Pages;

use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Add_New_Menu_Item;


if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}


class Add_New extends Page
{
  const PAGE_ID = 'groove-add-new';
  const POST_TYPE = 'groove_folio_page';

  public function __construct()
  {
    $this->add_post_action('groove_create_folio', 'create_folio');

    add_action('groove/menu/register', function (Menu_Manager $menu) {
      $menu->register(static::PAGE_ID, new Add_New_Menu_Item($this));
    }, Overview::MENU_PRIORITY + 20);

    add_action('admin_init', function () {
      if (!isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== static::PAGE_ID) {
        return;
      }
      wp_safe_redirect(admin_url('admin.php?page=groove-all-folios&open_add_new=1'));
      exit;
    });
  }

  public function create_folio()
  {
    $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    if ($action === 'groove_create_folio') {
      check_admin_referer('groove_create_folio_action', 'groove_nonce');

      if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to create folios.', 'groove'));
      }

      $themes = \Groove\Themes\Themes_Manager::get_all_themes();
      if (empty($themes)) {
        wp_die(esc_html__('No themes are available. Install a theme first.', 'groove'));
      }

      $default_theme_id = (string) get_option('groove_default_theme_id', '');
      if ($default_theme_id === '' || !isset($themes[$default_theme_id])) {
        $default_theme_id = (string) array_key_first($themes);
      }

      $theme_id = isset($_POST['themeId']) ? sanitize_key(wp_unslash($_POST['themeId'])) : $default_theme_id;
      if (empty($theme_id) || !isset($themes[$theme_id])) {
        wp_die(esc_html__('Invalid theme selection.', 'groove'));
      }
      // Any theme shipping a sample-content.php can be seeded. The definition
      // is null for themes that ship none, which also disables the request.
      $sample_content = \Groove\Themes\Themes_Manager::get_sample_content($theme_id);
      $seed_sample_content = $sample_content !== null && $this->is_sample_seed_requested();

      $default_status = (string) get_option('groove_default_folio_status', 'draft');
      if (!in_array($default_status, ['draft', 'publish'], true)) {
        $default_status = 'draft';
      }

      $title = (string) get_option('groove_default_folio_title', '');
      if ($title === '') {
        $title = esc_html__('A new folio', 'groove');
      }

      $fields = array(
        'post_type' => 'groove_folio',
        'post_status' => $default_status,
        'post_title' => $title,
        'post_name' => sanitize_title($title),
        'post_content' => '',
        'meta_input' => array(
          'theme_id' => $theme_id,
          'subtitle' => '',
          'use_folio' => '1',
          'show_logo' => '1',
          'copyright' => '',
        ),
      );

      if ($theme_id === 'groove-proposal') {
        $fields['meta_input']['proposal_show_in_page_nav'] = '1';
        $fields['meta_input']['proposal_color_scheme'] = 'default';
      }

      if ($seed_sample_content) {
        if ($sample_content['subtitle'] !== '') {
          $fields['meta_input']['subtitle'] = $sample_content['subtitle'];
        }
        foreach ($sample_content['folio_meta'] as $meta_key => $meta_value) {
          $fields['meta_input'][$meta_key] = $meta_value;
        }
      }

      $folio_id = wp_insert_post($fields);

      if (!is_wp_error($folio_id)) {
        if ($seed_sample_content) {
          $this->create_sample_pages((int) $folio_id, $default_status, $sample_content['pages']);
        }

        \Groove\Analytics::track('folio_created', [
          'theme'  => $theme_id,
          'status' => $default_status,
        ]);

        $redirect_url = admin_url('admin.php?page=groove-folio&folio_id=' . $folio_id);
        wp_safe_redirect($redirect_url);
        exit;
      }
      else {
        wp_die(esc_html($folio_id->get_error_message()));
      }
    }
  }

  public function get_title()
  {
    return esc_html__('Add New', 'groove');
  }

  public function create_tabs()
  {
    return array();
  }

  public function display__themes()
  {
    $themes = \Groove\Themes\Themes_Manager::get_all_themes();
    $first_theme_id = '';
    $first_theme_name = '';
    if (!empty($themes)) {
      $saved_default = (string) get_option('groove_default_theme_id', '');
      if ($saved_default !== '' && isset($themes[$saved_default])) {
        $first_theme_id = $saved_default;
      } else {
        $first_theme_id = (string) array_key_first($themes);
      }

      if ($first_theme_id !== '' && isset($themes[$first_theme_id]['name'])) {
        $first_theme_name = (string) $themes[$first_theme_id]['name'];
      }
    }

    if (empty($themes)) {
      echo '<p>' . esc_html__('No themes available. Please install a theme first.', 'groove') . '</p>';
      return;
    }

    $theme_count = count($themes);

    // One toggle per theme that ships sample-content.php. Only the toggle for
    // the selected theme is visible; groove-main.js swaps them on selection via
    // data-add-new-theme-target and re-enables the fields it un-hides.
    $sample_definitions = array();
    foreach (array_keys($themes) as $theme_id) {
      $definition = \Groove\Themes\Themes_Manager::get_sample_content((string) $theme_id);
      if ($definition !== null) {
        $sample_definitions[(string) $theme_id] = $definition;
      }
    }
?>
<p class="g-folio__themes-desc">
  <?php
  /* translators: %s: number of available themes */
  $themes_help_text = sprintf(
    _n('Choose a theme to get started. %s theme available.', 'Choose a theme to get started. %s themes available.', $theme_count, 'groove'),
    number_format_i18n($theme_count)
  );
  echo esc_html($themes_help_text);
  ?>
</p>
<p class="g-folio__themes-selected">
  <?php echo esc_html__('Selected theme:', 'groove'); ?>
  <strong id="g-folio-selected-theme-name"><?php echo esc_html($first_theme_name); ?></strong>
</p>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
  <?php wp_nonce_field('groove_create_folio_action', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="groove_create_folio" />

  <div class="g-folio__themes" role="radiogroup" aria-label="<?php echo esc_attr__('Available themes', 'groove'); ?>">
    <?php
    foreach ($themes as $id => $theme) {
      $is_first = ($id === $first_theme_id);
      $card_classes = 'g-folio__theme-option g-folio__theme-option--add-new relative cursor-pointer rounded-lg border-2 transition-all';
      $card_classes .= $is_first ? ' border-indigo-600 ring-1 ring-indigo-600' : ' border-gray-200 hover:border-gray-300';
      echo '<div class="g-folio__theme-card-wrap">';
      echo '<button type="button" class="' . esc_attr($card_classes) . '"
                 data-theme-id="' . esc_attr($id) . '"
                 data-theme-name="' . esc_attr($theme['name']) . '"
                 role="radio"
                 aria-checked="' . ($is_first ? 'true' : 'false') . '"
                 tabindex="' . ($is_first ? '0' : '-1') . '">';
      echo '<div class="g-folio__theme-option-thumb aspect-w-16 aspect-h-9 overflow-hidden rounded-t-lg rounded-b-none border-b border-gray-200">';
      echo '<img src="' . esc_url($theme['thumbnail_url']) . '" alt="' . esc_attr($theme['name']) . '" class="object-cover w-full h-full" loading="lazy" />';
      echo '</div>';
      echo '<div class="g-folio__theme-option-name p-2 text-center text-sm font-medium text-gray-900 border-t border-gray-100 bg-gray-50/50 rounded-b-lg">';
      echo esc_html($theme['name']);
      echo '</div>';
      echo '<span class="active-badge absolute -top-2 -right-2 inline-flex items-center rounded-full bg-indigo-600 px-2.5 py-0.5 text-xs font-medium text-white shadow-sm ring-2 ring-white ' . ($is_first ? '' : 'hidden') . '">';
      echo esc_html__('Selected', 'groove');
      echo '</span>';
      echo '</button>';
      echo '<button type="button" class="g-theme-preview-btn" data-theme-id="' . esc_attr($id) . '" aria-label="' . esc_attr(sprintf(__('Preview %s theme', 'groove'), $theme['name'])) . '">';
      echo esc_html__('Preview', 'groove');
      echo '</button>';
      echo '</div>';
    }
?>
  </div>
  <input type="hidden" id="g-add-new-theme-id" name="themeId" value="<?php echo esc_attr($first_theme_id)?>" />
  <?php foreach ($sample_definitions as $sample_theme_id => $sample) :
    $is_selected_theme = ($sample_theme_id === $first_theme_id);
    // Fields in a hidden section start disabled so a no-JS submit cannot seed
    // the wrong theme's content; groove-main.js re-enables the visible one.
    $field_disabled = $is_selected_theme ? '' : ' disabled="disabled"';
    $field_id = 'seed_sample_content_' . sanitize_key($sample_theme_id);
    ?>
  <div class="<?php echo $is_selected_theme ? '' : 'hidden'; ?> my-5"
    data-add-new-theme-target="<?php echo esc_attr($sample_theme_id); ?>" data-disable-hidden-fields="1">
    <label for="<?php echo esc_attr($field_id); ?>" class="inline-flex items-center text-sm text-gray-800">
      <input type="hidden" name="seed_sample_content" value="0"<?php echo $field_disabled; ?> />
      <input type="checkbox" id="<?php echo esc_attr($field_id); ?>" name="seed_sample_content" value="1"
        class="mr-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"<?php echo $field_disabled; ?> />
      <?php echo esc_html($sample['label']); ?>
    </label>
    <?php if ($sample['description'] !== '') : ?>
    <p class="m-0 mt-2 text-xs text-gray-500">
      <?php echo esc_html($sample['description']); ?>
    </p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="g-folio__theme-button">
    <button type="submit" class="button button-primary">
      <?php echo esc_html__('Continue', 'groove'); ?>
    </button>
  </div>
</form>
<?php
  }

  /**
   * Whether the submitted form asked for sample content.
   *
   * `seed_sample_content` is the current field; `seed_proposal_sample` is the
   * field this flow used while only groove-proposal could be seeded, and is
   * still accepted so a form submitted from a cached page keeps working.
   *
   * @return bool
   */
  private function is_sample_seed_requested(): bool
  {
    foreach (array('seed_sample_content', 'seed_proposal_sample') as $field) {
      if (!isset($_POST[$field])) {
        continue;
      }
      // The paired hidden input means '0' arrives when the box is unticked.
      if ((string) wp_unslash($_POST[$field]) === '1') {
        return true;
      }
    }

    return false;
  }

  /**
   * Create the folio pages defined by a theme's sample-content.php.
   *
   * @param string $status  Post status inherited from the new folio.
   * @param array  $pages   Normalised pages from Themes_Manager::get_sample_content().
   */
  private function create_sample_pages(int $folio_id, string $status, array $pages): void
  {
    $status = in_array($status, array('draft', 'publish', 'private', 'pending'), true) ? $status : 'draft';

    foreach ($pages as $index => $page) {
      $page_id = wp_insert_post(array(
        'post_type' => 'groove_folio_page',
        'post_status' => $status,
        'post_title' => $page['title'],
        'post_content' => $page['content'],
        'menu_order' => $index + 1,
        'meta_input' => array(
          'folio_id' => $folio_id,
        ),
      ));

      if (is_wp_error($page_id) || !$page_id || empty($page['feature_image'])) {
        continue;
      }

      // Themes that build a hero from the featured image (magazine, newsletter)
      // ask for one by slug. Silently skipped when the placeholder pool has not
      // been curated yet — the page still gets its body content.
      $attachment_id = $this->get_sample_image_attachment_id((string) $page['feature_image']);
      if ($attachment_id > 0) {
        set_post_thumbnail((int) $page_id, $attachment_id);
      }
    }
  }

  /**
   * Attachment ID for a curated placeholder slug, importing it once if needed.
   *
   * The placeholder pool lives on disk rather than in the media library, so the
   * first seed that wants one copies it into uploads and tags the attachment
   * with `_groove_pexels_slug` so later seeds reuse it instead of duplicating.
   *
   * @param string $slug  Manifest slug, e.g. 'ph-cityscape'.
   * @return int          0 when the file has not been curated or the import failed.
   */
  private function get_sample_image_attachment_id(string $slug): int
  {
    static $resolved = array();

    $slug = sanitize_key($slug);
    if ($slug === '') {
      return 0;
    }
    if (isset($resolved[$slug])) {
      return $resolved[$slug];
    }

    $resolved[$slug] = 0;

    $source = \Groove\Themes\Themes_Manager::sample_image_path($slug);
    if ($source === '' || !is_readable($source)) {
      return 0; // Curation has not run — the page simply has no featured image.
    }

    $existing = get_posts(array(
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'posts_per_page' => 1,
      'fields' => 'ids',
      'no_found_rows' => true,
      'meta_key' => '_groove_pexels_slug',
      'meta_value' => $slug,
    ));
    if (!empty($existing)) {
      $resolved[$slug] = (int) $existing[0];
      return $resolved[$slug];
    }

    $contents = file_get_contents($source);
    if ($contents === false) {
      return 0;
    }

    $upload = wp_upload_bits('groove-' . $slug . '.jpg', null, $contents);
    if (!is_array($upload) || !empty($upload['error']) || empty($upload['file'])) {
      return 0;
    }

    $attachment_id = wp_insert_attachment(array(
      'post_mime_type' => 'image/jpeg',
      'post_title' => $slug,
      'post_content' => '',
      'post_status' => 'inherit',
    ), $upload['file']);

    if (is_wp_error($attachment_id) || !$attachment_id) {
      return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata(
      (int) $attachment_id,
      wp_generate_attachment_metadata((int) $attachment_id, $upload['file'])
    );

    update_post_meta((int) $attachment_id, '_groove_pexels_slug', $slug);

    // Alt text comes from the Pexels manifest when it exists; never fatal when
    // the integration or credits.json is absent.
    if (class_exists('\Groove\Pexels\Credits')) {
      $credit = \Groove\Pexels\Credits::get($slug);
      $alt = is_array($credit) && !empty($credit['alt']) ? sanitize_text_field((string) $credit['alt']) : '';
      if ($alt !== '') {
        update_post_meta((int) $attachment_id, '_wp_attachment_image_alt', $alt);
      }
    }

    $resolved[$slug] = (int) $attachment_id;

    return $resolved[$slug];
  }

  public function display_content()
  {
?>
<div class="g-folio__content g-folio__postbox-themes">
  <div class="g-folio__postbox postbox-container" style="width: 656px">
    <div class="postbox">
      <div class="g-folio__postbox-header">
        <div class="g-folio__postbox-header-left">
          <h2 class="g-folio__postbox-title">
            <?php echo esc_html__('Themes', 'groove'); ?>
          </h2>
        </div>
        <div class="g-folio__postbox-header-right">
          <div class="g-folio__postbox-close">
            <?php
            $from = isset($_GET['from']) ? sanitize_key(wp_unslash($_GET['from'])) : 'groove-overview';
            $allowed_from = array('groove-overview', 'groove-all-folios');
            if (!in_array($from, $allowed_from, true)) {
              $from = 'groove-overview';
            }
            ?>
            <a type="submit" class="g-folio__postbox-icon gicon-close"
              href="<?php echo esc_url(add_query_arg(array('page' => $from), admin_url('admin.php'))); ?>"></a>
          </div>
        </div>
      </div>
      <div class="g-folio__postbox-body">
        <?php $this->display__themes()?>
      </div>
    </div>
  </div>
</div>
<?php
  }
}
