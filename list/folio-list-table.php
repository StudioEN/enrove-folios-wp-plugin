<?php
namespace Groove\List;
use Groove\List\List_Table;
use Groove\Pages\All_Folios;

class Folio_List_Table extends List_Table {
  function __construct(All_Folios $page, $post_type) {
    parent::__construct($page, $post_type);

    add_filter('get_edit_post_link', [$this, 'edit_link'], 10, 2);
  }

  public function count_posts () {
    $post_type = $this->screen->post_type;
    return wp_count_posts($post_type);
  }

  function edit_link( $url, $post_id ) {
    $post_type = get_post_type( $post_id );
    $meta = get_post_meta( $post_id );
    $theme_id = isset($meta['theme_id'][0]) ? $meta['theme_id'][0] : '';

    if ( $this->post_type === $post_type ) {
      $url = add_query_arg(
        array(
          'page' => 'groove-folio',
          'folio_id' => (int) $post_id,
          'theme_id' => sanitize_key($theme_id),
        ),
        admin_url('admin.php')
      );
      return esc_url($url);
    }

    return $url;
  }
}
?>
