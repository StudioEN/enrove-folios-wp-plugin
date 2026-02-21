<?php
namespace Groove\Menu;

use Groove\Pages\Folio;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Item_Page;


if (!defined('ABSPATH')) {
	exit;
}

class Folio_Menu_Item extends Menu_Item_Page
{

	public function is_visible()
	{
		return false;
	}

	public function get_parent_slug()
	{
		return Overview::PAGE_ID;
	}

	public function get_label()
	{
		return esc_html__('Folio', 'groove');
	}

	public function get_page_title()
	{
		return '';
	}

	public function get_capability()
	{
		return 'edit_posts';
	}
}