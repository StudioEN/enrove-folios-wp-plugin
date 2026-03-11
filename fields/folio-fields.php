<?php
namespace Groove\Fields;

use Groove\Utils\Utils;

class FolioFields
{
  public $name;
  public $feature_image;
  public $logo;
  public $copyright;
  public $author;
  public $subtitle;
  public $permission;
  public $use_folio;
  public $permalink;
  public $title;
  public $header_font;
  public $body_font;
  public $fonts;
  public $on_this_page_label;
  public $password;
  public $byline;
  public $show_byline;


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
    } else {
      $this->feature_image = null;
    }

    $logo_id = isset($meta['logo_id'][0]) ? (int) $meta['logo_id'][0] : 0;
    $this->logo = $logo_id > 0 ? get_post($logo_id) : null;

    $this->ID = $post->ID;
    $this->name = $post->post_name ?? '';
    $this->title = $post->post_title ?? '';
    $this->author = $post->post_author ?? '';
    $this->password = $post->post_password ?? '';

    $this->copyright = $meta['copyright'][0] ?? '';
    $this->subtitle = $meta['subtitle'][0] ?? '';
    $this->permission = $meta['permission'][0] ?? '';
    $this->use_folio = $meta['use_folio'][0] ?? '1';
    $this->theme_id = $meta['theme_id'][0] ?? '';
    $this->permalink = $meta['permalink'][0] ?? '';
    $legacy_font = Utils::normalize_primary_font_key($meta['fonts'][0] ?? '');
    $this->header_font = Utils::normalize_primary_font_key($meta['header_font'][0] ?? '');
    $this->body_font = Utils::normalize_primary_font_key($meta['body_font'][0] ?? '');
    if ($this->header_font === '' && $legacy_font !== '') {
      $this->header_font = $legacy_font;
    }
    if ($this->body_font === '' && $legacy_font !== '') {
      $this->body_font = $legacy_font;
    }
    // Keep legacy property populated for older call sites.
    $this->fonts = $this->body_font;
    $this->on_this_page_label = Utils::sanitize_on_this_page_label($meta['on_this_page_label'][0] ?? '');
    $this->byline = isset($meta['byline'][0]) ? (int) $meta['byline'][0] : (int) $this->author;
    if ($this->byline <= 0) {
      $this->byline = (int) $this->author;
    }
    $this->show_byline = isset($meta['show_byline'][0]) ? ((string) $meta['show_byline'][0] === '0' ? '0' : '1') : '1';
  }
}
?>
