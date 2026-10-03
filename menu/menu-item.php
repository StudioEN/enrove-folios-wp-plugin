<?php

namespace Enrove\Menu;
use Enrove\Pages\Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class Menu_Item {
	public $page;

	public function __construct(Page $page) {
		$this->page = $page;
	}

	abstract function get_capability();
	abstract function get_label();
	abstract function get_parent_slug();
	abstract function is_visible();

	public function render() {
		$this->page->display_page();
	}
}
