<?php
namespace Groove\Themes;

class Theme_Page_Data {

  public $id;
  public $title;
  public $feature_image;
  public $theme_name;
  public $pages;

  public function __construct($id, $title, $pages, $feature_image, $theme_name) {
    $this->id = $id;
    $this->title = $title;
    $this->pages = $pages;
    $this->cover = $feature_image;
    $this->theme_name = $theme_name;
  }
}

?>