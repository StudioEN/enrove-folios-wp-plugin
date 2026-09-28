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
		$query_string = isset($_SERVER['QUERY_STRING']) ? wp_unslash($_SERVER['QUERY_STRING']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed into an array that callers only pass back through add_query_arg() and esc_url() to rebuild the current admin URL with another tab; sanitize_text_field() would strip %-encoded values before parse_str() decodes them.
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
		$option_page = isset($_POST['option_page']) ? sanitize_key(wp_unslash($_POST['option_page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only decides whether to register this page's settings fields; wp-admin/options.php verifies the {$option_page}-options nonce before saving anything.
		if (!empty($option_page) && static::PAGE_ID === $option_page) {
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
				$text = isset($button_item['text']) ? (string) $button_item['text'] : '';
				$icon = isset($button_item['icon']) ? trim((string) $button_item['icon']) : '';
				$has_icon = $icon !== '';
				$attributes = '';

				if (!empty($button_item['attrs']) && is_array($button_item['attrs'])) {
					foreach ($button_item['attrs'] as $attr_name => $attr_value) {
						if (!is_string($attr_name) || !preg_match('/^[a-zA-Z_:][a-zA-Z0-9:._-]*$/', $attr_name)) {
							continue;
						}
						if ($attr_value === null || $attr_value === false) {
							continue;
						}

						$attributes .= ' ' . esc_attr($attr_name) . '="' . esc_attr((string) $attr_value) . '"';
					}
				}

				$content = esc_html($text);
				if ($has_icon) {
					$icon_parts = preg_split('/\s+/', $icon);
					$icon_classes = array();

					if (!empty($icon_parts)) {
						foreach ($icon_parts as $icon_part) {
							$sanitized_icon_part = sanitize_html_class($icon_part);
							if ($sanitized_icon_part !== '') {
								$icon_classes[] = $sanitized_icon_part;
							}
						}
					}

					if (!in_array('dashicons', $icon_classes, true)) {
						array_unshift($icon_classes, 'dashicons');
					}

					$content = '<span class="' . esc_attr(implode(' ', $icon_classes)) . '" aria-hidden="true"></span>';
					if ($text !== '') {
						$content .= '<span class="screen-reader-text">' . esc_html($text) . '</span>';
					}
				}

				if ($ui === 'wp') {
					$wp_classes = 'button';
					if ($type === 'primary') {
						$wp_classes .= ' button-primary';
					} else if ($type === 'secondary') {
						$wp_classes .= ' button-secondary';
					}

					if ($has_icon) {
						$wp_classes .= ' g-page-header__icon-button';
					}

					if (!empty($button_item['class'])) {
						$class_parts = preg_split('/\s+/', (string) $button_item['class']);
						if (!empty($class_parts)) {
							foreach ($class_parts as $class_part) {
								$sanitized_class_part = sanitize_html_class($class_part);
								if ($sanitized_class_part !== '') {
									$wp_classes .= ' ' . $sanitized_class_part;
								}
							}
						}
					}

					if (isset($button_item['link'])) {
						echo '<a href="' . esc_url($button_item['link']) . '" class="' . esc_attr($wp_classes) . '"' . $attributes . '>' . $content . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes and $content are assembled above from esc_attr()/esc_html()-escaped parts only.
					} else if (isset($button_item['action'])) {
						$button_type = 'submit';
						if (isset($button_item['button_type']) && in_array($button_item['button_type'], array('submit', 'button'), true)) {
							$button_type = $button_item['button_type'];
						}
						echo '<button type="' . esc_attr($button_type) . '" name="action" value="' . esc_attr($button_item['action']) . '" class="' . esc_attr($wp_classes) . '"' . $attributes . '>' . $content . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes and $content are assembled above from esc_attr()/esc_html()-escaped parts only.
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
						echo '<a href="' . esc_url($button_item['link']) . '" class="g-tailwind-link-reset ' . esc_attr($classes) . '"' . $attributes . '>' . $content . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes and $content are assembled above from esc_attr()/esc_html()-escaped parts only.
					} else if (isset($button_item['action'])) {
						$button_type = 'submit';
						if (isset($button_item['button_type']) && in_array($button_item['button_type'], array('submit', 'button'), true)) {
							$button_type = $button_item['button_type'];
						}
						echo '<button type="' . esc_attr($button_type) . '" name="action" value="' . esc_attr($button_item['action']) . '" class="' . esc_attr($classes) . '"' . $attributes . '>' . $content . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes and $content are assembled above from esc_attr()/esc_html()-escaped parts only.
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
