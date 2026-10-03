<?php
namespace Enrove\Fields;

use Enrove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class FolioFields
{
  public $name;
  public $feature_image;
  public $logo;
  public $copyright;
  public $author;
  public $subtitle;
  public $use_folio;
  public $permalink;
  public $title;
  public $header_font;
  public $body_font;
  public $fonts;
  public $on_this_page_label;
  public $byline;
  public $show_byline;
  public $show_logo;
  public $proposal_version;
  public $proposal_status;
  public $proposal_prepared_for;
  public $proposal_prepared_by;
  public $proposal_contact_email;
  public $proposal_contact_name;
  public $proposal_contact_role;
  public $proposal_contact_phone;
  public $proposal_contact_linkedin;
  public $proposal_contacts;
  public $proposal_client_name;
  public $proposal_client_logo_url;
  public $proposal_date;
  public $proposal_show_in_page_nav;
  public $proposal_color_scheme;
  public $proposal_open_text;
  public $proposal_revision_log;


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

    $this->copyright = $meta['copyright'][0] ?? '';
    $this->subtitle = $meta['subtitle'][0] ?? '';
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
    $this->show_logo = isset($meta['show_logo'][0]) ? ((string) $meta['show_logo'][0] === '0' ? '0' : '1') : '1';
    $this->proposal_version = isset($meta['proposal_version'][0]) ? sanitize_text_field((string) $meta['proposal_version'][0]) : '';
    $this->proposal_status = isset($meta['proposal_status'][0]) ? sanitize_text_field((string) $meta['proposal_status'][0]) : '';
    $this->proposal_prepared_for = isset($meta['proposal_prepared_for'][0]) ? sanitize_text_field((string) $meta['proposal_prepared_for'][0]) : '';
    $this->proposal_prepared_by = isset($meta['proposal_prepared_by'][0]) ? sanitize_text_field((string) $meta['proposal_prepared_by'][0]) : '';
    $this->proposal_contact_email = isset($meta['proposal_contact_email'][0]) ? sanitize_email((string) $meta['proposal_contact_email'][0]) : '';
    $this->proposal_contact_name = isset($meta['proposal_contact_name'][0]) ? sanitize_text_field((string) $meta['proposal_contact_name'][0]) : '';
    $this->proposal_contact_role = isset($meta['proposal_contact_role'][0]) ? sanitize_text_field((string) $meta['proposal_contact_role'][0]) : '';
    $this->proposal_contact_phone = isset($meta['proposal_contact_phone'][0]) ? sanitize_text_field((string) $meta['proposal_contact_phone'][0]) : '';
    $this->proposal_contact_linkedin = isset($meta['proposal_contact_linkedin'][0]) ? esc_url_raw((string) $meta['proposal_contact_linkedin'][0]) : '';
    $this->proposal_contacts = isset($meta['proposal_contacts'][0]) ? sanitize_textarea_field((string) $meta['proposal_contacts'][0]) : '';
    $this->proposal_client_name = isset($meta['proposal_client_name'][0]) ? sanitize_text_field((string) $meta['proposal_client_name'][0]) : '';
    $this->proposal_client_logo_url = isset($meta['proposal_client_logo_url'][0]) ? esc_url_raw((string) $meta['proposal_client_logo_url'][0]) : '';
    $this->proposal_date = isset($meta['proposal_date'][0]) ? sanitize_text_field((string) $meta['proposal_date'][0]) : '';
    $this->proposal_show_in_page_nav = isset($meta['proposal_show_in_page_nav'][0]) ? ((string) $meta['proposal_show_in_page_nav'][0] === '0' ? '0' : '1') : '1';
    $this->proposal_color_scheme = isset($meta['proposal_color_scheme'][0]) && (string) $meta['proposal_color_scheme'][0] === 'dynamic' ? 'dynamic' : 'default';
    $this->proposal_open_text = isset($meta['proposal_open_text'][0]) ? sanitize_text_field((string) $meta['proposal_open_text'][0]) : '';
    $this->proposal_revision_log = isset($meta['proposal_revision_log'][0]) ? (string) $meta['proposal_revision_log'][0] : '[]';
  }
}
?>
