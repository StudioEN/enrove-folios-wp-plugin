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
		$query_string = $_SERVER['QUERY_STRING'];
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
		if (!empty($_POST['option_page']) && static::PAGE_ID === $_POST['option_page']) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
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
	<?php $this->display_content()?>
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
			echo '<div class="g-folio__buttons">';
			foreach ($button_items as $button_item) {
				$classes = 'g-folio__button ' . ($button_item['type'] ? 'g-folio__button-' . $button_item['type'] : '');

				if (isset($button_item['link'])) {
					echo '<a href="' . $button_item['link'] . '" class="' . $classes . '">' . $button_item['text'] . '</a>';
				}
				else if (isset($button_item['action'])) {
					echo '<button type="submit" name="action" value="' . $button_item['action'] . '" class="' . $classes . '">' . $button_item['text'] . '</button>';
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
<div class="g-top-bar-root <?php $tabs == null ? '' : 'tabs'?>">
	<div class="g-top-bar">
		<div class="g-top-bar__left">
			<div class="g-top-bar-logo"></div>
			<div class="g-top-bar-title">
				<?php echo $this->get_title(); ?>
			</div>
			<?php $this->display_left_button_items()?>
		</div>

		<div class="g-top-bar__right">
			<?php $this->display_right_button_items()?>
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