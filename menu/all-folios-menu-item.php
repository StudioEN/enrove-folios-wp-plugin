<?php
namespace Groove\Menu;

use Groove\Menu\Menu_Item_Page;
use Groove\Pages\Overview;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class All_Folios_Menu_Item extends Menu_Item_Page {
	
	public function is_visible() {
		return true;
	}

	public function get_parent_slug() {
		return Overview::PAGE_ID;
	}

	public function get_label() {
		return esc_html__( 'All Folios', 'groove-folios' );
	}

	public function get_page_title() {
		return esc_html__( 'All Folios', 'groove-folios' );
	}

	public function get_capability() {
		return 'edit_posts';
	}
}
