<?php
namespace Groove\List;
use Groove\Pages\Page;

if (!defined('ABSPATH')) {
	exit;
}

class List_Table extends \WP_List_Table
{
	protected $page;
	/** Hierarchy depth for the title's em-dash pad. Core declares it on WP_Posts_List_Table, not WP_List_Table, so reading it undeclared was a PHP deprecation. */
	protected $current_level = 0;
	protected $wp_query;
	protected $post_type;

	function __construct(Page $page, $post_type)
	{
		global $wpdb;

		parent::__construct(array(
			'plural' => 'posts',
			'screen' => get_current_screen()
		));

		$this->page = $page;
		$this->post_type = $post_type;
		$this->screen->post_type = $post_type;
	}

	public function ensure_script()
	{
	}

	protected function handle_row_actions($item, $column_name, $primary)
	{
		if ($primary !== $column_name) {
			return '';
		}

		$post_meta = get_post_meta($item->ID);
		$post = $item;
		$post_type_object = get_post_type_object($post->post_type);
		$can_edit_post = current_user_can('edit_post', $post->ID);
		$actions = array();
		$title = _draft_or_post_title();

		if ($can_edit_post && 'trash' !== $post->post_status) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_edit_post_link($post->ID),
				/* translators: %s: Post title. */
				esc_attr(sprintf(__('Edit &#8220;%s&#8221;', 'groove-folios'), $title)),
				__('Edit', 'groove-folios')
			);

