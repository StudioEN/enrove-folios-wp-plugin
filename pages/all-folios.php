<?php
namespace Groove\Pages;

use Groove\List\Folio_List_Table;
use Groove\Menu\All_Folios_Menu_Item;
use Groove\Menu\Menu_Manager;
use Groove\Pages\Overview;
use Groove\Pages\Page;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class All_Folios extends Page
{
	const PAGE_ID = 'groove-all-folios';
	const POST_TYPE = 'groove_folio';

	public function get_title()
	{
		return 'All Folios';
	}

	public function create_tabs()
	{
		return array();
	}

	public function __construct()
	{
		$this->left_button_items = [
			array(
				'text'        => esc_html__('Add New', 'groove-folios'),
				'type'        => 'primary',
				'action'      => 'groove_open_add_new',
				'button_type' => 'button',
				'attrs'       => ['data-groove-open-add-new' => '1'],
			)
		];

		add_action('groove/menu/register', function (Menu_Manager $menu) {
			$menu->register(static::PAGE_ID, new All_Folios_Menu_Item($this));
		}, Overview::MENU_PRIORITY + 20);

		add_action('admin_init', array($this, 'handle_duplicate_action'));
		add_action('admin_init', array($this, 'handle_bulk_action'));
		add_action('current_screen', array($this, 'register_column_preferences'));
	}

	/**
	 * Columns rendered by the folios table.
	 *
	 * The array key doubles as the `column-{key}` class on every cell and as the
	 * value of the Screen Options checkbox, which is what lets WordPress' own
	 * column show/hide plumbing (common.js `columns`, the `hidden-columns` ajax
	 * handler and the `manage{screen}columnshidden` user meta) drive this table
	 * even though it is rendered by hand rather than by WP_List_Table::display().
	 */
	public function get_table_columns()
	{
		return array(
			'cb' => '',
			'title' => esc_html__('Folio Name', 'groove-folios'),
			'theme_name' => esc_html__('Theme Name', 'groove-folios'),
			'collection_tags' => esc_html__('Collection Tags', 'groove-folios'),
			'page_count' => esc_html__('Page Count', 'groove-folios'),
			'publish_status' => esc_html__('Publish Status', 'groove-folios'),
			'modified' => esc_html__('Last Updated', 'groove-folios'),
		);
	}

	/**
	 * Feed the column list to the Screen Options panel.
	 *
	 * WP_Screen::show_screen_options() only renders the panel when
	 * get_column_headers() returns something, and that runs the
	 * `manage_{screen_id}_columns` filter. 'cb' and 'title' are on core's
	 * "special" list, so they are never offered as hideable.
	 */
	public function register_column_preferences($screen)
	{
		if (!$screen instanceof \WP_Screen) {
			return;
		}

		if (!isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== static::PAGE_ID) {
			return;
		}

		add_filter("manage_{$screen->id}_columns", array($this, 'get_table_columns'));
	}

	/**
	 * Columns the current user has switched off in Screen Options.
	 */
	private function get_hidden_table_columns()
	{
		$screen = get_current_screen();

		if (!$screen instanceof \WP_Screen) {
			return array();
		}

		$hidden = get_hidden_columns($screen);

		return is_array($hidden) ? $hidden : array();
	}

	/**
	 * Build the class attribute for a cell in the folios table.
	 */
	private function column_classes($column, $hidden_columns, $extra_classes = '')
	{
		$classes = array('column-' . $column);

		if ($extra_classes !== '') {
			$classes[] = $extra_classes;
		}

		if (in_array($column, $hidden_columns, true)) {
			$classes[] = 'hidden';
		}

		return trim(implode(' ', $classes));
	}

	/**
	 * Run bulk actions before any output is sent.
	 *
	 * process_bulk_action() ends in wp_safe_redirect(), so it has to run on
	 * admin_init. Calling it from display_content() is too late: WordPress has
	 * already emitted the admin header by then, so the redirect cannot set its
	 * headers and PHP warns about output having started. Mirrors the timing of
	 * handle_duplicate_action() below.
	 */
	public function handle_bulk_action()
	{
		if (!isset($_GET['page']) || $_GET['page'] !== static::PAGE_ID) {
			return;
		}

		$this->process_bulk_action(
			$this->get_current_status(),
			$this->get_search_term(),
			$this->get_current_orderby(),
			$this->get_current_order(),
			$this->get_current_paged()
		);
	}

	public function handle_duplicate_action()
	{
		if (!isset($_GET['page']) || $_GET['page'] !== static::PAGE_ID) {
			return;
		}
		if (!isset($_GET['action']) || $_GET['action'] !== 'groove_duplicate_folio' || !isset($_GET['post'])) {
			return;
		}

		$post_id = (int) $_GET['post'];
		check_admin_referer('groove_duplicate_folio_' . $post_id);

		if (get_post_type($post_id) !== static::POST_TYPE || !current_user_can('edit_post', $post_id)) {
			return;
		}

		$new_id = $this->duplicate_folio($post_id);
		$status = isset($_GET['post_status']) ? sanitize_key(wp_unslash($_GET['post_status'])) : 'all';
		$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

		$redirect_args = array_filter(array(
			'post_status' => $status !== 'all' ? $status : null,
			's' => $search !== '' ? $search : null,
			'collection_tag' => $this->get_collection_tag_query_arg($this->get_current_collection_tags()),
			'bulk_action' => 'duplicate',
			'bulk_count' => $new_id ? 1 : 0,
		), function ($value) {
			return $value !== null;
		});

		wp_safe_redirect($this->build_page_url($redirect_args));
		exit;
	}

	private function get_current_status()
	{
		$status = isset($_GET['post_status']) ? sanitize_key(wp_unslash($_GET['post_status'])) : 'all';
		$allowed_statuses = array('all', 'publish', 'draft', 'pending', 'private', 'trash');

		if (!in_array($status, $allowed_statuses, true)) {
			return 'all';
		}

		return $status;
	}

	private function get_search_term()
	{
		return isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
	}

	private function get_current_collection_tags()
	{
		if (!isset($_GET['collection_tag'])) {
			return array();
		}

		$raw_tags = wp_unslash($_GET['collection_tag']);
		if (!is_array($raw_tags)) {
			$raw_tags = array($raw_tags);
		}

		$tags = array_values(array_unique(array_filter(array_map('sanitize_title', $raw_tags))));
		return array_values(array_filter($tags, function ($tag) {
			return $tag !== '';
		}));
	}

	private function get_collection_tag_query_arg($tags)
	{
		$tags = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $tags))));
		return empty($tags) ? null : $tags;
	}

	private function get_collection_tag_terms()
	{
		$terms = get_terms(array(
			'taxonomy' => 'groove_collection_tag',
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC',
		));

		return is_wp_error($terms) ? array() : (array) $terms;
	}

	private function get_collection_tag_links($post_id, $status, $search, $orderby, $order)
	{
		$terms = get_the_terms((int) $post_id, 'groove_collection_tag');
		if (is_wp_error($terms) || empty($terms)) {
			return '';
		}

		$links = array();
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}

			$url = $this->build_page_url(array_filter(array(
				'post_status' => $status !== 'all' ? $status : null,
				's' => $search !== '' ? $search : null,
				'orderby' => $orderby !== 'modified' ? $orderby : null,
				'order' => $order !== 'DESC' ? $order : null,
				'collection_tag' => array($term->slug),
			), function ($value) {
				return $value !== null;
			}));

			$links[] = '<a href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>';
		}

		return implode(', ', $links);
	}

	private function get_collection_tag_toggle_url($slug, $status, $search, $orderby, $order)
	{
		$slug = sanitize_title($slug);
		$current_tags = $this->get_current_collection_tags();

		if (in_array($slug, $current_tags, true)) {
			$updated_tags = array();
		} else {
			$updated_tags = array($slug);
		}

		return $this->build_page_url(array_filter(array(
			'post_status' => $status !== 'all' ? $status : null,
			's' => $search !== '' ? $search : null,
			'orderby' => $orderby !== 'modified' ? $orderby : null,
			'order' => $order !== 'DESC' ? $order : null,
			'collection_tag' => $this->get_collection_tag_query_arg($updated_tags),
		), function ($value) {
			return $value !== null;
		}));
	}

	private function get_current_paged()
	{
		return max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1);
	}

	private function get_current_orderby()
	{
		$orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'modified';
		$allowed_orderby = array('title', 'modified', 'page_count', 'theme_name');

		if (!in_array($orderby, $allowed_orderby, true)) {
			return 'modified';
		}

		return $orderby;
	}

	private function get_current_order()
	{
		$order = isset($_GET['order']) ? strtoupper(sanitize_key(wp_unslash($_GET['order']))) : 'DESC';
		return $order === 'ASC' ? 'ASC' : 'DESC';
	}

	private function get_status_counts()
	{
		$counts = wp_count_posts(static::POST_TYPE);

		$publish = (int) ($counts->publish ?? 0);
		$draft = (int) ($counts->draft ?? 0);
		$pending = (int) ($counts->pending ?? 0);
		$private = (int) ($counts->private ?? 0);
		$future = (int) ($counts->future ?? 0);
		$trash = (int) ($counts->trash ?? 0);

		return array(
			'all' => $publish + $draft + $pending + $private + $future,
			'publish' => $publish,
			'draft' => $draft,
			'pending' => $pending,
			'private' => $private,
			'trash' => $trash,
		);
	}

	private function get_status_label($status)
	{
		switch ($status) {
			case 'publish':
				return esc_html__('Published', 'groove-folios');
			case 'draft':
				return esc_html__('Draft', 'groove-folios');
			case 'pending':
				return esc_html__('Pending', 'groove-folios');
			case 'private':
				return esc_html__('Private', 'groove-folios');
			case 'trash':
				return esc_html__('Trash', 'groove-folios');
			default:
				return esc_html__('All', 'groove-folios');
		}
	}

	private function build_page_url($args = array())
	{
		return add_query_arg(
			array_merge(
				array('page' => static::PAGE_ID),
				$args
			),
			admin_url('admin.php')
		);
	}

	private function get_folios_query($status, $search, $paged, $orderby, $order, $per_page = 20)
	{
		$query_args = array(
			'post_type' => static::POST_TYPE,
			'post_status' => $status === 'all' ? array('publish', 'draft', 'pending', 'private', 'future') : $status,
			'posts_per_page' => $per_page,
			'paged' => $paged,
			'orderby' => $orderby,
			'order' => $order,
		);

		if ($orderby === 'theme_name') {
			$query_args['orderby'] = 'meta_value';
			$query_args['meta_key'] = 'theme_id';
		}

		if ($search !== '') {
			$query_args['s'] = $search;
		}

		if (isset($_GET['theme_id']) && $_GET['theme_id'] !== '') {
			$query_args['meta_key'] = 'theme_id';
			$query_args['meta_value'] = sanitize_key($_GET['theme_id']);
		}

		$collection_tags = $this->get_current_collection_tags();
		if (!empty($collection_tags)) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'groove_collection_tag',
					'field' => 'slug',
					'terms' => $collection_tags,
					'operator' => 'IN',
				),
			);
		}

		return new \WP_Query($query_args);
	}

	private function get_page_counts_for_folio_ids($folio_ids)
	{
		global $wpdb;

		$ids = array_values(array_filter(array_map('intval', (array) $folio_ids)));
		if (empty($ids)) {
			return array();
		}

		$placeholders = implode(',', array_fill(0, count($ids), '%d'));
		$sql = "
			SELECT
				pm.meta_value AS folio_id,
				COUNT(*) AS page_count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type = %s
				AND p.post_status IN ('publish','draft','pending','private','future')
				AND pm.meta_key = %s
				AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
			GROUP BY pm.meta_value
		";

		$params = array_merge(array('groove_folio_page', 'folio_id'), $ids);
		$prepared_sql = $wpdb->prepare($sql, $params);
		$rows = $wpdb->get_results($prepared_sql);

		$counts = array_fill_keys($ids, 0);
		foreach ((array) $rows as $row) {
			$folio_id = (int) $row->folio_id;
			$counts[$folio_id] = (int) $row->page_count;
		}

		return $counts;
	}

	private function get_folios_results($status, $search, $paged, $orderby, $order)
	{
		$per_page = 20;

		if ($orderby !== 'page_count') {
			$query = $this->get_folios_query($status, $search, $paged, $orderby, $order, $per_page);
			$posts = $query->posts;
			$post_ids = wp_list_pluck($posts, 'ID');

			return array(
				'posts' => $posts,
				'total_items' => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'page_counts' => $this->get_page_counts_for_folio_ids($post_ids),
			);
		}

		$ids_query_args = array(
			'post_type' => static::POST_TYPE,
			'post_status' => $status === 'all' ? array('publish', 'draft', 'pending', 'private', 'future') : $status,
			'posts_per_page' => -1,
			'fields' => 'ids',
			'orderby' => 'modified',
			'order' => 'DESC',
		);
		if ($search !== '') {
			$ids_query_args['s'] = $search;
		}

		if (isset($_GET['theme_id']) && $_GET['theme_id'] !== '') {
			$ids_query_args['meta_key'] = 'theme_id';
			$ids_query_args['meta_value'] = sanitize_key($_GET['theme_id']);
		}

		$collection_tags = $this->get_current_collection_tags();
		if (!empty($collection_tags)) {
			$ids_query_args['tax_query'] = array(
				array(
					'taxonomy' => 'groove_collection_tag',
					'field' => 'slug',
					'terms' => $collection_tags,
					'operator' => 'IN',
				),
			);
		}

		$ids_query = new \WP_Query($ids_query_args);

		$all_ids = array_map('intval', (array) $ids_query->posts);
		$page_counts = $this->get_page_counts_for_folio_ids($all_ids);

		usort($all_ids, function ($a, $b) use ($page_counts, $order) {
			$a_count = (int) ($page_counts[$a] ?? 0);
			$b_count = (int) ($page_counts[$b] ?? 0);

			if ($a_count === $b_count) {
				return $a <=> $b;
			}

			$cmp = $a_count <=> $b_count;
			return $order === 'ASC' ? $cmp : -$cmp;
		});

		$total_items = count($all_ids);
		$total_pages = $total_items > 0 ? (int) ceil($total_items / $per_page) : 0;
		$offset = ($paged - 1) * $per_page;
		$current_page_ids = array_slice($all_ids, $offset, $per_page);
		$posts = array();

		if (!empty($current_page_ids)) {
			$posts = get_posts(array(
				'post_type' => static::POST_TYPE,
				'post_status' => $status === 'all' ? array('publish', 'draft', 'pending', 'private', 'future') : $status,
				'post__in' => $current_page_ids,
				'orderby' => 'post__in',
				'posts_per_page' => $per_page,
			));
		}

		return array(
			'posts' => $posts,
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'page_counts' => $page_counts,
		);
	}

	private function get_sort_url($column, $current_orderby, $current_order, $status, $search)
	{
		$next_order = 'ASC';
		if ($column === $current_orderby && $current_order === 'ASC') {
			$next_order = 'DESC';
		}

		return $this->build_page_url(array_filter(array(
			'post_status' => $status !== 'all' ? $status : null,
			's' => $search !== '' ? $search : null,
			'orderby' => $column,
			'order' => $next_order,
			'theme_id' => isset($_GET['theme_id']) && $_GET['theme_id'] !== '' ? sanitize_key($_GET['theme_id']) : null,
			'collection_tag' => $this->get_collection_tag_query_arg($this->get_current_collection_tags()),
		), function ($value) {
			return $value !== null;
		}));
	}

	private function get_available_bulk_actions($status)
	{
		if ($status === 'trash') {
			return array(
				'untrash' => esc_html__('Restore', 'groove-folios'),
				'delete' => esc_html__('Delete Permanently', 'groove-folios'),
			);
		}

		return array(
			'duplicate' => esc_html__('Duplicate', 'groove-folios'),
			'trash' => esc_html__('Move to Trash', 'groove-folios'),
		);
	}

	private function get_current_bulk_action()
	{
		$action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '-1';
		$action2 = isset($_REQUEST['action2']) ? sanitize_key(wp_unslash($_REQUEST['action2'])) : '-1';

		if ($action !== '-1') {
			return $action;
		}
		if ($action2 !== '-1') {
			return $action2;
		}

		return false;
	}

	private function process_bulk_action($status, $search, $orderby, $order, $paged)
	{
		$action = $this->get_current_bulk_action();
		if (!$action) {
			return;
		}

		$available_actions = $this->get_available_bulk_actions($status);
		if (!isset($available_actions[$action])) {
			return;
		}

		check_admin_referer('groove_bulk_folios_action', '_groove_bulk_nonce');

		$post_ids = isset($_REQUEST['post']) ? (array) wp_unslash($_REQUEST['post']) : array();
		$post_ids = array_values(array_filter(array_map('intval', $post_ids)));
		if (empty($post_ids)) {
			return;
		}

		$updated_count = 0;
		foreach ($post_ids as $post_id) {
			if (get_post_type($post_id) !== static::POST_TYPE) {
				continue;
			}
			if (!current_user_can('delete_post', $post_id)) {
				continue;
			}

			switch ($action) {
				case 'trash':
					if (wp_trash_post($post_id)) {
						$updated_count++;
					}
					break;
				case 'untrash':
					if (wp_untrash_post($post_id)) {
						$updated_count++;
					}
					break;
				case 'delete':
					if (wp_delete_post($post_id, true)) {
						$updated_count++;
					}
					break;
				case 'duplicate':
					if ($this->duplicate_folio($post_id)) {
						$updated_count++;
					}
					break;
			}
		}

		$redirect_args = array_filter(array(
			'post_status' => $status !== 'all' ? $status : null,
			's' => $search !== '' ? $search : null,
			'orderby' => $orderby !== 'modified' ? $orderby : null,
			'order' => $order !== 'DESC' ? $order : null,
			'paged' => $paged > 1 ? $paged : null,
			'theme_id' => isset($_GET['theme_id']) && $_GET['theme_id'] !== '' ? sanitize_key($_GET['theme_id']) : null,
			'collection_tag' => $this->get_collection_tag_query_arg($this->get_current_collection_tags()),
			'bulk_action' => $action,
			'bulk_count' => $updated_count,
		), function ($value) {
			return $value !== null;
		});

		wp_safe_redirect($this->build_page_url($redirect_args));
		exit;
	}

	/**
	 * Report the outcome of a bulk action as a toast.
	 *
	 * The list table underneath is the thing the operator came back to read, so
	 * the count is reported over it rather than pushed on top of it. Removals
	 * report as info: nothing about a trashed folio needs a green pill.
	 */
	private function queue_bulk_toast()
	{
		if (!isset($_GET['bulk_action']) || !isset($_GET['bulk_count'])) {
			return;
		}

		$action = sanitize_key(wp_unslash($_GET['bulk_action']));
		$count = (int) wp_unslash($_GET['bulk_count']);
		if ($count < 1) {
			return;
		}

		$message = '';
		$type = \Groove\Toast::SUCCESS;

		switch ($action) {
			case 'trash':
				$message = sprintf(
					/* translators: %s: number of folios moved to trash */
					_n('%s folio moved to Trash.', '%s folios moved to Trash.', $count, 'groove-folios'),
					number_format_i18n($count)
				);
				$type = \Groove\Toast::INFO;
				break;
			case 'untrash':
				$message = sprintf(
					/* translators: %s: number of folios restored */
					_n('%s folio restored.', '%s folios restored.', $count, 'groove-folios'),
					number_format_i18n($count)
				);
				break;
			case 'delete':
				$message = sprintf(
					/* translators: %s: number of folios deleted */
					_n('%s folio deleted permanently.', '%s folios deleted permanently.', $count, 'groove-folios'),
					number_format_i18n($count)
				);
				$type = \Groove\Toast::INFO;
				break;
			case 'duplicate':
				$message = sprintf(
					/* translators: %s: number of folios duplicated */
					_n('%s folio duplicated.', '%s folios duplicated.', $count, 'groove-folios'),
					number_format_i18n($count)
				);
				break;
		}

		if ($message === '') {
			return;
		}

		\Groove\Toast::add($message, $type, array('bulk_action', 'bulk_count'));
	}

	private function duplicate_folio($post_id)
	{
		$source = get_post($post_id);
		if (!$source || $source->post_type !== static::POST_TYPE) {
			return false;
		}

		$new_title = sprintf(
			/* translators: %s: original folio title */
			__('Copy of %s', 'groove-folios'),
			$source->post_title
		);

		$new_folio_id = wp_insert_post(array(
			'post_type'    => static::POST_TYPE,
			'post_title'   => $new_title,
			'post_name'    => sanitize_title($new_title),
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
			'post_content' => $source->post_content,
			'post_excerpt' => $source->post_excerpt,
		));

		if (is_wp_error($new_folio_id) || !$new_folio_id) {
			return false;
		}

		$skip_keys = array('_edit_lock', '_edit_last', '_wp_old_slug');
		$this->copy_post_meta_values($post_id, $new_folio_id, $skip_keys);

		$term_ids = wp_get_object_terms($post_id, 'groove_collection_tag', array('fields' => 'ids'));
		if (!is_wp_error($term_ids) && !empty($term_ids)) {
			wp_set_object_terms($new_folio_id, array_map('intval', $term_ids), 'groove_collection_tag', false);
		}

		// Duplicate child folio pages.
		$pages = get_posts(array(
			'post_type'      => 'groove_folio_page',
			'post_status'    => array('publish', 'draft', 'pending', 'private', 'future'),
			'posts_per_page' => -1,
			'meta_key'       => 'folio_id',
			'meta_value'     => (string) $post_id,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		));

		$page_id_map = array();
		foreach ($pages as $page) {
			$new_page_id = wp_insert_post(array(
				'post_type'    => 'groove_folio_page',
				'post_title'   => $page->post_title,
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id(),
				'post_content' => $page->post_content,
				'post_excerpt' => $page->post_excerpt,
				'menu_order'   => $page->menu_order,
			));

			if (is_wp_error($new_page_id) || !$new_page_id) {
				continue;
			}

			$new_page_slug = wp_unique_post_slug(
				sanitize_title((string) $page->post_title),
				(int) $new_page_id,
				'draft',
				'groove_folio_page',
				0
			);
			if ($new_page_slug !== '') {
				wp_update_post(array(
					'ID' => (int) $new_page_id,
					'post_name' => $new_page_slug,
				));
			}

			$page_id_map[(int) $page->ID] = (int) $new_page_id;
			$this->copy_post_meta_values($page->ID, $new_page_id, array_merge($skip_keys, array('folio_id')));

			// Point to the new folio.
			update_post_meta($new_page_id, 'folio_id', $new_folio_id);
		}

		$replacement_map = $this->build_duplication_replacement_map($source, (int) $new_folio_id, $page_id_map);
		$this->rewrite_duplicated_post((int) $new_folio_id, $replacement_map, $skip_keys);

		foreach ($page_id_map as $new_page_id) {
			$this->rewrite_duplicated_post((int) $new_page_id, $replacement_map, array_merge($skip_keys, array('folio_id')));
		}

		return $new_folio_id;
	}

	private function copy_post_meta_values($source_post_id, $target_post_id, $skip_keys = array())
	{
		$all_meta = get_post_meta((int) $source_post_id);
		foreach ($all_meta as $meta_key => $meta_values) {
			if (in_array($meta_key, $skip_keys, true)) {
				continue;
			}
			foreach ((array) $meta_values as $meta_value) {
				add_post_meta((int) $target_post_id, $meta_key, maybe_unserialize($meta_value));
			}
		}
	}

	private function rewrite_duplicated_post($post_id, array $replacement_map, $skip_meta_keys = array())
	{
		$post = get_post((int) $post_id);
		if (!$post) {
			return;
		}

		$post_update = array('ID' => (int) $post_id);
		$content = $this->rewrite_duplicated_text((string) $post->post_content, $replacement_map);
		$excerpt = $this->rewrite_duplicated_text((string) $post->post_excerpt, $replacement_map);

		if ($content !== (string) $post->post_content) {
			$post_update['post_content'] = $content;
		}

		if ($excerpt !== (string) $post->post_excerpt) {
			$post_update['post_excerpt'] = $excerpt;
		}

		if (count($post_update) > 1) {
			wp_update_post($post_update);
		}

		$this->rewrite_post_meta_values((int) $post_id, $replacement_map, $skip_meta_keys);
	}

	private function rewrite_post_meta_values($post_id, array $replacement_map, $skip_keys = array())
	{
		$all_meta = get_post_meta((int) $post_id);
		foreach ($all_meta as $meta_key => $meta_values) {
			if (in_array($meta_key, $skip_keys, true)) {
				continue;
			}

			$rewritten_values = array();
			$has_changes = false;

			foreach ((array) $meta_values as $meta_value) {
				$original_value = maybe_unserialize($meta_value);
				$rewritten_value = $this->rewrite_duplicated_meta_value($original_value, $replacement_map);
				if ($rewritten_value !== $original_value) {
					$has_changes = true;
				}
				$rewritten_values[] = $rewritten_value;
			}

			if (!$has_changes) {
				continue;
			}

			delete_post_meta((int) $post_id, $meta_key);
			foreach ($rewritten_values as $rewritten_value) {
				add_post_meta((int) $post_id, $meta_key, $rewritten_value);
			}
		}
	}

	private function rewrite_duplicated_meta_value($value, array $replacement_map)
	{
		if (is_string($value)) {
			return $this->rewrite_duplicated_text($value, $replacement_map);
		}

		if (!is_array($value)) {
			return $value;
		}

		foreach ($value as $key => $item) {
			$value[$key] = $this->rewrite_duplicated_meta_value($item, $replacement_map);
		}

		return $value;
	}

	private function rewrite_duplicated_text($value, array $replacement_map)
	{
		if (!is_string($value) || $value === '') {
			return $value;
		}

		$rewritten = strtr($value, $replacement_map['strings'] ?? array());

		foreach ($replacement_map['regex'] ?? array() as $pattern => $replacement) {
			$rewritten = (string) preg_replace($pattern, $replacement, $rewritten);
		}

		return $rewritten;
	}

	private function build_duplication_replacement_map(\WP_Post $source_folio, int $new_folio_id, array $page_id_map): array
	{
		$string_replacements = array();
		$regex_replacements = array();
		$source_folio_id = (int) $source_folio->ID;

		$this->add_post_replacement_variants($string_replacements, $source_folio_id, $new_folio_id, 0, 0);

		foreach ($page_id_map as $source_page_id => $new_page_id) {
			$this->add_post_replacement_variants(
				$string_replacements,
				(int) $source_page_id,
				(int) $new_page_id,
				$source_folio_id,
				$new_folio_id
			);
		}

		$id_map = array($source_folio_id => $new_folio_id);
		foreach ($page_id_map as $source_page_id => $new_page_id) {
			$id_map[(int) $source_page_id] = (int) $new_page_id;
		}

		foreach ($id_map as $old_id => $new_id) {
			$old_id = (int) $old_id;
			$new_id = (int) $new_id;

			$regex_replacements['/([?&](?:folio_id|p|post|page_id|post_id)=)' . preg_quote((string) $old_id, '/') . '\b/'] = '$1' . $new_id;
			$regex_replacements['/(["\'](?:folio_id|p|post|page_id|post_id)["\']\s*:\s*)' . preg_quote((string) $old_id, '/') . '\b/'] = '$1' . $new_id;
			$regex_replacements['/(\b(?:folio_id|p|post|page_id|post_id)\b\s*:\s*)' . preg_quote((string) $old_id, '/') . '\b/'] = '$1' . $new_id;
		}

		return array(
			'strings' => $string_replacements,
			'regex' => $regex_replacements,
		);
	}

	private function add_post_replacement_variants(array &$string_replacements, int $source_post_id, int $new_post_id, int $source_folio_id, int $new_folio_id)
	{
		$this->add_url_replacement_variants(
			$string_replacements,
			Utils::get_folio_preview_query_url_by_id($source_post_id),
			Utils::get_folio_preview_query_url_by_id($new_post_id)
		);

		$this->add_url_replacement_variants(
			$string_replacements,
			$this->get_frontend_folio_url($source_post_id, $source_folio_id),
			$this->get_frontend_folio_url($new_post_id, $new_folio_id)
		);
	}

	private function get_frontend_folio_url(int $post_id, int $folio_id = 0): string
	{
		$post = get_post($post_id);
		if (!$post || !Utils::is_groove_post($post)) {
			return '';
		}

		$prefix = '';
		if ($post->post_type === 'groove_folio_page') {
			$resolved_folio_id = $folio_id > 0 ? $folio_id : (int) get_post_meta($post_id, 'folio_id', true);
			$folio = get_post($resolved_folio_id);
			if ($folio) {
				$prefix = Utils::get_post_slug($folio);
			}
		}

		return (string) Utils::get_folio_permalink($post, $prefix);
	}

	private function add_url_replacement_variants(array &$string_replacements, $source_url, $new_url)
	{
		$source_url = is_string($source_url) ? trim($source_url) : '';
		$new_url = is_string($new_url) ? trim($new_url) : '';
		if ($source_url === '' || $new_url === '' || $source_url === $new_url) {
			return;
		}

		$this->add_string_replacement($string_replacements, $source_url, $new_url);
		$this->add_string_replacement($string_replacements, str_replace('&', '&amp;', $source_url), str_replace('&', '&amp;', $new_url));
		$this->add_string_replacement($string_replacements, str_replace('&', '&#038;', $source_url), str_replace('&', '&#038;', $new_url));
		if (wp_parse_url($source_url, PHP_URL_QUERY) === null && wp_parse_url($new_url, PHP_URL_QUERY) === null) {
			$this->add_string_replacement($string_replacements, trailingslashit(untrailingslashit($source_url)), trailingslashit(untrailingslashit($new_url)));
		}

		$source_relative = $this->get_relative_url($source_url);
		$new_relative = $this->get_relative_url($new_url);
		$this->add_string_replacement($string_replacements, $source_relative, $new_relative);
		$this->add_string_replacement($string_replacements, str_replace('&', '&amp;', $source_relative), str_replace('&', '&amp;', $new_relative));
		$this->add_string_replacement($string_replacements, str_replace('&', '&#038;', $source_relative), str_replace('&', '&#038;', $new_relative));
		if (wp_parse_url($source_relative, PHP_URL_QUERY) === null && wp_parse_url($new_relative, PHP_URL_QUERY) === null) {
			$this->add_string_replacement($string_replacements, trailingslashit(untrailingslashit($source_relative)), trailingslashit(untrailingslashit($new_relative)));
		}
	}

	private function get_relative_url(string $url): string
	{
		$path = (string) wp_parse_url($url, PHP_URL_PATH);
		$query = (string) wp_parse_url($url, PHP_URL_QUERY);
		if ($path === '' && $query === '') {
			return '';
		}

		return $query !== '' ? $path . '?' . $query : $path;
	}

	private function add_string_replacement(array &$string_replacements, string $source_value, string $new_value)
	{
		if ($source_value === '' || $new_value === '' || $source_value === $new_value) {
			return;
		}

		$string_replacements[$source_value] = $new_value;
	}

	private function get_folio_pages_url($folio_id)
	{
		return add_query_arg(
			array(
				'page' => 'groove-folio',
				'folio_id' => (int) $folio_id,
			),
			admin_url('admin.php')
		);
	}

	private function get_post_status_display_label($post_status)
	{
		$status_object = get_post_status_object($post_status);
		if ($status_object && !empty($status_object->label)) {
			return (string) $status_object->label;
		}

		return ucfirst((string) $post_status);
	}

	private function get_last_modified_by($post_id)
	{
		$last_editor_id = (int) get_post_meta($post_id, '_edit_last', true);
		if ($last_editor_id > 0) {
			$user = get_userdata($last_editor_id);
			if ($user) {
				return $user->display_name;
			}
		}

		$author = get_userdata((int) get_post_field('post_author', $post_id));
		return $author ? $author->display_name : esc_html__('Unknown user', 'groove-folios');
	}

	public function display_content()
	{
		$status = $this->get_current_status();
		$search = $this->get_search_term();
		$current_collection_tags = $this->get_current_collection_tags();
		$collection_tag_terms = $this->get_collection_tag_terms();
		$paged = $this->get_current_paged();
		$orderby = $this->get_current_orderby();
		$order = $this->get_current_order();
		$status_counts = $this->get_status_counts();
		$bulk_actions = $this->get_available_bulk_actions($status);
		$results = $this->get_folios_results($status, $search, $paged, $orderby, $order);
		$quick_edit_table = new Folio_List_Table($this, static::POST_TYPE);
		$hidden_columns = $this->get_hidden_table_columns();
		$visible_column_count = max(1, count(array_diff(array_keys($this->get_table_columns()), $hidden_columns)));
		$posts = (array) $results['posts'];
		$page_counts = (array) $results['page_counts'];
		$total_items = (int) $results['total_items'];
		$total_pages = (int) $results['total_pages'];
		?>
		<?php $this->queue_bulk_toast(); ?>
		<form id="posts-filter" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr(static::PAGE_ID); ?>" />
			<input type="hidden" name="post_status" value="<?php echo esc_attr($status); ?>" />
			<input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>" />
			<input type="hidden" name="order" value="<?php echo esc_attr($order); ?>" />
			<?php foreach ($current_collection_tags as $collection_tag): ?>
				<input type="hidden" name="collection_tag[]" value="<?php echo esc_attr($collection_tag); ?>" />
			<?php endforeach; ?>
			<?php wp_nonce_field('groove_bulk_folios_action', '_groove_bulk_nonce'); ?>

			<div class="g-all-folios__filters-row">
				<?php if (!empty($collection_tag_terms)): ?>
					<div class="g-all-folios__collection-filters" aria-label="<?php esc_attr_e('Collection tag filters', 'groove-folios'); ?>">
						<a
							href="<?php echo esc_url($this->build_page_url(array_filter(array(
								'post_status' => $status !== 'all' ? $status : null,
								's' => $search !== '' ? $search : null,
								'orderby' => $orderby !== 'modified' ? $orderby : null,
								'order' => $order !== 'DESC' ? $order : null,
							), function ($value) {
								return $value !== null;
							}))); ?>"
							class="g-all-folios__collection-pill<?php echo empty($current_collection_tags) ? ' is-active' : ''; ?>"
							data-tooltip="<?php esc_attr_e('Show all collections', 'groove-folios'); ?>">
							<?php esc_html_e('All Collections', 'groove-folios'); ?>
						</a>
						<?php foreach ($collection_tag_terms as $term): ?>
							<?php if (!$term instanceof \WP_Term) {
								continue;
							} ?>
							<a
								href="<?php echo esc_url($this->get_collection_tag_toggle_url($term->slug, $status, $search, $orderby, $order)); ?>"
								class="g-all-folios__collection-pill<?php echo in_array($term->slug, $current_collection_tags, true) ? ' is-active' : ''; ?>"
								data-tooltip="<?php esc_attr_e('Show collection', 'groove-folios'); ?>">
								<?php echo esc_html($term->name); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<ul class="subsubsub">
				<?php
				$total_statuses = count($status_counts);
				$status_index = 0;
				foreach ($status_counts as $status_key => $count) {
					$status_index++;
					$is_active = $status_key === $status;
					$status_url = $this->build_page_url(array_filter(array(
						'post_status' => $status_key,
						's' => $search !== '' ? $search : null,
						'orderby' => $orderby !== 'modified' ? $orderby : null,
						'order' => $order !== 'DESC' ? $order : null,
						'theme_id' => isset($_GET['theme_id']) && $_GET['theme_id'] !== '' ? sanitize_key($_GET['theme_id']) : null,
						'collection_tag' => $this->get_collection_tag_query_arg($current_collection_tags),
					), function ($value) {
						return $value !== null;
					}));
					?>
					<li class="<?php echo esc_attr($status_key); ?>">
						<a href="<?php echo esc_url($status_url); ?>"
							class="<?php echo esc_attr($is_active ? 'current' : ''); ?>">
							<?php echo esc_html($this->get_status_label($status_key)); ?>
							<span class="count">(<?php echo esc_html(number_format_i18n($count)); ?>)</span>
						</a>
						<?php if ($status_index < $total_statuses): ?>
							|
						<?php endif; ?>
					</li>
					<?php
				}
				?>
			</ul>

			<p class="search-box">
				<label class="screen-reader-text"
					for="post-search-input"><?php esc_html_e('Search folios', 'groove-folios'); ?>:</label>
				<input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
				<input type="submit" id="search-submit" class="button"
					value="<?php esc_attr_e('Search Folios', 'groove-folios'); ?>" />
			</p>

			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-top"
						class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove-folios'); ?></label>
					<select name="action" id="bulk-action-selector-top">
						<option value="-1"><?php esc_html_e('Bulk actions', 'groove-folios'); ?></option>
						<?php foreach ($bulk_actions as $action_key => $action_label): ?>
							<option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="submit" id="doaction" class="button action"
						value="<?php esc_attr_e('Apply', 'groove-folios'); ?>" />
				</div>
				<div class="tablenav-pages">
					<?php if ($total_pages > 1): ?>
						<?php
						$base_url = $this->build_page_url(array_filter(array(
							'post_status' => $status !== 'all' ? $status : null,
							's' => $search !== '' ? $search : null,
							'orderby' => $orderby !== 'modified' ? $orderby : null,
							'order' => $order !== 'DESC' ? $order : null,
							'theme_id' => isset($_GET['theme_id']) && $_GET['theme_id'] !== '' ? sanitize_key($_GET['theme_id']) : null,
							'collection_tag' => $this->get_collection_tag_query_arg($current_collection_tags),
							'paged' => '%#%',
						), function ($value) {
							return $value !== null;
						}));
						$pagination_links = paginate_links(array(
							'base' => $base_url,
							'format' => '',
							'current' => $paged,
							'total' => $total_pages,
							'type' => 'array',
							'prev_text' => '&lsaquo;',
							'next_text' => '&rsaquo;',
						));
						?>
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html(_n('%s item', '%s items', $total_items, 'groove-folios')),
								esc_html(number_format_i18n($total_items))
							);
							?>
						</span>
						<span class="pagination-links">
							<?php echo wp_kses_post(implode(' ', (array) $pagination_links)); ?>
						</span>
					<?php else: ?>
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html(_n('%s item', '%s items', $total_items, 'groove-folios')),
								esc_html(number_format_i18n($total_items))
							);
							?>
						</span>
					<?php endif; ?>
				</div>
				<br class="clear" />
			</div>

			<table class="wp-list-table widefat striped table-view-list posts">
				<thead>
					<tr>
						<th scope="col" id="cb"
							class="<?php echo esc_attr($this->column_classes('cb', $hidden_columns, 'manage-column check-column')); ?>">
							<label class="screen-reader-text"
								for="cb-select-all-1"><?php esc_html_e('Select all folios', 'groove-folios'); ?></label>
							<input id="cb-select-all-1" type="checkbox" />
						</th>
						<th scope="col" id="title"
							class="<?php echo esc_attr($this->column_classes('title', $hidden_columns, 'manage-column column-primary ' . ($orderby === 'title' ? 'sorted ' . strtolower($order) : 'sortable desc'))); ?>">
							<a
								href="<?php echo esc_url($this->get_sort_url('title', $orderby, $order, $status, $search)); ?>">
								<span><?php esc_html_e('Folio Name', 'groove-folios'); ?></span>
								<span class="sorting-indicators"><span class="sorting-indicator asc"
										aria-hidden="true"></span><span class="sorting-indicator desc"
										aria-hidden="true"></span></span>
							</a>
						</th>
						<th scope="col" id="theme_name"
							class="<?php echo esc_attr($this->column_classes('theme_name', $hidden_columns, 'manage-column ' . ($orderby === 'theme_name' ? 'sorted ' . strtolower($order) : 'sortable desc'))); ?>">
							<a
								href="<?php echo esc_url($this->get_sort_url('theme_name', $orderby, $order, $status, $search)); ?>">
								<span><?php esc_html_e('Theme Name', 'groove-folios'); ?></span>
								<span class="sorting-indicators"><span class="sorting-indicator asc"
										aria-hidden="true"></span><span class="sorting-indicator desc"
										aria-hidden="true"></span></span>
							</a>
						</th>
						<th scope="col" id="collection_tags"
							class="<?php echo esc_attr($this->column_classes('collection_tags', $hidden_columns, 'manage-column')); ?>">
							<?php esc_html_e('Collection Tags', 'groove-folios'); ?>
						</th>
						<th scope="col" id="page_count"
							class="<?php echo esc_attr($this->column_classes('page_count', $hidden_columns, 'manage-column ' . ($orderby === 'page_count' ? 'sorted ' . strtolower($order) : 'sortable desc'))); ?>">
							<a
								href="<?php echo esc_url($this->get_sort_url('page_count', $orderby, $order, $status, $search)); ?>">
								<span><?php esc_html_e('Page Count', 'groove-folios'); ?></span>
								<span class="sorting-indicators"><span class="sorting-indicator asc"
										aria-hidden="true"></span><span class="sorting-indicator desc"
										aria-hidden="true"></span></span>
							</a>
						</th>
						<th scope="col" id="publish_status"
							class="<?php echo esc_attr($this->column_classes('publish_status', $hidden_columns, 'manage-column')); ?>">
							<?php esc_html_e('Publish Status', 'groove-folios'); ?>
						</th>
						<th scope="col" id="modified"
							class="<?php echo esc_attr($this->column_classes('modified', $hidden_columns, 'manage-column ' . ($orderby === 'modified' ? 'sorted ' . strtolower($order) : 'sortable desc'))); ?>">
							<a
								href="<?php echo esc_url($this->get_sort_url('modified', $orderby, $order, $status, $search)); ?>">
								<span><?php esc_html_e('Last Updated', 'groove-folios'); ?></span>
								<span class="sorting-indicators"><span class="sorting-indicator asc"
										aria-hidden="true"></span><span class="sorting-indicator desc"
										aria-hidden="true"></span></span>
							</a>
						</th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php if (!empty($posts)): ?>
						<?php foreach ($posts as $post): ?>
							<?php
							$post_id = (int) $post->ID;
							$post_status = (string) get_post_status($post_id);
							$title = get_the_title($post_id);
							$theme_id = (string) get_post_meta($post_id, 'theme_id', true);
							$edit_url = add_query_arg(
								array(
									'page' => 'groove-folio',
									'folio_id' => (int) $post_id,
									'theme_id' => sanitize_key($theme_id),
								),
								admin_url('admin.php')
							);
							$pages_url = $this->get_folio_pages_url($post_id);
							$page_count = (int) ($page_counts[$post_id] ?? 0);
							$view_url = Utils::get_folio_permalink_by_id($post_id);
							if (!$view_url) {
								$view_url = get_permalink($post_id);
							}
							$is_preview_status = in_array($post_status, array('draft', 'pending', 'future'), true);
							$row_view_url = $view_url;
							$row_view_label = $is_preview_status ? esc_html__('Preview', 'groove-folios') : esc_html__('View', 'groove-folios');
							$modified_label = sprintf(
								/* translators: 1: date/time value, 2: user display name */
								esc_html__('%1$s by %2$s', 'groove-folios'),
								get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $post_id),
								$this->get_last_modified_by($post_id)
							);
							$quick_edit_title = $title !== '' ? $title : esc_html__('(no title)', 'groove-folios');
							$quick_edit_aria_label = sprintf(
								/* translators: %s: Folio title. */
								esc_attr__('Quick edit "%s" inline', 'groove-folios'),
								wp_strip_all_tags($quick_edit_title)
							);
							?>
							<tr id="post-<?php echo esc_attr((string) $post_id); ?>">
								<th scope="row"
									class="<?php echo esc_attr($this->column_classes('cb', $hidden_columns, 'check-column')); ?>">
									<label class="screen-reader-text"
										for="cb-select-<?php echo esc_attr((string) $post_id); ?>"><?php esc_html_e('Select folio', 'groove-folios'); ?></label>
									<input id="cb-select-<?php echo esc_attr((string) $post_id); ?>" type="checkbox" name="post[]"
										value="<?php echo esc_attr((string) $post_id); ?>" />
								</th>
								<td class="<?php echo esc_attr($this->column_classes('title', $hidden_columns, 'title has-row-actions column-primary page-title')); ?>"
									data-colname="<?php esc_attr_e('Folio Name', 'groove-folios'); ?>">
									<strong>
										<a class="row-title" href="<?php echo esc_url($edit_url); ?>">
											<?php echo esc_html($title !== '' ? $title : esc_html__('(no title)', 'groove-folios')); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'groove-folios'); ?></a> |
										</span>
										<?php if ($post_status !== 'trash'): ?>
											<span class="inline hide-if-no-js">
												<button type="button" class="button-link editinline"
													aria-label="<?php echo esc_attr($quick_edit_aria_label); ?>"
													aria-expanded="false"><?php esc_html_e('Quick Edit', 'groove-folios'); ?></button> |
											</span>
										<?php endif; ?>
										<?php if ($post_status !== 'trash'): ?>
											<span class="duplicate">
												<a href="<?php echo esc_url(wp_nonce_url($this->build_page_url(array_filter(array(
													'action' => 'groove_duplicate_folio',
													'post' => $post_id,
													'post_status' => $status !== 'all' ? $status : null,
													's' => $search !== '' ? $search : null,
													'orderby' => $orderby !== 'modified' ? $orderby : null,
													'order' => $order !== 'DESC' ? $order : null,
													'collection_tag' => $this->get_collection_tag_query_arg($current_collection_tags),
												), function ($value) {
													return $value !== null;
												})), 'groove_duplicate_folio_' . $post_id)); ?>"><?php esc_html_e('Duplicate', 'groove-folios'); ?></a> |
											</span>
										<?php endif; ?>
										<span class="view">
											<a href="<?php echo esc_url($row_view_url); ?>" target="_blank"
												rel="noopener noreferrer"><?php echo esc_html($row_view_label); ?></a> |
										</span>
										<?php if ($post_status === 'trash'): ?>
											<span class="untrash">
												<a
													href="<?php echo esc_url(wp_nonce_url(admin_url('post.php?action=untrash&post=' . $post_id), 'untrash-post_' . $post_id)); ?>"><?php esc_html_e('Restore', 'groove-folios'); ?></a>
												|
											</span>
											<span class="delete">
												<a class="submitdelete"
													href="<?php echo esc_url(get_delete_post_link($post_id, '', true)); ?>"><?php esc_html_e('Delete Permanently', 'groove-folios'); ?></a>
											</span>
										<?php else: ?>
											<span class="trash">
												<a class="submitdelete"
													href="<?php echo esc_url(get_delete_post_link($post_id)); ?>"><?php esc_html_e('Trash', 'groove-folios'); ?></a>
											</span>
										<?php endif; ?>
									</div>
									<?php
									if (function_exists('get_inline_data')) {
										get_inline_data(get_post($post_id));
									}
									?>
									<button type="button" class="toggle-row"><span
											class="screen-reader-text"><?php esc_html_e('Show more details', 'groove-folios'); ?></span></button>
								</td>
								<td class="<?php echo esc_attr($this->column_classes('theme_name', $hidden_columns)); ?>"
									data-colname="<?php esc_attr_e('Theme Name', 'groove-folios'); ?>">
									<?php
									$all_themes = \Groove\Themes\Themes_Manager::get_all_themes();
									if (isset($all_themes[$theme_id])) {
										echo esc_html($all_themes[$theme_id]['name']);
									} else {
										echo esc_html($theme_id) . ' ' . esc_html__('(Unknown)', 'groove-folios');
									}
									?>
								</td>
								<td class="<?php echo esc_attr($this->column_classes('collection_tags', $hidden_columns)); ?>"
									data-colname="<?php esc_attr_e('Collection Tags', 'groove-folios'); ?>">
									<?php
									$collection_tag_links = $this->get_collection_tag_links($post_id, $status, $search, $orderby, $order);
									echo $collection_tag_links !== '' ? wp_kses_post($collection_tag_links) : '&mdash;';
									?>
								</td>
								<td class="<?php echo esc_attr($this->column_classes('page_count', $hidden_columns)); ?>"
									data-colname="<?php esc_attr_e('Page Count', 'groove-folios'); ?>">
									<a href="<?php echo esc_url($pages_url); ?>">
										<?php echo esc_html(number_format_i18n($page_count)); ?>
									</a>
								</td>
								<td class="<?php echo esc_attr($this->column_classes('publish_status', $hidden_columns)); ?>"
									data-colname="<?php esc_attr_e('Publish Status', 'groove-folios'); ?>">
									<?php echo esc_html($this->get_post_status_display_label($post_status)); ?>
								</td>
								<td class="<?php echo esc_attr($this->column_classes('modified', $hidden_columns)); ?>"
									data-colname="<?php esc_attr_e('Last Updated', 'groove-folios'); ?>">
									<?php echo esc_html($modified_label); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr class="no-items">
							<td class="colspanchange" colspan="<?php echo esc_attr((string) $visible_column_count); ?>">
								<?php esc_html_e('No folios found for the current filters.', 'groove-folios'); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<td class="<?php echo esc_attr($this->column_classes('cb', $hidden_columns, 'manage-column check-column')); ?>">
							<label class="screen-reader-text"
								for="cb-select-all-2"><?php esc_html_e('Select all folios', 'groove-folios'); ?></label>
							<input id="cb-select-all-2" type="checkbox" />
						</td>
						<th scope="col"
							class="<?php echo esc_attr($this->column_classes('title', $hidden_columns, 'manage-column column-primary')); ?>">
							<?php esc_html_e('Folio Name', 'groove-folios'); ?>
						</th>
						<th scope="col"
							class="<?php echo esc_attr($this->column_classes('theme_name', $hidden_columns, 'manage-column')); ?>">
							<?php esc_html_e('Theme Name', 'groove-folios'); ?>
						</th>
						<th scope="col"
							class="<?php echo esc_attr($this->column_classes('collection_tags', $hidden_columns, 'manage-column')); ?>">
							<?php esc_html_e('Collection Tags', 'groove-folios'); ?>
						</th>
						<th scope="col"
							class="<?php echo esc_attr($this->column_classes('page_count', $hidden_columns, 'manage-column')); ?>">
							<?php esc_html_e('Page Count', 'groove-folios'); ?>
						</th>
						<th scope="col"
							class="<?php echo esc_attr($this->column_classes('publish_status', $hidden_columns, 'manage-column')); ?>">
							<?php esc_html_e('Publish Status', 'groove-folios'); ?>
						</th>
						<th scope="col"
							class="<?php echo esc_attr($this->column_classes('modified', $hidden_columns, 'manage-column')); ?>">
							<?php esc_html_e('Last Updated', 'groove-folios'); ?>
						</th>
					</tr>
				</tfoot>
			</table>
			<?php if (!empty($posts)): ?>
				<?php
				$quick_edit_post = get_post((int) $posts[0]->ID);
				if ($quick_edit_post instanceof \WP_Post) {
					setup_postdata($quick_edit_post);
				}
				$quick_edit_table->inline_edit();
				if ($quick_edit_post instanceof \WP_Post) {
					wp_reset_postdata();
				}
				?>
			<?php endif; ?>

			<?php if ($total_pages > 1): ?>
				<div class="tablenav bottom">
					<div class="alignleft actions bulkactions">
						<label for="bulk-action-selector-bottom"
							class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove-folios'); ?></label>
						<select name="action2" id="bulk-action-selector-bottom">
							<option value="-1"><?php esc_html_e('Bulk actions', 'groove-folios'); ?></option>
							<?php foreach ($bulk_actions as $action_key => $action_label): ?>
								<option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="submit" id="doaction2" class="button action"
							value="<?php esc_attr_e('Apply', 'groove-folios'); ?>" />
					</div>
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html(_n('%s item', '%s items', $total_items, 'groove-folios')),
								esc_html(number_format_i18n($total_items))
							);
							?>
						</span>
						<?php
						$bottom_base_url = $this->build_page_url(array_filter(array(
							'post_status' => $status !== 'all' ? $status : null,
							's' => $search !== '' ? $search : null,
							'orderby' => $orderby !== 'modified' ? $orderby : null,
							'order' => $order !== 'DESC' ? $order : null,
							'theme_id' => isset($_GET['theme_id']) && $_GET['theme_id'] !== '' ? sanitize_key($_GET['theme_id']) : null,
							'collection_tag' => $this->get_collection_tag_query_arg($current_collection_tags),
							'paged' => '%#%',
						), function ($value) {
							return $value !== null;
						}));
						echo wp_kses_post(
							paginate_links(array(
								'base' => $bottom_base_url,
								'format' => '',
								'current' => $paged,
								'total' => $total_pages,
								'type' => 'plain',
								'prev_text' => '&lsaquo;',
								'next_text' => '&rsaquo;',
							))
						);
						?>
					</div>
					<br class="clear" />
				</div>
			<?php else: ?>
				<div class="tablenav bottom">
					<div class="alignleft actions bulkactions">
						<label for="bulk-action-selector-bottom"
							class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove-folios'); ?></label>
						<select name="action2" id="bulk-action-selector-bottom">
							<option value="-1"><?php esc_html_e('Bulk actions', 'groove-folios'); ?></option>
							<?php foreach ($bulk_actions as $action_key => $action_label): ?>
								<option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="submit" id="doaction2" class="button action"
							value="<?php esc_attr_e('Apply', 'groove-folios'); ?>" />
					</div>
					<br class="clear" />
				</div>
			<?php endif; ?>
		</form>
		<?php $this->display_add_new_modal(); ?>
		<?php
	}

	private function display_add_new_modal()
	{
		?>
		<div id="g-add-new-modal" class="g-add-new-modal" role="dialog" aria-modal="true"
			aria-labelledby="g-add-new-modal-title" hidden>
			<div class="g-add-new-modal__backdrop"></div>
			<div class="g-add-new-modal__dialog">
				<div class="g-add-new-modal__header">
					<h2 id="g-add-new-modal-title" class="g-add-new-modal__title">
						<?php echo esc_html__('Themes', 'groove-folios'); ?>
					</h2>
					<button type="button" class="g-add-new-modal__close"
						aria-label="<?php echo esc_attr__('Close', 'groove-folios'); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
							<path d="M2 2l12 12M14 2L2 14" stroke="currentColor" stroke-width="1.75"
								stroke-linecap="round" />
						</svg>
					</button>
				</div>
				<div class="g-add-new-modal__body">
					<?php \Groove\Plugin::instance()->add_new->display__themes(); ?>
				</div>
			</div>
		</div>
		<?php
	}
}
