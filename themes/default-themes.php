<?php
namespace Groove\Themes;
use Groove\Modules\Assets;

class Default_Themes extends Assets {
 

  public function get_themes () {
    return array(
      'theme-1' => array(
        'ID' => 'theme-1',
        'logo_url' => $this->get_images_assets_url('theme-g-logo-01.png'),
        'thumbnail_url' => $this->get_images_assets_url('theme-thumb-01.png'),
        'cover_url' => $this->get_images_assets_url('theme-cover-01.png'),
        'name' => 'Folio Starter'
      ),
      'theme-2' => array(
        'ID' => 'theme-2',
        'logo_url' => $this->get_images_assets_url('theme-g-logo-02.png'),
        'thumbnail_url' => $this->get_images_assets_url('theme-thumb-02.png'),
        'cover_url' => $this->get_images_assets_url('theme-cover-02.png'),
        'name' => 'Groove eBook'
      ),
    );
  }
}


?>