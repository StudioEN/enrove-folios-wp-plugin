<?php

namespace Groove\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class Menu_Item_Page extends Menu_Item {
	abstract function get_page_title();
}
