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

	final public static function get_url()
	{
		return admin_url('admin.php?page=' . static::PAGE_ID);
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

	/**
	 * Print extra attributes, each escaped where it is printed. A name that is
	 * not a plain attribute name is skipped, as is a null or false value.
	 *
	 * @param array $attrs Attribute name => value.
	 */
	protected function print_attributes($attrs)
	{
		if (empty($attrs) || !is_array($attrs)) {
			return;
		}

		foreach ($attrs as $attr_name => $attr_value) {
			if (!is_string($attr_name) || !preg_match('/^[a-zA-Z_:][a-zA-Z0-9:._-]*$/', $attr_name)) {
				continue;
			}
			if ($attr_value === null || $attr_value === false) {
				continue;
			}

			echo ' ' . esc_attr($attr_name) . '="' . esc_attr((string) $attr_value) . '"';
		}
	}

	/**
	 * A header button's label: its text, or a dashicon with the text kept for
	 * screen readers.
	 *
	 * @param string   $text         Button text.
	 * @param string[] $icon_classes Sanitised icon classes; empty for a text button.
	 */
	protected function print_button_content($text, array $icon_classes)
	{
		if (empty($icon_classes)) {
			echo esc_html($text);
			return;
		}

		echo '<span class="' . esc_attr(implode(' ', $icon_classes)) . '" aria-hidden="true"></span>';
		if ($text !== '') {
			echo '<span class="screen-reader-text">' . esc_html($text) . '</span>';
		}
	}

	/**
	 * One header button, an <a> for a link item and a <button> for an action item.
	 *
	 * @param array    $button_item  The item.
	 * @param string   $classes      Its class list, unescaped.
	 * @param string   $text         Button text.
	 * @param string[] $icon_classes Sanitised icon classes; empty for a text button.
	 */
	protected function print_button_item($button_item, $classes, $text, array $icon_classes)
	{
		$attrs = isset($button_item['attrs']) ? $button_item['attrs'] : array();

		if (isset($button_item['link'])) {
			echo '<a href="' . esc_url($button_item['link']) . '" class="' . esc_attr($classes) . '"';
			$this->print_attributes($attrs);
			echo '>';
			$this->print_button_content($text, $icon_classes);
			echo '</a>';
			return;
		}

		if (!isset($button_item['action'])) {
			return;
		}

		$button_type = 'submit';
		if (isset($button_item['button_type']) && in_array($button_item['button_type'], array('submit', 'button'), true)) {
			$button_type = $button_item['button_type'];
		}

		echo '<button type="' . esc_attr($button_type) . '" name="action" value="' . esc_attr($button_item['action']) . '" class="' . esc_attr($classes) . '"';
		$this->print_attributes($attrs);
		echo '>';
		$this->print_button_content($text, $icon_classes);
		echo '</button>';
	}

	// pages/folio.php display_tabs() loop body:

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
				$icon_classes = array();
				if ($has_icon) {
					$icon_parts = preg_split('/\s+/', $icon);

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

					$this->print_button_item($button_item, $wp_classes, $text, $icon_classes);
					continue;
				}

				$base_classes = 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2';

				if ($type === 'primary') {
					$classes = $base_classes . ' border border-transparent bg-indigo-600 text-white hover:bg-indigo-700';
				} else {
					$classes = $base_classes . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50';
				}

				$this->print_button_item($button_item, isset($button_item['link']) ? 'g-tailwind-link-reset ' . $classes : $classes, $text, $icon_classes);
			}
			echo '</div>';
		}
	}

	public function display_tabs()
	{
	}

	/**
	 * Whether this screen's header carries the "Finish setup" button while
	 * first-run setup is unsettled. Overview has a panel for it instead, and
	 * the folio editor keeps its header for the folio's own actions.
	 *
	 * @return bool
	 */
	protected function shows_setup_entry()
	{
		return true;
	}

	public function display_nav()
	{
		$tabs = $this->get_tabs();
		$setup_entry = $this->shows_setup_entry() && \Groove\Setup\First_Run::should_offer();
		?>
		<div class="g-page-header">
			<div class="g-page-header__left">
				<h1 class="wp-heading-inline g-page-header__title"><?php echo esc_html($this->get_title()); ?></h1>
				<?php $this->display_left_button_items(); ?>
			</div>
			<?php if (!empty($this->right_button_items) || $setup_entry): ?>
			<div class="g-page-header__right">
				<?php if ($setup_entry) { \Groove\Setup\First_Run::display_header_entry(); } ?>
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
