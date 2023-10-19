<?php
namespace Groove;

use Groove\Autoloader;
use Groove\Modules\Modules_Manager;
use Groove\Contents\Contents_Manager;
use Groove\Pages\Overview;
use Groove\Pages\All_Folios;
use Groove\Pages\Folio;
use Groove\Pages\Add_New;
use Groove\Pages\Support;
use Groove\Pages\Settings;
use Groove\Menu\Menu_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Groove plugin.
 *
 * The main plugin handler class is responsible for initializing Groove. The
 * class registers and all the components required to run the plugin.
 *
 * @since 1.0.0
 */
class Plugin {
	const GROOVE_DEFAULT_POST_TYPES = [ 'folio', 'folio-page' ];

  public static $instance = null;

  public $overview;
  public $support;
	public $settings;
  public $add_new;
  public $all_folios;
	public $folio;
	public $menu_manager;
	public $modules_manager;
  public $contents_manager;
	
	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf( 'Cloning instances of the singleton "%s" class is forbidden.', get_class( $this ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'1.0.0'
		);
	}

	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf( 'Unserializing instances of the singleton "%s" class is forbidden.', get_class( $this ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'1.0.0'
		);
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			do_action( 'groove/loaded' );
		}

		return self::$instance;
	}

	public function init() {
		$this->add_cpt_support();
		$this->init_components();


		do_action( 'groove/init' );
	}

	public function get_install_time() {
		$installed_time = get_option( '_groove_installed_time' );

		if ( ! $installed_time ) {
			$installed_time = time();

			update_option( '_groove_installed_time', $installed_time );
		}

		return $installed_time;
	}

	private function init_components() {
		$this->folio = new Folio();
    $this->overview = new Overview();
		$this->all_folios = new All_Folios();
		$this->add_new = new Add_New();
		$this->support = new Support();
		$this->settings = new Settings();
		
		$this->menu_manager = new Menu_Manager();
		$this->modules_manager = new Modules_Manager();
    $this->contents_manager = new Contents_Manager();
    
		$this->menu_manager->register_actions();
	}

	/**
	 * @since 2.3.0
	 * @access public
	 */
	public function init_common() {
		
	}

	private function add_cpt_support() {
		$cpt_support = get_option( 'groove_cpt_support', self::GROOVE_DEFAULT_POST_TYPES);

		foreach ( $cpt_support as $cpt_slug ) {
			add_post_type_support($cpt_slug, 'groove');
		}
	}

	private function register_autoloader() {
		require_once GROOVE_PATH . '/includes/autoloader.php';

		Autoloader::run();
	}

	public function __get( $property ) {
		if ( property_exists( $this, $property ) ) {
			throw new \Exception( 'Cannot access private property.' );
		}

		return null;
	}

	/**
	 * Plugin constructor.
	 *
	 * Initializing Groove plugin.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function __construct() {
		$this->register_autoloader();

		add_filter( 'admin_body_class', function ($classes) {
			$classes .= ' groove';
			return $classes;
		});

		
		add_filter('rewrite_rules_array', function ($rules) {
			$new_rules = array(
				'groove-preview/?$' => 'index.php?preview=true',
			);

			return $new_rules + $rules;
		});

	
		add_filter( 'template_include', function ( $template ) {
			$post_type = get_post_type();

			if ($post_type == 'groove_folio' || $post_type == 'groove_folio_page') {
				$plugin_dir = plugin_dir_path( __FILE__ );
				$template = $plugin_dir . 'folio-preview-template.php';
			}

			return $template; 
		}, 10, 2);

		add_action( 'init', [ $this, 'init' ], 0 );
	}

	final public static function get_title() {
		return esc_html__( 'Groove', 'groove' );
	}
}

if ( ! defined( 'GROOVE_TESTS' ) ) {
	Plugin::instance();
}
