<?php
namespace Enrove\Modules\EnroveMain;

use Enrove\Modules\BaseModule;
use Enrove\Utils\Request;

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
		return 'enrove-main';
	}

	private function is_in_block_editor_page()
	{
		global $pagenow;

		// post.php and post-new.php set the global post before any script is
		// enqueued, so the screen is known without reading the request.
		$post = get_post();

		return $post && in_array($pagenow, array('post.php', 'post-new.php'), true) && use_block_editor_for_post($post);
	}

	/**
	 * The admin page slug of this request (core's $plugin_page), or ''.
	 */
	private function current_page()
	{
		global $plugin_page;

		return is_string($plugin_page) ? $plugin_page : '';
	}

	private function enqueue_scripts()
	{
		// The Vite dev server (npm run dev) when it is running, in a checkout:
		// vite-dev.php is left out of the WordPress.org package, so a released
		// plugin always reads the compiled build from the committed manifest.
		if (!class_exists(Vite_Dev::class) || !Vite_Dev::enqueue()) {
			$manifest_path = plugin_dir_path(dirname(__DIR__)) . 'assets/build/.vite/manifest.json';
			if (file_exists($manifest_path)) {
				$manifest = json_decode(file_get_contents($manifest_path), true);
				if (isset($manifest['assets/css/tailwind.css']['file'])) {
					$css_file = $manifest['assets/css/tailwind.css']['file'];
					wp_enqueue_style('enrove-tailwind', plugin_dir_url(dirname(__DIR__)) . 'assets/build/' . $css_file, [], ENROVE_VERSION);
				}
			}
		}

		$enrove_css_path = plugin_dir_path(dirname(__DIR__)) . 'assets/css/enrove-main.css';
		$enrove_css_version = file_exists($enrove_css_path) ? (string) filemtime($enrove_css_path) : ENROVE_VERSION;
		wp_enqueue_style('enrove', $this->get_css_assets_url('enrove-main', null, 'default', true), [], $enrove_css_version);
		// Loaded ahead of enrove-main so window.enroveShowToast is defined before
		// anything on the page reaches for it. Toggletips come first of all:
		// the toast drain opens one for any outcome that names an anchor.
		wp_enqueue_script('enrove-toggletip', $this->get_js_assets_url('enrove-toggletip'), [], ENROVE_VERSION, true);
		// Versioned by filemtime like enrove-main, which calls enroveStatusToast():
		// a cached older copy of this file would leave the folio save status mute.
		$enrove_toast_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/enrove-toast.js';
		$enrove_toast_js_version = file_exists($enrove_toast_js_path) ? (string) filemtime($enrove_toast_js_path) : ENROVE_VERSION;
		wp_enqueue_script('enrove-toast', $this->get_js_assets_url('enrove-toast'), ['enrove-toggletip'], $enrove_toast_js_version, true);
		wp_enqueue_script('enrove-form-state', $this->get_js_assets_url('enrove-form-state'), ['enrove-toggletip'], ENROVE_VERSION, true);
		$enrove_main_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/enrove-main.js';
		$enrove_main_js_version = file_exists($enrove_main_js_path) ? (string) filemtime($enrove_main_js_path) : ENROVE_VERSION;
		wp_enqueue_script('enrove-main', $this->get_js_assets_url('enrove-main'), ['jquery', 'wp-i18n', 'enrove-toast'], $enrove_main_js_version, true);
		wp_set_script_translations('enrove-main', 'enrove-folios');
		// The one dialog behaviour every Enrove dialog shares: open, close,
		// Escape, focus trap and the counted scroll lock.
		$enrove_dialog_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/enrove-dialog.js';
		$enrove_dialog_js_version = file_exists($enrove_dialog_js_path) ? (string) filemtime($enrove_dialog_js_path) : ENROVE_VERSION;
		wp_enqueue_script('enrove-dialog', $this->get_js_assets_url('enrove-dialog'), [], $enrove_dialog_js_version, true);
		wp_enqueue_script('enrove-inline-edit', $this->get_js_assets_url('enrove-inline-edit'), ['jquery', 'wp-i18n', 'wp-a11y'], ENROVE_VERSION, true);
		wp_set_script_translations('enrove-inline-edit', 'enrove-folios');

		$current_page = $this->current_page();
		if ($current_page === \Enrove\Pages\Themes::PAGE_ID) {
			$enrove_themes_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/enrove-themes.js';
			$enrove_themes_js_version = file_exists($enrove_themes_js_path) ? (string) filemtime($enrove_themes_js_path) : ENROVE_VERSION;
			wp_enqueue_script('enrove-themes', $this->get_js_assets_url('enrove-themes'), ['enrove-dialog'], $enrove_themes_js_version, true);
		}

		// The reset confirmation, on its Settings tab only.
		if ($current_page === \Enrove\Pages\Settings::PAGE_ID) {
			$enrove_reset_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/enrove-reset.js';
			$enrove_reset_js_version = file_exists($enrove_reset_js_path) ? (string) filemtime($enrove_reset_js_path) : ENROVE_VERSION;
			wp_enqueue_script('enrove-reset', $this->get_js_assets_url('enrove-reset'), ['enrove-dialog'], $enrove_reset_js_version, true);
		}

		// First-run setup, on Enrove's own admin pages (not the list tables or
		// the block editor) while fonts or photos are undecided; and on
		// Overview, whose panel is the way back, while one is declined.
		$offers_setup = \Enrove\Setup\First_Run::should_offer()
			|| ($current_page === \Enrove\Pages\Overview::PAGE_ID && \Enrove\Setup\First_Run::should_offer_return());
		if ($current_page !== '' && $offers_setup) {
			$enrove_setup_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/enrove-setup.js';
			$enrove_setup_js_version = file_exists($enrove_setup_js_path) ? (string) filemtime($enrove_setup_js_path) : ENROVE_VERSION;
			wp_enqueue_script('enrove-setup', $this->get_js_assets_url('enrove-setup'), ['enrove-dialog', 'enrove-toast'], $enrove_setup_js_version, true);

			// Not over the Add New dialog that All Folios opens from a link.
			// Opens by itself only while something is undecided, never to revisit
			// a "no".
			$auto_open = \Enrove\Setup\First_Run::should_offer() && !Request::has('open_add_new') && \Enrove\Setup\First_Run::take_auto_open();
			wp_add_inline_script(
				'enrove-setup',
				'window.ENROVE_SETUP = ' . wp_json_encode(\Enrove\Setup\First_Run::script_settings($auto_open), JSON_HEX_TAG | JSON_HEX_AMP) . ';',
				'before'
			);
			add_action('admin_footer', ['\Enrove\Setup\First_Run', 'render_dialog']);
		}

		if ($this->is_in_block_editor_page()) {
			$enrove_breadcrumb_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/enrove-gutenberg-breadcrumb.js';
			$enrove_breadcrumb_js_version = file_exists($enrove_breadcrumb_js_path) ? (string) filemtime($enrove_breadcrumb_js_path) : ENROVE_VERSION;
			wp_enqueue_script('enrove-gutenberg-breadcrumb', $this->get_js_assets_url('enrove-gutenberg-breadcrumb'), ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-components'], $enrove_breadcrumb_js_version, true);
		}

		wp_enqueue_media();
	}

	private function add_frontend_settings()
	{
		$folio_name = '';
		$folio_setup_url = '';
		$editor_theme_color_source_url = '';
		global $pagenow;
		$post = get_post();

		if ($post && $post->post_type === 'enrove_folio_page') {
			$page_featured_image_id = (int) get_post_thumbnail_id($post->ID);
			if ($page_featured_image_id > 0) {
				$editor_theme_color_source_url = (string) wp_get_attachment_image_url($page_featured_image_id, 'large');
				if ($editor_theme_color_source_url === '') {
					$editor_theme_color_source_url = (string) wp_get_attachment_image_url($page_featured_image_id, 'full');
				}
			}

			// The link is made when post-new.php creates the page, so a new page
			// from Add Page already has it here; no folio ID is read from the URL.
			$folio_id = (int) get_post_meta($post->ID, 'folio_id', true);
			if ($folio_id && current_user_can('edit_post', $folio_id)) {
				$folio_post = get_post($folio_id);
				if ($folio_post) {
					$folio_name = $folio_post->post_title;
				}
				$folio_setup_url = \Enrove\Pages\Folio::get_edit_url($folio_id, array('tab_key' => 'pages'));
			}
		}

		// post.php edits an existing post; everything else is a new one.
		$post_action = $pagenow === 'post.php' ? 'edit' : 'create';

		$current_page = $this->current_page();
		$theme_preview_base_url = '';
		$theme_preview_nonce = '';
		$open_add_new_modal = false;
		if (in_array($current_page, ['enrove-add-new', 'enrove-all-folios', 'enrove-folio', 'enrove-themes'], true)) {
			$theme_preview_base_url = add_query_arg([], site_url('/'));
			$theme_preview_nonce    = wp_create_nonce('enrove_theme_preview');
		}
		if ($current_page === 'enrove-all-folios' && Request::has('open_add_new')) {
			$open_add_new_modal = true;
		}

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
			'enrove-main',
			'window.ENROVE_SETTINGS = ' . wp_json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP) . ';' .
			'window.ENROVE_SCREEN_ID = window.ENROVE_SETTINGS.screenId;' .
			'window.ENROVE_POST = !!window.ENROVE_SETTINGS.isPost;' .
			'window.ENROVE_POST_TYPE = window.ENROVE_SETTINGS.postType;' .
			'window.ENROVE_FOLIO_NAME = window.ENROVE_SETTINGS.folioName;' .
			'window.ENROVE_FOLIO_SETUP_URL = window.ENROVE_SETTINGS.folioSetupUrl;' .
			'window.ENROVE_EDITOR_THEME_COLOR_SOURCE_URL = window.ENROVE_SETTINGS.editorThemeColorSourceUrl;' .
			'window.ENROVE_ADMIN_POST_URL = window.ENROVE_SETTINGS.adminPostUrl;',
			'before'
		);

		do_action('enrove/main/init', $this);
	}

	private function is_top_tabs_active()
	{
		$id = $this->get_scrren_id();

		if (!$id) {
			return false;
		}

		$is_enrove_folio = strpos($id ?? '', 'enrove-folio') === 0;

		return apply_filters(
			'enrove/top-bar-tabs/is-active',
			$is_enrove_folio,
			get_current_screen()
		);
	}

	/**
	 * Folios' own admin page slugs, as registered with add_menu_page().
	 */
	private function get_enrove_page_slugs()
	{
		return array(
			'enrove-overview',
			'enrove-all-folios',
			'enrove-folio',
			'enrove-add-new',
			'enrove-themes',
			'enrove-settings',
		);
	}

	/**
	 * Folios' own post types — list tables and the block editor.
	 */
	private function get_enrove_post_types()
	{
		return array('enrove_folio', 'enrove_folio_page');
	}

	/**
	 * Is this one of Folios' own screens?
	 *
	 * Matched on the exact menu slug and post type rather than by looking for
	 * "groove" anywhere in the screen id, as it did under the plugin's old
	 * name. Any plugin whose menu slug starts with "groove" inherited that substring in every one of its screen ids —
	 * Groove Creative's pages are toplevel_page_groove-creative and
	 * groove-creative_page_gc-*, all of which matched — and Folios would then
	 * load its whole stylesheet and script bundle over another plugin's admin
	 * pages.
	 */
	private function is_enrove_screen($current_screen)
	{
		if (in_array($current_screen->post_type ?? '', $this->get_enrove_post_types(), true)) {
			return true;
		}

		$page = $this->current_page();

		return $page !== '' && in_array($page, $this->get_enrove_page_slugs(), true);
	}

	private function is_top_bar_active()
	{
		$current_screen = get_current_screen();

		if (!$current_screen) {
			return false;
		}

		$is_enrove_page = $this->is_enrove_screen($current_screen);

		return apply_filters(
			'enrove/top-bar-tabs/is-active',
			$is_enrove_page,
			$current_screen
		);
	}

	public function remove_wp_footer($default)
	{
		if (!$this->is_top_bar_active()) {
			return $default;
		}
		return '<span class="g-folio__footer-version">' . esc_html(sprintf(
			/* translators: 1: plugin version number, 2: the plugin author's name. */
			__('Enrove Folios v%1$s by %2$s', 'enrove-folios'),
			ENROVE_VERSION,
			'StudioEN'
		)) . '</span>';
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
						$enrove_breadcrumb_js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/enrove-gutenberg-breadcrumb.js';
						$enrove_breadcrumb_js_version = file_exists($enrove_breadcrumb_js_path) ? (string) filemtime($enrove_breadcrumb_js_path) : ENROVE_VERSION;
						wp_enqueue_script('enrove-gutenberg-breadcrumb', $this->get_js_assets_url('enrove-gutenberg-breadcrumb'), ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-components'], $enrove_breadcrumb_js_version, true);
					}
				}
			);
		});

		add_filter('admin_footer_text', [$this, 'remove_wp_footer']);
	}
}
