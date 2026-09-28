<?php
namespace Groove;

use Groove\Autoloader;
use Groove\Modules\Modules_Manager;
use Groove\Contents\Contents_Manager;
use Groove\Pages\Overview;
use Groove\Pages\All_Folios;
use Groove\Pages\Folio;
use Groove\Pages\Add_New;
use Groove\Pages\Settings;
use Groove\Pages\Themes;
use Groove\Menu\Menu_Manager;
use Groove\Themes\Themes_Manager;
use Groove\Themes\Theme_Blocks;
use Groove\Themes\Site_Theme_Isolation;
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
			sprintf('Cloning instances of the singleton "%s" class is forbidden.', esc_html(get_class($this))),
			esc_html(GROOVE_VERSION)
		);
	}

	public function __wakeup()
	{
		_doing_it_wrong(
			__FUNCTION__,
			sprintf('Unserializing instances of the singleton "%s" class is forbidden.', esc_html(get_class($this))),
			esc_html(GROOVE_VERSION)
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
		$this->clean_up_removed_features();

		do_action('groove/init');
	}

	/**
	 * Clear what the removed Feedback page and usage analytics left behind.
	 *
	 * A site that simply upgrades never deactivates, so the feedback retry cron
	 * would otherwise stay in the cron array and fire hourly with nothing left
	 * to answer it, and the analytics opt-in would sit in the options table
	 * recording a consent to something the plugin no longer does.
	 *
	 * Submissions already stored in the `groove_feedback` post type are left
	 * alone. They are the site owner's data; the post type is just no longer
	 * registered, so they are invisible rather than gone. Drop this method once
	 * no install can still be upgrading from a build that had either feature.
	 */
	private function clean_up_removed_features()
	{
		if (get_option('_groove_removed_features_cleaned')) {
			return;
		}

		wp_clear_scheduled_hook('groove/feedback/retry');
		delete_option('groove_usage_analytics');

		update_option('_groove_removed_features_cleaned', 1);
	}

	/**
	 * Flush rewrite rules once per plugin version so /folio/ URLs always resolve.
	 * Uses a version-stamped DB option to avoid flushing on every request.
	 */
	private function maybe_flush_rewrite_rules()
	{
		// The /folio/ routing is handled entirely by the template_include filter
		// (see the __construct below). No custom rewrite rules are needed.
		// Keeping this method in case we need to flush for other reasons in future.
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
		// NOTE: No custom rewrite rules here by design.
		//
		// All /folio/ routing is handled by the template_include filter in __construct().
		// Adding rewrite rules for groove_folio/groove_folio_page causes WordPress to see
		// a CPT archive query (post_type=groove_folio, no 'name'). Since has_archive=false,
		// WordPress's redirect_canonical fires and bounces the visitor to the home page —
		// before template_include ever gets a chance to intercept.
		//
		// The template_include approach works cleanly without any rewrite rules:
		// the request naturally 404s in WP's main loop, then the filter swaps in
		// folio-preview-template.php which does its own draft-safe WP_Query.
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
		Theme_Blocks::register();
		Site_Theme_Isolation::register();

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


		// Run before redirect_canonical (priority 10) to prevent WP from
		// "helpfully" redirecting 404s (drafts) to the homepage.
		add_action('template_redirect', function () {
			// Theme picker preview — admin-only, nonce verified inside the template.
			if (isset($_GET['groove_theme_preview'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing check only; theme-picker-preview-template.php verifies the nonce and capability before rendering.
				Site_Theme_Isolation::isolate_front_end();
				require_once plugin_dir_path(__FILE__) . 'theme-picker-preview-template.php';
				exit;
			}

			$current_path = Utils::get_current_path();
			$base_slug = Utils::get_folio_base_slug();
			$pattern = '#^/' . preg_quote($base_slug, '#') . '/#';
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only front-end routing: public query vars that pick which folio to render, like core's ?p=; nothing is written.
			$is_query_preview = isset($_GET['groove_preview']) && '1' === $_GET['groove_preview'];
			$query_folio_id = isset($_GET['folio_id']) ? intval(wp_unslash($_GET['folio_id'])) : 0;
			$query_post_id = isset($_GET['p']) ? intval(wp_unslash($_GET['p'])) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$is_query_groove_context = false;
			if ($query_folio_id && get_post_type($query_folio_id) === 'groove_folio') {
				$is_query_groove_context = true;
			}
			if ($query_post_id) {
				$query_post_type = get_post_type($query_post_id);
				if (in_array($query_post_type, array('groove_folio', 'groove_folio_page'), true)) {
					$is_query_groove_context = true;
				}
			}

			if ($is_query_preview || $is_query_groove_context || preg_match($pattern, $current_path)) {
				// A folio is its own document: nothing of the site's theme.
				Site_Theme_Isolation::isolate_front_end();
				$plugin_dir = plugin_dir_path(__FILE__);
				require_once $plugin_dir . 'folio-preview-template.php';
				exit; // Stop WP execution, we've handled the template
			}
		}, 5);

		// Sync slug to the post title whenever a folio page is saved via the
		// classic editor (the REST-based block editor is handled separately below).
		// Held in a variable so the callback can unhook itself around its own
		// wp_update_post(): __FUNCTION__ inside a closure is "{closure}", which
		// names no registered callback, so the old remove_action() removed nothing.
		$sync_page_slug = function ($post_id, $post, $update) use (&$sync_page_slug) {
			// Skip autosaves and revisions.
			if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
				return;
			}
			if (!current_user_can('edit_post', $post_id)) {
				return;
			}

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- post_title only arrives from a classic-editor or Quick Edit save, and both requests verify their nonce (update-post_{ID}, inlineeditnonce) before save_post fires; edit_post is checked above.
			if (!isset($_POST['post_title']) || '' === sanitize_text_field(wp_unslash($_POST['post_title']))) {
				return;
			}
			$title = sanitize_text_field(wp_unslash($_POST['post_title']));
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			$new_slug = wp_unique_post_slug(
				sanitize_title($title),
				$post_id,
				$post->post_status,
				'groove_folio_page',
				$post->post_parent
			);
			// Only update if the slug has actually changed, and unhooked while
			// updating, so the save this triggers does not come back here.
			if ($new_slug !== $post->post_name) {
				remove_action('save_post_groove_folio_page', $sync_page_slug, 10);
				wp_update_post(array('ID' => $post_id, 'post_name' => $new_slug));
				add_action('save_post_groove_folio_page', $sync_page_slug, 10, 3);
			}
		};
		add_action('save_post_groove_folio_page', $sync_page_slug, 10, 3);

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
				if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
					return;
				}
				if (!current_user_can('edit_post', $post_id)) {
					return;
				}

				// Classic editor passes folio_id in the URL / POST.
				// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- A first save is either core's auto-draft on opening post-new.php (a screen load core runs without a nonce) or a save whose request verified its own nonce; edit_post is checked above, and the int must name a groove_folio. It only links the new page to that folio.
				$folio_id = 0;
				if (!empty($_GET['folio_id'])) {
					$folio_id = intval(wp_unslash($_GET['folio_id']));
				} elseif (!empty($_POST['folio_id'])) {
					$folio_id = intval(wp_unslash($_POST['folio_id']));
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

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
				$referer = wp_get_referer();
				if ($referer) {
				$query = wp_parse_url($referer, PHP_URL_QUERY);
				parse_str((string) $query, $params);
				if (!empty($params['folio_id'])) {
					$folio_id = (int) $params['folio_id'];
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
		return esc_html__('Groove', 'groove-folios');
	}
}

if (!defined('GROOVE_TESTS')) {
	Plugin::instance();
}
