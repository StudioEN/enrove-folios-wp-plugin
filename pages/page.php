<?php

namespace Groove\Pages;

use Groove\Modules\Assets;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

abstract class Page extends Assets
{

	const PAGE_ID = '';

	public $tabs;
	public $left_button_items;

	public $right_button_items;

	abstract protected function create_tabs();
	abstract protected function get_title();

	final public static function parse_query()
	{
		$query_string = isset($_SERVER['QUERY_STRING']) ? wp_unslash($_SERVER['QUERY_STRING']) : '';
		$query = array();
		parse_str($query_string, $query);

		return $query;
	}

	final public static function get_url()
	{
		return admin_url('admin.php?page=' . static::PAGE_ID);
	}

	public function __construct()
	{
		$option_page = isset($_POST['option_page']) ? sanitize_key(wp_unslash($_POST['option_page'])) : '';
		if (!empty($option_page) && static::PAGE_ID === $option_page) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			add_action('admin_init', [$this, 'register_fields']);
		}
	}

	/**
	 * Register an admin-only POST action handler.
	 */
	final public function add_post_action($action, $handle_name)
	{
		add_action('admin_post_' . $action, [$this, $handle_name]);
	}

	/**
	 * Register a POST action handler accessible to logged-out users.
	 * Use sparingly! Usually only for public preview forms or auth callbacks.
	 */
	final public function add_public_post_action($action, $handle_name)
	{
		add_action('admin_post_' . $action, [$this, $handle_name]);
		add_action('admin_post_nopriv_' . $action, [$this, $handle_name]);
	}

	final public function get_tabs()
	{
		$this->ensure_tabs();

		return $this->tabs;
	}

	public function display_page()
	{
		$this->display_nav();
		?>
		<div class="wrap">
			<?php $this->display_content() ?>
		</div>
		<?php
	}

	public function display_left_button_items()
	{
		$button_items = $this->left_button_items;
		$this->display_button_items($button_items);
	}

	public function display_right_button_items()
	{
		$button_items = $this->right_button_items;
		$this->display_button_items($button_items);
	}

	public function display_button_items($button_items)
	{
		if (!empty($button_items)) {
			echo '<div class="flex space-x-3">';
			foreach ($button_items as $button_item) {
				$type = isset($button_item['type']) ? $button_item['type'] : 'default';

				$base_classes = 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2';

				if ($type === 'primary') {
					$classes = $base_classes . ' border border-transparent bg-indigo-600 text-white hover:bg-indigo-700';
				} else {
					$classes = $base_classes . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50';
				}

				if (isset($button_item['link'])) {
					echo '<a href="' . esc_url($button_item['link']) . '" class="' . $classes . '">' . esc_html($button_item['text']) . '</a>';
				} else if (isset($button_item['action'])) {
					echo '<button type="submit" name="action" value="' . esc_attr($button_item['action']) . '" class="' . $classes . '">' . esc_html($button_item['text']) . '</button>';
				}
			}
			echo '</div>';
		}
	}

	public function display_tabs()
	{
	}

	public function display_nav()
	{
		$tabs = $this->get_tabs();
		?>
		<div class="bg-gray-900 text-white shadow <?php $tabs == null ? '' : 'tabs' ?>">
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
				<div class="flex items-center justify-between h-16">
					<div class="flex items-center">
						<div class="flex-shrink-0">
							<!-- Logo Placeholder -->
							<div class="h-8 w-8 bg-indigo-500 rounded-md flex items-center justify-center font-bold text-white">
								G</div>
						</div>
						<div class="ml-4 font-semibold text-lg tracking-tight">
							<?php echo esc_html($this->get_title()); ?>
						</div>
						<div class="ml-6 flex items-center space-x-4">
							<?php $this->display_left_button_items() ?>
						</div>
					</div>

					<div class="flex items-center space-x-4">
						<?php $this->display_right_button_items() ?>
					</div>
				</div>
			</div>
			<?php $this->display_tabs(); ?>
		</div>
		<?php
	}

	public function display_content()
	{

	}

	private function ensure_tabs()
	{
		if ($this->tabs === null) {
			$this->tabs = $this->create_tabs();
			$page_id = static::PAGE_ID;

			do_action("groove/after_create_page/{$page_id}", $this);
		}
	}
}

?>
