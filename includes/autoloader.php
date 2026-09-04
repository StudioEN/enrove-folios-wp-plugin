<?php
namespace Groove;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Groove autoloader.
 *
 * Groove autoloader handler class is responsible for loading the different
 * classes needed to run the plugin.
 *
 * @since 1.6.0
 */
class Autoloader {

	/**
	 * Classes map.
	 *
	 * Maps Groove classes to file names.
	 *
	 * @since 1.6.0
	 * @access private
	 * @static
	 *
	 * @var array Classes used by groove.
	 */
	private static $classes_map;

	/**
	 * Classes aliases.
	 *
	 * Maps Groove classes to aliases.
	 *
	 * @since 1.6.0
	 * @access private
	 * @static
	 *
	 * @var array Classes aliases.
	 */
	private static $classes_aliases;

	/**
	 * Default path for autoloader.
	 *
	 * @var string
	 */
	private static $default_path;

	/**
	 * Default namespace for autoloader.
	 *
	 * @var string
	 */
	private static $default_namespace;

	/**
	 * Run autoloader.
	 *
	 * Register a function as `__autoload()` implementation.
	 *
	 * @param string $default_path
	 * @param string $default_namespace
	 *
	 * @since 1.6.0
	 * @access public
	 * @static
	 */
	public static function run($default_path = '', $default_namespace = '') {
		if ( '' === $default_path ) {
			$default_path = GROOVE_PATH;
		}

		if ('' === $default_namespace) {
			$default_namespace = __NAMESPACE__;
		}

		self::$default_path = $default_path;
		self::$default_namespace = $default_namespace;

		spl_autoload_register([ __CLASS__, 'autoload' ]);
	}

	/**
	 * Get classes aliases.
	 *
	 * Retrieve the classes aliases names.
	 *
	 * @since 1.6.0
	 * @access public
	 * @static
	 *
	 * @return array Classes aliases.
	 */
	public static function get_classes_aliases() {
		if ( ! self::$classes_aliases ) {
			self::init_classes_aliases();
		}

		return self::$classes_aliases;
	}

	public static function get_classes_map() {
		if ( ! self::$classes_map ) {
			self::init_classes_map();
		}

		return self::$classes_map;
	}

	private static function init_classes_map() {
		self::$classes_map = [
			'Settings'  => 'includes/settings.php',
			'Analytics' => 'includes/analytics.php',
			'Toast'     => 'includes/toast.php',
		];
	}

	/**
	 * Normalize Class Name
	 *
	 * Used to convert control names to class names.
	 *
	 * @param $string
	 * @param string $delimiter
	 *
	 * @return mixed
	 */
	private static function normalize_class_name($string, $delimiter = ' ') {
		return ucwords(str_replace( '-', '_', $string), $delimiter);
	}

	private static function init_classes_aliases() {
		self::$classes_aliases = [
			
		];
	}

	/**
	 * Load class.
	 *
	 * For a given class name, require the class file.
	 *
	 * @since 1.6.0
	 * @access private
	 * @static
	 *
	 * @param string $relative_class_name Class name.
	 */
	private static function load_class($relative_class_name) {
		$classes_map = self::get_classes_map();
    
		if (isset($classes_map[ $relative_class_name ])) {
      $filename = self::$default_path . '/' . $classes_map[$relative_class_name];
		} else {
      $filename = strtolower(preg_replace(
				[ '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
				[ '$1-$2', '-', DIRECTORY_SEPARATOR ],
				$relative_class_name
			));
        
      $filename = self::$default_path . $filename . '.php';
    }
      
		if (is_readable($filename)) {
			require_once $filename;
		}
	}

	private static function autoload($class) {
    $relative_class_name = preg_replace( '/^' . self::$default_namespace . '\\\/', '', $class);

		if (!class_exists($relative_class_name)) {
			self::load_class($relative_class_name);
		}	
	}
}
