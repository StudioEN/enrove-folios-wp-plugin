<?php
namespace Groove\Fields;

class FolioFields
{
  public $name;
  public $feature_image;
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

  public function __construct($post)
  {
    if (!isset($post->ID)) {
      return;
    }

    $meta = get_post_meta($post->ID);

    if (has_post_thumbnail($post->ID)) {
      $thumbnail_id = get_post_thumbnail_id($post->ID);
      $this->feature_image = get_post($thumbnail_id);
    }
    else {
      $this->feature_image = null;
    }

    $this->ID = $post->ID;
    $this->name = $post->post_name ?? '';
    $this->title = $post->post_title ?? '';
    $this->author = $post->post_author ?? '';
    $this->password = $post->post_password ?? '';

    $this->copyright = $meta['copyright'][0] ?? '';
    $this->subtitle = $meta['subtitle'][0] ?? '';
    $this->permission = $meta['permission'][0] ?? '';
    $this->theme_id = $meta['theme_id'][0] ?? '';
    $this->permalink = $meta['permalink'][0] ?? '';
    $this->fonts = $meta['fonts'][0] ?? '';
  }
}
?>