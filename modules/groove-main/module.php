<?php
namespace Groove\Modules\GrooveMain;

use Groove\Modules\BaseModule;

if (!defined('ABSPATH')) {
	exit;
}

class Module extends BaseModule
{

	public static function is_active()
	{
		return is_admin();
	}

	public function get_name()
	{
		return 'groove-main';
	}

	private function is_in_block_editor_page()
	{
		$post = get_post();

		if (!use_block_editor_for_post($post)) {
			return false;
		}

		// Check if it's a new post/page in the block editor
		if (strpos($_SERVER['REQUEST_URI'], 'post-new.php') !== false) {
			return true;
		}

		// Check if it's an existing post/page being edited in the block editor
		if (isset($_GET['post']) && strpos($_SERVER['REQUEST_URI'], 'post.php') !== false) {
			return true;
		}



		return false;
	}

	private function enqueue_scripts()
	{
		// Vite integration
		$is_vite_dev = false;
		$vite_port = 5173;

		// Optional: simple check to see if the dev server is active
		// Note: in a deep WP dev environment we might use a constant `define('IS_VITE_DEVELOPMENT', true);`
		// Here we'll do a quick socket check (fails gracefully if not running)
		if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
			$connection = @fsockopen('localhost', $vite_port, $errno, $errstr, 0.1);
			if (is_resource($connection)) {
				$is_vite_dev = true;
				fclose($connection);
			}
		}

		if ($is_vite_dev) {
			// Enqueue Vite client for HMR
			wp_enqueue_script('vite-client', 'http://localhost:' . $vite_port . '/@vite/client', [], null, true);

			// Add type="module" to vite-client
			add_filter('script_loader_tag', function ($tag, $handle, $src) {
				if ($handle === 'vite-client' || $handle === 'groove-tailwind-vite') {
					return '<script type="module" src="' . esc_url($src) . '"></script>';
				}
				return $tag;
			}, 10, 3);

			// Enqueue our tailwind entry directly from the Vite dev server
			wp_enqueue_script('groove-tailwind-vite', 'http://localhost:' . $vite_port . '/assets/css/tailwind.css', [], null, true);
		} else {
			// Production mode: try to read the manifest.json
			$manifest_path = plugin_dir_path(dirname(__DIR__)) . 'assets/build/.vite/manifest.json';
			if (file_exists($manifest_path)) {
				$manifest = json_decode(file_get_contents($manifest_path), true);
				if (isset($manifest['assets/css/tailwind.css']['file'])) {
					$css_file = $manifest['assets/css/tailwind.css']['file'];
					wp_enqueue_style('groove-tailwind', plugin_dir_url(dirname(__DIR__)) . 'assets/build/' . $css_file, [], GROOVE_VERSION);
				}
			}
		}

		$groove_css_path = plugin_dir_path(dirname(__DIR__)) . 'assets/css/groove-main.css';
		$groove_css_version = file_exists($groove_css_path) ? (string) filemtime($groove_css_path) : GROOVE_VERSION;
		wp_enqueue_style('groove', $this->get_css_assets_url('groove-main', null, 'default', true), [], $groove_css_version);
		wp_enqueue_script('groove-main', $this->get_js_assets_url('groove-main'), ['jquery'], GROOVE_VERSION, true);
		wp_enqueue_script('groove-inline-edit', $this->get_js_assets_url('groove-inline-edit'), ['jquery'], GROOVE_VERSION, true);

		if ($this->is_in_block_editor_page()) {
			$groove_breadcrumb_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/groove-gutenberg-breadcrumb.js';
			$groove_breadcrumb_js_version = file_exists($groove_breadcrumb_js_path) ? (string) filemtime($groove_breadcrumb_js_path) : GROOVE_VERSION;
			wp_enqueue_script('groove-gutenberg-breadcrumb', $this->get_js_assets_url('groove-gutenberg-breadcrumb'), ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-components'], $groove_breadcrumb_js_version, true);
		}

		wp_enqueue_media();
	}

