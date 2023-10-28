<?php
namespace Groove\List;
use Groove\List\List_Table;
use Groove\Pages\Folio;

class Folio_Page_List_Table extends List_Table {
  function __construct(Folio $page, $post_type) {
    parent::__construct($page, $post_type);

    add_filter('get_edit_post_link', [$this, 'edit_post_link'], 10, 3);
  }

  public function edit_post_link($link, $post_id, $context) {
    
    if ($context === 'display') {
      $link = $link . '&folio_id=' . $_REQUEST['folio_id'];
    }
    
    // 对于其他上下文，保持原始链接不变
    return $link;
  }

  function get_columns () {
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['title'] = _x( 'Title', 'column name' );
		$posts_columns['menu_order'] = _x( 'Menu position', 'column name' );
		$posts_columns['date'] = __( 'Date' );	

    return $posts_columns;
  }

  public function get_query_args () {
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