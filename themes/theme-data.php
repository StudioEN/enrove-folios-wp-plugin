<?php
namespace Groove\Themes;

class Theme_Data {

  public $id;
  public $title;
  public $subtitle;
  public $cover;
  public $author;

  public function __construct($id, $title, $subtitle, $cover, $logo, $author) {
    $this->id = $id;
    $this->title = $title;
    $this->subtitle = $subtitle;
    $this->cover = $cover;
    $this->author = $author;
    $this->logo = $logo;
  }
}

?>