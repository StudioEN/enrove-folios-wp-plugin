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
				'folio_id' => $post_meta["folio_id"][0]
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

		if (isset($_REQUEST['folio_id'])) {
			$meta_value = isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : '';
	
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) 
					FROM $wpdb->posts AS p
					INNER JOIN $wpdb->postmeta AS pm ON p.ID = pm.post_id
					WHERE p.post_type = %s
					AND pm.meta_key = %s
					AND pm.meta_value = %s",
					$post_type,
					$meta_key,
					$meta_value
				)
			);

			return $count;
		}

		return 0;
	}

  protected function get_views() {
		$post_type = $this->screen->post_type;
    $avail_post_stati = get_available_post_statuses( $post_type );

		$status_links = array();
		$num_posts    = $this->count_posts();
		$total_posts  = array_sum( (array) $num_posts );
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
			$total_posts -= $num_posts->$state;
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

			if ( ! in_array( $status_name, $avail_post_stati, true ) || empty( $num_posts->$status_name ) ) {
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
				translate_nooped_plural( $status->label_count, $num_posts->$status_name ),
				number_format_i18n( $num_posts->$status_name )
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
      'parent'   => array( 'parent', false ),
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

    if ($column_name == 'title') {
      return $post->post_title;
    } else if ($column_name == 'date') {
      return $post->post_date;
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
				$total_items = $post_counts[ $_REQUEST['post_status'] ];
			} elseif ( isset( $_REQUEST['show_sticky'] ) && $_REQUEST['show_sticky'] ) {
				$total_items = $this->sticky_posts_count;
			} elseif ( isset( $_GET['author'] ) && get_current_user_id() === (int) $_GET['author'] ) {
				$total_items = $this->user_posts_count;
			} else {
				$total_items = array_sum( $post_counts );

				// Subtract post types that are not included in the admin all list.
				foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
					$total_items -= $post_counts[ $state ];
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
}
?>