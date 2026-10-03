<?php
namespace Enrove\Contents;

if (!defined('ABSPATH')) {
  exit;
}

class Contents_Manager {
  private $contents = [];

  public function __construct () {
    $contents_namespace_prefix = $this->get_contents_namespace_prefix();

		foreach ( $this->get_contents_names() as $content_name ) {
			$class_name = str_replace( '-', ' ', $content_name );
			$class_name = str_replace( ' ', '', ucwords( $class_name ) );

			$class_name = $contents_namespace_prefix . '\\Contents\\' . $class_name . '\Content';

			if ( $class_name::is_active() ) {
				$this->contents[$content_name] = $class_name::instance();
			}
		}
  }

  public function get_contents_names () {
    return [
      'folio',
      'folio-page'
    ];
  }

  public function get_content ($content_name) {
    if ($content_name) {
			if (isset($this->contents[$content_name])) {
				return $this->contents[$content_name];
			}

			return null;
		}

		return $this->contents;
  }

  protected function get_contents_namespace_prefix () {
    return 'Enrove';
  }
}

?>