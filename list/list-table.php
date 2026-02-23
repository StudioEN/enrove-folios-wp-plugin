<?php
namespace Groove\List;
use Groove\Pages\Page;

class List_Table extends \WP_List_Table {
  protected $page;
  protected $wp_query;
  protected $post_type;

  function __construct(Page $page, $post_type) {
    global $wpdb;

    parent::__construct(array(
      'plural' => 'posts',
      'screen' => get_current_screen()
    ));

    $this->page = $page;
    $this->post_type = $post_type;
    $this->screen->post_type = $post_type;	
  }

	public function ensure_script () {
  }

	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$post_meta        = get_post_meta($item->ID);
		$post             = $item;
		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post    = current_user_can( 'edit_post', $post->ID );
		$actions          = array();
		$title            = _draft_or_post_title();

		if ( $can_edit_post && 'trash' !== $post->post_status ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ),
				__( 'Edit' )
			);

			if ( 'wp_block' !== $post->post_type ) {
				$actions['inline hide-if-no-js'] = sprintf(
					'<button type="button" class="button-link editinline" aria-label="%s" aria-expanded="false">%s</button>',
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Quick edit &#8220;%s&#8221; inline' ), $title ) ),
					__( 'Quick&nbsp;Edit' )
				);
			}
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			if ( 'trash' === $post->post_status ) {
				$actions['untrash'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Restore &#8220;%s&#8221; from the Trash' ), $title ) ),
					__( 'Restore' )
				);
			} elseif ( EMPTY_TRASH_DAYS ) {
				$actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), $title ) ),
					_x( 'Trash', 'verb' )
				);
			}

			if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID, '', true ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $title ) ),
					__( 'Delete Permanently' )
				);
			}
		}

		if ( is_post_type_viewable( $post_type_object ) ) {
			
			$query_args = array(
				'folio_id' => isset($post_meta["folio_id"][0]) ? $post_meta["folio_id"][0] : ''
			);

			if ( in_array( $post->post_status, array( 'pending', 'draft', 'future' ), true ) ) {
				if ( $can_edit_post ) {
					$preview_link    = get_preview_post_link( $post, $query_args );
					$actions['view'] = sprintf(
						'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
						esc_url( $preview_link ),
						/* translators: %s: Post title. */
						esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $title ) ),
						__( 'Preview' )
					);
				}
			} elseif ( 'trash' !== $post->post_status ) {
				$actions['view'] = sprintf(
					'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
					get_permalink( $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ),
					__( 'View' )
				);
			}
		}

		if ( 'wp_block' === $post->post_type ) {
			$actions['export'] = sprintf(
				'<button type="button" class="wp-list-reusable-blocks__export button-link" data-id="%s" aria-label="%s">%s</button>',
				$post->ID,
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'Export &#8220;%s&#8221; as JSON' ), $title ) ),
				__( 'Export as JSON' )
			);
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {

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
			$actions = apply_filters( 'page_row_actions', $actions, $post );
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
			$actions = apply_filters( 'post_row_actions', $actions, $post );
		}

		return $this->row_actions( $actions );
	}

  protected function column_cb( $item ) {
    return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->ID);
  }

	protected function _column_title( $post, $classes, $data, $primary ) {
		echo '<td class="' . $classes . ' page-title" ', $data, '>';
		echo $this->column_title( $post );
		echo $this->handle_row_actions( $post, 'title', $primary );
		echo '</td>';
	}

	public function column_title( $post ) {
		$can_edit_post = current_user_can( 'edit_post', $post->ID );

		if ( $can_edit_post && 'trash' !== $post->post_status ) {
			$lock_holder = wp_check_post_lock( $post->ID );

			if ( $lock_holder ) {
				$lock_holder   = get_userdata( $lock_holder );
				$locked_avatar = get_avatar( $lock_holder->ID, 18 );
				$locked_text = esc_html( sprintf( __( '%s is currently editing' ), $lock_holder->display_name ) );
			} else {
				$locked_avatar = '';
				$locked_text   = '';
			}

			echo '<div class="locked-info"><span class="locked-avatar">' . $locked_avatar . '</span> <span class="locked-text">' . $locked_text . "</span></div>\n";
		}

		$pad = str_repeat( '&#8212; ', $this->current_level );
		echo '<strong>';

		$title = $post->post_title;

		if ( $can_edit_post && 'trash' !== $post->post_status ) {
			printf(
				'<a class="row-title" href="%s" aria-label="%s">%s%s</a>',
				get_edit_post_link( $post->ID ),
				esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $title ) ),
				$pad,
				$title
			);
		} else {
			printf(
				'<span>%s%s</span>',
				$pad,
				$title
			);
		}
		_post_states( $post );

		if ( isset( $parent_name ) ) {
			$post_type_object = get_post_type_object( $post->post_type );
			echo ' | ' . $post_type_object->labels->parent_item_colon . ' ' . esc_html( $parent_name );
		}

		echo "</strong>\n";

		get_inline_data( $post );
	}


	protected function get_bulk_actions() {
		$actions       = array();
		$post_type_obj = get_post_type_object( $this->screen->post_type );

		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			if ( $this->is_trash ) {
				$actions['untrash'] = __( 'Restore' );
			} else {
				$actions['edit'] = __( 'Edit' );
			}
		}

		if ( current_user_can( $post_type_obj->cap->delete_posts ) ) {
			if ( $this->is_trash || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = __( 'Delete permanently' );
			} else {
				$actions['trash'] = __( 'Move to Trash' );
			}
		}

		return $actions;
	}

	protected function count_posts () {
		global $wpdb;

		$post_type = $this->screen->post_type;
		$meta_key = 'folio_id';
		$counts = array_fill_keys( get_post_stati(), 0 );

		if ( isset( $_REQUEST['folio_id'] ) ) {
			$meta_value = absint( wp_unslash( $_REQUEST['folio_id'] ) );

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

			foreach ( (array) $results as $result ) {
				if ( ! isset( $result->post_status ) || ! isset( $result->num_posts ) ) {
					continue;
				}

				$counts[ $result->post_status ] = (int) $result->num_posts;
			}
		}

		return $counts;
	}

	protected function get_views() {
		$post_type = $this->screen->post_type;
		$avail_post_stati = get_available_post_statuses( $post_type );

		$status_links = array();
		$num_posts    = (array) $this->count_posts();
		$total_posts  = array_sum( $num_posts );
		$class        = '';

		$current_user_id = get_current_user_id();
		$all_args        = array( 
      'page' => $this->page::PAGE_ID,
			'folio_id' => isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : null,
			'theme_id' => isset($_REQUEST['theme_id']) ? $_REQUEST['theme_id'] : null,
			'tab_key' => isset($_REQUEST['tab_key']) ? $_REQUEST['tab_key'] : null,
    );
		$mine            = '';

		// Subtract post types that are not included in the admin all list.
		foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
			$total_posts -= isset( $num_posts[ $state ] ) ? $num_posts[ $state ] : 0;
		}

		if ( $this->user_posts_count && $this->user_posts_count !== $total_posts ) {
			if ( isset( $_GET['author'] ) && ( $current_user_id === (int) $_GET['author'] ) ) {
				$class = 'current';
			}

			$mine_args = array(
				'post_type' => $post_type,
				'author'    => $current_user_id,
				'theme_id' => isset($_REQUEST['theme_id']) ? $_REQUEST['theme_id'] : null,
				'folio_id'	=> isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : null,
				'tab_key' => isset($_REQUEST['tab_key']) ? $_REQUEST['tab_key'] : null,
			);

			$mine_inner_html = sprintf(
				/* translators: %s: Number of posts. */
				_nx(
					'Mine <span class="count">(%s)</span>',
					'Mine <span class="count">(%s)</span>',
					$this->user_posts_count,
					'posts'
				),
				number_format_i18n( $this->user_posts_count )
			);

			$mine = array(
				'url'     => esc_url( add_query_arg( $mine_args, 'edit.php' ) ),
				'label'   => $mine_inner_html,
				'current' => isset( $_GET['author'] ) && ( $current_user_id === (int) $_GET['author'] ),
			);

			$all_args['all_posts'] = 1;
			$class                 = '';
		}

		$all_inner_html = sprintf(
			/* translators: %s: Number of posts. */
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts'
			),
			number_format_i18n( $total_posts )
		);

		$status_links['all'] = array(
			'url'     => esc_url( add_query_arg( $all_args, 'admin.php' ) ),
			'label'   => $all_inner_html,
			'current' => empty( $class ) && ( $this->is_base_request() || isset( $_REQUEST['all_posts'] ) ),
		);

		if ( $mine ) {
			$status_links['mine'] = $mine;
		}

		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
			$class = '';

			$status_name = $status->name;
			$status_count = isset( $num_posts[ $status_name ] ) ? $num_posts[ $status_name ] : 0;

			if ( ! in_array( $status_name, $avail_post_stati, true ) || empty( $status_count ) ) {
				continue;
			}

			if ( isset( $_REQUEST['post_status'] ) && $status_name === $_REQUEST['post_status'] ) {
				$class = 'current';
			}

			$status_args = array(
				'post_status' => $status_name,
				'page' => $this->page::PAGE_ID,
				'tab_key' => isset($_REQUEST['tab_key']) ? $_REQUEST['tab_key'] : null,
				'folio_id' => isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : null,
				'theme_id' => isset($_REQUEST['theme_id']) ? $_REQUEST['theme_id'] : null
			);

			$status_label = sprintf(
				translate_nooped_plural( $status->label_count, $status_count ),
				number_format_i18n( $status_count )
			);

			$status_links[ $status_name ] = array(
				'url'     => esc_url( add_query_arg( $status_args, 'admin.php' ) ),
				'label'   => $status_label,
				'current' => isset( $_REQUEST['post_status'] ) && $status_name === $_REQUEST['post_status'],
			);
		}

		if ( ! empty( $this->sticky_posts_count ) ) {
			$class = ! empty( $_REQUEST['show_sticky'] ) ? 'current' : '';

			$sticky_args = array(
				'post_type'   => $post_type,
				'show_sticky' => 1,
			);

			$sticky_inner_html = sprintf(
				/* translators: %s: Number of posts. */
				_nx(
					'Sticky <span class="count">(%s)</span>',
					'Sticky <span class="count">(%s)</span>',
					$this->sticky_posts_count,
					'posts'
				),
				number_format_i18n( $this->sticky_posts_count )
			);

			$sticky_link = array(
				'sticky' => array(
					'url'     => esc_url( add_query_arg( $sticky_args, 'edit.php' ) ),
					'label'   => $sticky_inner_html,
					'current' => ! empty( $_REQUEST['show_sticky'] ),
				),
			);

			// Sticky comes after Publish, or if not listed, after All.
			$split        = 1 + array_search( ( isset( $status_links['publish'] ) ? 'publish' : 'all' ), array_keys( $status_links ), true );
			$status_links = array_merge( array_slice( $status_links, 0, $split ), $sticky_link, array_slice( $status_links, $split ) );
		}

		return $this->get_views_links( $status_links );
	}

  protected function get_primary_column_name() {
    return 'title';
  }

  function get_columns () {
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['title'] = _x( 'Title', 'column name' );
		$posts_columns['date'] = __( 'Date' );	

    return $posts_columns;
  }

  protected function get_sortable_columns() {
		$sortables = array(
      'title'    => array( 'title', false, __( 'Title' ), __( 'Table ordered by Title.' ) ),
      'date'     => array( 'date', true, __( 'Date' ), __( 'Table ordered by Date.' ), 'desc' ),
    );

		return $sortables;
	}

  protected function get_column_info() {
    $columns = $this->get_columns();
    $sortables = $this->get_sortable_columns();
    $primary = $this->get_primary_column_name();

    return $this->_column_headers = array( $columns, array(), $sortables, $primary);
  }

  public function column_default( $item, $column_name ) {
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

  public function get_query_args () {
		$args = array(
			'post_type' => $this->post_type
		);

		if (isset($_REQUEST['orderby'])) {
			$args['orderby'] = $_REQUEST['orderby'] ;
		}

		if (isset($_REQUEST['order'])) {
			$args['order'] = $_REQUEST['order'] ;
		}

		if (isset($_REQUEST['post_status'])) {
			$args['post_status'] = $_REQUEST['post_status'] ;
		}

		return $args;
	}

	public function get_wp_query () {
		$args = $this->get_query_args();
		$wp_query = new \WP_Query($args);

		return $wp_query;
	}

  public function prepare_items() {
		$this->wp_query = $this->get_wp_query();
    
		$avail_post_stati = wp_edit_posts_query();

		$this->set_hierarchical_display(
			is_post_type_hierarchical( $this->screen->post_type )
			&& 'menu_order title' === $this->wp_query->query['orderby']
		);

		$post_type = $this->screen->post_type;
		$per_page  = $this->get_items_per_page( 'edit_' . $post_type . '_per_page' );

		$per_page = apply_filters( 'edit_posts_per_page', $per_page, $post_type );

		if ( $this->hierarchical_display ) {
			$total_items = $this->wp_query->post_count;
		} elseif ( $this->wp_query->found_posts || $this->get_pagenum() === 1 ) {
			$total_items = $this->wp_query->found_posts;
		} else {
			$post_counts = (array) wp_count_posts( $post_type, 'readable' );

			if ( isset( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'], $avail_post_stati, true ) ) {
				$total_items = isset( $post_counts[ $_REQUEST['post_status'] ] ) ? $post_counts[ $_REQUEST['post_status'] ] : 0;
			} elseif ( isset( $_REQUEST['show_sticky'] ) && $_REQUEST['show_sticky'] ) {
				$total_items = $this->sticky_posts_count;
			} elseif ( isset( $_GET['author'] ) && get_current_user_id() === (int) $_GET['author'] ) {
				$total_items = $this->user_posts_count;
			} else {
				$total_items = array_sum( $post_counts );

				// Subtract post types that are not included in the admin all list.
				foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
					$total_items -= isset( $post_counts[ $state ] ) ? $post_counts[ $state ] : 0;
				}
			}
		}

		$this->is_trash = isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'];

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

    $this->items = $this->wp_query->posts;

    unset($this->wp_query);
	}

  public function no_items() {
		if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
			echo get_post_type_object( $this->screen->post_type )->labels->not_found_in_trash;
		} else {
			echo get_post_type_object( $this->screen->post_type )->labels->not_found;
		}
	}

	public function single_row ($post) {
		$post_owner = ( get_current_user_id() === (int) $post->post_author ) ? 'self' : 'other';
	?>
<tr id="post-<?php echo $post->ID; ?>"
	class="<?php echo trim( ' author-' . $post_owner . ' status-' . $post->post_status ); ?>">
	<?php $this->single_row_columns( $post ); ?>
</tr>
<?php
	}

	public function ajax_rows( $posts = array(), $level = 0 ) {
		add_filter( 'the_title', 'esc_html' );
		$this->_display_rows( $posts, $level );
	}

	/**
	 * @param array $posts
	 * @param int   $level
	 */
	private function _display_rows( $posts, $level = 0 ) {
		$post_type = $this->screen->post_type;

		// Create array of post IDs.
		$post_ids = array();

		foreach ( $posts as $a_post ) {
			$post_ids[] = $a_post->ID;
		}

		if ( post_type_supports( $post_type, 'comments' ) ) {
			$this->comment_pending_count = get_pending_comments_num( $post_ids );
		}
		update_post_author_caches( $posts );

		foreach ( $posts as $post ) {
			$this->single_row( $post );
		}
	}

	public function inline_edit() {
		global $mode, $post;

		$screen = $this->screen;

		$post             = get_default_post_to_edit( $screen->post_type );
		if ( ! ( $post instanceof \WP_Post ) ) {
			$post = get_default_post_to_edit( 'post' );
		}
		$GLOBALS['post'] = $post;
		$post_type_object = get_post_type_object( $screen->post_type );

		$taxonomy_names          = get_object_taxonomies( $screen->post_type );
		$hierarchical_taxonomies = array();
		$flat_taxonomies         = array();

		foreach ( $taxonomy_names as $taxonomy_name ) {
			$taxonomy = get_taxonomy( $taxonomy_name );

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
			if ( ! apply_filters( 'quick_edit_show_taxonomy', $show_in_quick_edit, $taxonomy_name, $screen->post_type ) ) {
				continue;
			}

			if ( $taxonomy->hierarchical ) {
				$hierarchical_taxonomies[] = $taxonomy;
			} else {
				$flat_taxonomies[] = $taxonomy;
			}
		}

		$m            = ( isset( $mode ) && 'excerpt' === $mode ) ? 'excerpt' : 'list';
		$can_publish  = current_user_can( $post_type_object->cap->publish_posts );
		$core_columns = array(
			'cb'         => true,
			'date'       => true,
			'title'      => true,
			'menu_order' => true,
			'categories' => true,
			'tags'       => true,
			'comments'   => true,
			'author'     => true,
		);
		?>

<form method="get">
	<table style="display: none">
		<tbody id="inlineedit">
			<?php
		$hclass              = count( $hierarchical_taxonomies ) ? 'post' : 'page';
		$inline_edit_classes = "inline-edit-row inline-edit-row-$hclass";
		$bulk_edit_classes   = "bulk-edit-row bulk-edit-row-$hclass bulk-edit-{$screen->post_type}";
		$quick_edit_classes  = "quick-edit-row quick-edit-row-$hclass inline-edit-{$screen->post_type}";

		$bulk = 0;

		while ( $bulk < 2 ) :
			$classes  = $inline_edit_classes . ' ';
			$classes .= $bulk ? $bulk_edit_classes : $quick_edit_classes;
			?>
			<tr id="<?php echo $bulk ? 'bulk-edit' : 'inline-edit'; ?>" class="<?php echo $classes; ?>"
				style="display: none">
				<td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">
					<div class="inline-edit-wrapper" role="region"
						aria-labelledby="<?php echo $bulk ? 'bulk' : 'quick'; ?>-edit-legend">
						<fieldset class="inline-edit-col-left">
							<legend class="inline-edit-legend" id="<?php echo $bulk ? 'bulk' : 'quick'; ?>-edit-legend">
								<?php echo $bulk ? __( 'Bulk Edit' ) : __( 'Quick Edit' ); ?>
							</legend>
							<div class="inline-edit-col">

								<?php if ( post_type_supports( $screen->post_type, 'title' ) ) : ?>

								<?php if ( $bulk ) : ?>

								<div id="bulk-title-div">
									<div id="bulk-titles"></div>
								</div>

								<?php else : // $bulk ?>

						<label>
							<span class="title"><?php _e( 'Title' ); ?></span>
								<span class="input-text-wrap"><input type="text" name="post_title" class="ptitle"
										value="" /></span>
								</label>

								<?php if ( is_post_type_viewable( $screen->post_type ) ) : ?>

								<label>
									<span class="title">
										<?php _e( 'Slug' ); ?>
									</span>
									<span class="input-text-wrap"><input type="text" name="post_name" value=""
											autocomplete="off" spellcheck="false" /></span>
								</label>

								<?php endif; // is_post_type_viewable() ?>

					<?php endif; // $bulk ?>

				<?php endif; // post_type_supports( ... 'title' ) ?>

				<?php if ( ! $bulk ) : ?>
								<fieldset class="inline-edit-date">
									<legend><span class="title">
											<?php _e( 'Date' ); ?>
										</span></legend>
									<?php touch_time( 1, 1, 0, 1 ); ?>
								</fieldset>
								<br class="clear" />
								<?php endif; // $bulk ?>

				<?php
				if ( post_type_supports( $screen->post_type, 'author' ) ) {
					$authors_dropdown = '';

					if ( current_user_can( $post_type_object->cap->edit_others_posts ) ) {
						$dropdown_name  = 'post_author';
						$dropdown_class = 'authors';
						if ( wp_is_large_user_count() ) {
							$authors_dropdown = sprintf( '<select name="%s" class="%s hidden"></select>', esc_attr( $dropdown_name ), esc_attr( $dropdown_class ) );
						} else {
							$users_opt = array(
								'hide_if_only_one_author' => false,
								'capability'              => array( $post_type_object->cap->edit_posts ),
								'name'                    => $dropdown_name,
								'class'                   => $dropdown_class,
								'multi'                   => 1,
								'echo'                    => 0,
								'show'                    => 'display_name_with_login',
							);

							if ( $bulk ) {
								$users_opt['show_option_none'] = __( '&mdash; No Change &mdash;' );
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
							$users_opt = apply_filters( 'quick_edit_dropdown_authors_args', $users_opt, $bulk );

							$authors = wp_dropdown_users( $users_opt );

							if ( $authors ) {
								$authors_dropdown  = '<label class="inline-edit-author">';
								$authors_dropdown .= '<span class="title">' . __( 'Author' ) . '</span>';
								$authors_dropdown .= $authors;
								$authors_dropdown .= '</label>';
							}
						}
					} // current_user_can( 'edit_others_posts' )

					if ( ! $bulk ) {
						echo $authors_dropdown;
					}
				} // post_type_supports( ... 'author' )
				?>

								<?php if ( ! $bulk && $can_publish ) : ?>

								<div class="inline-edit-group wp-clearfix">
									<label class="alignleft">
										<span class="title">
											<?php _e( 'Password' ); ?>
										</span>
										<span class="input-text-wrap"><input type="text" name="post_password"
												class="inline-edit-password-input" value="" /></span>
									</label>

									<span class="alignleft inline-edit-or">
										<?php
							/* translators: Between password field and private checkbox on post quick edit interface. */
							_e( '&ndash;OR&ndash;' );
							?>
									</span>
									<label class="alignleft inline-edit-private">
										<input type="checkbox" name="keep_private" value="private" />
										<span class="checkbox-title">
											<?php _e( 'Private' ); ?>
										</span>
									</label>
								</div>

								<?php endif; ?>

							</div>
						</fieldset>

						<?php if ( count( $hierarchical_taxonomies ) && ! $bulk ) : ?>

						<fieldset class="inline-edit-col-center inline-edit-categories">
							<div class="inline-edit-col">

								<?php foreach ( $hierarchical_taxonomies as $taxonomy ) : ?>

								<span class="title inline-edit-categories-label">
									<?php echo esc_html( $taxonomy->labels->name ); ?>
								</span>
								<input type="hidden"
									name="<?php echo ( 'category' === $taxonomy->name ) ? 'post_category[]' : 'tax_input[' . esc_attr( $taxonomy->name ) . '][]'; ?>"
									value="0" />
								<ul class="cat-checklist <?php echo esc_attr( $taxonomy->name ); ?>-checklist">
									<?php wp_terms_checklist( 0, array( 'taxonomy' => $taxonomy->name ) ); ?>
								</ul>

								<?php endforeach; // $hierarchical_taxonomies as $taxonomy ?>

					</div>
				</fieldset>

			<?php endif; // count( $hierarchical_taxonomies ) && ! $bulk ?>

			<fieldset class="inline-edit-col-right">
				<div class="inline-edit-col">

				<?php
				if ( post_type_supports( $screen->post_type, 'author' ) && $bulk ) {
					echo $authors_dropdown;
				}
				?>

								<?php if ( post_type_supports( $screen->post_type, 'page-attributes' ) ) : ?>

								<?php if ( $post_type_object->hierarchical ) : ?>

								<label>
									<span class="title">
										<?php _e( 'Parent' ); ?>
									</span>
									<?php
							$dropdown_args = array(
								'post_type'         => $post_type_object->name,
								'selected'          => $post->post_parent,
								'name'              => 'post_parent',
								'show_option_none'  => __( 'Main Page (no parent)' ),
								'option_none_value' => 0,
								'sort_column'       => 'menu_order, post_title',
							);

							if ( $bulk ) {
								$dropdown_args['show_option_no_change'] = __( '&mdash; No Change &mdash;' );
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
							$dropdown_args = apply_filters( 'quick_edit_dropdown_pages_args', $dropdown_args, $bulk );

							wp_dropdown_pages( $dropdown_args );
							?>
								</label>

								<?php endif; // hierarchical ?>

					<?php if ( ! $bulk ) : ?>

								<label>
									<span class="title">
										<?php _e( 'Order' ); ?>
									</span>
									<span class="input-text-wrap"><input type="text" name="menu_order"
											class="inline-edit-menu-order-input"
											value="<?php echo $post->menu_order; ?>" /></span>
								</label>

								<?php endif; // ! $bulk ?>

				<?php endif; // post_type_supports( ... 'page-attributes' ) ?>

				<?php if ( 0 < count( get_page_templates( null, $screen->post_type ) ) ) : ?>

								<label>
									<span class="title">
										<?php _e( 'Template' ); ?>
									</span>
									<select name="page_template">
										<?php if ( $bulk ) : ?>
										<option value="-1">
											<?php _e( '&mdash; No Change &mdash;' ); ?>
										</option>
										<?php endif; // $bulk ?>
							<?php
							/** This filter is documented in wp-admin/includes/meta-boxes.php */
							$default_title = apply_filters( 'default_page_template_title', __( 'Default template' ), 'quick-edit' );
							?>
										<option value="default">
											<?php echo esc_html( $default_title ); ?>
										</option>
										<?php page_template_dropdown( '', $screen->post_type ); ?>
									</select>
								</label>

								<?php endif; ?>

								<?php if ( count( $flat_taxonomies ) && ! $bulk ) : ?>

								<?php foreach ( $flat_taxonomies as $taxonomy ) : ?>

								<?php if ( current_user_can( $taxonomy->cap->assign_terms ) ) : ?>
								<?php $taxonomy_name = esc_attr( $taxonomy->name ); ?>
								<div class="inline-edit-tags-wrap">
									<label class="inline-edit-tags">
										<span class="title">
											<?php echo esc_html( $taxonomy->labels->name ); ?>
										</span>
										<textarea data-wp-taxonomy="<?php echo $taxonomy_name; ?>" cols="22" rows="1"
											name="tax_input[<?php echo esc_attr( $taxonomy->name ); ?>]"
											class="tax_input_<?php echo esc_attr( $taxonomy->name ); ?>"
											aria-describedby="inline-edit-<?php echo esc_attr( $taxonomy->name ); ?>-desc"></textarea>
									</label>
									<p class="howto" id="inline-edit-<?php echo esc_attr( $taxonomy->name ); ?>-desc">
										<?php echo esc_html( $taxonomy->labels->separate_items_with_commas ); ?>
									</p>
								</div>
								<?php endif; // current_user_can( 'assign_terms' ) ?>

					<?php endforeach; // $flat_taxonomies as $taxonomy ?>

				<?php endif; // count( $flat_taxonomies ) && ! $bulk ?>

				<?php if ( post_type_supports( $screen->post_type, 'comments' ) || post_type_supports( $screen->post_type, 'trackbacks' ) ) : ?>

								<?php if ( $bulk ) : ?>

								<div class="inline-edit-group wp-clearfix">

									<?php if ( post_type_supports( $screen->post_type, 'comments' ) ) : ?>

									<label class="alignleft">
										<span class="title">
											<?php _e( 'Comments' ); ?>
										</span>
										<select name="comment_status">
											<option value="">
												<?php _e( '&mdash; No Change &mdash;' ); ?>
											</option>
											<option value="open">
												<?php _e( 'Allow' ); ?>
											</option>
											<option value="closed">
												<?php _e( 'Do not allow' ); ?>
											</option>
										</select>
									</label>

									<?php endif; ?>

									<?php if ( post_type_supports( $screen->post_type, 'trackbacks' ) ) : ?>

									<label class="alignright">
										<span class="title">
											<?php _e( 'Pings' ); ?>
										</span>
										<select name="ping_status">
											<option value="">
												<?php _e( '&mdash; No Change &mdash;' ); ?>
											</option>
											<option value="open">
												<?php _e( 'Allow' ); ?>
											</option>
											<option value="closed">
												<?php _e( 'Do not allow' ); ?>
											</option>
										</select>
									</label>

									<?php endif; ?>

								</div>

								<?php else : // $bulk ?>

						<div class="inline-edit-group wp-clearfix">

						<?php if ( post_type_supports( $screen->post_type, 'comments' ) ) : ?>

								<label class="alignleft">
									<input type="checkbox" name="comment_status" value="open" />
									<span class="checkbox-title">
										<?php _e( 'Allow Comments' ); ?>
									</span>
								</label>

								<?php endif; ?>

								<?php if ( post_type_supports( $screen->post_type, 'trackbacks' ) ) : ?>

								<label class="alignleft">
									<input type="checkbox" name="ping_status" value="open" />
									<span class="checkbox-title">
										<?php _e( 'Allow Pings' ); ?>
									</span>
								</label>

								<?php endif; ?>

							</div>

							<?php endif; // $bulk ?>

				<?php endif; // post_type_supports( ... comments or pings ) ?>

					<div class="inline-edit-group wp-clearfix">

						<label class="inline-edit-status alignleft">
							<span class="title"><?php _e( 'Status' ); ?></span>
							<select name="_status">
								<?php if ( $bulk ) : ?>
								<option value="-1">
									<?php _e( '&mdash; No Change &mdash;' ); ?>
								</option>
								<?php endif; // $bulk ?>

								<?php if ( $can_publish ) : // Contributors only get "Unpublished" and "Pending Review". ?>
									<option value="publish"><?php _e( 'Published' ); ?>
								</option>
								<option value="future">
									<?php _e( 'Scheduled' ); ?>
								</option>
								<?php if ( $bulk ) : ?>
								<option value="private">
									<?php _e( 'Private' ); ?>
								</option>
								<?php endif; // $bulk ?>
								<?php endif; ?>

								<option value="pending">
									<?php _e( 'Pending Review' ); ?>
								</option>
								<option value="draft">
									<?php _e( 'Draft' ); ?>
								</option>
							</select>
							</label>

							<?php if ( 'post' === $screen->post_type && $can_publish && current_user_can( $post_type_object->cap->edit_others_posts ) ) : ?>

							<?php if ( $bulk ) : ?>

							<label class="alignright">
								<span class="title">
									<?php _e( 'Sticky' ); ?>
								</span>
								<select name="sticky">
									<option value="-1">
										<?php _e( '&mdash; No Change &mdash;' ); ?>
									</option>
									<option value="sticky">
										<?php _e( 'Sticky' ); ?>
									</option>
									<option value="unsticky">
										<?php _e( 'Not Sticky' ); ?>
									</option>
								</select>
							</label>

							<?php else : // $bulk ?>

								<label class="alignleft">
									<input type="checkbox" name="sticky" value="sticky" />
									<span class="checkbox-title"><?php _e( 'Make this post sticky' ); ?></span>
							</label>

							<?php endif; // $bulk ?>

						<?php endif; // 'post' && $can_publish && current_user_can( 'edit_others_posts' ) ?>

					</div>

				<?php if ( $bulk && current_theme_supports( 'post-formats' ) && post_type_supports( $screen->post_type, 'post-formats' ) ) : ?>
							<?php $post_formats = get_theme_support( 'post-formats' ); ?>

							<label class="alignleft">
								<span class="title">
									<?php _ex( 'Format', 'post format' ); ?>
								</span>
								<select name="post_format">
									<option value="-1">
										<?php _e( '&mdash; No Change &mdash;' ); ?>
									</option>
									<option value="0">
										<?php echo get_post_format_string( 'standard' ); ?>
									</option>
									<?php if ( is_array( $post_formats[0] ) ) : ?>
									<?php foreach ( $post_formats[0] as $format ) : ?>
									<option value="<?php echo esc_attr( $format ); ?>">
										<?php echo esc_html( get_post_format_string( $format ) ); ?>
									</option>
									<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</label>

							<?php endif; ?>

					</div>
					</fieldset>

					<?php
			list( $columns ) = $this->get_column_info();

			foreach ( $columns as $column_name => $column_display_name ) {
				if ( isset( $core_columns[ $column_name ] ) ) {
					continue;
				}

				if ( $bulk ) {

					/**
					 * Fires once for each column in Bulk Edit mode.
					 *
					 * @since 2.7.0
					 *
					 * @param string $column_name Name of the column to edit.
					 * @param string $post_type   The post type slug.
					 */
					do_action( 'bulk_edit_custom_box', $column_name, $screen->post_type );
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
					do_action( 'quick_edit_custom_box', $column_name, $screen->post_type, '' );
				}
			}
			?>

					<div class="submit inline-edit-save">
						<?php if ( ! $bulk ) : ?>
						<?php wp_nonce_field( 'inlineeditnonce', '_inline_edit', false ); ?>
						<button type="button" class="button button-primary save">
							<?php _e( 'Update' ); ?>
						</button>
						<?php else : ?>
						<?php submit_button( __( 'Update' ), 'primary', 'bulk_edit', false ); ?>
						<?php endif; ?>

						<button type="button" class="button cancel">
							<?php _e( 'Cancel' ); ?>
						</button>

						<?php if ( ! $bulk ) : ?>
						<span class="spinner"></span>
						<?php endif; ?>

						<input type="hidden" name="post_view" value="<?php echo esc_attr( $m ); ?>" />
						<input type="hidden" name="screen" value="<?php echo esc_attr( $screen->id ); ?>" />
						<?php if ( ! $bulk && ! post_type_supports( $screen->post_type, 'author' ) ) : ?>
						<input type="hidden" name="post_author" value="<?php echo esc_attr( $post->post_author ); ?>" />
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
