<?php 
namespace Groove\Fields;

class FolioFields {

  public $copyright;
  public $author;
  public $subtitle;
  public $permission;
  public $permalink;
  public $title;
  public $fonts;
  public $password;

  public $theme_id;

  public $ID;

  public function __construct($post) {
    $meta = get_post_meta($post->ID);

    

    $this->ID = $post->ID;
    $this->copyright = $meta['copyright'][0];
    $this->author = $post->post_author;
    $this->subtitle = $meta['subtitle'][0];
    $this->permission = $meta['permission'][0];
    $this->theme_id = $meta['theme_id'][0];
    $this->permalink = $meta['permalink'][0];
    $this->title = $post->post_title;
    $this->fonts = $meta['fonts'][0];
    $this->password = $post->post_password;
  }
}
?>