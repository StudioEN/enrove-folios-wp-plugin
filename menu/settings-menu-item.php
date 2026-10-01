<?php
namespace Groove\Menu;

use Groove\Menu\Menu_Item_Page;
use Groove\Pages\Overview;
use Groove\Pages\Settings;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Settings_Menu_Item extends Menu_Item_Page {
	

	public function is_visible() {
		return true;
	}

	public function get_parent_slug() {
		return Overview::PAGE_ID;
	}

	public function get_label() {
		return esc_html__( 'Settings', 'groove-folios' );
	}

	public function get_page_title() {
		return esc_html__( 'Groove Folios Settings', 'groove-folios' );
	}

	public function get_capability() {
		return 'manage_options';
	}
}
