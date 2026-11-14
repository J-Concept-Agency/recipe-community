<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/** Reviews list (WP_List_Table): filters, search, sorting, bulk actions. */
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
			'verified'   => __( 'Verified', 'recipe-community' ),
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

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		global $wpdb;
		$ids = $wpdb->get_col( 'SELECT DISTINCT post_id FROM ' . RCOM_Reviews::table() );
		$cur = array( 'post' => (int) ( $_GET['post_id'] ?? 0 ), 'rating' => (int) ( $_GET['rating'] ?? 0 ), 'status' => sanitize_key( $_GET['status'] ?? '' ), 'photo' => ! empty( $_GET['photo'] ) );
		echo '<div class="alignleft actions"><select name="post_id"><option value="0">' . esc_html__( 'All recipes', 'recipe-community' ) . '</option>';
		foreach ( $ids as $id ) {
			echo '<option value="' . (int) $id . '"' . selected( $cur['post'], (int) $id, false ) . '>' . esc_html( get_the_title( $id ) ?: '#' . $id ) . '</option>';
		}
		echo '</select><select name="rating"><option value="0">' . esc_html__( 'All stars', 'recipe-community' ) . '</option>';
		for ( $i = 5; $i >= 1; $i-- ) {
			echo '<option value="' . $i . '"' . selected( $cur['rating'], $i, false ) . '>' . $i . ' ★</option>';
		}
		echo '</select><select name="status"><option value="">' . esc_html__( 'All statuses', 'recipe-community' ) . '</option><option value="published"' . selected( $cur['status'], 'published', false ) . '>' . esc_html__( 'Published', 'recipe-community' ) . '</option><option value="hidden"' . selected( $cur['status'], 'hidden', false ) . '>' . esc_html__( 'Hidden', 'recipe-community' ) . '</option></select>';
		echo '<label><input type="checkbox" name="photo" value="1"' . checked( $cur['photo'], true, false ) . '> ' . esc_html__( 'Photo pending', 'recipe-community' ) . '</label> ';
		submit_button( __( 'Filter', 'recipe-community' ), '', 'filter_action', false );
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
		if ( ! empty( $_GET['status'] ) && in_array( $_GET['status'], array( 'published', 'hidden' ), true ) ) {
			$w[] = 'status = %s';
			$a[] = $_GET['status'];
		}
		if ( ! empty( $_GET['photo'] ) ) {
			$w[] = "photo_status = 'pending'";
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
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	protected function column_cb( $r ) {
		return '<input type="checkbox" name="ids[]" value="' . (int) $r->id . '">';
	}

	protected function column_recipe( $r ) {
		$base = admin_url( 'admin.php?page=rcom' );
		$act  = function ( $do ) use ( $r, $base ) {
			return wp_nonce_url( add_query_arg( array( 'rcom_do' => $do, 'id' => $r->id ), $base ), 'rcom_admin' );
		};
		$acts = array(
			'edit'   => '<a href="' . esc_url( add_query_arg( array( 'view' => 'edit', 'id' => $r->id ), $base ) ) . '">' . esc_html__( 'Reply / edit', 'recipe-community' ) . '</a>',
			'toggle' => '<a href="' . esc_url( $act( 'hidden' === $r->status ? 'show' : 'hide' ) ) . '">' . esc_html( 'hidden' === $r->status ? __( 'Show', 'recipe-community' ) : __( 'Hide', 'recipe-community' ) ) . '</a>',
			'delete' => '<a href="' . esc_url( $act( 'delete' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this review?', 'recipe-community' ) ) . '\')">' . esc_html__( 'Delete', 'recipe-community' ) . '</a>',
		);
		return '<strong><a href="' . esc_url( get_permalink( $r->post_id ) ) . '">' . esc_html( get_the_title( $r->post_id ) ?: '#' . $r->post_id ) . '</a></strong>' . ( 'hidden' === $r->status ? ' <em>(' . esc_html__( 'hidden', 'recipe-community' ) . ')</em>' : '' ) . $this->row_actions( $acts );
	}

	protected function column_default( $r, $col ) {
		switch ( $col ) {
			case 'rating':
				return '<span style="color:#d97706;white-space:nowrap">' . str_repeat( '★', (int) $r->rating ) . '<span style="color:#ccc">' . str_repeat( '★', 5 - (int) $r->rating ) . '</span></span>';
			case 'body':
				$b = mb_strlen( $r->body ) > 90 ? mb_substr( $r->body, 0, 90 ) . '…' : $r->body;
				return esc_html( $b ) . ( $r->reply_text ? ' <span class="dashicons dashicons-format-chat" title="' . esc_attr__( 'Has a reply', 'recipe-community' ) . '"></span>' : '' );
			case 'name':
			case 'email':
				return esc_html( $r->$col );
			case 'bbq_type':
				return esc_html( RCOM_Front::bbq_types()[ $r->bbq_type ][0] ?? $r->bbq_type );
			case 'photo':
				if ( ! $r->photo_file ) {
					return '&ndash;';
				}
				$s = array( 'pending' => __( 'Pending', 'recipe-community' ), 'approved' => __( 'Approved', 'recipe-community' ) );
				return '<img src="' . esc_url( RCOM_Photo::url( $r->photo_file ) ) . '" width="48" height="48" style="object-fit:cover;vertical-align:middle;border-radius:3px" loading="lazy" alt=""> ' . esc_html( $s[ $r->photo_status ] ?? '' );
			case 'verified':
				return $r->verified ? '<span class="dashicons dashicons-yes-alt" style="color:#00a32a" title="' . esc_attr__( 'Verified', 'recipe-community' ) . '"></span>' : '&ndash;';
			case 'created_at':
				return esc_html( get_date_from_gmt( $r->created_at, get_option( 'date_format' ) . ' H:i' ) );
		}
		return '';
	}

	public function no_items() {
		esc_html_e( 'No reviews found.', 'recipe-community' );
	}
}
