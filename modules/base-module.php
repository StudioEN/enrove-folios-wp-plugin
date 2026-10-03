<?php
namespace Enrove\Modules;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

abstract class BaseModule extends Assets
{
	protected static $_instances = [];

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

	public function __clone()
	{
		_doing_it_wrong(
			__FUNCTION__,
			sprintf('Cloning instances of the singleton "%s" class is forbidden.', esc_html(get_class($this))),
			esc_html(ENROVE_VERSION)
		);
	}

	public function __wakeup()
	{
		_doing_it_wrong(
			__FUNCTION__,
			sprintf('Unserializing instances of the singleton "%s" class is forbidden.', esc_html(get_class($this))),
			esc_html(ENROVE_VERSION)
		);
	}
}
