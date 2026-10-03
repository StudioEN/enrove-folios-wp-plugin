<?php
namespace Enrove\List;
use Enrove\List\List_Table;
use Enrove\Pages\All_Folios;
use Enrove\Pages\Folio;
use Enrove\Pages\Page;
use Enrove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Folio_List_Table extends List_Table {
  function __construct(Page $page, $post_type) {
    parent::__construct($page, $post_type);

    add_filter('get_edit_post_link', [$this, 'edit_link'], 10, 2);
  }

  function edit_link( $url, $post_id ) {
    if ( $this->post_type === get_post_type( $post_id ) ) {
      return esc_url(Folio::get_edit_url((int) $post_id));
    }

    return $url;
  }

  function get_columns()
  {
    return array(
      'cb' => '<input type="checkbox" />',
      'title' => esc_html__('Folio Name', 'enrove-folios'),
      'theme_name' => esc_html__('Theme Name', 'enrove-folios'),
      'collection_tags' => esc_html__('Collection Tags', 'enrove-folios'),
      'page_count' => esc_html__('Page Count', 'enrove-folios'),
      'publish_status' => esc_html__('Publish Status', 'enrove-folios'),
      'modified' => esc_html__('Last Updated', 'enrove-folios'),
    );
  }

  protected function get_sortable_columns()
  {
    return array(
      'title' => array('title', false, __('Folio Name', 'enrove-folios'), __('Table ordered by Folio Name.', 'enrove-folios')),
      'theme_name' => array('theme_name', false, __('Theme Name', 'enrove-folios'), __('Table ordered by Theme Name.', 'enrove-folios')),
      'page_count' => array('page_count', false, __('Page Count', 'enrove-folios'), __('Table ordered by Page Count.', 'enrove-folios')),
      'modified' => array('modified', true, __('Last Updated', 'enrove-folios'), __('Table ordered by Last Updated.', 'enrove-folios'), 'desc'),
    );
  }

  public function column_default($item, $column_name)
  {
    $post = $item;
    $post_id = (int) $post->ID;

    switch ($column_name) {
      case 'theme_name':
        $theme_id = (string) get_post_meta($post_id, 'theme_id', true);
        $all_themes = \Enrove\Themes\Themes_Manager::get_all_themes();
        if (isset($all_themes[$theme_id])) {
          return esc_html($all_themes[$theme_id]['name']);
        }
        return esc_html($theme_id) . ' ' . esc_html__('(Unknown)', 'enrove-folios');

      case 'collection_tags':
        $terms = get_the_terms($post_id, 'enrove_collection_tag');
        if (is_wp_error($terms) || empty($terms)) {
          return '&mdash;';
        }
        $names = array();
        foreach ($terms as $term) {
          if ($term instanceof \WP_Term) {
            $names[] = esc_html($term->name);
          }
        }
        return !empty($names) ? implode(', ', $names) : '&mdash;';

      case 'page_count':
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Page count for one folio row; core has no API that counts posts by meta value, and the count must be fresh after each edit.
        $count = (int) $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*)
           FROM {$wpdb->posts} p
           INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
           WHERE p.post_type = %s
             AND p.post_status IN ('publish','draft','pending','private','future')
             AND pm.meta_key = %s
             AND CAST(pm.meta_value AS UNSIGNED) = %d",
          'enrove_folio_page',
          'folio_id',
          $post_id
        ));
        $pages_url = Folio::get_edit_url((int) $post_id);
        return '<a href="' . esc_url($pages_url) . '">' . esc_html(number_format_i18n($count)) . '</a>';

      case 'publish_status':
        $status_object = get_post_status_object($post->post_status);
        return esc_html($status_object && !empty($status_object->label) ? (string) $status_object->label : ucfirst((string) $post->post_status));

      case 'modified':
        $last_editor_id = (int) get_post_meta($post_id, '_edit_last', true);
        $display_name = '';
        if ($last_editor_id > 0) {
          $user = get_userdata($last_editor_id);
          if ($user) {
            $display_name = $user->display_name;
          }
        }
        if (empty($display_name)) {
          $author = get_userdata((int) $post->post_author);
          $display_name = $author ? $author->display_name : esc_html__('Unknown user', 'enrove-folios');
        }
        return esc_html(sprintf(
          /* translators: 1: date/time value, 2: user display name */
          __('%1$s by %2$s', 'enrove-folios'),
          get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $post_id),
          $display_name
        ));
    }

    return parent::column_default($item, $column_name);
  }

  protected function handle_row_actions($item, $column_name, $primary)
  {
    if ($primary !== $column_name) {
      return '';
    }

    $post = $item;
    $post_id = (int) $post->ID;
    $post_type_object = get_post_type_object($post->post_type);
    $can_edit_post = current_user_can('edit_post', $post_id);
    $actions = array();
    $title = get_the_title($post_id);
    if ($title === '') {
      $title = esc_html__('(no title)', 'enrove-folios');
    }

    if ($can_edit_post && 'trash' !== $post->post_status) {
      $actions['edit'] = sprintf(
        '<a href="%s" aria-label="%s">%s</a>',
        get_edit_post_link($post_id),
        /* translators: %s: Folio title. */
        esc_attr(sprintf(__('Edit &#8220;%s&#8221;', 'enrove-folios'), $title)),
        __('Edit', 'enrove-folios')
      );

      $actions['inline hide-if-no-js'] = sprintf(
        '<button type="button" class="button-link editinline" aria-label="%s" aria-expanded="false">%s</button>',
        /* translators: %s: Folio title. */
        esc_attr(sprintf(__('Quick edit &#8220;%s&#8221; inline', 'enrove-folios'), $title)),
        __('Quick&nbsp;Edit', 'enrove-folios')
      );
    }

    if ($can_edit_post && 'trash' !== $post->post_status) {
      $duplicate_url = All_Folios::get_duplicate_url($post_id);
      $actions['duplicate'] = sprintf(
        '<a href="%s" aria-label="%s">%s</a>',
        esc_url($duplicate_url),
        esc_attr(sprintf(
          /* translators: %s: Folio title. */
          __('Duplicate &#8220;%s&#8221;', 'enrove-folios'),
          $title
        )),
        esc_html__('Duplicate', 'enrove-folios')
      );
    }

    if (current_user_can('delete_post', $post_id)) {
      if ('trash' === $post->post_status) {
        $actions['untrash'] = sprintf(
          '<a href="%s" aria-label="%s">%s</a>',
          wp_nonce_url(admin_url(sprintf($post_type_object->_edit_link . '&amp;action=untrash', $post_id)), 'untrash-post_' . $post_id),
          /* translators: %s: Folio title. */
          esc_attr(sprintf(__('Restore &#8220;%s&#8221; from the Trash', 'enrove-folios'), $title)),
          __('Restore', 'enrove-folios')
        );
      } elseif (EMPTY_TRASH_DAYS) {
        $actions['trash'] = sprintf(
          '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
          get_delete_post_link($post_id),
          /* translators: %s: Folio title. */
          esc_attr(sprintf(__('Move &#8220;%s&#8221; to the Trash', 'enrove-folios'), $title)),
          _x('Trash', 'verb', 'enrove-folios')
        );
      }

      if ('trash' === $post->post_status || !EMPTY_TRASH_DAYS) {
        $actions['delete'] = sprintf(
          '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
          get_delete_post_link($post_id, '', true),
          /* translators: %s: Folio title. */
          esc_attr(sprintf(__('Delete &#8220;%s&#8221; permanently', 'enrove-folios'), $title)),
          __('Delete Permanently', 'enrove-folios')
        );
      }
    }

    if (is_post_type_viewable($post_type_object)) {
      $is_preview_status = in_array($post->post_status, array('pending', 'draft', 'future'), true);

      if ($is_preview_status) {
        if ($can_edit_post) {
          $view_url = get_preview_post_link($post);
          $actions['view'] = sprintf(
            '<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
            esc_url($view_url),
            /* translators: %s: Folio title. */
            esc_attr(sprintf(__('Preview &#8220;%s&#8221;', 'enrove-folios'), $title)),
            __('Preview', 'enrove-folios')
          );
        }
      } elseif ('trash' !== $post->post_status) {
        $view_url = Utils::get_folio_permalink_by_id($post_id);
        if (!$view_url) {
          $view_url = get_permalink($post_id);
        }
        $actions['view'] = sprintf(
          '<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
          esc_url($view_url),
          /* translators: %s: Folio title. */
          esc_attr(sprintf(__('View &#8220;%s&#8221;', 'enrove-folios'), $title)),
          __('View', 'enrove-folios')
        );
      }
    }

    $actions = apply_filters('post_row_actions', $actions, $post); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's Posts list-table hook, applied deliberately so other plugins' row actions reach this posts list.

    return $this->row_actions($actions);
  }
}
?>
