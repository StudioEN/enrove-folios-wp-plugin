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
use Groove\Pages\Themes;
use Groove\Menu\Menu_Manager;
use Groove\Themes\Themes_Manager;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
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
class Plugin
{
	const GROOVE_DEFAULT_POST_TYPES = ['folio', 'folio-page'];

	public static $instance = null;

	public $overview;
	public $support;
	public $settings;
	public $add_new;
	public $all_folios;
	public $folio;
	public $themes;
	public $menu_manager;
	public $modules_manager;
	public $contents_manager;

	public function __clone()
	{
		_doing_it_wrong(
			__FUNCTION__,
			sprintf('Cloning instances of the singleton "%s" class is forbidden.', get_class($this)), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'1.0.0'
		);
	}

	public function __wakeup()
	{
		_doing_it_wrong(
			__FUNCTION__,
			sprintf('Unserializing instances of the singleton "%s" class is forbidden.', get_class($this)), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'1.0.0'
		);
	}

	public static function instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
			do_action('groove/loaded');
		}

		return self::$instance;
	}

	public function init()
	{
		$this->add_rewrite();
		$this->maybe_flush_rewrite_rules();
		$this->add_cpt_support();
		$this->init_components();

		do_action('groove/init');
	}

	/**
	 * Flush rewrite rules once per plugin version so /folio/ URLs always resolve.
	 * Uses a version-stamped DB option to avoid flushing on every request.
	 */
	private function maybe_flush_rewrite_rules()
	{
		$flushed_version = get_option('groove_rewrite_flushed_version', '');
		if ($flushed_version !== GROOVE_VERSION) {
			flush_rewrite_rules(false); // false = soft flush (no .htaccess write)
			update_option('groove_rewrite_flushed_version', GROOVE_VERSION);
		}
	}

	public function get_install_time()
	{
		$installed_time = get_option('_groove_installed_time');

		if (!$installed_time) {
			$installed_time = time();

			update_option('_groove_installed_time', $installed_time);
		}

		return $installed_time;
	}

	private function init_components()
	{
		$this->folio = new Folio();
		$this->overview = new Overview();
		$this->all_folios = new All_Folios();
		$this->add_new = new Add_New();
		$this->support = new Support();
		$this->settings = new Settings();
		$this->themes = new Themes();

		$this->menu_manager = new Menu_Manager();
		$this->modules_manager = new Modules_Manager();
		$this->contents_manager = new Contents_Manager();

		$this->menu_manager->register_actions();
	}

	/**
	 * @since 2.3.0
	 * @access public
	 */
	public function init_common()
	{

	}

	private function add_cpt_support()
	{
		$cpt_support = get_option('groove_cpt_support', self::GROOVE_DEFAULT_POST_TYPES);

		foreach ($cpt_support as $cpt_slug) {
			add_post_type_support($cpt_slug, 'groove');
		}
	}

	private function add_rewrite()
	{
		add_rewrite_rule('^folio/[^/]+/page/[^/]+/?$', 'index.php?post_type=groove_folio_page', 'top');
		add_rewrite_rule('^folio/[^/]+/?$', 'index.php?post_type=groove_folio', 'top');
	}

	private function register_autoloader()
	{
		require_once GROOVE_PATH . '/includes/autoloader.php';

		Autoloader::run();
	}

	public function __get($property)
	{
		if (property_exists($this, $property)) {
			throw new \Exception('Cannot access private property.');
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
	private function __construct()
	{
		$this->register_autoloader();

		// Boot the theme registry immediately so it is available everywhere.
		Themes_Manager::register_defaults();

		add_filter('admin_body_class', function ($classes) {
			$classes .= ' groove';
			return $classes;
		});


		add_filter('rewrite_rules_array', function ($rules) {
			$new_rules = array(
				'groove-preview/?$' => 'index.php?preview=true',
			);

			return $new_rules + $rules;
		});

		add_filter('post_type_link', function ($post_link, $post, $leavename) {
			$groove_post_link = Utils::get_folio_permalink_by_id($post->ID);

			if ($groove_post_link) {
				return $groove_post_link;
			}

			return $post_link;
		}, 10, 3);


		add_filter('template_include', function ($template) {
			$url_parts = parse_url($_SERVER['REQUEST_URI']);
			$current_path = $url_parts['path'];
			$pattern = '/^\/folio\//';

			if (preg_match($pattern, $current_path)) {
				$plugin_dir = plugin_dir_path(__FILE__);
				$template = $plugin_dir . 'folio-preview-template.php';
			}

			return $template;
		}, 10, 2);

		add_action('save_post_groove_folio_page', function ($post_id, $post, $update) {
			// Skip autosaves and new post creation (not updates).
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}

			// Sync slug to the post title whenever a folio page is saved
			// via the classic editor (REST-based block editor is handled separately below).
			if (!empty($_POST['post_title'])) {
				$title = sanitize_text_field($_POST['post_title']);
				$new_slug = wp_unique_post_slug(
					sanitize_title($title),
					$post_id,
					$post->post_status,
					'groove_folio_page',
					$post->post_parent
				);
				// Only update if the slug has actually changed to avoid recursion.
				if ($new_slug !== $post->post_name) {
					remove_action('save_post_groove_folio_page', __FUNCTION__, 10);
					wp_update_post(array('ID' => $post_id, 'post_name' => $new_slug));
					add_action('save_post_groove_folio_page', __FUNCTION__, 10, 3);
				}
			}
		}, 10, 3);

		// Block editor saves via REST API — fires after the post is fully written.
		add_action('rest_after_insert_groove_folio_page', function ($post) {
			if (empty($post->post_title)) {
				return;
			}
			$new_slug = wp_unique_post_slug(
				sanitize_title($post->post_title),
				$post->ID,
				$post->post_status,
				'groove_folio_page',
				$post->post_parent
			);
			if ($new_slug !== $post->post_name) {
				wp_update_post(array('ID' => $post->ID, 'post_name' => $new_slug));
			}
		});

		// ── folio_id meta injection ─────────────────────────────────────────────
		// When a new folio page is created via "Add Page" the folio_id is in the URL
		// but never automatically saved to post meta. Both hooks below handle this:
		// classic editor via save_post (POST data), block editor via REST (query string).

		add_action('save_post_groove_folio_page', function ($post_id, $post, $update) {
			// Only care about the very first save (not an update).
			if ($update) {
				return;
			}
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}

			// Classic editor passes folio_id in the URL / POST.
			$folio_id = 0;
			if (!empty($_REQUEST['folio_id'])) {
				$folio_id = (int)$_REQUEST['folio_id'];
			}
			elseif (!empty($_POST['folio_id'])) {
				$folio_id = (int)$_POST['folio_id'];
			}

			if ($folio_id && get_post_type($folio_id) === 'groove_folio') {
				update_post_meta($post_id, 'folio_id', $folio_id);
			}
		}, 20, 3);

		// Block editor creates posts via REST — the folio_id comes from the Referer header.
		add_action('rest_after_insert_groove_folio_page', function ($post, $request) {
			if (get_post_meta($post->ID, 'folio_id', true)) {
				return; // Already set — nothing to do.
			}

			// The block editor opens a URL like post-new.php?post_type=groove_folio_page&folio_id=X
			// The Referer header carries that URL into REST requests.
			$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
			if ($referer) {
				$query = wp_parse_url($referer, PHP_URL_QUERY);
				parse_str((string)$query, $params);
				if (!empty($params['folio_id'])) {
					$folio_id = (int)$params['folio_id'];
					if ($folio_id && get_post_type($folio_id) === 'groove_folio') {
						update_post_meta($post->ID, 'folio_id', $folio_id);
					}
				}
			}
		}, 10, 2);

		add_action('init', [$this, 'init'], 0);
	}

	final public static function get_title()
	{
		return esc_html__('Groove', 'groove');
	}
}

if (!defined('GROOVE_TESTS')) {
	Plugin::instance();
}