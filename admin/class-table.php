<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/** Reviews list (WP_List_Table): compact fixed columns, filters, search, bulk actions. */
class RCOM_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'review', 'plural' => 'reviews', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox">',
			'recipe'     => __( 'Recipe', 'recipe-community' ),
			'rating'     => __( 'Stars', 'recipe-community' ),
			'body'       => __( 'Review', 'recipe-community' ),
			'name'       => __( 'Name', 'recipe-community' ),
			'email'      => __( 'Email', 'recipe-community' ),
			'bbq_type'   => __( 'BBQ', 'recipe-community' ),
			'photo'      => __( 'Photo', 'recipe-community' ),
			'status'     => __( 'Status', 'recipe-community' ),
			'created_at' => __( 'Date', 'recipe-community' ),
		);
	}

	protected function get_sortable_columns() {
		return array( 'rating' => array( 'rating', false ), 'created_at' => array( 'created_at', true ), 'name' => array( 'name', false ) );
	}

	protected function get_bulk_actions() {
		return array(
			'hide'          => __( 'Hide', 'recipe-community' ),
			'show'          => __( 'Show', 'recipe-community' ),
			'approve_photo' => __( 'Approve photo', 'recipe-community' ),
			'delete'        => __( 'Delete', 'recipe-community' ),
		);
	}

	/** Filters and search on one row, inside a GET form rendered by the screen. */
	public function filters() {
		global $wpdb;
		$ids = $wpdb->get_col( 'SELECT DISTINCT post_id FROM ' . RCOM_Reviews::table() );
		$cur = array( 'post' => (int) ( $_GET['post_id'] ?? 0 ), 'rating' => (int) ( $_GET['rating'] ?? 0 ), 'status' => sanitize_key( $_GET['status'] ?? '' ), 'photo' => ! empty( $_GET['photo'] ) );
		echo '<div class="rcom-toolbar"><select name="post_id" aria-label="' . esc_attr__( 'Recipe', 'recipe-community' ) . '"><option value="0">' . esc_html__( 'All recipes', 'recipe-community' ) . '</option>';
		foreach ( $ids as $id ) {
			echo '<option value="' . (int) $id . '"' . selected( $cur['post'], (int) $id, false ) . '>' . esc_html( get_the_title( $id ) ?: '#' . $id ) . '</option>';
		}
		echo '</select><select name="rating" aria-label="' . esc_attr__( 'Stars', 'recipe-community' ) . '"><option value="0">' . esc_html__( 'All stars', 'recipe-community' ) . '</option>';
		for ( $i = 5; $i >= 1; $i-- ) {
			echo '<option value="' . $i . '"' . selected( $cur['rating'], $i, false ) . '>' . $i . ' ★</option>';
		}
		echo '</select><select name="status" aria-label="' . esc_attr__( 'Status', 'recipe-community' ) . '"><option value="">' . esc_html__( 'All statuses', 'recipe-community' ) . '</option><option value="published"' . selected( $cur['status'], 'published', false ) . '>' . esc_html__( 'Published', 'recipe-community' ) . '</option><option value="hidden"' . selected( $cur['status'], 'hidden', false ) . '>' . esc_html__( 'Hidden', 'recipe-community' ) . '</option><option value="orphan"' . selected( $cur['status'], 'orphan', false ) . '>' . esc_html__( 'Recipe deleted', 'recipe-community' ) . '</option></select>';
		echo '<label class="rcom-chk"><input type="checkbox" name="photo" value="1"' . checked( $cur['photo'], true, false ) . '> ' . esc_html__( 'Photo pending', 'recipe-community' ) . '</label>';
		echo '<input type="search" name="s" value="' . esc_attr( wp_unslash( $_GET['s'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Search reviews', 'recipe-community' ) . '" aria-label="' . esc_attr__( 'Search reviews', 'recipe-community' ) . '">';
		echo '<button class="rcom-btn-a primary">' . esc_html__( 'Filter', 'recipe-community' ) . '</button>';
		if ( array_filter( array( $cur['post'], $cur['rating'], $cur['status'], $cur['photo'], $_GET['s'] ?? '' ) ) ) {
			echo '<a class="rcom-btn-a" href="' . esc_url( admin_url( 'admin.php?page=rcom-reviews' ) ) . '">' . esc_html__( 'Reset', 'recipe-community' ) . '</a>';
		}
		echo '</div>';
	}

	public function prepare_items() {
		global $wpdb;
		$t = RCOM_Reviews::table();
		$w = array( '1=1' );
		$a = array();
		if ( ! empty( $_GET['post_id'] ) ) {
			$w[] = 'post_id = %d';
			$a[] = (int) $_GET['post_id'];
		}
		if ( ! empty( $_GET['rating'] ) ) {
			$w[] = 'rating = %d';
			$a[] = (int) $_GET['rating'];
		}
		if ( ! empty( $_GET['status'] ) && in_array( $_GET['status'], array( 'published', 'hidden', 'orphan' ), true ) ) {
			$w[] = 'status = %s';
			$a[] = $_GET['status'];
		}
		if ( ! empty( $_GET['photo'] ) ) {
			$w[] = "photo_status = 'pending'";
		}
		foreach ( array( 'has_text' => "body <> ''", 'has_photo' => "photo_file <> ''", 'verified' => 'verified = 1', 'optin' => 'newsletter_optin = 1' ) as $k => $cond ) {
			if ( ! empty( $_GET[ $k ] ) ) {
				$w[] = $cond;
			}
		}
		if ( ! empty( $_GET['days'] ) ) {
			$w[] = 'created_at >= %s';
			$a[] = gmdate( 'Y-m-d H:i:s', time() - (int) $_GET['days'] * DAY_IN_SECONDS );
		}
		if ( ! empty( $_GET['s'] ) ) {
			$like = '%' . $wpdb->esc_like( wp_unslash( $_GET['s'] ) ) . '%';
			$w[]  = '(body LIKE %s OR name LIKE %s OR email LIKE %s)';
			array_push( $a, $like, $like, $like );
		}
		$where = implode( ' AND ', $w );
		$ob    = in_array( $_GET['orderby'] ?? '', array( 'rating', 'created_at', 'name' ), true ) ? $_GET['orderby'] : 'created_at';
		$od    = 'asc' === strtolower( $_GET['order'] ?? '' ) ? 'ASC' : 'DESC';
		$per   = 20;
		$page  = $this->get_pagenum();
		$total = (int) $wpdb->get_var( $a ? $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$where}", $a ) : "SELECT COUNT(*) FROM {$t} WHERE {$where}" );
		$sql   = "SELECT * FROM {$t} WHERE {$where} ORDER BY {$ob} {$od}, id DESC LIMIT %d OFFSET %d";
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $a, array( $per, ( $page - 1 ) * $per ) ) ) );
		_prime_post_caches( array_unique( array_map( 'intval', wp_list_pluck( $this->items, 'post_id' ) ) ), false, false ); // one query instead of one per row
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/** Bulk actions left, count and pagination right, on one row. Only one nonce field. */
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );
		}
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		if ( $this->has_items() ) {
			echo '<div class="alignleft actions bulkactions">';
			$this->bulk_actions( $which );
			echo '</div>';
		}
		$this->pagination( $which );
		echo '</div>';
	}

	protected function column_cb( $r ) {
		return '<input type="checkbox" name="ids[]" value="' . (int) $r->id . '">';
	}

	protected function column_recipe( $r ) {
		$detail = add_query_arg( array( 'post' => $r->post_id ), admin_url( 'admin.php?page=rcom-recipes' ) ) . '#rcom-r' . (int) $r->id;
		$title  = get_the_title( $r->post_id ) ?: '#' . $r->post_id;
		$acts   = array(
			'edit'  => '<a href="' . esc_url( $detail ) . '">' . esc_html__( 'Edit', 'recipe-community' ) . '</a>',
			'reply' => '<a href="' . esc_url( $detail ) . '">' . esc_html__( 'Reply', 'recipe-community' ) . '</a>',
		);
		if ( 'pending' === $r->photo_status ) {
			$acts['photo'] = '<a href="' . esc_url( RCOM_Admin::action_url( 'approve_photo', $r->id ) ) . '">' . esc_html__( 'Approve photo', 'recipe-community' ) . '</a>';
		}
		$acts['toggle'] = '<a href="' . esc_url( RCOM_Admin::action_url( 'hidden' === $r->status ? 'show' : 'hide', $r->id ) ) . '">' . esc_html( 'hidden' === $r->status ? __( 'Show', 'recipe-community' ) : __( 'Hide', 'recipe-community' ) ) . '</a>';
		$acts['delete'] = '<a class="submitdelete" href="' . esc_url( RCOM_Admin::action_url( 'delete', $r->id ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this review?', 'recipe-community' ) ) . '\')">' . esc_html__( 'Delete', 'recipe-community' ) . '</a>';
		return '<a class="rcom-cut rcom-rt" href="' . esc_url( $detail ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $title ) . '</a>' . $this->row_actions( $acts );
	}

	protected function column_default( $r, $col ) {
		switch ( $col ) {
			case 'rating':
				return '<span class="rcom-a-stars" title="' . (int) $r->rating . ' / 5">' . str_repeat( '★', (int) $r->rating ) . '<i>' . str_repeat( '★', 5 - (int) $r->rating ) . '</i></span>';
			case 'body':
				return '<span class="rcom-clamp" title="' . esc_attr( $r->body ) . '">' . esc_html( $r->body ) . '</span>' . ( $r->reply_text ? ' <span class="rcom-muted" title="' . esc_attr__( 'Has a reply', 'recipe-community' ) . '">&#8617;</span>' : '' );
			case 'name':
				return '<span class="rcom-cut" title="' . esc_attr( $r->name ) . '">' . esc_html( $r->name ) . '</span>';
			case 'email':
				return '<span class="rcom-cut rcom-muted" title="' . esc_attr( $r->email ) . '">' . esc_html( $r->email ) . '</span>';
			case 'bbq_type':
				return esc_html( RCOM_Front::bbq_types()[ $r->bbq_type ][0] ?? $r->bbq_type );
			case 'photo':
				if ( ! $r->photo_file ) {
					return '<span class="rcom-muted">&ndash;</span>';
				}
				$u = esc_url( RCOM_Photo::url( $r->photo_file ) );
				return '<a class="rcom-th" href="' . $u . '" target="_blank" rel="noopener"><img src="' . esc_url( RCOM_Photo::thumb_url( $r->photo_file ) ) . '" width="40" height="40" loading="lazy" alt=""><img class="big" src="' . $u . '" loading="lazy" alt=""></a><span class="rcom-dot ' . ( 'pending' === $r->photo_status ? 'warn' : 'ok' ) . '" title="' . esc_attr( 'pending' === $r->photo_status ? __( 'Waiting', 'recipe-community' ) : __( 'Approved', 'recipe-community' ) ) . '"></span>';
			case 'status':
				$h = $r->verified ? '<span class="rcom-pill ok">' . esc_html__( 'Verified', 'recipe-community' ) . '</span>' : '<span class="rcom-pill">' . esc_html__( 'Unverified', 'recipe-community' ) . '</span>';
				return ( $r->is_demo ? '<span class="rcom-pill warn">' . esc_html__( 'Demo', 'recipe-community' ) . '</span> ' : '' ) . $h . ( 'hidden' === $r->status ? ' <span class="rcom-pill off">' . esc_html__( 'Hidden', 'recipe-community' ) . '</span>' : ( 'orphan' === $r->status ? ' <span class="rcom-pill off">' . esc_html__( 'Recipe deleted', 'recipe-community' ) . '</span>' : '' ) );
			case 'created_at':
				return '<span title="' . esc_attr( RCOM_Views::dt( $r->created_at, true ) ) . '">' . esc_html( RCOM_Views::dt( $r->created_at ) ) . '</span>';
		}
		return '';
	}

	public function no_items() {
		esc_html_e( 'No reviews found.', 'recipe-community' );
	}
}
