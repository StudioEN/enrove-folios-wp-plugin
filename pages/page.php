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
		?>
		<div class="wrap">
			<?php $this->display_nav(); ?>
			<?php $this->display_content(); ?>
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
			$button_count = count($button_items);
			$group_class = $button_count > 1 ? ' g-page-header__buttons-group' : '';
			echo '<div class="g-page-header__buttons' . esc_attr($group_class) . '">';
			foreach ($button_items as $button_item) {
				$type = isset($button_item['type']) ? $button_item['type'] : 'default';
				$ui = isset($button_item['ui']) ? $button_item['ui'] : 'wp';

				if ($ui === 'wp') {
					$wp_classes = 'button';
					if ($type === 'primary') {
						$wp_classes .= ' button-primary';
					} else if ($type === 'secondary') {
						$wp_classes .= ' button-secondary';
					}

					if (isset($button_item['link'])) {
						echo '<a href="' . esc_url($button_item['link']) . '" class="' . esc_attr($wp_classes) . '">' . esc_html($button_item['text']) . '</a>';
					} else if (isset($button_item['action'])) {
						echo '<button type="submit" name="action" value="' . esc_attr($button_item['action']) . '" class="' . esc_attr($wp_classes) . '">' . esc_html($button_item['text']) . '</button>';
					}
					continue;
				}

				$base_classes = 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2';

				if ($type === 'primary') {
					$classes = $base_classes . ' border border-transparent bg-indigo-600 text-white hover:bg-indigo-700';
				} else {
					$classes = $base_classes . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50';
				}

					if (isset($button_item['link'])) {
						echo '<a href="' . esc_url($button_item['link']) . '" class="g-tailwind-link-reset ' . $classes . '">' . esc_html($button_item['text']) . '</a>';
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
		<div class="g-page-header">
			<div class="g-page-header__left">
				<h1 class="wp-heading-inline g-page-header__title"><?php echo esc_html($this->get_title()); ?></h1>
				<?php $this->display_left_button_items(); ?>
			</div>
			<?php if (!empty($this->right_button_items)): ?>
			<div class="g-page-header__right">
				<?php $this->display_right_button_items(); ?>
			</div>
			<?php endif; ?>
		</div>
		<hr class="wp-header-end">

		<?php
		if (!empty($tabs)) {
			$this->display_tabs();
		}
		?>
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
