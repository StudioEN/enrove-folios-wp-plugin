<?php
namespace Groove\Menu;

use Groove\Menu\Menu_Item_Page;
use Groove\Pages\Overview;
use Groove\Pages\Support;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Support_Menu_Item extends Menu_Item_Page {
	public function is_visible() {
		return true;
	}

	public function get_parent_slug() {
		return Overview::PAGE_ID;
	}

	public function get_label() {
		return esc_html__( 'Support', 'groove' );
	}

	public function get_page_title() {
		return '';
	}

	public function get_capability() {
		return 'manage_options';
	}
}
