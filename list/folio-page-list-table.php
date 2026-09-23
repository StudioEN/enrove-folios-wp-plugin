<?php
namespace Groove\List;
use Groove\List\List_Table;
use Groove\Pages\Folio;

if (!defined('ABSPATH')) {
  exit;
}

class Folio_Page_List_Table extends List_Table
{
  function __construct(Folio $page, $post_type)
  {
    parent::__construct($page, $post_type);

    add_filter('get_edit_post_link', [$this, 'edit_post_link'], 10, 3);
  }

  public function edit_post_link($link, $post_id, $context)
  {
    if ($context === 'display' && isset($_REQUEST['folio_id'])) {
      $link = $link . '&folio_id=' . (int) $_REQUEST['folio_id'];
    }
    return $link;
  }

  public function get_columns()
  {
    $posts_columns = array();
    $posts_columns['cb'] = '<input type="checkbox" />';
    $posts_columns['title'] = esc_html__('Page Name', 'groove-folios');
    $posts_columns['menu_order'] = esc_html__('Menu Position', 'groove-folios');
    $posts_columns['publish_status'] = esc_html__('Publish Status', 'groove-folios');
    $posts_columns['modified'] = esc_html__('Last Updated', 'groove-folios');

    return $posts_columns;
  }

  protected function get_sortable_columns()
  {
    $sortables = array(
      'title' => array('title', false, __('Page Name', 'groove-folios'), __('Table ordered by Page Name.', 'groove-folios')),
      'menu_order' => array('menu_order', false, __('Menu Position', 'groove-folios'), __('Table ordered by Menu Position.', 'groove-folios')),
      'modified' => array('modified', true, __('Last Updated', 'groove-folios'), __('Table ordered by Last Updated.', 'groove-folios'), 'desc'),
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
        $display_name = $author ? $author->display_name : esc_html__('Unknown user', 'groove-folios');
      }
      $modified_label = sprintf(
        /* translators: 1: date/time value, 2: user display name */
        esc_html__('%1$s by %2$s', 'groove-folios'),
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
        $locked_avatar = get_avatar($lock_holder->ID, 18);
        $locked_text = esc_html(sprintf(__('%s is currently editing', 'groove-folios'), $lock_holder->display_name));
      } else {
        $locked_avatar = '';
        $locked_text = '';
      }

      echo '<div class="locked-info"><span class="locked-avatar">' . $locked_avatar . '</span> <span class="locked-text">' . $locked_text . "</span></div>\n";
    }

    $pad = str_repeat('&#8212; ', $this->current_level);
    echo '<strong class="g-folio__title-wrap">';

    $title = (string) $post->post_title;
    $title_tooltip = esc_attr($title);
    if ($title === '') {
      $title_display = esc_html__('(no title)', 'groove-folios');
    } else {
      $title_display = esc_html($title);
    }

    if ($can_edit_post && 'trash' !== $post->post_status) {
      printf(
        '<a class="row-title g-folio__truncate-text" href="%s" aria-label="%s" title="%s">%s%s</a>',
        get_edit_post_link($post->ID),
        esc_attr(sprintf(__('&#8220;%s&#8221; (Edit)', 'groove-folios'), $title !== '' ? $title : __('(no title)', 'groove-folios'))),
        $title_tooltip,
        $pad,
        $title_display
      );
    } else {
      printf(
        '<span class="g-folio__truncate-text" title="%s">%s%s</span>',
        $title_tooltip,
        $pad,
        $title_display
      );
    }

    echo "</strong>\n";

    get_inline_data($post);
  }

  public function get_query_args()
  {
    $args = parent::get_query_args();

    if (isset($_REQUEST['folio_id'])) {
      $args['meta_query'] = array(
        array(
          'key' => 'folio_id',
          'value' => $_REQUEST['folio_id'],
          'compare' => '=',
          'type' => 'NUMERIC'
        )
      );
    }

    return $args;
  }
}
?>