<?php
namespace Groove\Pages;
use Groove\Fields\FolioFields;
use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Folio_Menu_Item;
use Groove\List\Folio_Page_List_Table;
use Groove\List\Folio_List_Table;
use Groove\Themes\Default_Themes;
use Groove\Utils\Utils;



if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Folio extends Page
{
  const PAGE_ID = 'groove-folio';
  const POST_TYPE = 'groove_folio_page';

  private $folio;
  private $fields;

  public function __construct()
  {
    $this->add_post_action('save_groove_folio_draft', 'save_folio_draft');
    $this->add_post_action('save_groove_folio', 'save_folio_publish');
    $this->add_post_action('save_groove_folio_manual', 'save_folio_manual');
    $this->add_post_action('save_groove_folio_unpublish', 'save_folio_unpublish');
    $this->add_post_action('auto_save_groove_folio', 'auto_save_folio');

    $folio_id = (int) Utils::get_groove_post_id();
    $folio_copy_link = '';
    $is_published = false;
    if ($folio_id > 0) {
      $folio_post = get_post($folio_id);
      if ($folio_post && $folio_post->post_type === 'groove_folio' && $folio_post->post_status === 'publish') {
        $is_published = true;
      }

      $folio_copy_link = (string) Utils::get_folio_permalink($folio_post, '');
      if ($folio_copy_link === '') {
        $folio_copy_link = (string) Utils::get_folio_permalink_by_id($folio_id);
      }
    }

    $preview_text = $is_published ? 'View' : 'Preview';
    $preview_tooltip_text = $is_published ? __('View Folio', 'groove-folios') : __('Preview Folio', 'groove-folios');
    $preview_link = $is_published
      ? $folio_copy_link
      : Utils::get_folio_permalink_by_id(Utils::get_groove_post_id());
    $publish_button_text = $is_published ? 'Unpublish' : 'Publish';
    $publish_button_type = $is_published ? 'secondary' : 'primary';
    $publish_button_action = $is_published ? 'save_groove_folio_unpublish' : 'save_groove_folio';

    $this->left_button_items = [
      array(
        'text' => 'Add Page',
        'type' => '',
        'link' => add_query_arg(
          array(
            'post_type' => 'groove_folio_page',
            'folio_id' => (int) Utils::get_groove_post_id(),
          ),
          admin_url('post-new.php')
        )
      )
    ];

    $this->right_button_items = [
      array(
        'text' => $preview_text,
        'type' => 'secondary',
        'ui' => 'wp',
        'link' => $preview_link,
        'class' => 'g-tooltip-button',
        'attrs' => array(
          'id' => 'g-folio-preview-link',
          'title' => $preview_tooltip_text,
          'data-tooltip-text' => $preview_tooltip_text,
          'aria-label' => $preview_tooltip_text,
        ),
      ),
      array(
        'text' => 'Save',
        'type' => 'secondary',
        'ui' => 'wp',
        'action' => 'save_groove_folio_manual'
      ),
      array(
        'text' => $publish_button_text,
        'type' => $publish_button_type,
        'ui' => 'wp',
        'action' => $publish_button_action
      ),
      array(
        'text' => __('Copy link', 'groove-folios'),
        'type' => 'secondary',
        'ui' => 'wp',
        'action' => 'copy_groove_folio_link',
        'button_type' => 'button',
        'icon' => 'dashicons-admin-links',
        'class' => 'g-tooltip-button',
        'attrs' => array(
          'id' => 'g-copy-folio-link',
          'aria-label' => __('Copy link', 'groove-folios'),
          'data-copy-link' => $folio_copy_link,
          'data-copy-text' => __('Copy link', 'groove-folios'),
          'data-copied-text' => __('Copied', 'groove-folios'),
          'data-tooltip-text' => __('Copy link', 'groove-folios'),
        ),
      )
    ];

    add_action('save_post', [$this, 'save_post']);

    add_action('wp_ajax_groove_folio_inline_save', [$this, 'inline_save']);

    add_action('groove/menu/register', function (Menu_Manager $menu) {
      $menu->register(static::PAGE_ID, new Folio_Menu_Item($this));
    }, Overview::MENU_PRIORITY + 20);

    add_action('current_screen', function () {
      // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view parameters: which admin screen and which folio to open; nothing is written.
      $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
      if ($current_page !== static::PAGE_ID) {
        return;
      }

      $folio_id = isset($_GET['folio_id']) ? intval(wp_unslash($_GET['folio_id'])) : 0;

      if (!isset($_GET['folio_id'])) {
        $this->redirect_to_all_folios();
      } else if (!$folio_id) {
        $this->redirect_to_all_folios();
      } else {
        $this->get_folio_fields();
      }
      // phpcs:enable WordPress.Security.NonceVerification.Recommended
    });
  }

  public function inline_save()
  {
    global $mode;

    check_ajax_referer('inlineeditnonce', '_inline_edit');

    if (!isset($_POST['post_ID']) || !intval(wp_unslash($_POST['post_ID']))) {
      wp_die();
    }

    $post_id = intval(wp_unslash($_POST['post_ID']));


    $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
    if ('page' === $post_type) {
      if (!current_user_can('edit_page', $post_id)) {
        wp_die(esc_html__('Sorry, you are not allowed to edit this page.', 'groove-folios'));
      }
    } else {
      if (!current_user_can('edit_post', $post_id)) {
        wp_die(esc_html__('Sorry, you are not allowed to edit this post.', 'groove-folios'));
      }
    }

    $last = wp_check_post_lock($post_id);
    if ($last) {
      $last_user = get_userdata($last);
      $last_user_name = $last_user ? $last_user->display_name : __('Someone', 'groove-folios');

      /* translators: %s: User's display name. */
      $msg_template = __('Saving is disabled: %s is currently editing this post.', 'groove-folios');

      if ('page' === $post_type) {
        /* translators: %s: User's display name. */
        $msg_template = __('Saving is disabled: %s is currently editing this page.', 'groove-folios');
      }

      printf(esc_html($msg_template), esc_html($last_user_name));
      wp_die();
    }

    $data = &$_POST;
    $post = get_post($post_id, ARRAY_A);

    // Since it's coming from the database.
    $post = wp_slash($post);

    $data['content'] = $post['post_content'];
    $data['excerpt'] = $post['post_excerpt'];

    // Rename.
    $data['user_ID'] = get_current_user_id();

    if (isset($data['post_parent'])) {
      $data['parent_id'] = $data['post_parent'];
    }

    // Status.
    if (isset($data['keep_private']) && 'private' === $data['keep_private']) {
      $data['visibility'] = 'private';
      $data['post_status'] = 'private';
    } else {
      $data['post_status'] = $data['_status'];
    }

    if (empty($data['comment_status'])) {
      $data['comment_status'] = 'closed';
    }

    if (empty($data['ping_status'])) {
      $data['ping_status'] = 'closed';
    }

    // Exclude terms from taxonomies that are not supposed to appear in Quick Edit.
    if (!empty($data['tax_input'])) {
      foreach ($data['tax_input'] as $taxonomy => $terms) {
        $tax_object = get_taxonomy($taxonomy);
        /** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own Quick Edit filter, applied exactly as wp_ajax_inline_save() does so other plugins' settings are honoured.
        if (!apply_filters('quick_edit_show_taxonomy', $tax_object->show_in_quick_edit, $taxonomy, $post['post_type'])) {
          unset($data['tax_input'][$taxonomy]);
        }
      }
    }

    // Hack: wp_unique_post_slug() doesn't work for drafts, so we will fake that our post is published.
    if (!empty($data['post_name']) && in_array($post['post_status'], array('draft', 'pending'), true)) {
      $post['post_status'] = 'publish';
      $data['post_name'] = wp_unique_post_slug($data['post_name'], $post['ID'], $post['post_status'], $post['post_type'], $post['post_parent']);
    }

    // Update the post.
    edit_post();

    $saved_post = get_post($post_id);
    $saved_post_type = $saved_post ? $saved_post->post_type : static::POST_TYPE;

    if ($saved_post_type === 'groove_folio') {
      $table = new Folio_List_Table($this, 'groove_folio');
    } else {
      $table = new Folio_Page_List_Table($this, static::POST_TYPE);
    }

    $post_view = isset($_POST['post_view']) ? sanitize_key(wp_unslash($_POST['post_view'])) : '';
    $mode = 'excerpt' === $post_view ? 'excerpt' : 'list';

    $level = 0;
    if (is_post_type_hierarchical($table->screen->post_type)) {
      $request_post = array($saved_post);
      $parent = $request_post[0]->post_parent;

      while ($parent > 0) {
        $parent_post = get_post($parent);
        $parent = $parent_post->post_parent;
        $level++;
      }
    }

    $table->ajax_rows(array($saved_post), $level);

    wp_die();
  }

  public function save_post($post_id)
  {
    if ('groove_folio_page' === get_post_type($post_id)) {
      if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
      }
      if (!current_user_can('edit_post', $post_id)) {
        return;
      }
      $folio_id = get_post_meta($post_id, 'folio_id', true);
      // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- save_post fires either inside a save whose request verified its own nonce (post.php's update-post_{ID}, Quick Edit's inlineeditnonce) or on core's auto-draft when post-new.php opens (a screen load core runs without a nonce); edit_post is checked above, and the int must name a groove_folio. It only links the page to that folio.
      if (!$folio_id && isset($_POST['folio_id'])) {
        $folio_id = intval(wp_unslash($_POST['folio_id']));
      }
      if (!$folio_id && isset($_GET['folio_id'])) {
        $folio_id = intval(wp_unslash($_GET['folio_id']));
      }
      // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
      if ($folio_id && 'groove_folio' !== get_post_type($folio_id)) {
        return;
      }
      update_post_meta($post_id, 'folio_id', $folio_id);
    }
  }

  public function get_fields()
  {
    return $this->get_folio_fields();
  }

  public function get_folio_fields()
  {
    if ($this->folio == null) {
      if (isset($_GET['folio_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: the folio this screen edits.
        $id = intval(wp_unslash($_GET['folio_id'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
        if (!$id) {
          $this->redirect_to_all_folios();
        }
        $args = array(
          'posts_per_page' => 1, // 获取一条数据
          'post__in' => array($id),
          'post_type' => 'groove_folio',
          'post_status' => array('draft', 'publish', 'pending', 'private')
        );
        $wp_query = new \WP_Query($args);

        if (!$wp_query->have_posts()) {
          $this->redirect_to_all_folios();
        } else {
          $this->folio = $wp_query->post;
          $this->fields = new FolioFields($this->folio);
        }
      }
    }

    return $this->fields;
  }

  public function redirect_to_all_folios()
  {
    $redirect_url = add_query_arg(array('page' => 'groove-all-folios'), admin_url('admin.php'));
    wp_safe_redirect($redirect_url);
    exit;
  }

  public function save_folio_draft()
  {
    $this->save_folio('draft');
  }

  public function save_folio_publish()
  {
    $this->save_folio('publish');
  }

  public function save_folio_unpublish()
  {
    $this->save_folio('draft');
  }

  public function save_folio_manual()
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only reads the folio's current status; save_folio() runs check_admin_referer('groove_save_folio', 'groove_nonce') before anything is written.
    $id = isset($_POST['folio_id']) ? intval(wp_unslash($_POST['folio_id'])) : 0;
    $this->save_folio($this->get_current_folio_status($id, 'draft'));
  }

  public function auto_save_folio()
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only reads the folio's current status; save_folio() runs check_admin_referer('groove_save_folio', 'groove_nonce') before anything is written.
    $id = isset($_POST['folio_id']) ? intval(wp_unslash($_POST['folio_id'])) : 0;
    $this->save_folio($this->get_current_folio_status($id, 'draft'));
  }

  private function get_current_folio_status($id, $fallback_status = 'draft')
  {
    if (!$id) {
      return $fallback_status;
    }

    $post = get_post($id);
    if (!$post || $post->post_type !== 'groove_folio') {
      return $fallback_status;
    }

    $status = get_post_status($post);
    if (!is_string($status) || $status === '' || $status === 'trash') {
      return $fallback_status;
    }

    return $status;
  }

  public function save_folio($post_status = 'publish')
  {
    if (!is_string($post_status) || empty($post_status)) {
      $post_status = 'publish';
    }

    // Nonce check must come before reading any POST data.
    check_admin_referer('groove_save_folio', 'groove_nonce');

    if (empty($_POST['folio_id'])) {
      wp_send_json(array('code' => 400, 'message' => 'Missing folio ID.'));
      return;
    }

    $id = intval(wp_unslash($_POST['folio_id']));

    if (!current_user_can('edit_post', $id)) {
      wp_send_json(array('code' => 403, 'message' => 'Forbidden'));
      return;
    }

    $folio_post = get_post($id);
    if (!$folio_post || $folio_post->post_type !== 'groove_folio') {
      wp_send_json(array('code' => 404, 'message' => 'Folio not found.'));
      return;
    }

    $fields = new FolioFields($folio_post);

    $post_title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : $fields->title;
    $post_author = $fields->author;
    $subtitle = isset($_POST['subtitle']) ? sanitize_text_field(wp_unslash($_POST['subtitle'])) : $fields->subtitle;
    $byline = isset($_POST['byline']) ? intval(wp_unslash($_POST['byline'])) : (int) $fields->byline;
    if ($byline <= 0) {
      $byline = 0;
      $show_byline = '0';
    } elseif (!get_userdata($byline)) {
      $byline = $post_author;
      $show_byline = '1';
    } else {
      $show_byline = '1';
    }
    $copyright = isset($_POST['copyright']) ? sanitize_text_field(wp_unslash($_POST['copyright'])) : $fields->copyright;
    $use_folio = isset($_POST['use_folio']) ? sanitize_key(wp_unslash($_POST['use_folio'])) : $fields->use_folio;
    $use_folio = $use_folio === '1' ? '1' : '0';
    $show_logo = isset($_POST['show_logo']) ? sanitize_key(wp_unslash($_POST['show_logo'])) : $fields->show_logo;
    $show_logo = $show_logo === '1' ? '1' : '0';
    $theme_id = isset($_POST['theme_id']) ? sanitize_key(wp_unslash($_POST['theme_id'])) : $fields->theme_id;
    $header_font = isset($_POST['header_font']) ? sanitize_key(wp_unslash($_POST['header_font'])) : $fields->header_font;
    $header_font = Utils::normalize_primary_font_key($header_font);
    $body_font = isset($_POST['body_font']) ? sanitize_key(wp_unslash($_POST['body_font'])) : $fields->body_font;
    $body_font = Utils::normalize_primary_font_key($body_font);
    $on_this_page_label = isset($_POST['on_this_page_label']) ? sanitize_text_field(wp_unslash($_POST['on_this_page_label'])) : $fields->on_this_page_label;
    $on_this_page_label = Utils::sanitize_on_this_page_label($on_this_page_label);
    $proposal_version = isset($_POST['proposal_version']) ? sanitize_text_field(wp_unslash($_POST['proposal_version'])) : (string) ($fields->proposal_version ?? '');

    // Auto-increment version on explicit publish if the user didn't manually change it.
    $is_explicit_publish = isset($_POST['action']) && $_POST['action'] === 'save_groove_folio';
    $stored_version = (string) ($fields->proposal_version ?? '');
    if ($is_explicit_publish && $proposal_version === $stored_version && preg_match('/^(v?)(\d+)\.(\d+)$/', $proposal_version, $m)) {
      $proposal_version = $m[1] . $m[2] . '.' . ((int) $m[3] + 1);
    }

    $proposal_status = $this->get_folio_status_label($post_status);
    $proposal_prepared_for = isset($_POST['proposal_prepared_for']) ? sanitize_text_field(wp_unslash($_POST['proposal_prepared_for'])) : (string) ($fields->proposal_prepared_for ?? '');
    $proposal_prepared_by = isset($_POST['proposal_prepared_by']) ? sanitize_text_field(wp_unslash($_POST['proposal_prepared_by'])) : (string) ($fields->proposal_prepared_by ?? '');
    $proposal_contact_email = isset($_POST['proposal_contact_email']) ? sanitize_email(wp_unslash($_POST['proposal_contact_email'])) : (string) ($fields->proposal_contact_email ?? '');
    $proposal_contact_name = isset($_POST['proposal_contact_name']) ? sanitize_text_field(wp_unslash($_POST['proposal_contact_name'])) : (string) ($fields->proposal_contact_name ?? '');
    $proposal_contact_role = isset($_POST['proposal_contact_role']) ? sanitize_text_field(wp_unslash($_POST['proposal_contact_role'])) : (string) ($fields->proposal_contact_role ?? '');
    $proposal_contact_phone = isset($_POST['proposal_contact_phone']) ? sanitize_text_field(wp_unslash($_POST['proposal_contact_phone'])) : (string) ($fields->proposal_contact_phone ?? '');
    $proposal_contact_linkedin = isset($_POST['proposal_contact_linkedin']) ? esc_url_raw(wp_unslash($_POST['proposal_contact_linkedin'])) : (string) ($fields->proposal_contact_linkedin ?? '');
    $proposal_contacts = isset($_POST['proposal_contacts']) ? sanitize_textarea_field(wp_unslash($_POST['proposal_contacts'])) : (string) ($fields->proposal_contacts ?? '');
    $proposal_client_name = isset($_POST['proposal_client_name']) ? sanitize_text_field(wp_unslash($_POST['proposal_client_name'])) : (string) ($fields->proposal_client_name ?? '');
    $proposal_client_logo_url = isset($_POST['proposal_client_logo_url']) ? esc_url_raw(wp_unslash($_POST['proposal_client_logo_url'])) : (string) ($fields->proposal_client_logo_url ?? '');
    $proposal_date = isset($_POST['proposal_date']) ? sanitize_text_field(wp_unslash($_POST['proposal_date'])) : (string) ($fields->proposal_date ?? '');
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $proposal_date)) {
      $proposal_date = '';
    }
    $proposal_show_in_page_nav = isset($_POST['proposal_show_in_page_nav']) ? sanitize_key(wp_unslash($_POST['proposal_show_in_page_nav'])) : (string) ($fields->proposal_show_in_page_nav ?? '1');
    $proposal_show_in_page_nav = $proposal_show_in_page_nav === '0' ? '0' : '1';
    $proposal_color_scheme = isset($_POST['proposal_color_scheme']) ? sanitize_key(wp_unslash($_POST['proposal_color_scheme'])) : (string) ($fields->proposal_color_scheme ?? 'default');
    $proposal_color_scheme = $proposal_color_scheme === 'dynamic' ? 'dynamic' : 'default';
    $proposal_open_text = isset($_POST['proposal_open_text']) ? sanitize_text_field(wp_unslash($_POST['proposal_open_text'])) : (string) ($fields->proposal_open_text ?? '');
    $collection_tags_raw = isset($_POST['collection_tags']) ? sanitize_text_field(wp_unslash($_POST['collection_tags'])) : '';
    $collection_tag_names = array_values(array_unique(array_filter(array_map('trim', explode(',', $collection_tags_raw)))));

    // feature_image_id: empty-string means 'clear image', positive int means 'set image'.
    // A missing or null field means 'keep existing' — we do NOT delete in that case.
    $feature_image_id = isset($_POST['feature_image_id']) ? intval(wp_unslash($_POST['feature_image_id'])) : -1;
    $logo_id = isset($_POST['logo_id']) ? intval(wp_unslash($_POST['logo_id'])) : -1;

    // Derive slug from title and ensure it is unique for this post.
    $desired_slug = sanitize_title($post_title);
    $post_name = wp_unique_post_slug(
      $desired_slug,
      $id,
      $post_status,
      'groove_folio',
      0
    );

    $update_args = array(
      'ID' => $id,
      'post_status' => $post_status,
      // Password protection is temporarily disabled for folios.
      'post_password' => '',
      'post_title' => $post_title,
      'post_name' => $post_name,
      'post_author' => $post_author,
      'post_content' => '',
      'meta_input' => array(
        'theme_id' => $theme_id,
        'subtitle' => $subtitle,
        'byline' => $byline,
        'show_byline' => $show_byline,
        'copyright' => $copyright,
        'use_folio' => $use_folio,
        'show_logo' => $show_logo,
        // Keep legacy `fonts` synced for existing theme CSS/older installs.
        'fonts' => $body_font,
        'header_font' => $header_font,
        'body_font' => $body_font,
        'on_this_page_label' => $on_this_page_label,
        'proposal_version' => $proposal_version,
        'proposal_status' => $proposal_status,
        'proposal_prepared_for' => $proposal_prepared_for,
        'proposal_prepared_by' => $proposal_prepared_by,
        'proposal_contact_email' => $proposal_contact_email,
        'proposal_contact_name' => $proposal_contact_name,
        'proposal_contact_role' => $proposal_contact_role,
        'proposal_contact_phone' => $proposal_contact_phone,
        'proposal_contact_linkedin' => $proposal_contact_linkedin,
        'proposal_contacts' => $proposal_contacts,
        'proposal_client_name' => $proposal_client_name,
        'proposal_client_logo_url' => $proposal_client_logo_url,
        'proposal_date' => $proposal_date,
        'proposal_show_in_page_nav' => $proposal_show_in_page_nav,
        'proposal_color_scheme' => $proposal_color_scheme,
        'proposal_open_text' => $proposal_open_text,
      )
    );

    $folio_result = wp_update_post($update_args);

    if (!is_wp_error($folio_result)) {
      wp_set_object_terms($id, $collection_tag_names, 'groove_collection_tag', false);
    }

    // Record a revision log entry on explicit publish.
    if ($is_explicit_publish && !is_wp_error($folio_result)) {
      $revision_log_raw = get_post_meta($id, 'proposal_revision_log', true);
      $revision_log = is_string($revision_log_raw) && $revision_log_raw !== '' ? json_decode($revision_log_raw, true) : array();
      if (!is_array($revision_log)) {
        $revision_log = array();
      }

      $revision_note = isset($_POST['proposal_revision_note']) ? sanitize_text_field(wp_unslash($_POST['proposal_revision_note'])) : '';
      $current_user = wp_get_current_user();

      $revision_log[] = array(
        'version' => $proposal_version,
        'status'  => $proposal_status,
        'date'    => gmdate('c'),
        'user'    => $current_user->display_name ?: $current_user->user_login,
        'note'    => $revision_note,
      );

      // Cap at 50 entries, keeping the most recent.
      if (count($revision_log) > 50) {
        $revision_log = array_slice($revision_log, -50);
      }

      update_post_meta($id, 'proposal_revision_log', wp_json_encode($revision_log));
    }

    if ($feature_image_id === 0) {
      // Explicitly cleared (value was sent as empty string -> 0).
      delete_post_thumbnail($id);
    } elseif ($feature_image_id > 0) {
      set_post_thumbnail($id, $feature_image_id);
    }
    // $feature_image_id === -1 means field was not sent; keep existing thumbnail.

    if ($logo_id === 0) {
      delete_post_meta($id, 'logo_id');
    } elseif ($logo_id > 0) {
      update_post_meta($id, 'logo_id', $logo_id);
    }
    // $logo_id === -1 means field was not sent; keep existing logo.

    // Newsletter colors are now generated per-visitor from local time.
    delete_post_meta($id, 'newsletter_theme_preset');

    if ($post_status === 'publish' && !is_wp_error($folio_result)) {
      $pages_query = new \WP_Query(array(
        'post_type' => 'groove_folio_page',
        'post_status' => 'any',
        'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- folio_id meta is the page-to-folio link; this fetches one folio's pages to publish them with it.
          array(
            'key' => 'folio_id',
            'value' => $id,
            'compare' => '='
          )
        ),
        'posts_per_page' => -1
      ));

      if ($pages_query->have_posts()) {
        foreach ($pages_query->posts as $child_page) {
          if ($child_page->post_status !== 'publish') {
            wp_update_post(array(
              'ID' => $child_page->ID,
              'post_status' => 'publish'
            ));
          }
        }
      }
    }

    if (!is_wp_error($folio_result)) {
      // The JS button handler uses AJAX and expects JSON {code:0} to trigger location.reload().
      wp_send_json(array(
        'code' => 0,
        'post_id' => $id,
        'slug' => $post_name,
      ));
    } else {
      wp_send_json(array(
        'code' => 500,
        'message' => $folio_result->get_error_message(),
      ));
    }
  }

  public function get_title()
  {
    $fields = $this->get_folio_fields();
    if ($fields && !empty($fields->title)) {
      return $fields->title;
    }

    return esc_html__('Folio', 'groove-folios');
  }

  private function get_collection_tag_names($folio_id)
  {
    $terms = get_the_terms((int) $folio_id, 'groove_collection_tag');
    if (is_wp_error($terms) || empty($terms)) {
      return array();
    }

    return array_values(array_filter(array_map(function ($term) {
      return $term instanceof \WP_Term ? (string) $term->name : '';
    }, $terms)));
  }

  public function create_tabs()
  {
    $fields = $this->get_fields();
    $is_proposal_theme = (string) ($fields->theme_id ?? '') === 'groove-proposal';

    $tabs = [
      'setup' => [
        'label' => esc_html__('Setup', 'groove-folios'),
      ],
      'pages' => [
        'label' => esc_html__('Pages', 'groove-folios'),
      ]
    ];

    if ($is_proposal_theme) {
      $tabs['proposal'] = [
        'label' => esc_html__('Proposal', 'groove-folios'),
        'attrs' => ['data-theme-target' => 'groove-proposal'],
      ];
    }

    return $tabs;
  }

  public function display_content()
  {
    $tabs = $this->get_tabs();
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'setup'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: which tab to show.
    ?>
    <div class="g-top-bar-tabs-content">
      <?php
      foreach ($tabs as $tab_id => $tab) {
        $style = 'display: none';

        if ($tab_key === $tab_id) {
          $style = 'display: block';
        }

        echo '<div data-tab-content-id="' . esc_attr($tab_id) . '" style="' . esc_attr($style) . '">';

        if ('setup' === $tab_id) {
          $this->display_tab_fields();
        } elseif ('proposal' === $tab_id) {
          $this->display_tab_proposal();
        } else {
          $this->display_tab_pages();
        }
        echo "</div>";
      }
      ?>
    </div>
    <?php
  }

  public function display_tab_fields()
  {
    $fields = $this->get_folio_fields();
    ?>
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <input hidden name="folio_id" value="<?php echo esc_attr($fields->ID) ?>" />
      <div class="lg:col-span-2 space-y-6">
        <?php $this->display_essentials() ?>
      </div>

      <div class="space-y-6">
        <?php $this->display_customization() ?>
        <?php $this->display_publishing() ?>
      </div>
    </div>
    <?php
  }

  public function display_tab_proposal()
  {
    $fields = $this->get_fields();
    $proposal_version          = (string) ($fields->proposal_version ?? '');
    $proposal_status           = $this->get_folio_status_label((string) ($this->folio->post_status ?? 'draft'));
    $proposal_prepared_for     = (string) ($fields->proposal_prepared_for ?? '');
    $proposal_contact_email    = (string) ($fields->proposal_contact_email ?? '');
    $proposal_contact_name     = (string) ($fields->proposal_contact_name ?? '');
    $proposal_contact_role     = (string) ($fields->proposal_contact_role ?? '');
    $proposal_contact_phone    = (string) ($fields->proposal_contact_phone ?? '');
    $proposal_contact_linkedin = (string) ($fields->proposal_contact_linkedin ?? '');
    $proposal_contacts         = (string) ($fields->proposal_contacts ?? '');
    $proposal_client_name      = (string) ($fields->proposal_client_name ?? '');
    $proposal_client_logo_url  = (string) ($fields->proposal_client_logo_url ?? '');
    $proposal_date             = (string) ($fields->proposal_date ?? '');
    $proposal_show_in_page_nav = (string) ($fields->proposal_show_in_page_nav ?? '1') !== '0';
    $proposal_color_scheme     = (string) ($fields->proposal_color_scheme ?? 'default');
    $proposal_color_scheme     = $proposal_color_scheme === 'dynamic' ? 'dynamic' : 'default';
    $proposal_open_text        = (string) ($fields->proposal_open_text ?? '');
    ?>
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- Left column: Document + Client -->
      <div class="min-w-0 space-y-6">

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
            <h3 class="text-sm font-semibold text-gray-800 m-0"><?php esc_html_e('Document', 'groove-folios'); ?></h3>
          </div>
          <div class="p-4 space-y-4">
            <div>
              <label for="proposal_version" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Version', 'groove-folios'); ?></label>
              <input type="text" id="proposal_version" name="proposal_version"
                value="<?php echo esc_attr($proposal_version); ?>"
                placeholder="v1.0"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
              <p class="mt-1 mb-0 text-xs text-gray-400"><?php esc_html_e('Auto-increments on publish. Edit to override.', 'groove-folios'); ?></p>
              <button
                type="button"
                class="button-link mt-2"
                data-version-history-open>
                <?php esc_html_e('View version history', 'groove-folios'); ?>
              </button>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Status', 'groove-folios'); ?></label>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-sm font-medium text-gray-700"><?php echo esc_html($proposal_status ?: __('Draft', 'groove-folios')); ?></span>
              </div>
              <p class="mt-1 mb-0 text-xs text-gray-400"><?php esc_html_e('Read-only. Mirrors the folio publish status.', 'groove-folios'); ?></p>
            </div>
            <div>
              <label for="proposal_date" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Date', 'groove-folios'); ?></label>
              <input type="date" id="proposal_date" name="proposal_date"
                value="<?php echo esc_attr($proposal_date); ?>"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            </div>
            <div>
              <label for="proposal_revision_note" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Revision Note', 'groove-folios'); ?></label>
              <input type="text" id="proposal_revision_note" name="proposal_revision_note"
                value=""
                placeholder="<?php esc_attr_e('Optional note for this version', 'groove-folios'); ?>"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
              <p class="mt-1 mb-0 text-xs text-gray-400"><?php esc_html_e('Included in version history when you publish. Not saved between sessions.', 'groove-folios'); ?></p>
            </div>
          </div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
            <h3 class="text-sm font-semibold text-gray-800 m-0"><?php esc_html_e('Client', 'groove-folios'); ?></h3>
          </div>
          <div class="p-4 space-y-4">
            <div>
              <label for="proposal_client_name" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Client Name', 'groove-folios'); ?></label>
              <input type="text" id="proposal_client_name" name="proposal_client_name"
                value="<?php echo esc_attr($proposal_client_name); ?>"
                placeholder="Client Name"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Client Logo', 'groove-folios'); ?></label>
              <?php
                $default_client_logo_url = GROOVE_URL . 'themes/groove-proposal/assets/images/theme-g-logo.png';
                $client_logo_preview = $proposal_client_logo_url !== '' ? esc_url_raw($proposal_client_logo_url) : '';
              ?>
              <input type="hidden" id="proposal_client_logo_url" name="proposal_client_logo_url"
                value="<?php echo esc_attr($proposal_client_logo_url); ?>" />
              <div class="flex items-start space-x-4">
                <div class="g-folio__media-preview-frame flex items-center justify-center rounded border border-gray-200 bg-gray-50 p-2 <?php echo $client_logo_preview === '' ? 'hidden' : ''; ?>"
                  id="g-client-logo-preview-frame">
                  <img id="g-client-logo-preview" class="g-folio__media-preview-image"
                    src="<?php echo esc_url($client_logo_preview !== '' ? $client_logo_preview : $default_client_logo_url); ?>" />
                </div>
                <div class="flex flex-col space-y-2">
                  <button type="button" id="g-client-logo-select" class="button button-secondary"><?php echo $client_logo_preview !== '' ? esc_html__('Replace logo', 'groove-folios') : esc_html__('Select logo', 'groove-folios'); ?></button>
                  <button type="button" id="g-client-logo-default" data-default-url="<?php echo esc_url($default_client_logo_url); ?>"
                    class="button-link <?php echo $client_logo_preview === '' ? 'hidden' : ''; ?>"><?php esc_html_e('Use default', 'groove-folios'); ?></button>
                  <button type="button" id="g-client-logo-remove"
                    class="button-link text-red-600 <?php echo $client_logo_preview === '' ? 'hidden' : ''; ?>"><?php esc_html_e('Remove', 'groove-folios'); ?></button>
                </div>
              </div>
            </div>
            <div>
              <label for="proposal_prepared_for" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
                <?php esc_html_e('Prepared For', 'groove-folios'); ?>
                <span class="font-normal text-gray-400 normal-case tracking-normal ml-1">(<?php esc_html_e('legacy', 'groove-folios'); ?>)</span>
              </label>
              <input type="text" id="proposal_prepared_for" name="proposal_prepared_for"
                value="<?php echo esc_attr($proposal_prepared_for); ?>"
                placeholder="Client Name"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
              <p class="mt-1 mb-0 text-xs text-gray-400"><?php esc_html_e('Superseded by Client Name above when set.', 'groove-folios'); ?></p>
            </div>
          </div>
        </div>

      </div>

      <!-- Right column: Contact + Additional Contacts + Behaviour -->
      <div class="min-w-0 space-y-6">

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
            <h3 class="text-sm font-semibold text-gray-800 m-0"><?php esc_html_e('Primary Contact', 'groove-folios'); ?></h3>
          </div>
          <div class="p-4 space-y-4">
            <div>
              <label for="proposal_contact_name" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Name', 'groove-folios'); ?></label>
              <input type="text" id="proposal_contact_name" name="proposal_contact_name"
                value="<?php echo esc_attr($proposal_contact_name); ?>"
                placeholder="Alex Morgan"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            </div>
            <div>
              <label for="proposal_contact_role" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Role', 'groove-folios'); ?></label>
              <input type="text" id="proposal_contact_role" name="proposal_contact_role"
                value="<?php echo esc_attr($proposal_contact_role); ?>"
                placeholder="Engagement Lead"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            </div>
            <div>
              <label for="proposal_contact_email" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Email', 'groove-folios'); ?></label>
              <input type="email" id="proposal_contact_email" name="proposal_contact_email"
                value="<?php echo esc_attr($proposal_contact_email); ?>"
                placeholder="hello@example.com"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            </div>
            <div>
              <label for="proposal_contact_phone" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Phone', 'groove-folios'); ?></label>
              <input type="text" id="proposal_contact_phone" name="proposal_contact_phone"
                value="<?php echo esc_attr($proposal_contact_phone); ?>"
                placeholder="+1 (555) 123-4567"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            </div>
          </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
            <h3 class="text-sm font-semibold text-gray-800 m-0"><?php esc_html_e('Additional Contacts', 'groove-folios'); ?></h3>
          </div>
          <div class="p-4">
            <textarea id="proposal_contacts" name="proposal_contacts" rows="5"
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              placeholder="Name | Role | email@example.com | +1 555-555-5555 | https://linkedin.com/in/username&#10;Name | Role | email@example.com"><?php echo esc_textarea($proposal_contacts); ?></textarea>
            <p class="mt-2 mb-0 text-xs text-gray-500">
              <?php esc_html_e('Optional. One contact per line using pipes: Name | Role | Email | Phone | LinkedIn URL. When set, replaces the primary contact above.', 'groove-folios'); ?>
            </p>
          </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
            <h3 class="text-sm font-semibold text-gray-800 m-0"><?php esc_html_e('Behaviour', 'groove-folios'); ?></h3>
          </div>
          <div class="p-4 space-y-4">
            <div>
              <label for="proposal_open_text" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
                <?php esc_html_e('Cover CTA Text', 'groove-folios'); ?>
              </label>
              <input type="text" id="proposal_open_text" name="proposal_open_text"
                value="<?php echo esc_attr($proposal_open_text); ?>"
                placeholder="<?php esc_attr_e('Open proposal', 'groove-folios'); ?>"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
              <p class="mt-2 mb-0 text-xs text-gray-500">
                <?php esc_html_e('Optional. Defaults to “Open proposal” on the cover CTA.', 'groove-folios'); ?>
              </p>
            </div>
            <div>
              <label for="proposal_color_scheme" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
                <?php esc_html_e('Color Scheme', 'groove-folios'); ?>
              </label>
              <select id="proposal_color_scheme" name="proposal_color_scheme"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="default" <?php selected($proposal_color_scheme, 'default'); ?>><?php esc_html_e('Default', 'groove-folios'); ?></option>
                <option value="dynamic" <?php selected($proposal_color_scheme, 'dynamic'); ?>><?php esc_html_e('Dynamic (from feature image)', 'groove-folios'); ?></option>
              </select>
              <p class="mt-2 mb-0 text-xs text-gray-500">
                <?php esc_html_e('Dynamic mode extracts accent colors from the folio feature image and applies them across cover and pages.', 'groove-folios'); ?>
              </p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
                <?php esc_html_e('In-Page Navigation', 'groove-folios'); ?>
              </label>
              <div class="flex items-center gap-2">
                <input type="hidden" name="proposal_show_in_page_nav" value="0" />
                <input type="checkbox" id="g-proposal-show-in-page-nav" name="proposal_show_in_page_nav" value="1"
                  class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  <?php echo checked($proposal_show_in_page_nav, true, false); ?> />
                <label for="g-proposal-show-in-page-nav" class="text-sm text-gray-700">
                  <?php esc_html_e('Show in-page section navigation', 'groove-folios'); ?>
                </label>
              </div>
              <p class="mt-2 mb-0 text-xs text-gray-500"><?php esc_html_e('When enabled, an "On this page" sidebar shows links to sections within each page.', 'groove-folios'); ?></p>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php $this->display_version_history(); ?>
    <?php
  }

  public function display_version_history()
  {
    $fields = $this->get_fields();
    $log_raw = (string) ($fields->proposal_revision_log ?? '[]');
    $revision_log = json_decode($log_raw, true);
    if (!is_array($revision_log)) {
      $revision_log = array();
    }
    // Show newest first.
    $revision_log = array_reverse($revision_log);
    $total = count($revision_log);
    $visible_limit = 10;
    ?>
    <div
      id="g-version-history-modal"
      class="g-theme-picker-modal hidden"
      aria-hidden="true">
      <div class="g-theme-picker-modal__backdrop" data-version-history-close></div>
      <div class="g-theme-picker-modal__frame g-version-history-modal__frame">
        <div class="g-theme-picker-modal__header">
          <div class="g-theme-picker-modal__heading">
            <h3 class="g-theme-picker-modal__title"><?php esc_html_e('Version History', 'groove-folios'); ?></h3>
            <p class="g-theme-picker-modal__subtitle"><?php esc_html_e('History is recorded each time you publish the proposal.', 'groove-folios'); ?></p>
          </div>
          <button
            type="button"
            class="g-theme-picker-modal__close"
            data-version-history-close
            aria-label="<?php esc_attr_e('Close version history', 'groove-folios'); ?>">
            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
          </button>
        </div>
        <div class="g-theme-picker-modal__body">
        <?php if ($total === 0) : ?>
          <p class="text-sm text-gray-400 m-0"><?php esc_html_e('No version history yet. History is recorded when you publish.', 'groove-folios'); ?></p>
        <?php else : ?>
          <table class="w-full text-sm text-left">
            <thead>
              <tr class="border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                <th class="pb-2 pr-3"><?php esc_html_e('Version', 'groove-folios'); ?></th>
                <th class="pb-2 pr-3"><?php esc_html_e('Status', 'groove-folios'); ?></th>
                <th class="pb-2 pr-3"><?php esc_html_e('Date', 'groove-folios'); ?></th>
                <th class="pb-2 pr-3"><?php esc_html_e('By', 'groove-folios'); ?></th>
                <th class="pb-2"><?php esc_html_e('Note', 'groove-folios'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($revision_log as $i => $entry) :
                $hidden = $i >= $visible_limit && $total > $visible_limit;
                $version = $entry['version'] ?? '';
                $status = $entry['status'] ?? '';
                $date_raw = $entry['date'] ?? '';
                $date_display = $date_raw ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_raw)) : '';
                $user = $entry['user'] ?? '';
                $note = $entry['note'] ?? '';
              ?>
                <tr class="border-b border-gray-100 <?php echo $hidden ? 'g-revision-row-hidden hidden' : ''; ?>">
                  <td class="py-1.5 pr-3 font-medium text-gray-800"><?php echo esc_html($version); ?></td>
                  <td class="py-1.5 pr-3 text-gray-600"><?php echo esc_html($status); ?></td>
                  <td class="py-1.5 pr-3 text-gray-500 whitespace-nowrap"><?php echo esc_html($date_display); ?></td>
                  <td class="py-1.5 pr-3 text-gray-500"><?php echo esc_html($user); ?></td>
                  <td class="py-1.5 text-gray-500"><?php echo esc_html($note); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php if ($total > $visible_limit) : ?>
            <button type="button"
              class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium cursor-pointer bg-transparent border-0 p-0"
              onclick="document.querySelectorAll('.g-revision-row-hidden').forEach(function(r){r.classList.toggle('hidden')});this.textContent=this.textContent==='<?php echo esc_js(__('Show all', 'groove-folios')); ?>'?'<?php echo esc_js(__('Show less', 'groove-folios')); ?>':'<?php echo esc_js(__('Show all', 'groove-folios')); ?>'">
              <?php esc_html_e('Show all', 'groove-folios'); ?>
            </button>
          <?php endif; ?>
        <?php endif; ?>
        </div>
        <div class="g-theme-picker-modal__footer">
          <button
            type="button"
            class="g-theme-picker-modal__done"
            data-version-history-close>
            <?php esc_html_e('Done', 'groove-folios'); ?>
          </button>
        </div>
      </div>
    </div>
    <?php
  }

  public function display_customization()
  {
    $fields = $this->get_fields();
    $header_font = Utils::normalize_primary_font_key($fields->header_font);
    $body_font = Utils::normalize_primary_font_key($fields->body_font);
    $available_fonts = Utils::get_supported_primary_fonts();
    $on_this_page_label = isset($fields->on_this_page_label) ? (string) $fields->on_this_page_label : '';
    $on_this_page_placeholder = Utils::get_default_on_this_page_label();
    $all_themes = \Groove\Themes\Themes_Manager::get_all_themes();
    $current_id = (string) ($fields->theme_id ?? '');
    if ($current_id === '' || !isset($all_themes[$current_id])) {
      $current_id = !empty($all_themes) ? (string) array_key_first($all_themes) : '';
    }
    $current_theme = $current_id !== '' && isset($all_themes[$current_id]) ? $all_themes[$current_id] : null;
    ?>
    <div class="bg-white border mb-5 border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
        <h3 class="text-sm font-semibold text-gray-800 m-0">Customization</h3>
      </div>
      <div class="p-4 space-y-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Theme', 'groove-folios'); ?></label>
          <div class="g-theme-picker-summary">
            <div class="g-theme-picker-summary__layout">
              <div class="g-theme-picker-summary__thumb">
                <img
                  src="<?php echo esc_url($current_theme['thumbnail_url'] ?? ''); ?>"
                  alt="<?php echo esc_attr($current_theme['name'] ?? __('Selected theme', 'groove-folios')); ?>"
                  class="g-theme-picker-summary__thumb-image"
                  data-theme-summary-thumbnail />
              </div>
              <div class="g-theme-picker-summary__content">
                <p class="g-theme-picker-summary__name" data-theme-summary-name><?php echo esc_html($current_theme['name'] ?? __('No theme selected', 'groove-folios')); ?></p>
                <p class="g-theme-picker-summary__description" data-theme-summary-description><?php echo esc_html($current_theme['description'] ?? ''); ?></p>
                <button
                  type="button"
                  class="button button-secondary g-theme-picker-summary__button"
                  data-theme-picker-open>
                  <?php esc_html_e('Change theme', 'groove-folios'); ?>
                </button>
              </div>
            </div>
          </div>
          <input type="hidden" name="theme_id" id="g-active-theme-id" value="<?php echo esc_attr($current_id); ?>" />
        </div>
        <div class="space-y-4">
          <div>
            <label for="g-header-font" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Primary
              FONT</label>
            <div class="flex items-center gap-2">
              <select id="g-header-font" name="header_font"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">Theme Default</option>
                <?php foreach ($available_fonts as $font_key => $font): ?>
                  <option value="<?php echo esc_attr($font_key); ?>" <?php selected($header_font, $font_key); ?>>
                    <?php echo esc_html($font['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" id="g-reset-header-font"
                class="g-tooltip-button inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                aria-label="<?php esc_attr_e('Reset font', 'groove-folios'); ?>"
                data-tooltip-text="<?php esc_attr_e('Reset font', 'groove-folios'); ?>">
                <span class="dashicons dashicons-undo" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div>
            <label for="g-body-font" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Secondary
              FONT</label>
            <div class="flex items-center gap-2">
              <select id="g-body-font" name="body_font"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">Theme Default</option>
                <?php foreach ($available_fonts as $font_key => $font): ?>
                  <option value="<?php echo esc_attr($font_key); ?>" <?php selected($body_font, $font_key); ?>>
                    <?php echo esc_html($font['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" id="g-reset-body-font"
                class="g-tooltip-button inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                aria-label="<?php esc_attr_e('Reset font', 'groove-folios'); ?>"
                data-tooltip-text="<?php esc_attr_e('Reset font', 'groove-folios'); ?>">
                <span class="dashicons dashicons-undo" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <p class="m-0 text-xs text-gray-500">Primary font styles titles and headings. Secondary font styles the body text.
          </p>
        </div>
        <div>
          <label for="g-on-this-page-label"
            class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">ON
            THIS PAGE LABEL</label>
          <input type="text" id="g-on-this-page-label" name="on_this_page_label"
            value="<?php echo esc_attr($on_this_page_label); ?>"
            placeholder="<?php echo esc_attr($on_this_page_placeholder); ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
      </div>
    </div>
    <?php $this->display_theme_selection(); ?>
    <?php
  }


  public function display_publishing()
  {
    $fields = $this->get_fields();

    $is_using_folio = $fields->use_folio !== '0';

    $permalink = isset($fields->permalink) ? $fields->permalink : '';
    $base_slug = \Groove\Utils\Utils::get_folio_base_slug();
    ?>
    <div class="bg-white border mb-5 border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
        <h3 class="text-sm font-semibold text-gray-800 m-0">Publishing</h3>
      </div>
      <div class="p-4 space-y-4">
        <div class="flex items-center">
          <input type="hidden" name="use_folio" value="0" />
          <input type="checkbox" id="use_folio" name="use_folio" value="1"
            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?php echo checked($is_using_folio, true, false) ?> />
          <label for="use_folio" class="ml-2 block text-sm text-gray-900">Use Folio Cover</label>
        </div>
        <div>
          <label for="permalink" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">PERMALINK
            (Read-only)</label>
          <div class="mt-1 flex rounded-md shadow-sm">
            <span
              class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 sm:text-sm">/<?php echo esc_html($base_slug); ?>/</span>
            <input disabled type="text" id="permalink" name="permalink" value="<?php echo esc_attr($fields->name) ?>"
              class="block w-full min-w-0 flex-1 rounded-none rounded-r-md border-gray-300 px-3 py-2 bg-gray-100 text-gray-500 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  public function display_essentials()
  {
    $fields = $this->get_fields();
    $selected_author = get_post_field('post_author', $fields->ID);

    $feature_image = isset($fields->feature_image) ? $fields->feature_image : '';
    $logo = isset($fields->logo) ? $fields->logo : '';

    $subtitle = isset($fields->subtitle) ? $fields->subtitle : '';
    $copyright = isset($fields->copyright) ? $fields->copyright : '';
    $collection_tags = implode(', ', $this->get_collection_tag_names((int) $fields->ID));
    $all_collection_tags_terms = get_terms(array('taxonomy' => 'groove_collection_tag', 'hide_empty' => false));
    $all_collection_tag_names_list = is_wp_error($all_collection_tags_terms) ? [] : array_map(function ($t) {
      return $t->name;
    }, $all_collection_tags_terms);
    $collection_tags_settings_url = add_query_arg(
      array(
        'page' => 'groove-settings',
        'tab_key' => 'collections',
      ),
      admin_url('admin.php')
    );

    // Get current theme info for default image fallback
    $all_themes = \Groove\Themes\Themes_Manager::get_all_themes();
    $current_theme = $all_themes[$fields->theme_id] ?? reset($all_themes);
    $theme_cover_url = $current_theme["cover_url"] ?? '';
    $theme_logo_url = $current_theme["logo_url"] ?? '';
    $feature_image_src = ($feature_image instanceof \WP_Post && !empty($feature_image->guid)) ? $feature_image->guid : $theme_cover_url;
    $logo_image_src = ($logo instanceof \WP_Post && !empty($logo->guid)) ? $logo->guid : $theme_logo_url;
    $show_logo = (string) ($fields->show_logo ?? '1') !== '0';
    ?>
    <div class="bg-white border mb-5 border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
        <h3 class="text-sm font-semibold text-gray-800 m-0">Essentials</h3>
      </div>
      <div class="p-4 space-y-4">
        <?php wp_nonce_field('groove_save_folio', 'groove_nonce'); ?>
        <div>
          <label for="title" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">TITLE</label>
          <input type="text" id="title" name="title" value="<?php echo esc_attr($fields->title) ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
        <div>
          <label for="subtitle"
            class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">SUBTITLE</label>
          <input placeholder="Option" type="text" id="subtitle" name="subtitle" value="<?php echo esc_attr($subtitle) ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
        <div>
          <label for="collection_tags"
            class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Collection Tags', 'groove-folios'); ?></label>
          <input type="text" id="collection_tags" name="collection_tags" value="<?php echo esc_attr($collection_tags) ?>"
            placeholder="<?php esc_attr_e('Magazine, 2026, Weekly', 'groove-folios'); ?>"
            autocomplete="off"
            data-tags="<?php echo esc_attr(wp_json_encode($all_collection_tag_names_list)); ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
          <p class="mt-1 mb-0 text-xs text-gray-400">
            <?php esc_html_e('Comma-separated tags. Manage available tags from Settings.', 'groove-folios'); ?>
            <?php if (current_user_can('manage_options')): ?>
              <a href="<?php echo esc_url($collection_tags_settings_url); ?>"><?php esc_html_e('Open Collections settings', 'groove-folios'); ?></a>
            <?php endif; ?>
          </p>
        </div>
        <div class="g-folio__media-row">
          <div class="g-folio__media-col">
            <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">FEATURE IMAGE</label>
            <input value="<?php echo $feature_image instanceof \WP_Post ? (int) $feature_image->ID : ''; ?>" type="hidden"
              name="feature_image_id" id="feature-media-id">
            <div class="flex items-start space-x-4">
              <div
                class="g-folio__media-preview-frame flex items-center justify-center rounded border border-gray-200 bg-gray-50 p-2">
                <img id="feature-preview" class="g-folio__media-preview-image" src="<?php echo esc_url($feature_image_src); ?>" />
              </div>
              <div class="flex flex-col space-y-2">
                <button type="button" id="feature-image" class="button button-secondary">Replace image</button>
                <button type="button" data-default-url="<?php echo esc_url($theme_cover_url); ?>" id="use-default-image"
                  class="button-link">Use default</button>
              </div>
            </div>
          </div>
          <div class="g-folio__media-col">
            <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">LOGO</label>
            <input value="<?php echo $logo instanceof \WP_Post ? (int) $logo->ID : ''; ?>" type="hidden" name="logo_id"
              id="logo-media-id">
            <div class="flex items-start space-x-4">
              <div
                class="g-folio__media-preview-frame flex items-center justify-center rounded border border-gray-200 bg-gray-50 p-2">
                <img id="logo-preview" class="g-folio__media-preview-image" src="<?php echo esc_url($logo_image_src); ?>" />
              </div>
              <div class="flex flex-col space-y-2">
                <button type="button" id="logo-image" class="button button-secondary">Replace image</button>
                <button type="button" data-default-url="<?php echo esc_url($theme_logo_url); ?>" id="use-default-logo"
                  class="button-link">Use default</button>
              </div>
            </div>
            <div class="mt-3 flex items-center">
              <input type="hidden" name="show_logo" value="0" />
              <input type="checkbox" id="show_logo" name="show_logo" value="1"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?php echo checked($show_logo, true, false); ?> />
              <label for="show_logo" class="ml-2 block text-sm text-gray-900">Include logo</label>
            </div>
          </div>
        </div>
        <?php
        $selected_byline = isset($fields->byline) ? (int) $fields->byline : (int) $selected_author;
        if (isset($fields->show_byline) && (string) $fields->show_byline === '0') {
          $selected_byline = 0;
        } elseif ($selected_byline <= 0) {
          $selected_byline = (int) $selected_author;
        }
        ?>
        <div>
          <label for="byline" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">BYLINE</label>
          <?php
          wp_dropdown_users(array(
            'name' => 'byline',
            'id' => 'byline',
            'selected' => $selected_byline,
            'class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
            'show_option_none' => __('Hide byline', 'groove-folios'),
            'option_none_value' => '0',
          ));
          ?>
        </div>
        <div>
          <label for="copyright"
            class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">COPYRIGHT</label>
          <input placeholder="Option" type="text" id="copyright" name="copyright" value="<?php echo esc_attr($copyright) ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
      </div>
    </div>
    <?php
  }

  public function display_theme_selection()
  {
    $fields = $this->get_fields();
    $all_themes = \Groove\Themes\Themes_Manager::get_all_themes();
    $current_id = (string) ($fields->theme_id ?? '');
    if ($current_id === '' || !isset($all_themes[$current_id])) {
      $current_id = !empty($all_themes) ? (string) array_key_first($all_themes) : '';
    }
    ?>
    <div
      id="g-theme-picker-modal"
      class="g-theme-picker-modal hidden"
      aria-hidden="true">
      <div class="g-theme-picker-modal__backdrop" data-theme-picker-close></div>
      <div class="g-theme-picker-modal__frame">
        <div class="g-theme-picker-modal__header">
          <div class="g-theme-picker-modal__heading">
            <h3 class="g-theme-picker-modal__title"><?php esc_html_e('Choose a theme', 'groove-folios'); ?></h3>
            <p class="g-theme-picker-modal__subtitle"><?php esc_html_e('Switch the folio theme for the cover and inner pages.', 'groove-folios'); ?></p>
          </div>
          <button
            type="button"
            class="g-theme-picker-modal__close"
            data-theme-picker-close
            aria-label="<?php esc_attr_e('Close theme picker', 'groove-folios'); ?>">
            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
          </button>
        </div>
        <div class="g-theme-picker-modal__body">
          <div class="g-theme-picker-modal__grid">
            <?php foreach ($all_themes as $id => $theme):
              $active = ($id === $current_id);
              ?>
              <button
                type="button"
                class="g-folio__theme-option g-theme-picker-modal__option <?php echo $active ? 'is-active' : ''; ?>"
                data-theme-id="<?php echo esc_attr($id); ?>"
                data-theme-name="<?php echo esc_attr($theme['name']); ?>"
                data-theme-thumbnail-url="<?php echo esc_url($theme['thumbnail_url']); ?>"
                data-theme-description="<?php echo esc_attr($theme['description'] ?? ''); ?>"
                aria-pressed="<?php echo $active ? 'true' : 'false'; ?>">
                <div class="g-theme-picker-modal__option-media">
                  <img src="<?php echo esc_url($theme['thumbnail_url']); ?>" alt="<?php echo esc_attr($theme['name']); ?>"
                    class="g-theme-picker-modal__option-image" />
                </div>
                <div class="g-theme-picker-modal__option-copy">
                  <div class="g-theme-picker-modal__option-name">
                    <?php echo esc_html($theme['name']); ?>
                  </div>
                  <?php if (!empty($theme['description'])): ?>
                    <div class="g-theme-picker-modal__option-description">
                      <?php echo esc_html($theme['description']); ?>
                    </div>
                  <?php endif; ?>
                </div>
                <span
                  class="active-badge g-theme-picker-modal__badge <?php echo $active ? '' : 'is-hidden'; ?>"><?php esc_html_e('Selected', 'groove-folios'); ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="g-theme-picker-modal__footer">
          <button
            type="button"
            class="g-theme-picker-modal__done"
            data-theme-picker-close>
            <?php esc_html_e('Done', 'groove-folios'); ?>
          </button>
        </div>
      </div>
    </div>
    <?php
  }

  public function display_tabs()
  {
    $tabs = $this->get_tabs();
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'setup'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: which tab to highlight.

    $q = $this->parse_query();
    ?>
    <nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Folio tabs', 'groove-folios'); ?>">
      <?php
      foreach ($tabs as $tab_id => $tab) {
        $active_class = $tab_key === $tab_id ? ' nav-tab-active' : '';
        $q['tab_key'] = $tab_id;
        $tab_url = add_query_arg($q, admin_url('admin.php'));
        $extra_attrs = '';
      if (!empty($tab['attrs']) && is_array($tab['attrs'])) {
        foreach ($tab['attrs'] as $attr_name => $attr_val) {
          $extra_attrs .= ' ' . esc_attr($attr_name) . '="' . esc_attr($attr_val) . '"';
        }
      }
      echo '<a href="' . esc_url($tab_url) . '" class="nav-tab' . esc_attr($active_class) . '"' . $extra_attrs . '>' . esc_html($tab['label']) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra_attrs is built just above from esc_attr()-escaped names and values.
      }
      ?>
    </nav>
    <?php
  }

  private function get_pages_tab_current_status()
  {
    $status = isset($_GET['post_status']) ? sanitize_key(wp_unslash($_GET['post_status'])) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-view parameter: filters, sorts or pages the Pages tab; nothing is written.
    $allowed_statuses = array('all', 'publish', 'draft', 'pending', 'private', 'trash');

    if (!in_array($status, $allowed_statuses, true)) {
      return 'all';
    }

    return $status;
  }

  private function get_pages_tab_search_term()
  {
    return isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-view parameter: filters, sorts or pages the Pages tab; nothing is written.
  }

  private function get_pages_tab_current_paged()
  {
    return max(1, isset($_GET['paged']) ? intval(wp_unslash($_GET['paged'])) : 1); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-view parameter: filters, sorts or pages the Pages tab; nothing is written.
  }

  private function get_pages_tab_current_orderby()
  {
    $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'modified'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-view parameter: filters, sorts or pages the Pages tab; nothing is written.
    $allowed_orderby = array('title', 'menu_order', 'modified');

    if (!in_array($orderby, $allowed_orderby, true)) {
      return 'modified';
    }

    return $orderby;
  }

  private function get_pages_tab_current_order()
  {
    $order = isset($_GET['order']) ? strtoupper(sanitize_key(wp_unslash($_GET['order']))) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-view parameter: filters, sorts or pages the Pages tab; nothing is written.
    return $order === 'ASC' ? 'ASC' : 'DESC';
  }

  private function build_pages_tab_url($folio_id, $args = array())
  {
    return add_query_arg(
      array_merge(
        array(
          'page' => static::PAGE_ID,
          'tab_key' => 'pages',
          'folio_id' => (int) $folio_id,
        ),
        $args
      ),
      admin_url('admin.php')
    );
  }

  private function get_pages_tab_status_label($status)
  {
    switch ($status) {
      case 'publish':
        return esc_html__('Published', 'groove-folios');
      case 'draft':
        return esc_html__('Draft', 'groove-folios');
      case 'pending':
        return esc_html__('Pending', 'groove-folios');
      case 'private':
        return esc_html__('Private', 'groove-folios');
      case 'trash':
        return esc_html__('Trash', 'groove-folios');
      default:
        return esc_html__('All', 'groove-folios');
    }
  }

  private function get_folio_status_label($status)
  {
    switch ($status) {
      case 'publish':
        return esc_html__('Published', 'groove-folios');
      case 'draft':
      case 'auto-draft':
        return esc_html__('Draft', 'groove-folios');
      case 'pending':
        return esc_html__('Pending', 'groove-folios');
      case 'private':
        return esc_html__('Private', 'groove-folios');
      case 'trash':
        return esc_html__('Trash', 'groove-folios');
      default:
        $status = is_string($status) ? trim($status) : '';
        if ($status === '') {
          return esc_html__('Draft', 'groove-folios');
        }

        return esc_html(ucwords(str_replace(array('-', '_'), ' ', $status)));
    }
  }

  private function get_pages_tab_status_counts($folio_id)
  {
    global $wpdb;

    $folio_id = (int) $folio_id;
    if ($folio_id <= 0) {
      return array(
        'all' => 0,
        'publish' => 0,
        'draft' => 0,
        'pending' => 0,
        'private' => 0,
        'trash' => 0,
      );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Per-status page counts for one folio, which no WP API provides (wp_count_posts() cannot filter by meta); prepared, and must reflect pages changed a moment ago.
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT p.post_status, COUNT(*) AS num_posts
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = %s
           AND pm.meta_key = %s
           AND CAST(pm.meta_value AS UNSIGNED) = %d
         GROUP BY p.post_status",
        static::POST_TYPE,
        'folio_id',
        $folio_id
      )
    );

    $counts = array(
      'publish' => 0,
      'draft' => 0,
      'pending' => 0,
      'private' => 0,
      'future' => 0,
      'trash' => 0,
    );

    foreach ((array) $rows as $row) {
      if (!isset($row->post_status, $row->num_posts)) {
        continue;
      }
      $status = (string) $row->post_status;
      if (array_key_exists($status, $counts)) {
        $counts[$status] = (int) $row->num_posts;
      }
    }

    return array(
      'all' => $counts['publish'] + $counts['draft'] + $counts['pending'] + $counts['private'] + $counts['future'],
      'publish' => $counts['publish'],
      'draft' => $counts['draft'],
      'pending' => $counts['pending'],
      'private' => $counts['private'],
      'trash' => $counts['trash'],
    );
  }

  private function get_pages_tab_results($folio_id, $status, $search, $paged, $orderby, $order, $per_page = 20)
  {
    $query_args = array(
      'post_type' => static::POST_TYPE,
      'post_status' => $status === 'all' ? array('publish', 'draft', 'pending', 'private', 'future') : $status,
      'posts_per_page' => $per_page,
      'paged' => $paged,
      'order' => $order,
      'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- folio_id meta is the page-to-folio link; the Pages tab lists one folio's pages.
        array(
          'key' => 'folio_id',
          'value' => (int) $folio_id,
          'compare' => '=',
          'type' => 'NUMERIC',
        ),
      ),
    );

    if ($orderby === 'menu_order') {
      $query_args['orderby'] = 'menu_order title';
    } elseif ($orderby === 'title') {
      $query_args['orderby'] = 'title';
    } else {
      $query_args['orderby'] = 'modified';
    }

    if ($search !== '') {
      $query_args['s'] = $search;
    }

    $query = new \WP_Query($query_args);

    return array(
      'posts' => (array) $query->posts,
      'total_items' => (int) $query->found_posts,
      'total_pages' => (int) $query->max_num_pages,
    );
  }

  private function get_pages_tab_sort_url($folio_id, $column, $current_orderby, $current_order, $status, $search)
  {
    $next_order = 'ASC';
    if ($column === $current_orderby && $current_order === 'ASC') {
      $next_order = 'DESC';
    }

    return $this->build_pages_tab_url(
      $folio_id,
      array_filter(array(
        'post_status' => $status !== 'all' ? $status : null,
        's' => $search !== '' ? $search : null,
        'orderby' => $column,
        'order' => $next_order,
      ), function ($value) {
        return $value !== null;
      })
    );
  }

  private function get_pages_tab_available_bulk_actions($status)
  {
    if ($status === 'trash') {
      return array(
        'untrash' => esc_html__('Restore', 'groove-folios'),
        'delete' => esc_html__('Delete Permanently', 'groove-folios'),
      );
    }

    return array(
      'trash' => esc_html__('Move to Trash', 'groove-folios'),
    );
  }

  private function get_pages_tab_current_bulk_action()
  {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads which bulk action was chosen; process_pages_tab_bulk_action() runs check_admin_referer('groove_bulk_pages_action') before acting on it.
    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '-1';
    $action2 = isset($_REQUEST['action2']) ? sanitize_key(wp_unslash($_REQUEST['action2'])) : '-1';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ($action !== '-1') {
      return $action;
    }
    if ($action2 !== '-1') {
      return $action2;
    }

    return false;
  }

  private function process_pages_tab_bulk_action($folio_id, $status, $search, $orderby, $order, $paged)
  {
    $action = $this->get_pages_tab_current_bulk_action();
    if (!$action) {
      return;
    }

    $available_actions = $this->get_pages_tab_available_bulk_actions($status);
    if (!isset($available_actions[$action])) {
      return;
    }

    check_admin_referer('groove_bulk_pages_action', '_groove_bulk_nonce');

    $post_ids = isset($_REQUEST['post']) ? array_map('intval', (array) wp_unslash($_REQUEST['post'])) : array();
    $post_ids = array_values(array_filter($post_ids));
    if (empty($post_ids)) {
      return;
    }

    $updated_count = 0;
    foreach ($post_ids as $post_id) {
      if (get_post_type($post_id) !== static::POST_TYPE) {
        continue;
      }

      $page_folio_id = (int) get_post_meta($post_id, 'folio_id', true);
      if ($page_folio_id !== (int) $folio_id) {
        continue;
      }

      if (!current_user_can('delete_post', $post_id)) {
        continue;
      }

      switch ($action) {
        case 'trash':
          if (wp_trash_post($post_id)) {
            $updated_count++;
          }
          break;
        case 'untrash':
          if (wp_untrash_post($post_id)) {
            $updated_count++;
          }
          break;
        case 'delete':
          if (wp_delete_post($post_id, true)) {
            $updated_count++;
          }
          break;
      }
    }

    $redirect_args = array_filter(array(
      'post_status' => $status !== 'all' ? $status : null,
      's' => $search !== '' ? $search : null,
      'orderby' => $orderby !== 'modified' ? $orderby : null,
      'order' => $order !== 'DESC' ? $order : null,
      'paged' => $paged > 1 ? $paged : null,
      'bulk_action' => $action,
      'bulk_count' => $updated_count,
    ), function ($value) {
      return $value !== null;
    });

    wp_safe_redirect($this->build_pages_tab_url($folio_id, $redirect_args));
    exit;
  }

  /**
   * Report the outcome of a Pages tab bulk action as a toast.
   *
   * Matches All Folios: the page list stays put and the count is reported over
   * it. Removals report as info rather than success.
   */
  private function queue_pages_tab_bulk_toast()
  {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only outcome parameters set by this page's own redirect after a nonce-checked bulk action; they only choose the toast.
    if (!isset($_GET['bulk_action']) || !isset($_GET['bulk_count'])) {
      return;
    }

    $action = sanitize_key(wp_unslash($_GET['bulk_action']));
    $count = intval(wp_unslash($_GET['bulk_count']));
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    if ($count < 1) {
      return;
    }

    $message = '';
    $type = \Groove\Toast::SUCCESS;

    switch ($action) {
      case 'trash':
        $message = sprintf(
          /* translators: %s: number of pages moved to trash */
          _n('%s page moved to Trash.', '%s pages moved to Trash.', $count, 'groove-folios'),
          number_format_i18n($count)
        );
        $type = \Groove\Toast::INFO;
        break;
      case 'untrash':
        $message = sprintf(
          /* translators: %s: number of pages restored */
          _n('%s page restored.', '%s pages restored.', $count, 'groove-folios'),
          number_format_i18n($count)
        );
        break;
      case 'delete':
        $message = sprintf(
          /* translators: %s: number of pages deleted */
          _n('%s page deleted permanently.', '%s pages deleted permanently.', $count, 'groove-folios'),
          number_format_i18n($count)
        );
        $type = \Groove\Toast::INFO;
        break;
    }

    if ($message === '') {
      return;
    }

    \Groove\Toast::add($message, $type, array('bulk_action', 'bulk_count'));
  }

  private function get_pages_tab_last_modified_by($post_id)
  {
    $last_editor_id = (int) get_post_meta($post_id, '_edit_last', true);
    if ($last_editor_id > 0) {
      $user = get_userdata($last_editor_id);
      if ($user) {
        return $user->display_name;
      }
    }

    $author = get_userdata((int) get_post_field('post_author', $post_id));
    return $author ? $author->display_name : esc_html__('Unknown user', 'groove-folios');
  }

  public function display_tab_pages()
  {
    $folio_id = isset($_GET['folio_id']) ? intval(wp_unslash($_GET['folio_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: the folio whose pages are listed.
    $status = $this->get_pages_tab_current_status();
    $search = $this->get_pages_tab_search_term();
    $paged = $this->get_pages_tab_current_paged();
    $orderby = $this->get_pages_tab_current_orderby();
    $order = $this->get_pages_tab_current_order();
    $this->process_pages_tab_bulk_action($folio_id, $status, $search, $orderby, $order, $paged);

    $status_counts = $this->get_pages_tab_status_counts($folio_id);
    $bulk_actions = $this->get_pages_tab_available_bulk_actions($status);
    $results = $this->get_pages_tab_results($folio_id, $status, $search, $paged, $orderby, $order);
    $posts = (array) $results['posts'];
    $total_items = (int) $results['total_items'];
    $total_pages = (int) $results['total_pages'];
    $quick_edit_table = new Folio_Page_List_Table($this, static::POST_TYPE);
    ?>
    <?php $this->queue_pages_tab_bulk_toast(); ?>
    <form id="pages-filter" method="get">
      <input type="hidden" name="page" value="<?php echo esc_attr(static::PAGE_ID); ?>" />
      <input type="hidden" name="tab_key" value="pages" />
      <input type="hidden" name="folio_id" value="<?php echo esc_attr((string) $folio_id); ?>" />
      <input type="hidden" name="post_status" class="post_status_page" value="<?php echo esc_attr($status); ?>" />
      <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>" />
      <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>" />
      <?php wp_nonce_field('groove_bulk_pages_action', '_groove_bulk_nonce'); ?>

      <ul class="subsubsub">
        <?php
        $total_statuses = count($status_counts);
        $status_index = 0;
        foreach ($status_counts as $status_key => $count) {
          $status_index++;
          $is_active = $status_key === $status;
          $status_url = $this->build_pages_tab_url(
            $folio_id,
            array_filter(array(
              'post_status' => $status_key,
              's' => $search !== '' ? $search : null,
              'orderby' => $orderby !== 'modified' ? $orderby : null,
              'order' => $order !== 'DESC' ? $order : null,
            ), function ($value) {
              return $value !== null;
            })
          );
          ?>
          <li class="<?php echo esc_attr($status_key); ?>">
            <a href="<?php echo esc_url($status_url); ?>" class="<?php echo esc_attr($is_active ? 'current' : ''); ?>">
              <?php echo esc_html($this->get_pages_tab_status_label($status_key)); ?>
              <span class="count">(<?php echo esc_html(number_format_i18n($count)); ?>)</span>
            </a>
            <?php if ($status_index < $total_statuses): ?>
              |
            <?php endif; ?>
          </li>
          <?php
        }
        ?>
      </ul>

      <p class="search-box">
        <label class="screen-reader-text"
          for="post-search-input"><?php esc_html_e('Search folio pages', 'groove-folios'); ?>:</label>
        <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
        <input type="submit" id="search-submit" class="button"
          value="<?php esc_attr_e('Search Folio Pages', 'groove-folios'); ?>" />
      </p>

      <div class="tablenav top">
        <div class="alignleft actions bulkactions">
          <label for="bulk-action-selector-top"
            class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove-folios'); ?></label>
          <select name="action" id="bulk-action-selector-top">
            <option value="-1"><?php esc_html_e('Bulk actions', 'groove-folios'); ?></option>
            <?php foreach ($bulk_actions as $action_key => $action_label): ?>
              <option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'groove-folios'); ?>" />
        </div>
        <div class="tablenav-pages">
          <?php if ($total_pages > 1): ?>
            <?php
            $base_url = $this->build_pages_tab_url(
              $folio_id,
              array_filter(array(
                'post_status' => $status !== 'all' ? $status : null,
                's' => $search !== '' ? $search : null,
                'orderby' => $orderby !== 'modified' ? $orderby : null,
                'order' => $order !== 'DESC' ? $order : null,
                'paged' => '%#%',
              ), function ($value) {
                return $value !== null;
              })
            );
            $pagination_links = paginate_links(array(
              'base' => $base_url,
              'format' => '',
              'current' => $paged,
              'total' => $total_pages,
              'type' => 'array',
              'prev_text' => '&lsaquo;',
              'next_text' => '&rsaquo;',
            ));
            ?>
            <span class="displaying-num">
              <?php
              printf(
                /* translators: %s: number of items */
                esc_html(_n('%s item', '%s items', $total_items, 'groove-folios')),
                esc_html(number_format_i18n($total_items))
              );
              ?>
            </span>
            <span class="pagination-links">
              <?php echo wp_kses_post(implode(' ', (array) $pagination_links)); ?>
            </span>
          <?php else: ?>
            <span class="displaying-num">
              <?php
              printf(
                /* translators: %s: number of items */
                esc_html(_n('%s item', '%s items', $total_items, 'groove-folios')),
                esc_html(number_format_i18n($total_items))
              );
              ?>
            </span>
          <?php endif; ?>
        </div>
        <br class="clear" />
      </div>

      <table class="wp-list-table widefat striped table-view-list pages">
        <thead>
          <tr>
            <th scope="col" id="cb" class="manage-column column-cb check-column">
              <label class="screen-reader-text"
                for="cb-select-all-1"><?php esc_html_e('Select all pages', 'groove-folios'); ?></label>
              <input id="cb-select-all-1" type="checkbox" />
            </th>
            <th scope="col"
              class="manage-column column-primary <?php echo esc_attr($orderby === 'title' ? 'sorted ' . strtolower($order) : 'sortable desc'); ?>">
              <a
                href="<?php echo esc_url($this->get_pages_tab_sort_url($folio_id, 'title', $orderby, $order, $status, $search)); ?>">
                <span><?php esc_html_e('Page Name', 'groove-folios'); ?></span>
                <span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span
                    class="sorting-indicator desc" aria-hidden="true"></span></span>
              </a>
            </th>
            <th scope="col"
              class="manage-column <?php echo esc_attr($orderby === 'menu_order' ? 'sorted ' . strtolower($order) : 'sortable desc'); ?>">
              <a
                href="<?php echo esc_url($this->get_pages_tab_sort_url($folio_id, 'menu_order', $orderby, $order, $status, $search)); ?>">
                <span><?php esc_html_e('Menu Position', 'groove-folios'); ?></span>
                <span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span
                    class="sorting-indicator desc" aria-hidden="true"></span></span>
              </a>
            </th>
            <th scope="col" class="manage-column"><?php esc_html_e('Publish Status', 'groove-folios'); ?></th>
            <th scope="col"
              class="manage-column <?php echo esc_attr($orderby === 'modified' ? 'sorted ' . strtolower($order) : 'sortable desc'); ?>">
              <a
                href="<?php echo esc_url($this->get_pages_tab_sort_url($folio_id, 'modified', $orderby, $order, $status, $search)); ?>">
                <span><?php esc_html_e('Last Updated', 'groove-folios'); ?></span>
                <span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span
                    class="sorting-indicator desc" aria-hidden="true"></span></span>
              </a>
            </th>
          </tr>
        </thead>
        <tbody id="the-list">
          <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
              <?php
              $post_id = (int) $post->ID;
              $post_status = (string) get_post_status($post_id);
              $title = get_the_title($post_id);
              $post_title = $title !== '' ? $title : esc_html__('(no title)', 'groove-folios');
              $base_edit_url = get_edit_post_link($post_id, '');
              if ($base_edit_url) {
                $edit_url = add_query_arg(
                  array('folio_id' => (int) $folio_id),
                  $base_edit_url
                );
              } else {
                $edit_url = add_query_arg(
                  array(
                    'post' => $post_id,
                    'action' => 'edit',
                    'folio_id' => (int) $folio_id,
                  ),
                  admin_url('post.php')
                );
              }
              $view_url = Utils::get_folio_permalink_by_id($post_id);
              if (!$view_url) {
                $view_url = get_permalink($post_id);
              }
              $is_preview_status = in_array($post_status, array('draft', 'pending', 'future'), true);
              $row_view_url = $view_url;
              $row_view_label = $is_preview_status ? esc_html__('Preview', 'groove-folios') : esc_html__('View', 'groove-folios');
              $modified_label = sprintf(
                /* translators: 1: date/time value, 2: user display name */
                esc_html__('%1$s by %2$s', 'groove-folios'),
                get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $post_id),
                $this->get_pages_tab_last_modified_by($post_id)
              );
              $quick_edit_aria_label = sprintf(
                /* translators: %s: Page title. */
                esc_attr__('Quick edit "%s" inline', 'groove-folios'),
                wp_strip_all_tags($post_title)
              );
              ?>
              <tr id="post-<?php echo esc_attr((string) $post_id); ?>">
                <th scope="row" class="check-column">
                  <label class="screen-reader-text"
                    for="cb-select-<?php echo esc_attr((string) $post_id); ?>"><?php esc_html_e('Select page', 'groove-folios'); ?></label>
                  <input id="cb-select-<?php echo esc_attr((string) $post_id); ?>" type="checkbox" name="post[]"
                    value="<?php echo esc_attr((string) $post_id); ?>" />
                </th>
                <td class="title column-title has-row-actions column-primary page-title"
                  data-colname="<?php esc_attr_e('Page Name', 'groove-folios'); ?>">
                  <strong class="g-folio__title-wrap">
                    <a class="row-title g-folio__truncate-text" href="<?php echo esc_url($edit_url); ?>"
                      title="<?php echo esc_attr($post_title); ?>">
                      <?php echo esc_html($post_title); ?>
                    </a>
                  </strong>
                  <div class="row-actions">
                    <span class="edit"><a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'groove-folios'); ?></a>
                      |</span>
                    <?php if ($post_status !== 'trash'): ?>
                      <span class="inline hide-if-no-js">
                        <button type="button" class="button-link editinline"
                          aria-label="<?php echo esc_attr($quick_edit_aria_label); ?>"
                          aria-expanded="false"><?php esc_html_e('Quick Edit', 'groove-folios'); ?></button> |
                      </span>
                    <?php endif; ?>
                    <span class="view"><a href="<?php echo esc_url($row_view_url); ?>" target="_blank"
                        rel="noopener noreferrer"><?php echo esc_html($row_view_label); ?></a> |</span>
                    <?php if ($post_status === 'trash'): ?>
                      <span class="untrash"><a
                          href="<?php echo esc_url(wp_nonce_url(admin_url('post.php?action=untrash&post=' . $post_id), 'untrash-post_' . $post_id)); ?>"><?php esc_html_e('Restore', 'groove-folios'); ?></a>
                        |</span>
                      <span class="delete"><a class="submitdelete"
                          href="<?php echo esc_url(get_delete_post_link($post_id, '', true)); ?>"><?php esc_html_e('Delete Permanently', 'groove-folios'); ?></a></span>
                    <?php else: ?>
                      <span class="trash"><a class="submitdelete"
                          href="<?php echo esc_url(get_delete_post_link($post_id)); ?>"><?php esc_html_e('Trash', 'groove-folios'); ?></a></span>
                    <?php endif; ?>
                  </div>
                  <?php
                  if (function_exists('get_inline_data')) {
                    get_inline_data(get_post($post_id));
                  }
                  ?>
                  <button type="button" class="toggle-row"><span
                      class="screen-reader-text"><?php esc_html_e('Show more details', 'groove-folios'); ?></span></button>
                </td>
                <td data-colname="<?php esc_attr_e('Menu Position', 'groove-folios'); ?>">
                  <?php echo esc_html((string) (int) get_post_field('menu_order', $post_id)); ?>
                </td>
                <td data-colname="<?php esc_attr_e('Publish Status', 'groove-folios'); ?>">
                  <?php
                  $status_object = get_post_status_object($post_status);
                  echo esc_html($status_object && !empty($status_object->label) ? (string) $status_object->label : ucfirst($post_status));
                  ?>
                </td>
                <td data-colname="<?php esc_attr_e('Last Updated', 'groove-folios'); ?>">
                  <?php echo esc_html($modified_label); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="no-items">
              <td class="colspanchange" colspan="5">
                <?php esc_html_e('No pages found for the current filters.', 'groove-folios'); ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td class="manage-column column-cb check-column">
              <label class="screen-reader-text"
                for="cb-select-all-2"><?php esc_html_e('Select all pages', 'groove-folios'); ?></label>
              <input id="cb-select-all-2" type="checkbox" />
            </td>
            <th scope="col" class="manage-column column-primary"><?php esc_html_e('Page Name', 'groove-folios'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Menu Position', 'groove-folios'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Publish Status', 'groove-folios'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Last Updated', 'groove-folios'); ?></th>
          </tr>
        </tfoot>
      </table>

      <?php if (!empty($posts)): ?>
        <?php
        $quick_edit_post = get_post((int) $posts[0]->ID);
        if ($quick_edit_post instanceof \WP_Post) {
          setup_postdata($quick_edit_post);
        }
        $quick_edit_table->inline_edit();
        if ($quick_edit_post instanceof \WP_Post) {
          wp_reset_postdata();
        }
        ?>
      <?php endif; ?>

      <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
          <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-bottom"
              class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove-folios'); ?></label>
            <select name="action2" id="bulk-action-selector-bottom">
              <option value="-1"><?php esc_html_e('Bulk actions', 'groove-folios'); ?></option>
              <?php foreach ($bulk_actions as $action_key => $action_label): ?>
                <option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e('Apply', 'groove-folios'); ?>" />
          </div>
          <div class="tablenav-pages">
            <span class="displaying-num">
              <?php
              printf(
                /* translators: %s: number of items */
                esc_html(_n('%s item', '%s items', $total_items, 'groove-folios')),
                esc_html(number_format_i18n($total_items))
              );
              ?>
            </span>
            <?php
            $bottom_base_url = $this->build_pages_tab_url(
              $folio_id,
              array_filter(array(
                'post_status' => $status !== 'all' ? $status : null,
                's' => $search !== '' ? $search : null,
                'orderby' => $orderby !== 'modified' ? $orderby : null,
                'order' => $order !== 'DESC' ? $order : null,
                'paged' => '%#%',
              ), function ($value) {
                return $value !== null;
              })
            );
            echo wp_kses_post(
              paginate_links(array(
                'base' => $bottom_base_url,
                'format' => '',
                'current' => $paged,
                'total' => $total_pages,
                'type' => 'plain',
                'prev_text' => '&lsaquo;',
                'next_text' => '&rsaquo;',
              ))
            );
            ?>
          </div>
          <br class="clear" />
        </div>
      <?php else: ?>
        <div class="tablenav bottom">
          <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-bottom"
              class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove-folios'); ?></label>
            <select name="action2" id="bulk-action-selector-bottom">
              <option value="-1"><?php esc_html_e('Bulk actions', 'groove-folios'); ?></option>
              <?php foreach ($bulk_actions as $action_key => $action_label): ?>
                <option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e('Apply', 'groove-folios'); ?>" />
          </div>
          <br class="clear" />
        </div>
      <?php endif; ?>
    </form>
    <?php
  }

  public function display_page()
  {
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'setup'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: which tab to show.

    if ($tab_key === 'pages') {
      $original_right_buttons = $this->right_button_items;
      $this->right_button_items = array_values(array_filter(
        (array) $this->right_button_items,
        function ($item) {
          return isset($item['link']);
        }
      ));

      parent::display_page();

      $this->right_button_items = $original_right_buttons;
      return;
    }

    $folio_id = isset($_GET['folio_id']) ? intval(wp_unslash($_GET['folio_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: the folio this form edits; the save handler checks the groove_save_folio nonce.
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
      <?php wp_nonce_field('groove_save_folio', 'groove_nonce'); ?>
      <input type="hidden" name="folio_id" value="<?php echo esc_attr($folio_id); ?>" />
      <?php parent::display_page() ?>
    </form>
    <?php
  }
}
