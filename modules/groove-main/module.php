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
		wp_enqueue_style('groove', $this->get_css_assets_url('groove-main', null, 'default', true), [], GROOVE_VERSION);
		wp_enqueue_script('groove-main', $this->get_js_assets_url('groove-main'), ['jquery'], GROOVE_VERSION, true);
		wp_enqueue_script('groove-inline-edit', $this->get_js_assets_url('groove-inline-edit'), ['jquery'], GROOVE_VERSION, true);

		wp_enqueue_media();
	}

	private function add_frontend_settings()
	{
		echo '<script>window.GROOVE_SCREEN_ID = "' . $this->get_scrren_id() . '"; window.GROOVE_POST = ' . ($this->is_in_block_editor_page() ? 'true' : 'fa lse') . '; window.GROOVE_POST_TYPE = "' . (isset($_REQUEST['action']) ? $_REQUEST['action'] : 'create') . '";</script>';

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


		$is_groove_page = (strpos($current_screen->id ?? '', 'groove') >= 0);

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

			add_action('admin_enqueue_scripts', function () {
				    $this->add_frontend_settings();
				    $this->enqueue_scripts();
			    }
			    );
		    });

		add_filter('admin_footer_text', [$this, 'remove_wp_footer']);
	}
}