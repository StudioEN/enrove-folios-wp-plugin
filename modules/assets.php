<?php
namespace Enrove\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

abstract class Assets {

  public function get_scrren_id () {
		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return null;
		}

		return $current_screen->id;
	}

	final public function get_assets_url($file_name, $file_extension, $relative_url = null, $add_min_suffix = 'default') {
		if (!$relative_url) {
			$relative_url = $this->get_assets_relative_url() . $file_extension . '/';
		}

		$url = $this->get_assets_base_url() . $relative_url . $file_name;

		return $url . '.' . $file_extension;
	}
	
	final public function get_js_assets_url( $file_name, $relative_url = null, $add_min_suffix = 'default' ) {
		return $this->get_assets_url( $file_name, 'js', $relative_url, $add_min_suffix );
	}

	final public function get_images_assets_url( $file_name, $relative_url = null, $add_min_suffix = 'default' ) {
		if (!$relative_url) {
			$relative_url = $this->get_assets_relative_url() . 'images' . '/';
		}

		$url = $this->get_assets_base_url() . $relative_url . $file_name;

		return $url;
	}
	
	final public function get_css_assets_url ($file_name, $relative_url = null, $add_min_suffix = 'default', $add_direction_suffix = false) {
		static $direction_suffix = null;

		if ( ! $direction_suffix ) {
			$direction_suffix = is_rtl() ? '-rtl' : '';
		}

		if ( $add_direction_suffix ) {
			$file_name .= $direction_suffix;
		}

		return $this->get_assets_url( $file_name, 'css', $relative_url, $add_min_suffix );
	}

	
	public function get_assets_base_url() {
		return ENROVE_URL;
	}

	
	public function get_assets_relative_url() {
		return 'assets/';
	}
}
