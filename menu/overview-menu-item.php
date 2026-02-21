<?php
namespace Groove\Menu;

use Groove\Pages\Overview;
use Groove\Menu\Menu_Item_Page;

if (!defined('ABSPATH')) {
    exit;
}

class Overview_Menu_Item extends Menu_Item_Page
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
        return esc_html__('Overview', 'groove');
    }

    public function get_page_title()
    {
        return esc_html__('Groove Folios — Overview', 'groove');
    }

    public function get_capability()
    {
        return 'edit_posts';
    }
}
?>