	private function add_frontend_settings()
	{
		$folio_name = '';
		$folio_setup_url = '';
		$editor_theme_color_source_url = '';
		$post = get_post();
		if (!$post && !empty($_GET['post'])) {
			$post = get_post((int) $_GET['post']);
		}

		if ($post && $post->post_type === 'groove_folio_page') {
			$page_featured_image_id = (int) get_post_thumbnail_id($post->ID);
			if ($page_featured_image_id > 0) {
				$editor_theme_color_source_url = (string) wp_get_attachment_image_url($page_featured_image_id, 'large');
				if ($editor_theme_color_source_url === '') {
					$editor_theme_color_source_url = (string) wp_get_attachment_image_url($page_featured_image_id, 'full');
				}
			}

			$folio_id = get_post_meta($post->ID, 'folio_id', true);
			if (!$folio_id && !empty($_GET['folio_id'])) {
				$folio_id = (int) wp_unslash($_GET['folio_id']);
			}
			if ($folio_id) {
				$folio_post = get_post($folio_id);
				if ($folio_post) {
					$folio_name = $folio_post->post_title;
				}
				$folio_setup_url = add_query_arg(
					array(
						'page' => 'groove-folio',
						'folio_id' => (int) $folio_id,
						'tab_key' => 'pages',
					),
					admin_url('admin.php')
				);
			}
		}

		$post_action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'create';
		$settings = array(
			'screenId' => $this->get_scrren_id() ?? '',
			'isPost' => $this->is_in_block_editor_page(),
			'postType' => $post_action,
			'folioName' => $folio_name,
			'folioSetupUrl' => $folio_setup_url,
			'editorThemeColorSourceUrl' => $editor_theme_color_source_url,
			'adminPostUrl' => admin_url('admin-post.php'),
		);

		wp_add_inline_script(
			'groove-main',
			'window.GROOVE_SETTINGS = ' . wp_json_encode($settings) . ';' .
			'window.GROOVE_SCREEN_ID = window.GROOVE_SETTINGS.screenId;' .
			'window.GROOVE_POST = !!window.GROOVE_SETTINGS.isPost;' .
			'window.GROOVE_POST_TYPE = window.GROOVE_SETTINGS.postType;' .
			'window.GROOVE_FOLIO_NAME = window.GROOVE_SETTINGS.folioName;' .
			'window.GROOVE_FOLIO_SETUP_URL = window.GROOVE_SETTINGS.folioSetupUrl;' .
			'window.GROOVE_EDITOR_THEME_COLOR_SOURCE_URL = window.GROOVE_SETTINGS.editorThemeColorSourceUrl;' .
			'window.GROOVE_ADMIN_POST_URL = window.GROOVE_SETTINGS.adminPostUrl;',
			'before'
		);

		do_action('groove/main/init', $this);
	}

	private function is_top_tabs_active()
	{
		$id = $this->get_scrren_id();

		if (!$id) {
			return false;
		}

		$is_groove_folio = strpos($id ?? '', 'groove-folio') === 0;

		return apply_filters(
			'groove/top-bar-tabs/is-active',
			$is_groove_folio,
			get_current_screen()
		);
	}

	private function is_top_bar_active()
	{
		$current_screen = get_current_screen();

		if (!$current_screen) {
			return false;
		}


			$is_groove_page = (strpos($current_screen->id ?? '', 'groove') !== false);

		return apply_filters(
			'groove/top-bar-tabs/is-active',
			$is_groove_page,
			$current_screen
		);
	}

	public function remove_wp_footer()
	{
		return '<span class="g-folio__footer-version">Groove version ' . esc_html(GROOVE_VERSION) . ' by <a href="https://groove.com">StudioEN</a></span>';
	}

	public function __construct()
	{
		add_action('current_screen', function () {
			if (!$this->is_top_bar_active()) {
				return;
			}

			add_action(
				'admin_enqueue_scripts',
				function () {
					$this->enqueue_scripts();
					$this->add_frontend_settings();
				}
			);

			add_action(
				'enqueue_block_editor_assets',
				function () {
					if ($this->is_in_block_editor_page()) {
						$groove_breadcrumb_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/groove-gutenberg-breadcrumb.js';
						$groove_breadcrumb_js_version = file_exists($groove_breadcrumb_js_path) ? (string) filemtime($groove_breadcrumb_js_path) : GROOVE_VERSION;
						wp_enqueue_script('groove-gutenberg-breadcrumb', $this->get_js_assets_url('groove-gutenberg-breadcrumb'), ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-components'], $groove_breadcrumb_js_version, true);
					}
				}
			);
		});

		add_filter('admin_footer_text', [$this, 'remove_wp_footer']);
	}
}
