<?php
namespace Groove\Contents;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

abstract class BaseContent
{
	private $reflection;

	private $components = [];

	protected static $_instances = [];


	abstract public function get_key();
	abstract public function get_name();

	public static function instance()
	{
		$class_name = static::class_name();

		if (empty(static::$_instances[$class_name])) {
			static::$_instances[$class_name] = new static();
		}

		return static::$_instances[$class_name];
	}


	public static function is_active()
	{
		return true;
	}


	public static function class_name()
	{
		return get_called_class();
	}

	public static function get_experimental_data()
	{
		return [];
	}

	public function __clone()
	{
		_doing_it_wrong(
			__FUNCTION__,
			sprintf('Cloning instances of the singleton "%s" class is forbidden.', get_class($this)), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			GROOVE_VERSION
		);
	}


	public function __wakeup()
	{
		_doing_it_wrong(
			__FUNCTION__,
			sprintf('Unserializing instances of the singleton "%s" class is forbidden.', get_class($this)), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			GROOVE_VERSION
		);
	}


	public function get_reflection()
	{
		if (null === $this->reflection) {
			$this->reflection = new \ReflectionClass($this);
		}

		return $this->reflection;
	}


	public function add_component($id, $instance)
	{
		$this->components[$id] = $instance;
	}

	public function get_components()
	{
		return $this->components;
	}


	public function get_component($id)
	{
		if (isset($this->components[$id])) {
			return $this->components[$id];
		}

		return false;
	}


	final protected function get_assets_url($file_name, $file_extension, $relative_url = null, $add_min_suffix = 'default')
	{
		if (!$relative_url) {
			$relative_url = $this->get_assets_relative_url() . $file_extension . '/';
		}

		$url = $this->get_assets_base_url() . $relative_url . $file_name;

		return $url . '.' . $file_extension;
	}


	final protected function get_js_assets_url($file_name, $relative_url = null, $add_min_suffix = 'default')
	{
		return $this->get_assets_url($file_name, 'js', $relative_url, $add_min_suffix);
	}


	final protected function get_css_assets_url($file_name, $relative_url = null, $add_min_suffix = 'default', $add_direction_suffix = false)
	{
		static $direction_suffix = null;

		if (!$direction_suffix) {
			$direction_suffix = is_rtl() ? '-rtl' : '';
		}

		if ($add_direction_suffix) {
			$file_name .= $direction_suffix;
		}

		return $this->get_assets_url($file_name, 'css', $relative_url, $add_min_suffix);
	}


	protected function get_assets_base_url()
	{
		return GROOVE_URL;
	}


	protected function get_assets_relative_url()
	{
		return 'assets/';
	}
}
