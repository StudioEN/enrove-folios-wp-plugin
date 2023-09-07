<?php
namespace Groove\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Modules_Manager {
	private $modules = [];

	public function __construct() {
		$modules_namespace_prefix = $this->get_modules_namespace_prefix();

		foreach ( $this->get_modules_names() as $module_name) {
			$class_name = str_replace('-', ' ', $module_name);
			$class_name = str_replace(' ', '', ucwords($class_name));

			$class_name = $modules_namespace_prefix . '\\Modules\\' . $class_name . '\Module';

			if ( $class_name::is_active() ) {
				$this->modules[$module_name] = $class_name::instance();
			}
		}
	}

	public function get_modules_names() {
		return [
			'groove-main',
		];
	}

	public function get_modules($module_name) {
		if ($module_name) {
			if (isset($this->modules[$module_name])) {
				return $this->modules[$module_name];
			}

			return null;
		}

		return $this->modules;
	}

	protected function get_modules_namespace_prefix() {
		return 'Groove';
	}
}
