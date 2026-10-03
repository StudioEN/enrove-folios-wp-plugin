<?php
namespace Enrove\Menu;

use Enrove\Pages\Folio;
use Enrove\Pages\Overview;
use Enrove\Menu\Menu_Item_Page;


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
		return esc_html__('Folio', 'enrove-folios');
	}

	public function get_page_title()
	{
		return esc_html__('Edit Folio', 'enrove-folios');
	}

	public function get_capability()
	{
		return 'edit_posts';
	}
}