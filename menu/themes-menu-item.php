<?php
namespace Enrove\Menu;

use Enrove\Menu\Menu_Item_Page;
use Enrove\Pages\Overview;
use Enrove\Pages\Themes;

if (!defined('ABSPATH')) {
    exit;
}

class Themes_Menu_Item extends Menu_Item_Page
{

    public function is_visible()
    {
        return true;
    }

    public function get_parent_slug()
    {
        return Overview::PAGE_ID;
    }

    public function get_label()
    {
        return esc_html__('Themes', 'enrove-folios');
    }

    public function get_page_title()
    {
        return esc_html__('Folio Themes', 'enrove-folios');
    }

    public function get_capability()
    {
        return 'manage_options';
    }
}