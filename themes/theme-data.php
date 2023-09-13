<?php
namespace Groove\Themes;

class Theme_Data {

  public $id;
  public $title;
  public $subtitle;
  public $cover;
  public $author;
  public $theme_name;
  public $pages;

  public function __construct($id, $title, $subtitle, $pages, $cover, $logo, $theme_name, $author) {
    $this->id = $id;
    $this->title = $title;
    $this->subtitle = $subtitle;
    $this->pages = $pages;
    $this->cover = $cover;
    $this->author = $author;
    $this->logo = $logo;
    $this->theme_name = $theme_name;
  }
}

?>