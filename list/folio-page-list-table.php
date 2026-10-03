<?php
namespace Enrove\List;
use Enrove\List\List_Table;
use Enrove\Pages\Folio;
use Enrove\Contents\FolioPage\Publishing;

if (!defined('ABSPATH')) {
  exit;
}

class Folio_Page_List_Table extends List_Table
{
  function __construct(Folio $page, $post_type)
  {
    parent::__construct($page, $post_type);
  }

  /**
   * A folio's pages go live with it (Contents\FolioPage\Publishing), so
   * Quick Edit offers no live status while the folio is unpublished.
   */
  protected function rows_can_go_live()
  {
    return Publishing::is_folio_live($this->page->get_folio_id());
  }

  public function get_columns()
  {
    $posts_columns = array();
    $posts_columns['cb'] = '<input type="checkbox" />';
    $posts_columns['title'] = esc_html__('Page Name', 'enrove-folios');
    $posts_columns['menu_order'] = esc_html__('Menu Position', 'enrove-folios');
    $posts_columns['publish_status'] = esc_html__('Publish Status', 'enrove-folios');
    $posts_columns['modified'] = esc_html__('Last Updated', 'enrove-folios');

    return $posts_columns;
  }

  protected function get_sortable_columns()
  {
    $sortables = array(
      'title' => array('title', false, __('Page Name', 'enrove-folios'), __('Table ordered by Page Name.', 'enrove-folios')),
      'menu_order' => array('menu_order', false, __('Menu Position', 'enrove-folios'), __('Table ordered by Menu Position.', 'enrove-folios')),
      'modified' => array('modified', true, __('Last Updated', 'enrove-folios'), __('Table ordered by Last Updated.', 'enrove-folios'), 'desc'),
    );

    return $sortables;
  }

  public function column_default($item, $column_name)
  {
    $post = $item;
    if ($column_name === 'publish_status') {
      $status_object = get_post_status_object($post->post_status);
      return $status_object && !empty($status_object->label) ? (string) $status_object->label : ucfirst((string) $post->post_status);
    } elseif ($column_name === 'modified') {
      $last_editor_id = (int) get_post_meta($post->ID, '_edit_last', true);
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
      $modified_label = sprintf(
        /* translators: 1: date/time value, 2: user display name */
        esc_html__('%1$s by %2$s', 'enrove-folios'),
        get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $post->ID),
        $display_name
      );
      return esc_html($modified_label);
    }

    return parent::column_default($item, $column_name);
  }

  public function column_title($post)
  {
    $can_edit_post = current_user_can('edit_post', $post->ID);

    if ($can_edit_post && 'trash' !== $post->post_status) {
      $lock_holder = wp_check_post_lock($post->ID);

      if ($lock_holder) {
        $lock_holder = get_userdata($lock_holder);
        /* translators: %s: Display name of the user editing the page. */
        $locked_text = sprintf(__('%s is currently editing', 'enrove-folios'), $lock_holder->display_name);
      } else {
        $locked_text = '';
      }

      echo '<div class="locked-info"><span class="locked-avatar">';
      // get_avatar() straight into the echo: wp_kses_post() would strip the avatar's srcset and decoding.
      echo $lock_holder ? get_avatar($lock_holder->ID, 18) : '';
      echo '</span> <span class="locked-text">' . esc_html($locked_text) . "</span></div>\n";
    }

    $pad = str_repeat('&#8212; ', $this->current_level);
    echo '<strong class="g-folio__title-wrap">';

    $title = (string) $post->post_title;
    if ($title === '') {
      $title_display = __('(no title)', 'enrove-folios');
    } else {
      $title_display = $title;
    }

    if ($can_edit_post && 'trash' !== $post->post_status) {
      printf(
        '<a class="row-title g-folio__truncate-text" href="%s" aria-label="%s" title="%s">%s%s</a>',
        esc_url(get_edit_post_link($post->ID)),
        /* translators: %s: Page title. */
        esc_attr(sprintf(__('&#8220;%s&#8221; (Edit)', 'enrove-folios'), $title !== '' ? $title : __('(no title)', 'enrove-folios'))),
        esc_attr($title),
        esc_html($pad),
        esc_html($title_display)
      );
    } else {
      printf(
        '<span class="g-folio__truncate-text" title="%s">%s%s</span>',
        esc_attr($title),
        esc_html($pad),
        esc_html($title_display)
      );
    }

    echo "</strong>\n";

    get_inline_data($post);
  }
}
?>