			if ('wp_block' !== $post->post_type) {
				$actions['inline hide-if-no-js'] = sprintf(
					'<button type="button" class="button-link editinline" aria-label="%s" aria-expanded="false">%s</button>',
					/* translators: %s: Post title. */
					esc_attr(sprintf(__('Quick edit &#8220;%s&#8221; inline', 'groove-folios'), $title)),
					__('Quick&nbsp;Edit', 'groove-folios')
				);
			}
		}

		if (current_user_can('delete_post', $post->ID)) {
			if ('trash' === $post->post_status) {
				$actions['untrash'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					wp_nonce_url(admin_url(sprintf($post_type_object->_edit_link . '&amp;action=untrash', $post->ID)), 'untrash-post_' . $post->ID),
					/* translators: %s: Post title. */
					esc_attr(sprintf(__('Restore &#8220;%s&#8221; from the Trash', 'groove-folios'), $title)),
					__('Restore', 'groove-folios')
				);
			} elseif (EMPTY_TRASH_DAYS) {
				$actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link($post->ID),
					/* translators: %s: Post title. */
					esc_attr(sprintf(__('Move &#8220;%s&#8221; to the Trash', 'groove-folios'), $title)),
					_x('Trash', 'verb', 'groove-folios')
				);
			}

			if ('trash' === $post->post_status || !EMPTY_TRASH_DAYS) {
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link($post->ID, '', true),
					/* translators: %s: Post title. */
					esc_attr(sprintf(__('Delete &#8220;%s&#8221; permanently', 'groove-folios'), $title)),
					__('Delete Permanently', 'groove-folios')
				);
			}
		}

		if (is_post_type_viewable($post_type_object)) {

			$query_args = array(
				'folio_id' => isset($post_meta["folio_id"][0]) ? $post_meta["folio_id"][0] : ''
			);

			if (in_array($post->post_status, array('pending', 'draft', 'future'), true)) {
				if ($can_edit_post) {
					$preview_link = get_preview_post_link($post, $query_args);
					$actions['view'] = sprintf(
						'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
						esc_url($preview_link),
						/* translators: %s: Post title. */
						esc_attr(sprintf(__('Preview &#8220;%s&#8221;', 'groove-folios'), $title)),
						__('Preview', 'groove-folios')
					);
				}
			} elseif ('trash' !== $post->post_status) {
				$actions['view'] = sprintf(
					'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
					get_permalink($post->ID),
					/* translators: %s: Post title. */
					esc_attr(sprintf(__('View &#8220;%s&#8221;', 'groove-folios'), $title)),
					__('View', 'groove-folios')
				);
			}
		}

		if ('wp_block' === $post->post_type) {
			$actions['export'] = sprintf(
				'<button type="button" class="wp-list-reusable-blocks__export button-link" data-id="%s" aria-label="%s">%s</button>',
				$post->ID,
				/* translators: %s: Post title. */
				esc_attr(sprintf(__('Export &#8220;%s&#8221; as JSON', 'groove-folios'), $title)),
				__('Export as JSON', 'groove-folios')
			);
		}

		if (is_post_type_hierarchical($post->post_type)) {

			/**
			 * Filters the array of row action links on the Pages list table.
			 *
			 * The filter is evaluated only for hierarchical post types.
			 *
			 * @since 2.8.0
			 *
			 * @param string[] $actions An array of row action links. Defaults are
			 *                          'Edit', 'Quick Edit', 'Restore', 'Trash',
			 *                          'Delete Permanently', 'Preview', and 'View'.
			 * @param WP_Post  $post    The post object.
			 */
			$actions = apply_filters('page_row_actions', $actions, $post); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's Pages list-table hook, applied deliberately so other plugins' row actions reach this posts list.
		} else {

			/**
			 * Filters the array of row action links on the Posts list table.
			 *
			 * The filter is evaluated only for non-hierarchical post types.
			 *
			 * @since 2.8.0
			 *
			 * @param string[] $actions An array of row action links. Defaults are
			 *                          'Edit', 'Quick Edit', 'Restore', 'Trash',
			 *                          'Delete Permanently', 'Preview', and 'View'.
			 * @param WP_Post  $post    The post object.
			 */
			$actions = apply_filters('post_row_actions', $actions, $post); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's Posts list-table hook, applied deliberately so other plugins' row actions reach this posts list.
		}

		return $this->row_actions($actions);
	}

	protected function column_cb($item)
	{
		return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->ID);
	}

	protected function _column_title($post, $classes, $data, $primary)
	{
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data is the data-colname attribute WP_List_Table::single_row_columns() builds with esc_attr().
		echo '<td class="' . esc_attr($classes) . ' page-title" ', $data, '>';
		$this->column_title($post);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Row-action links built from escaped parts in handle_row_actions() and passed through core's row-action filters, printed unescaped as WP_Posts_List_Table does; kses would requote row_actions()' attributes.
		echo $this->handle_row_actions($post, 'title', $primary);
		echo '</td>';
	}

	public function column_title($post)
	{
		$can_edit_post = current_user_can('edit_post', $post->ID);

		if ($can_edit_post && 'trash' !== $post->post_status) {
			$lock_holder = wp_check_post_lock($post->ID);

			if ($lock_holder) {
				$lock_holder = get_userdata($lock_holder);
				$locked_avatar = get_avatar($lock_holder->ID, 18);
				/* translators: %s: Display name of the user editing the post. */
				$locked_text = sprintf(__('%s is currently editing', 'groove-folios'), $lock_holder->display_name);
			} else {
				$locked_avatar = '';
				$locked_text = '';
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $locked_avatar is get_avatar() markup, which escapes its own attributes; kses would requote them.
			echo '<div class="locked-info"><span class="locked-avatar">' . $locked_avatar . '</span> <span class="locked-text">' . esc_html($locked_text) . "</span></div>\n";
		}

		$pad = str_repeat('&#8212; ', $this->current_level);
		echo '<strong class="g-folio__title-wrap">';

		$title = (string) $post->post_title;

		if ($can_edit_post && 'trash' !== $post->post_status) {
			printf(
				'<a class="row-title g-folio__truncate-text" href="%s" aria-label="%s" title="%s">%s%s</a>',
				esc_url(get_edit_post_link($post->ID)),
				/* translators: %s: Post title. */
				esc_attr(sprintf(__('&#8220;%s&#8221; (Edit)', 'groove-folios'), $title)),
				esc_attr($title),
				esc_html($pad),
				esc_html($title)
			);
		} else {
			printf(
				'<span class="g-folio__truncate-text" title="%s">%s%s</span>',
				esc_attr($title),
				esc_html($pad),
				esc_html($title)
			);
		}
		_post_states($post);

		if (isset($parent_name)) {
			$post_type_object = get_post_type_object($post->post_type);
			echo ' | ' . esc_html($post_type_object->labels->parent_item_colon) . ' ' . esc_html($parent_name);
		}

		echo "</strong>\n";

		get_inline_data($post);
	}


	protected function get_bulk_actions()
	{
		$actions = array();
		$post_type_obj = get_post_type_object($this->screen->post_type);

		if (current_user_can($post_type_obj->cap->edit_posts)) {
			if ($this->is_trash) {
				$actions['untrash'] = __('Restore', 'groove-folios');
			} else {
				$actions['edit'] = __('Edit', 'groove-folios');
			}
		}

		if (current_user_can($post_type_obj->cap->delete_posts)) {
			if ($this->is_trash || !EMPTY_TRASH_DAYS) {
				$actions['delete'] = __('Delete permanently', 'groove-folios');
			} else {
				$actions['trash'] = __('Move to Trash', 'groove-folios');
			}
		}

		return $actions;
	}

	protected function count_posts()
	{
		global $wpdb;

		$post_type = $this->screen->post_type;
		$meta_key = 'folio_id';
		$counts = array_fill_keys(get_post_stati(), 0);

		if (isset($_REQUEST['folio_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter naming the folio whose pages are counted.
			$meta_value = absint(wp_unslash($_REQUEST['folio_id'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter naming the folio whose pages are counted.

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Per-status page counts for one folio; core has no API that counts posts by meta value, and the counts must be fresh after each bulk action.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.post_status, COUNT(*) AS num_posts
						FROM $wpdb->posts AS p
						INNER JOIN $wpdb->postmeta AS pm ON p.ID = pm.post_id
						WHERE p.post_type = %s
						AND pm.meta_key = %s
						AND pm.meta_value = %d
						GROUP BY p.post_status",
					$post_type,
					$meta_key,
					$meta_value
				)
			);

			foreach ((array) $results as $result) {
				if (!isset($result->post_status) || !isset($result->num_posts)) {
					continue;
				}

				$counts[$result->post_status] = (int) $result->num_posts;
			}
		}

		return $counts;
	}

	protected function get_views()
	{
		$post_type = $this->screen->post_type;
		$avail_post_stati = get_available_post_statuses($post_type);

		$status_links = array();
		$num_posts = (array) $this->count_posts();
		$total_posts = array_sum($num_posts);
		$class = '';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- get_views() only reads view parameters (folio, theme, tab, status, author) to build filter links; it changes nothing.
		$current_user_id = get_current_user_id();
		$all_args = array(
			'page' => $this->page::PAGE_ID,
			'folio_id' => isset($_REQUEST['folio_id']) ? absint(wp_unslash($_REQUEST['folio_id'])) : null,
			'theme_id' => isset($_REQUEST['theme_id']) ? sanitize_key(wp_unslash($_REQUEST['theme_id'])) : null,
			'tab_key' => isset($_REQUEST['tab_key']) ? sanitize_key(wp_unslash($_REQUEST['tab_key'])) : null,
		);
		$mine = '';

		// Subtract post types that are not included in the admin all list.
		foreach (get_post_stati(array('show_in_admin_all_list' => false)) as $state) {
			$total_posts -= isset($num_posts[$state]) ? $num_posts[$state] : 0;
		}

		if ($this->user_posts_count && $this->user_posts_count !== $total_posts) {
			if (isset($_GET['author']) && ($current_user_id === (int) $_GET['author'])) {
				$class = 'current';
			}

			$mine_args = array(
				'post_type' => $post_type,
				'author' => $current_user_id,
				'theme_id' => isset($_REQUEST['theme_id']) ? sanitize_key(wp_unslash($_REQUEST['theme_id'])) : null,
				'folio_id' => isset($_REQUEST['folio_id']) ? absint(wp_unslash($_REQUEST['folio_id'])) : null,
				'tab_key' => isset($_REQUEST['tab_key']) ? sanitize_key(wp_unslash($_REQUEST['tab_key'])) : null,
			);

			$mine_inner_html = sprintf(
				/* translators: %s: Number of posts. */
				_nx(
					'Mine <span class="count">(%s)</span>',
					'Mine <span class="count">(%s)</span>',
					$this->user_posts_count,
					'posts'
				, 'groove-folios'),
				number_format_i18n($this->user_posts_count)
			);

			$mine = array(
				'url' => esc_url(add_query_arg($mine_args, 'edit.php')),
				'label' => $mine_inner_html,
				'current' => isset($_GET['author']) && ($current_user_id === (int) $_GET['author']),
			);

			$all_args['all_posts'] = 1;
			$class = '';
		}

		$all_inner_html = sprintf(
			/* translators: %s: Number of posts. */
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts'
			, 'groove-folios'),
			number_format_i18n($total_posts)
		);

		$status_links['all'] = array(
			'url' => esc_url(add_query_arg($all_args, 'admin.php')),
			'label' => $all_inner_html,
			'current' => empty($class) && ($this->is_base_request() || isset($_REQUEST['all_posts'])),
		);

		if ($mine) {
			$status_links['mine'] = $mine;
		}

		foreach (get_post_stati(array('show_in_admin_status_list' => true), 'objects') as $status) {
			$class = '';

			$status_name = $status->name;
			$status_count = isset($num_posts[$status_name]) ? $num_posts[$status_name] : 0;

			if (!in_array($status_name, $avail_post_stati, true) || empty($status_count)) {
				continue;
			}

			if (isset($_REQUEST['post_status']) && $status_name === $_REQUEST['post_status']) {
				$class = 'current';
			}

			$status_args = array(
				'post_status' => $status_name,
				'page' => $this->page::PAGE_ID,
				'tab_key' => isset($_REQUEST['tab_key']) ? sanitize_key(wp_unslash($_REQUEST['tab_key'])) : null,
				'folio_id' => isset($_REQUEST['folio_id']) ? absint(wp_unslash($_REQUEST['folio_id'])) : null,
				'theme_id' => isset($_REQUEST['theme_id']) ? sanitize_key(wp_unslash($_REQUEST['theme_id'])) : null
			);

			$status_label = sprintf(
				translate_nooped_plural($status->label_count, $status_count),
				number_format_i18n($status_count)
			);

			$status_links[$status_name] = array(
				'url' => esc_url(add_query_arg($status_args, 'admin.php')),
				'label' => $status_label,
				'current' => isset($_REQUEST['post_status']) && $status_name === $_REQUEST['post_status'],
			);
		}

		if (!empty($this->sticky_posts_count)) {
			$class = !empty($_REQUEST['show_sticky']) ? 'current' : '';

			$sticky_args = array(
				'post_type' => $post_type,
				'show_sticky' => 1,
			);

			$sticky_inner_html = sprintf(
				/* translators: %s: Number of posts. */
				_nx(
					'Sticky <span class="count">(%s)</span>',
					'Sticky <span class="count">(%s)</span>',
					$this->sticky_posts_count,
					'posts'
				, 'groove-folios'),
				number_format_i18n($this->sticky_posts_count)
			);

			$sticky_link = array(
				'sticky' => array(
					'url' => esc_url(add_query_arg($sticky_args, 'edit.php')),
					'label' => $sticky_inner_html,
					'current' => !empty($_REQUEST['show_sticky']),
				),
			);

			// Sticky comes after Publish, or if not listed, after All.
			$split = 1 + array_search((isset($status_links['publish']) ? 'publish' : 'all'), array_keys($status_links), true);
			$status_links = array_merge(array_slice($status_links, 0, $split), $sticky_link, array_slice($status_links, $split));
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $this->get_views_links($status_links);
	}

	protected function get_primary_column_name()
	{
		return 'title';
	}

	function get_columns()
	{
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['title'] = _x('Title', 'column name', 'groove-folios');
		$posts_columns['date'] = __('Date', 'groove-folios');

		return $posts_columns;
	}

	protected function get_sortable_columns()
	{
		$sortables = array(
			'title' => array('title', false, __('Title', 'groove-folios'), __('Table ordered by Title.', 'groove-folios')),
			'date' => array('date', true, __('Date', 'groove-folios'), __('Table ordered by Date.', 'groove-folios'), 'desc'),
		);

		return $sortables;
	}

	protected function get_column_info()
	{
		$columns = $this->get_columns();
		$sortables = $this->get_sortable_columns();
		$primary = $this->get_primary_column_name();

		return $this->_column_headers = array($columns, array(), $sortables, $primary);
	}

	public function column_default($item, $column_name)
	{
		$post = $item;

		// echo json_encode($post);

		if ($column_name == 'title') {
			return $post->post_title;
		} else if ($column_name == 'date') {
			return $post->post_date;
		} else if ($column_name == 'menu_order') {
			return $post->menu_order;
		}

		return $post[$column_name];
	}

	public function get_query_args()
	{
		$args = array(
			'post_type' => $this->post_type
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view parameters (sort column, sort direction, status filter) that only shape the list query.
		if (isset($_REQUEST['orderby'])) {
			$args['orderby'] = sanitize_text_field(wp_unslash($_REQUEST['orderby']));
		}

		if (isset($_REQUEST['order'])) {
			$args['order'] = sanitize_key(wp_unslash($_REQUEST['order']));
		}

		if (isset($_REQUEST['post_status'])) {
			$args['post_status'] = sanitize_key(wp_unslash($_REQUEST['post_status']));
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $args;
	}

	public function get_wp_query()
	{
		$args = $this->get_query_args();
		$wp_query = new \WP_Query($args);

		return $wp_query;
	}

	public function prepare_items()
	{
		$this->wp_query = $this->get_wp_query();

		$avail_post_stati = wp_edit_posts_query();
		$post_status = isset($_REQUEST['post_status']) ? sanitize_key(wp_unslash($_REQUEST['post_status'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: the status filter.

		$this->set_hierarchical_display(
			is_post_type_hierarchical($this->screen->post_type)
			&& 'menu_order title' === $this->wp_query->query['orderby']
		);

		$post_type = $this->screen->post_type;
		$per_page = $this->get_items_per_page('edit_' . $post_type . '_per_page');

		$per_page = apply_filters('edit_posts_per_page', $per_page, $post_type); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's posts-per-page hook, applied deliberately so the screen honours the same setting as core posts lists.

		if ($this->hierarchical_display) {
			$total_items = $this->wp_query->post_count;
		} elseif ($this->wp_query->found_posts || $this->get_pagenum() === 1) {
			$total_items = $this->wp_query->found_posts;
		} else {
			$post_counts = (array) wp_count_posts($post_type, 'readable');

			if ('' !== $post_status && in_array($post_status, $avail_post_stati, true)) {
				$total_items = isset($post_counts[$post_status]) ? $post_counts[$post_status] : 0;
			} elseif (!empty($_REQUEST['show_sticky'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: the sticky-posts filter.
				$total_items = $this->sticky_posts_count;
			} elseif (isset($_GET['author']) && get_current_user_id() === (int) $_GET['author']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: the author filter.
				$total_items = $this->user_posts_count;
			} else {
				$total_items = array_sum($post_counts);

				// Subtract post types that are not included in the admin all list.
				foreach (get_post_stati(array('show_in_admin_all_list' => false)) as $state) {
					$total_items -= isset($post_counts[$state]) ? $post_counts[$state] : 0;
				}
			}
		}

		$this->is_trash = 'trash' === $post_status;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page' => $per_page,
			)
		);

		$this->items = $this->wp_query->posts;

		unset($this->wp_query);
	}

	public function no_items()
	{
		if (isset($_REQUEST['post_status']) && 'trash' === $_REQUEST['post_status']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: the status filter.
			echo esc_html(get_post_type_object($this->screen->post_type)->labels->not_found_in_trash);
		} else {
			echo esc_html(get_post_type_object($this->screen->post_type)->labels->not_found);
		}
	}

	public function single_row($post)
	{
		$post_owner = (get_current_user_id() === (int) $post->post_author) ? 'self' : 'other';
		?>
		<tr id="post-<?php echo (int) $post->ID; ?>"
			class="<?php echo esc_attr(trim(' author-' . $post_owner . ' status-' . $post->post_status)); ?>">
			<?php $this->single_row_columns($post); ?>
		</tr>
		<?php
	}

	public function ajax_rows($posts = array(), $level = 0)
	{
		add_filter('the_title', 'esc_html');
		$this->_display_rows($posts, $level);
	}

	/**
	 * @param array $posts
	 * @param int   $level
	 */
	private function _display_rows($posts, $level = 0)
	{
		$post_type = $this->screen->post_type;

		// Create array of post IDs.
		$post_ids = array();

		foreach ($posts as $a_post) {
			$post_ids[] = $a_post->ID;
		}

		if (post_type_supports($post_type, 'comments')) {
			$this->comment_pending_count = get_pending_comments_num($post_ids);
		}
		// WordPress 6.1+; on older sites the authors are simply fetched per row.
		if (function_exists('update_post_author_caches')) {
			update_post_author_caches($posts);
		}

		foreach ($posts as $post) {
			$this->single_row($post);
		}
	}

	public function inline_edit()
	{
		global $mode, $post;

		$screen = $this->screen;

		$post = get_default_post_to_edit($screen->post_type);
		if (!($post instanceof \WP_Post)) {
			$post = get_default_post_to_edit('post');
		}
		if (!($post instanceof \WP_Post)) {
			// Quick Edit's date fields call touch_time(), which expects a valid global post.
			$fallback_date = current_time('mysql');
			$post = new \WP_Post((object) array(
				'ID' => 0,
				'post_type' => $screen->post_type ?: 'post',
				'post_status' => 'draft',
				'post_date' => $fallback_date,
				'post_author' => get_current_user_id(),
				'post_password' => '',
				'post_name' => '',
				'post_parent' => 0,
				'menu_order' => 0,
			));
		}
		if ($post instanceof \WP_Post) {
			$post->filter = 'raw';
		}
		$GLOBALS['post'] = $post;
		$_inline_default_post = $GLOBALS['post']; // snapshot before hooks can corrupt $post
		$post_type_object = get_post_type_object($screen->post_type);

		$taxonomy_names = get_object_taxonomies($screen->post_type);
		$hierarchical_taxonomies = array();
		$flat_taxonomies = array();

		foreach ($taxonomy_names as $taxonomy_name) {
			$taxonomy = get_taxonomy($taxonomy_name);

			$show_in_quick_edit = $taxonomy->show_in_quick_edit;

			/**
			 * Filters whether the current taxonomy should be shown in the Quick Edit panel.
			 *
			 * @since 4.2.0
			 *
			 * @param bool   $show_in_quick_edit Whether to show the current taxonomy in Quick Edit.
			 * @param string $taxonomy_name      Taxonomy name.
			 * @param string $post_type          Post type of current Quick Edit post.
			 */
			if (!apply_filters('quick_edit_show_taxonomy', $show_in_quick_edit, $taxonomy_name, $screen->post_type)) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's quick_edit_show_taxonomy hook, applied deliberately so this Quick Edit panel behaves like core's for other plugins.
				continue;
			}

			if ($taxonomy->hierarchical) {
				$hierarchical_taxonomies[] = $taxonomy;
			} else {
				$flat_taxonomies[] = $taxonomy;
			}
		}

		$m = (isset($mode) && 'excerpt' === $mode) ? 'excerpt' : 'list';
		$can_publish = current_user_can($post_type_object->cap->publish_posts);
		$core_columns = array(
			'cb' => true,
			'date' => true,
			'title' => true,
			'menu_order' => true,
			'categories' => true,
			'tags' => true,
			'comments' => true,
			'author' => true,
		);
		?>

		<form method="get">
			<table style="display: none">
				<tbody id="inlineedit">
					<?php
					$hclass = count($hierarchical_taxonomies) ? 'post' : 'page';
					$inline_edit_classes = "inline-edit-row inline-edit-row-$hclass";
					$bulk_edit_classes = "bulk-edit-row bulk-edit-row-$hclass bulk-edit-{$screen->post_type}";
					$quick_edit_classes = "quick-edit-row quick-edit-row-$hclass inline-edit-{$screen->post_type}";

					$bulk = 0;

					while ($bulk < 2):
						$classes = $inline_edit_classes . ' ';
						$classes .= $bulk ? $bulk_edit_classes : $quick_edit_classes;
						?>
						<tr id="<?php echo $bulk ? 'bulk-edit' : 'inline-edit'; ?>" class="<?php echo esc_attr($classes); ?>"
							style="display: none">
							<td colspan="<?php echo (int) $this->get_column_count(); ?>" class="colspanchange">
								<div class="inline-edit-wrapper" role="region"
									aria-labelledby="<?php echo $bulk ? 'bulk' : 'quick'; ?>-edit-legend">
									<fieldset class="inline-edit-col-left">
										<legend class="inline-edit-legend" id="<?php echo $bulk ? 'bulk' : 'quick'; ?>-edit-legend">
											<?php echo $bulk ? esc_html__('Bulk Edit', 'groove-folios') : esc_html__('Quick Edit', 'groove-folios'); ?>
										</legend>
										<div class="inline-edit-col">

											<?php if (post_type_supports($screen->post_type, 'title')): ?>

												<?php if ($bulk): ?>

													<div id="bulk-title-div">
														<div id="bulk-titles"></div>
													</div>

												<?php else:  // $bulk ?>

													<label>
														<span class="title"><?php esc_html_e('Title', 'groove-folios'); ?></span>
														<span class="input-text-wrap"><input type="text" name="post_title" class="ptitle"
																value="" /></span>
													</label>

													<?php if (is_post_type_viewable($screen->post_type)): ?>

														<label>
															<span class="title">
																<?php esc_html_e('Slug', 'groove-folios'); ?>
															</span>
															<span class="input-text-wrap"><input type="text" name="post_name" value=""
																	autocomplete="off" spellcheck="false" /></span>
														</label>

													<?php endif; // is_post_type_viewable() ?>

												<?php endif; // $bulk ?>

											<?php endif; // post_type_supports( ... 'title' ) ?>

											<?php if (!$bulk): ?>
												<fieldset class="inline-edit-date">
													<legend><span class="title">
															<?php esc_html_e('Date', 'groove-folios'); ?>
														</span></legend>
													<?php $GLOBALS['post'] = $_inline_default_post; ?>
													<?php touch_time(1, 1, 0, 1); ?>
												</fieldset>
												<br class="clear" />
											<?php endif; // $bulk ?>

											<?php
											if (post_type_supports($screen->post_type, 'author')) {
												$authors_dropdown = '';

												if (current_user_can($post_type_object->cap->edit_others_posts)) {
													$dropdown_name = 'post_author';
													$dropdown_class = 'authors';
													if (function_exists('wp_is_large_user_count') && wp_is_large_user_count()) { // WordPress 6.0+
														$authors_dropdown = sprintf('<select name="%s" class="%s hidden"></select>', esc_attr($dropdown_name), esc_attr($dropdown_class));
													} else {
														$users_opt = array(
															'hide_if_only_one_author' => false,
															'capability' => array($post_type_object->cap->edit_posts),
															'name' => $dropdown_name,
															'class' => $dropdown_class,
															'multi' => 1,
															'echo' => 0,
															'show' => 'display_name_with_login',
														);

														if ($bulk) {
															$users_opt['show_option_none'] = __('&mdash; No Change &mdash;', 'groove-folios');
														}

														/**
														 * Filters the arguments used to generate the Quick Edit authors drop-down.
														 *
														 * @since 5.6.0
														 *
														 * @see wp_dropdown_users()
														 *
														 * @param array $users_opt An array of arguments passed to wp_dropdown_users().
														 * @param bool $bulk A flag to denote if it's a bulk action.
														 */
														$users_opt = apply_filters('quick_edit_dropdown_authors_args', $users_opt, $bulk); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's quick_edit_dropdown_authors_args hook, applied deliberately so this Quick Edit panel behaves like core's for other plugins.

														$authors = wp_dropdown_users($users_opt);

														if ($authors) {
															$authors_dropdown = '<label class="inline-edit-author">';
															$authors_dropdown .= '<span class="title">' . esc_html__('Author', 'groove-folios') . '</span>';
															$authors_dropdown .= $authors;
															$authors_dropdown .= '</label>';
														}
													}
												} // current_user_can( 'edit_others_posts' )
								
												if (!$bulk) {
													echo $authors_dropdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Label built above from escaped parts around wp_dropdown_users() markup, which core escapes; kses would requote its attributes.
												}
											} // post_type_supports( ... 'author' )
											?>

											<?php if (!$bulk && $can_publish): ?>

												<div class="inline-edit-group wp-clearfix">
													<label class="alignleft">
														<span class="title">
															<?php esc_html_e('Password', 'groove-folios'); ?>
														</span>
														<span class="input-text-wrap"><input type="text" name="post_password"
																class="inline-edit-password-input" value="" /></span>
													</label>

													<span class="alignleft inline-edit-or">
														<?php
														/* translators: Between password field and private checkbox on post quick edit interface. */
														esc_html_e('&ndash;OR&ndash;', 'groove-folios');
														?>
													</span>
													<label class="alignleft inline-edit-private">
														<input type="checkbox" name="keep_private" value="private" />
														<span class="checkbox-title">
															<?php esc_html_e('Private', 'groove-folios'); ?>
														</span>
													</label>
												</div>

											<?php endif; ?>

										</div>
									</fieldset>

									<?php if (count($hierarchical_taxonomies) && !$bulk): ?>

										<fieldset class="inline-edit-col-center inline-edit-categories">
											<div class="inline-edit-col">

												<?php foreach ($hierarchical_taxonomies as $taxonomy): ?>

													<span class="title inline-edit-categories-label">
														<?php echo esc_html($taxonomy->labels->name); ?>
													</span>
													<input type="hidden"
														name="<?php echo ('category' === $taxonomy->name) ? 'post_category[]' : 'tax_input[' . esc_attr($taxonomy->name) . '][]'; ?>"
														value="0" />
													<ul class="cat-checklist <?php echo esc_attr($taxonomy->name); ?>-checklist">
														<?php wp_terms_checklist(0, array('taxonomy' => $taxonomy->name)); ?>
													</ul>

												<?php endforeach; // $hierarchical_taxonomies as $taxonomy ?>

											</div>
										</fieldset>

									<?php endif; // count( $hierarchical_taxonomies ) && ! $bulk ?>

									<fieldset class="inline-edit-col-right">
										<div class="inline-edit-col">

											<?php
											if (post_type_supports($screen->post_type, 'author') && $bulk) {
												echo $authors_dropdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Label built above from escaped parts around wp_dropdown_users() markup, which core escapes; kses would requote its attributes.
											}
											?>

											<?php if (post_type_supports($screen->post_type, 'page-attributes')): ?>

												<?php if ($post_type_object->hierarchical): ?>

													<label>
														<span class="title">
															<?php esc_html_e('Parent', 'groove-folios'); ?>
														</span>
														<?php
														$dropdown_args = array(
															'post_type' => $post_type_object->name,
															'selected' => $post->post_parent,
															'name' => 'post_parent',
															'show_option_none' => __('Main Page (no parent)', 'groove-folios'),
															'option_none_value' => 0,
															'sort_column' => 'menu_order, post_title',
														);

														if ($bulk) {
															$dropdown_args['show_option_no_change'] = __('&mdash; No Change &mdash;', 'groove-folios');
														}

														/**
														 * Filters the arguments used to generate the Quick Edit page-parent drop-down.
														 *
														 * @since 2.7.0
														 * @since 5.6.0 The `$bulk` parameter was added.
														 *
														 * @see wp_dropdown_pages()
														 *
														 * @param array $dropdown_args An array of arguments passed to wp_dropdown_pages().
														 * @param bool  $bulk          A flag to denote if it's a bulk action.
														 */
														$dropdown_args = apply_filters('quick_edit_dropdown_pages_args', $dropdown_args, $bulk); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's quick_edit_dropdown_pages_args hook, applied deliberately so this Quick Edit panel behaves like core's for other plugins.

														wp_dropdown_pages($dropdown_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() prints markup it escapes itself.
														?>
													</label>

												<?php endif; // hierarchical ?>

												<?php if (!$bulk): ?>

													<label>
														<span class="title">
															<?php esc_html_e('Order', 'groove-folios'); ?>
														</span>
														<span class="input-text-wrap"><input type="text" name="menu_order"
																class="inline-edit-menu-order-input"
																value="<?php echo (int) $post->menu_order; ?>" /></span>
													</label>

												<?php endif; // ! $bulk ?>

											<?php endif; // post_type_supports( ... 'page-attributes' ) ?>

											<?php if (0 < count(get_page_templates(null, $screen->post_type))): ?>

												<label>
													<span class="title">
														<?php esc_html_e('Template', 'groove-folios'); ?>
													</span>
													<select name="page_template">
														<?php if ($bulk): ?>
															<option value="-1">
																<?php esc_html_e('&mdash; No Change &mdash;', 'groove-folios'); ?>
															</option>
														<?php endif; // $bulk ?>
														<?php
														/** This filter is documented in wp-admin/includes/meta-boxes.php */
														$default_title = apply_filters('default_page_template_title', __('Default template', 'groove-folios'), 'quick-edit'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's default_page_template_title hook, applied deliberately so this Quick Edit panel behaves like core's for other plugins.
														?>
														<option value="default">
															<?php echo esc_html($default_title); ?>
														</option>
														<?php page_template_dropdown('', $screen->post_type); ?>
													</select>
												</label>

											<?php endif; ?>

											<?php if (count($flat_taxonomies) && !$bulk): ?>

												<?php foreach ($flat_taxonomies as $taxonomy): ?>

													<?php if (current_user_can($taxonomy->cap->assign_terms)): ?>
														<?php $taxonomy_name = $taxonomy->name; ?>
														<?php
														$_flat_tax_terms = get_terms(array('taxonomy' => $taxonomy->name, 'hide_empty' => false));
														$_flat_tax_tag_names = is_wp_error($_flat_tax_terms) ? [] : array_map(function ($t) {
															return $t->name;
														}, $_flat_tax_terms);
														?>
														<div class="inline-edit-tags-wrap">
															<label class="inline-edit-tags">
																<span class="title">
																	<?php echo esc_html($taxonomy->labels->name); ?>
																</span>
																<textarea data-wp-taxonomy="<?php echo esc_attr($taxonomy_name); ?>" cols="22" rows="1"
																	name="tax_input[<?php echo esc_attr($taxonomy->name); ?>]"
																	class="tax_input_<?php echo esc_attr($taxonomy->name); ?>"
																	data-tags="<?php echo esc_attr(wp_json_encode($_flat_tax_tag_names)); ?>"
																	aria-describedby="inline-edit-<?php echo esc_attr($taxonomy->name); ?>-desc"></textarea>
															</label>
															<p class="howto" id="inline-edit-<?php echo esc_attr($taxonomy->name); ?>-desc">
																<?php echo esc_html($taxonomy->labels->separate_items_with_commas); ?>
															</p>
														</div>
													<?php endif; // current_user_can( 'assign_terms' ) ?>

												<?php endforeach; // $flat_taxonomies as $taxonomy ?>

											<?php endif; // count( $flat_taxonomies ) && ! $bulk ?>

											<?php if (post_type_supports($screen->post_type, 'comments') || post_type_supports($screen->post_type, 'trackbacks')): ?>

												<?php if ($bulk): ?>

													<div class="inline-edit-group wp-clearfix">

														<?php if (post_type_supports($screen->post_type, 'comments')): ?>

															<label class="alignleft">
																<span class="title">
																	<?php esc_html_e('Comments', 'groove-folios'); ?>
																</span>
																<select name="comment_status">
																	<option value="">
																		<?php esc_html_e('&mdash; No Change &mdash;', 'groove-folios'); ?>
																	</option>
																	<option value="open">
																		<?php esc_html_e('Allow', 'groove-folios'); ?>
																	</option>
																	<option value="closed">
																		<?php esc_html_e('Do not allow', 'groove-folios'); ?>
																	</option>
																</select>
															</label>

														<?php endif; ?>

														<?php if (post_type_supports($screen->post_type, 'trackbacks')): ?>

															<label class="alignright">
																<span class="title">
																	<?php esc_html_e('Pings', 'groove-folios'); ?>
																</span>
																<select name="ping_status">
																	<option value="">
																		<?php esc_html_e('&mdash; No Change &mdash;', 'groove-folios'); ?>
																	</option>
																	<option value="open">
																		<?php esc_html_e('Allow', 'groove-folios'); ?>
																	</option>
																	<option value="closed">
																		<?php esc_html_e('Do not allow', 'groove-folios'); ?>
																	</option>
																</select>
															</label>

														<?php endif; ?>

													</div>

												<?php else:  // $bulk ?>

													<div class="inline-edit-group wp-clearfix">

														<?php if (post_type_supports($screen->post_type, 'comments')): ?>

															<label class="alignleft">
																<input type="checkbox" name="comment_status" value="open" />
																<span class="checkbox-title">
																	<?php esc_html_e('Allow Comments', 'groove-folios'); ?>
																</span>
															</label>

														<?php endif; ?>

														<?php if (post_type_supports($screen->post_type, 'trackbacks')): ?>

															<label class="alignleft">
																<input type="checkbox" name="ping_status" value="open" />
																<span class="checkbox-title">
																	<?php esc_html_e('Allow Pings', 'groove-folios'); ?>
																</span>
															</label>

														<?php endif; ?>

													</div>

												<?php endif; // $bulk ?>

											<?php endif; // post_type_supports( ... comments or pings ) ?>

											<div class="inline-edit-group wp-clearfix">

												<label class="inline-edit-status alignleft">
													<span class="title"><?php esc_html_e('Status', 'groove-folios'); ?></span>
													<select name="_status">
														<?php if ($bulk): ?>
															<option value="-1">
																<?php esc_html_e('&mdash; No Change &mdash;', 'groove-folios'); ?>
															</option>
														<?php endif; // $bulk ?>

														<?php if ($can_publish):  // Contributors only get "Unpublished" and "Pending Review". ?>
															<option value="publish"><?php esc_html_e('Published', 'groove-folios'); ?>
															</option>
															<option value="future">
																<?php esc_html_e('Scheduled', 'groove-folios'); ?>
															</option>
															<?php if ($bulk): ?>
																<option value="private">
																	<?php esc_html_e('Private', 'groove-folios'); ?>
																</option>
															<?php endif; // $bulk ?>
														<?php endif; ?>

														<option value="pending">
															<?php esc_html_e('Pending Review', 'groove-folios'); ?>
														</option>
														<option value="draft">
															<?php esc_html_e('Draft', 'groove-folios'); ?>
														</option>
													</select>
												</label>

												<?php if ('post' === $screen->post_type && $can_publish && current_user_can($post_type_object->cap->edit_others_posts)): ?>

													<?php if ($bulk): ?>

														<label class="alignright">
															<span class="title">
																<?php esc_html_e('Sticky', 'groove-folios'); ?>
															</span>
															<select name="sticky">
																<option value="-1">
																	<?php esc_html_e('&mdash; No Change &mdash;', 'groove-folios'); ?>
																</option>
																<option value="sticky">
																	<?php esc_html_e('Sticky', 'groove-folios'); ?>
																</option>
																<option value="unsticky">
																	<?php esc_html_e('Not Sticky', 'groove-folios'); ?>
																</option>
															</select>
														</label>

													<?php else:  // $bulk ?>

														<label class="alignleft">
															<input type="checkbox" name="sticky" value="sticky" />
															<span class="checkbox-title"><?php esc_html_e('Make this post sticky', 'groove-folios'); ?></span>
														</label>

													<?php endif; // $bulk ?>

												<?php endif; // 'post' && $can_publish && current_user_can( 'edit_others_posts' ) ?>

											</div>

											<?php if ($bulk && current_theme_supports('post-formats') && post_type_supports($screen->post_type, 'post-formats')): ?>
												<?php $post_formats = get_theme_support('post-formats'); ?>

												<label class="alignleft">
													<span class="title">
														<?php echo esc_html_x('Format', 'post format', 'groove-folios'); ?>
													</span>
													<select name="post_format">
														<option value="-1">
															<?php esc_html_e('&mdash; No Change &mdash;', 'groove-folios'); ?>
														</option>
														<option value="0">
															<?php echo esc_html(get_post_format_string('standard')); ?>
														</option>
														<?php if (is_array($post_formats[0])): ?>
															<?php foreach ($post_formats[0] as $format): ?>
																<option value="<?php echo esc_attr($format); ?>">
																	<?php echo esc_html(get_post_format_string($format)); ?>
																</option>
															<?php endforeach; ?>
														<?php endif; ?>
													</select>
												</label>

											<?php endif; ?>

										</div>
									</fieldset>

									<?php
									list($columns) = $this->get_column_info();

									foreach ($columns as $column_name => $column_display_name) {
										if (isset($core_columns[$column_name])) {
											continue;
										}

										if ($bulk) {

											/**
											 * Fires once for each column in Bulk Edit mode.
											 *
											 * @since 2.7.0
											 *
											 * @param string $column_name Name of the column to edit.
											 * @param string $post_type   The post type slug.
											 */
											do_action('bulk_edit_custom_box', $column_name, $screen->post_type); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's bulk_edit_custom_box hook, applied deliberately so this Quick Edit panel behaves like core's for other plugins.
										} else {

											/**
											 * Fires once for each column in Quick Edit mode.
											 *
											 * @since 2.7.0
											 *
											 * @param string $column_name Name of the column to edit.
											 * @param string $post_type   The post type slug, or current screen name if this is a taxonomy list table.
											 * @param string $taxonomy    The taxonomy name, if any.
											 */
											do_action('quick_edit_custom_box', $column_name, $screen->post_type, ''); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's quick_edit_custom_box hook, applied deliberately so this Quick Edit panel behaves like core's for other plugins.
										}
									}
									?>

									<div class="submit inline-edit-save">
										<?php if (!$bulk): ?>
											<?php wp_nonce_field('inlineeditnonce', '_inline_edit', false); ?>
											<button type="button" class="button button-primary save">
												<?php esc_html_e('Update', 'groove-folios'); ?>
											</button>
										<?php else: ?>
											<?php submit_button(__('Update', 'groove-folios'), 'primary', 'bulk_edit', false); ?>
										<?php endif; ?>

										<button type="button" class="button cancel">
											<?php esc_html_e('Cancel', 'groove-folios'); ?>
										</button>

										<?php if (!$bulk): ?>
											<span class="spinner"></span>
										<?php endif; ?>

										<input type="hidden" name="post_view" value="<?php echo esc_attr($m); ?>" />
										<input type="hidden" name="screen" value="<?php echo esc_attr($screen->id); ?>" />
										<?php if (!$bulk && !post_type_supports($screen->post_type, 'author')): ?>
											<input type="hidden" name="post_author"
												value="<?php echo esc_attr($post->post_author); ?>" />
										<?php endif; ?>

										<div class="notice notice-error notice-alt inline hidden">
											<p class="error"></p>
										</div>
									</div>
								</div> <!-- end of .inline-edit-wrapper -->

							</td>
						</tr>

						<?php
						$bulk++;
					endwhile;
					?>
				</tbody>
			</table>
				</form>
				<?php
	}
}
?>
