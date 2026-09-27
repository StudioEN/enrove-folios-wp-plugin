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

		$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

		// Check if it's a new post/page in the block editor
		if (strpos($request_uri, 'post-new.php') !== false) {
			return true;
		}

		// Check if it's an existing post/page being edited in the block editor
		if (isset($_GET['post']) && strpos($request_uri, 'post.php') !== false) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only, to detect the post editor screen; the value is not used.
			return true;
		}



		return false;
	}

	private function enqueue_scripts()
	{
		// Vite integration
		$is_vite_dev = false;
		$vite_port = 5173;

		// Probe for the Vite dev server only on a local or development site
		// (WP_ENVIRONMENT_TYPE; Studio sets 'local'). A production install never
		// makes a request or enqueues anything from localhost — it reads the
		// committed manifest below. The probe fails gracefully when Vite is down.
		$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		if (in_array(wp_get_environment_type(), ['local', 'development'], true) && in_array($remote_addr, ['127.0.0.1', '::1'], true)) {
			// A 200 from /@vite/client means it is Vite on the port, not another local
			// app holding it; a refused connection fails fast, well inside the timeout.
			// GET, not HEAD: Vite answers HEAD on /@vite/client with a 404.
			$response = wp_remote_get('http://localhost:' . $vite_port . '/@vite/client', ['timeout' => 0.5, 'limit_response_size' => 1024]);
			if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
				$is_vite_dev = true;
			}
		}

		if ($is_vite_dev) {
			// Enqueue Vite client for HMR. No version on either dev-server script: Vite
			// serves them uncached, and a ?ver= query would change the module URL HMR tracks.
			wp_enqueue_script('vite-client', 'http://localhost:' . $vite_port . '/@vite/client', [], null, true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Local Vite dev server (development environments only); see comment above.

			// Add type="module" to the tags WordPress printed for the two dev-server scripts
			add_filter('script_loader_tag', function ($tag, $handle, $src) {
				if ($handle === 'vite-client' || $handle === 'groove-tailwind-vite') {
					$tag = preg_replace('/ type=([\'"])text\/javascript\1/', '', $tag);
					return preg_replace('/ src=/', ' type="module" src=', $tag, 1);
				}
				return $tag;
			}, 10, 3);

			// Enqueue our tailwind entry directly from the Vite dev server
			wp_enqueue_script('groove-tailwind-vite', 'http://localhost:' . $vite_port . '/assets/css/tailwind.css', [], null, true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Local Vite dev server (development environments only); see comment above.
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
		// Loaded ahead of groove-main so window.grooveShowToast is defined before
		// anything on the page reaches for it. Toggletips come first of all:
		// the toast drain opens one for any outcome that names an anchor.
		wp_enqueue_script('groove-toggletip', $this->get_js_assets_url('groove-toggletip'), [], GROOVE_VERSION, true);
		wp_enqueue_script('groove-toast', $this->get_js_assets_url('groove-toast'), ['groove-toggletip'], GROOVE_VERSION, true);
		wp_enqueue_script('groove-form-state', $this->get_js_assets_url('groove-form-state'), ['groove-toggletip'], GROOVE_VERSION, true);
		wp_enqueue_script('groove-main', $this->get_js_assets_url('groove-main'), ['jquery', 'groove-toast'], GROOVE_VERSION, true);
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameters (the post being edited, the admin page); they only choose what settings to hand the page's JS.
		if (!$post && !empty($_GET['post'])) {
			$post = get_post(intval(wp_unslash($_GET['post'])));
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
				$folio_id = intval(wp_unslash($_GET['folio_id']));
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

		$current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$theme_preview_base_url = '';
		$theme_preview_nonce = '';
		$open_add_new_modal = false;
		if (in_array($current_page, ['groove-add-new', 'groove-all-folios', 'groove-folio', 'groove-themes'], true)) {
			$theme_preview_base_url = add_query_arg([], site_url('/'));
			$theme_preview_nonce    = wp_create_nonce('groove_theme_preview');
		}
		if ($current_page === 'groove-all-folios' && !empty($_GET['open_add_new'])) {
			$open_add_new_modal = true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$settings = array(
			'screenId' => $this->get_scrren_id() ?? '',
			'isPost' => $this->is_in_block_editor_page(),
			'postType' => $post_action,
			'folioName' => $folio_name,
			'folioSetupUrl' => $folio_setup_url,
			'editorThemeColorSourceUrl' => $editor_theme_color_source_url,
			'adminPostUrl' => admin_url('admin-post.php'),
			'themePreviewBaseUrl' => $theme_preview_base_url,
			'themePreviewNonce' => $theme_preview_nonce,
			'openAddNewModal' => $open_add_new_modal,
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

	/**
	 * Folios' own admin page slugs, as registered with add_menu_page().
	 */
	private function get_groove_page_slugs()
	{
		return array(
			'groove-overview',
			'groove-all-folios',
			'groove-folio',
			'groove-add-new',
			'groove-themes',
			'groove-settings',
		);
	}

	/**
	 * Folios' own post types — list tables and the block editor.
	 */
	private function get_groove_post_types()
	{
		return array('groove_folio', 'groove_folio_page');
	}

	/**
	 * Is this one of Folios' own screens?
	 *
	 * Matched on the exact menu slug and post type rather than by looking for
	 * "groove" anywhere in the screen id. Any plugin whose menu slug starts
	 * with "groove" inherits that substring in every one of its screen ids —
	 * Groove Creative's pages are toplevel_page_groove-creative and
	 * groove-creative_page_gc-*, all of which matched — and Folios would then
	 * load its whole stylesheet and script bundle over another plugin's admin
	 * pages.
	 */
	private function is_groove_screen($current_screen)
	{
		if (in_array($current_screen->post_type ?? '', $this->get_groove_post_types(), true)) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

		return $page !== '' && in_array($page, $this->get_groove_page_slugs(), true);
	}

	private function is_top_bar_active()
	{
		$current_screen = get_current_screen();

		if (!$current_screen) {
			return false;
		}

		$is_groove_page = $this->is_groove_screen($current_screen);

		return apply_filters(
			'groove/top-bar-tabs/is-active',
			$is_groove_page,
			$current_screen
		);
	}

	public function remove_wp_footer($default)
	{
		if (!$this->is_top_bar_active()) {
			return $default;
		}
		return '<span class="g-folio__footer-version">Groove Folios v' . esc_html(GROOVE_VERSION) . ' by StudioEN</span>';
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
