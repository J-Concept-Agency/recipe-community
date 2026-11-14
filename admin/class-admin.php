<?php
defined( 'ABSPATH' ) || exit;

require_once RCOM_DIR . 'admin/class-views.php';

/** Admin: menu, router, actions, settings save, CSV export, recipe metabox. Everything needs manage_options + nonce. */
class RCOM_Admin {

	const SCREENS = array( 'rcom' => 'dashboard', 'rcom-reviews' => 'reviews', 'rcom-recipes' => 'recipes', 'rcom-helpful' => 'helpful', 'rcom-people' => 'people', 'rcom-settings' => 'settings', 'rcom-about' => 'about' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_rcom_repair', array( __CLASS__, 'repair' ) );
		add_action( 'admin_post_rcom_demo', array( __CLASS__, 'demo' ) );
		add_action( 'admin_init', array( 'RCOM_Integrity', 'schedule' ) );
		add_action( 'admin_post_rcom_export_fb', array( __CLASS__, 'export_feedback' ) );
		add_action( 'admin_post_rcom_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_rcom_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_metabox' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( RCOM_FILE ), function ( $l ) {
			array_unshift( $l, '<a href="' . esc_url( admin_url( 'admin.php?page=rcom-settings' ) ) . '">' . esc_html__( 'Settings', 'recipe-community' ) . '</a>' );
			return $l;
		} );
	}

	public static function menu() {
		$n     = RCOM_Reviews::pending_photos();
		$badge = $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . $n . '</span></span>' : '';
		add_menu_page( __( 'Community', 'recipe-community' ), __( 'Community', 'recipe-community' ) . $badge, 'manage_options', 'rcom', array( 'RCOM_Views', 'dashboard' ), 'dashicons-star-filled', 58 );
		$sub = array(
			'rcom'          => array( __( 'Dashboard', 'recipe-community' ), 'dashboard' ),
			'rcom-reviews'  => array( __( 'Reviews', 'recipe-community' ), 'reviews' ),
			'rcom-recipes'  => array( __( 'By recipe', 'recipe-community' ), 'recipes' ),
			'rcom-helpful'  => array( __( 'Helpful', 'recipe-community' ), 'helpful' ),
			'rcom-people'   => array( __( 'People', 'recipe-community' ), 'people' ),
			'rcom-settings' => array( __( 'Settings', 'recipe-community' ), 'settings' ),
			'rcom-about'    => array( __( 'About', 'recipe-community' ), 'about' ),
		);
		foreach ( $sub as $slug => $s ) {
			add_submenu_page( 'rcom', $s[0] . ' - ' . __( 'Community', 'recipe-community' ), $s[0], 'manage_options', $slug, array( 'RCOM_Views', $s[1] ) );
		}
	}

	public static function is_screen() {
		return isset( $_GET['page'] ) && isset( self::SCREENS[ sanitize_key( $_GET['page'] ) ] );
	}

	public static function assets() {
		if ( self::is_screen() ) {
			wp_enqueue_style( 'rcom-admin', RCOM_URL . 'admin/admin.css', array(), RCOM_VERSION );
		}
	}

	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_screen() ) {
			return;
		}
		$miss = RCOM_Integrity::missing();
		if ( $miss ) {
			echo '<div class="notice notice-error"><p><strong>Recipe Community:</strong> ' . esc_html( sprintf( /* translators: %s: table names */ __( 'database tables are missing (%s). Data in them cannot be recovered; the tables can be recreated empty.', 'recipe-community' ), implode( ', ', $miss ) ) ) . ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rcom_repair' ), 'rcom_repair' ) ) . '">' . esc_html__( 'Repair tables', 'recipe-community' ) . '</a></p></div>';
		}
		if ( ! RCOM_Settings::get( 'privacy_page' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Recipe Community:</strong> ' . esc_html__( 'no privacy page is set, so the privacy line under the review button is not shown.', 'recipe-community' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=rcom-settings' ) ) . '">' . esc_html__( 'Open settings', 'recipe-community' ) . '</a></p></div>';
		}
		if ( ! RCOM_Settings::get( 'terms_page' ) && ! RCOM_Settings::get( 'privacy_page' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Recipe Community:</strong> ' . esc_html__( 'no terms page is set. The review form asks for agreement but cannot link to the terms.', 'recipe-community' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=rcom-settings' ) ) . '">' . esc_html__( 'Open settings', 'recipe-community' ) . '</a></p></div>';
		}
		if ( RCOM_Settings::turnstile_ready() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Recipe Community:</strong> ' . esc_html__( 'the review form is hidden for visitors until you add Cloudflare Turnstile keys.', 'recipe-community' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=rcom-settings' ) ) . '">' . esc_html__( 'Open settings', 'recipe-community' ) . '</a></p></div>';
	}

	public static function demo() {
		self::guard();
		check_admin_referer( 'rcom_demo' );
		$n = 0;
		if ( 'clear' === ( $_POST['do'] ?? '' ) ) {
			$n = RCOM_Demo::clear();
		} else {
			$id = (int) ( $_POST['post'] ?? 0 );
			if ( $id && get_post( $id ) ) {
				$n = ( RCOM_Settings::enabled_for( $id ) ? RCOM_Demo::add( $id ) : 0 ) + ( RCOM_Settings::helpful_enabled_for( $id ) ? RCOM_Demo::add_votes( $id ) : 0 );
			}
		}
		wp_safe_redirect( add_query_arg( 'done', $n, admin_url( 'admin.php?page=rcom-about' ) ) );
		exit;
	}

	public static function repair() {
		self::guard();
		check_admin_referer( 'rcom_repair' );
		RCOM_Integrity::repair();
		wp_safe_redirect( admin_url( 'admin.php?page=rcom&done=1' ) );
		exit;
	}

	public static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'recipe-community' ), '', array( 'response' => 403 ) );
		}
	}

	/** Url of an admin action link (nonce included) that returns to $back afterwards. */
	public static function action_url( $do, $id, $back = '' ) {
		$back = $back ? $back : add_query_arg( array_map( 'sanitize_text_field', (array) wp_unslash( $_GET ) ), admin_url( 'admin.php' ) );
		return wp_nonce_url( add_query_arg( array( 'page' => 'rcom-reviews', 'rcom_do' => $do, 'id' => $id, 'rcom_back' => rawurlencode( remove_query_arg( array( 'done', '_wpnonce', 'rcom_do', 'id', 'rcom_back' ), $back ) ) ), admin_url( 'admin.php' ) ), 'rcom_admin' );
	}

	/** Row and bulk actions, then redirect (runs before output). */
	public static function handle() {
		if ( ! self::is_screen() ) {
			return;
		}
		self::guard();
		$do  = isset( $_REQUEST['rcom_do'] ) ? sanitize_key( $_REQUEST['rcom_do'] ) : '';
		$ids = array();
		if ( $do ) {
			check_admin_referer( 'rcom_admin' );
			$ids = array( (int) ( $_REQUEST['id'] ?? 0 ) );
		} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['ids'] ) ) {
			check_admin_referer( 'bulk-reviews' );
			$do  = sanitize_key( ( $_POST['action'] ?? '-1' ) !== '-1' ? $_POST['action'] : ( $_POST['action2'] ?? '' ) );
			$ids = array_map( 'intval', (array) $_POST['ids'] );
		}
		if ( 'revoke' === $do ) {
			$idn = isset( $_REQUEST['identity'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['identity'] ) ) : '';
			if ( preg_match( '/^[a-f0-9]{64}$/', $idn ) ) {
				RCOM_Account::revoke( $idn );
			}
			wp_safe_redirect( add_query_arg( 'done', 1, admin_url( 'admin.php?page=rcom-people' ) ) );
			exit;
		}
		if ( in_array( $do, array( 'fb_handled', 'fb_hide', 'fb_show', 'fb_delete' ), true ) ) {
			global $wpdb;
			$fid = (int) ( $_REQUEST['id'] ?? 0 );
			if ( 'fb_delete' === $do ) {
				$wpdb->delete( RCOM_Helpful::feedback(), array( 'id' => $fid ), array( '%d' ) );
			} else {
				$wpdb->update( RCOM_Helpful::feedback(), array( 'status' => array( 'fb_handled' => 'handled', 'fb_hide' => 'hidden', 'fb_show' => 'published' )[ $do ] ), array( 'id' => $fid ) );
			}
			$back = isset( $_REQUEST['rcom_back'] ) ? rawurldecode( wp_unslash( $_REQUEST['rcom_back'] ) ) : '';
			wp_safe_redirect( add_query_arg( 'done', 1, ( 0 === strpos( $back, admin_url( 'admin.php?page=rcom' ) ) ) ? $back : admin_url( 'admin.php?page=rcom-helpful' ) ) );
			exit;
		}
		if ( ! $do || ! $ids || ! in_array( $do, array( 'hide', 'show', 'approve_photo', 'delete_photo', 'delete', 'reply' ), true ) ) {
			return;
		}
		$n = 0;
		foreach ( $ids as $id ) {
			$r = RCOM_Reviews::get( $id );
			if ( ! $r ) {
				continue;
			}
			switch ( $do ) {
				case 'hide':
				case 'show':
					RCOM_Reviews::update( $id, array( 'status' => 'hide' === $do ? 'hidden' : 'published' ) );
					break;
				case 'approve_photo':
					if ( $r->photo_file ) {
						RCOM_Reviews::update( $id, array( 'photo_status' => 'approved' ) );
					}
					break;
				case 'delete_photo':
					RCOM_Photo::remove( $r->photo_file );
					RCOM_Reviews::update( $id, array( 'photo_file' => '', 'photo_status' => 'none' ) );
					break;
				case 'delete':
					RCOM_Reviews::delete( $id );
					break;
				case 'reply':
					$txt = sanitize_textarea_field( wp_unslash( $_POST['reply_text'] ?? '' ) );
					RCOM_Reviews::update( $id, array( 'reply_text' => $txt, 'reply_at' => '' === $txt ? null : current_time( 'mysql', true ) ) );
					break;
			}
			$n++;
		}
		$back = isset( $_REQUEST['rcom_back'] ) ? rawurldecode( wp_unslash( $_REQUEST['rcom_back'] ) ) : '';
		$back = ( 0 === strpos( $back, admin_url( 'admin.php?page=rcom' ) ) ) ? $back : admin_url( 'admin.php?page=rcom-reviews' );
		wp_safe_redirect( add_query_arg( 'done', $n, $back ) );
		exit;
	}

	/* ---------- settings ---------- */

	public static function save_settings() {
		self::guard();
		check_admin_referer( 'rcom_settings' );
		$old = RCOM_Settings::all();
		$str = static function ( $k, $d = '' ) {
			return isset( $_POST[ $k ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : $d;
		};
		$pts = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['post_types'] ?? array() ) ), array_keys( get_post_types( array( 'public' => true ) ) ) ) );
		$new = array(
			'turnstile_site'      => isset( $_POST['turnstile_site'] ) ? $str( 'turnstile_site' ) : $old['turnstile_site'],
			'turnstile_secret'    => '' !== $str( 'turnstile_secret' ) ? $str( 'turnstile_secret' ) : $old['turnstile_secret'],
			'reply_label'         => $str( 'reply_label' ),
			'mail_from'           => sanitize_email( wp_unslash( $_POST['mail_from'] ?? '' ) ),
			'post_types'          => $pts ? implode( ',', $pts ) : 'recept',
			'newsletter_provider' => isset( RCOM_Newsletter::providers()[ $_POST['newsletter_provider'] ?? '' ] ) ? sanitize_key( $_POST['newsletter_provider'] ) : 'none',
			'laposta_key'         => '' !== $str( 'laposta_key' ) ? $str( 'laposta_key' ) : $old['laposta_key'],
			'laposta_list'        => isset( $_POST['laposta_list'] ) ? $str( 'laposta_list' ) : $old['laposta_list'],
			'laposta_name_field'  => $str( 'laposta_name_field' ),
			'laposta_doi'         => 'laposta' === ( $_POST['laposta_doi'] ?? '' ) ? 'laposta' : 'ours',
			'laposta_quiet'       => empty( $_POST['laposta_quiet'] ) ? 0 : 1,
			'helpful_types'       => implode( ',', array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['helpful_types'] ?? array() ) ), array_keys( get_post_types( array( 'public' => true ) ) ) ) ) ),
			'helpful_threshold'   => max( 1, min( 1000, (int) ( $_POST['helpful_threshold'] ?? 5 ) ) ),
			'helpful_ts'          => empty( $_POST['helpful_ts'] ) ? 0 : 1,
			'helpful_links'       => empty( $_POST['helpful_links'] ) ? 0 : 1,
			'helpful_yes_label'   => $str( 'helpful_yes_label' ),
			'helpful_no_label'    => $str( 'helpful_no_label' ),
			'terms_page'          => (int) ( $_POST['terms_page'] ?? 0 ),
			'privacy_page'        => (int) ( $_POST['privacy_page'] ?? 0 ),
			'terms_label'         => $str( 'terms_label' ),
			'newsletter_label'    => $str( 'newsletter_label' ),
			'account_page'        => (int) ( $_POST['account_page'] ?? 0 ),
			'account_label'       => $str( 'account_label' ),
			'terms_snapshots'     => empty( $_POST['terms_snapshots'] ) ? 0 : 1,
			'on_delete'           => 'keep' === ( $_POST['on_delete'] ?? '' ) ? 'keep' : 'delete',
			'ai_abilities'        => empty( $_POST['ai_abilities'] ) ? 0 : 1,
			'show_credit'         => empty( $_POST['show_credit'] ) ? 0 : 1,
			'disable_updates'     => empty( $_POST['disable_updates'] ) ? 0 : 1,
			'keep_data'           => empty( $_POST['keep_data'] ) ? 0 : 1,
			'skin'                => isset( RCOM_Style::skins()[ $_POST['skin'] ?? '' ] ) ? sanitize_key( $_POST['skin'] ) : 'neutral',
			'extra_css'           => $old['extra_css'],
		);
		if ( isset( $_POST['extra_css'] ) ) {
			$css = RCOM_Style::clean( wp_unslash( $_POST['extra_css'] ) );
			if ( strlen( $css ) > RCOM_Style::MAX_CSS ) {
				$css_long = true; // keep the previous CSS rather than saving half of it
			} else {
				$new['extra_css'] = $css;
			}
		}
		if ( 'laposta' === $new['newsletter_provider'] && '' !== $new['laposta_list'] && '' === $new['laposta_name_field'] ) {
			update_option( 'rcom_settings', $new, false ); // so the API call below uses the new key
			$f                         = ( new RCOM_Laposta() )->fields( $new['laposta_list'] );
			$new['laposta_name_field'] = $f ? RCOM_Laposta::guess_name_field( $f ) : '';
		}
		$after = isset( $_POST['rcom_after'] ) ? sanitize_key( $_POST['rcom_after'] ) : '';
		if ( 'adopt' === $after && ( $e = self::existing_laposta() ) ) {
			$new['newsletter_provider'] = 'laposta';
			$new['laposta_key']         = $e['key'];
			$new['laposta_list']        = $e['list'];
		}
		if ( 'create_account' === $after ) {
			$r = self::create_account_page( sanitize_title( wp_unslash( $_POST['account_slug'] ?? '' ) ) ?: 'pitmaster', $str( 'account_title', 'Pitmaster' ) );
			if ( ! empty( $r['id'] ) ) {
				$new['account_page'] = (int) $r['id'];
			}
		}
		update_option( 'rcom_settings', $new, false );
		update_option( 'rcom_ai_on', $new['ai_abilities'] ? 1 : 0, true );
		update_option( 'rcom_account_pid', (int) $new['account_page'], true );
		delete_transient( 'rcom_update' );
		delete_transient( 'rcom_lp_lists' );
		$arg = array( 'done' => 1 );
		if ( ! empty( $css_long ) ) {
			$arg['css_long'] = 1;
		}
		if ( isset( $r ) && is_array( $r ) ) {
			$arg['acc'] = $r['status'];
			if ( ! empty( $r['suggest'] ) ) {
				$arg['acc_suggest'] = $r['suggest'];
			}
		}
		if ( 'rebuild' === $after ) {
			$arg['rebuilt'] = RCOM_Integrity::rebuild_cache();
		} elseif ( 'turnstile' === $after ) {
			$arg['ts'] = RCOM_Turnstile::test_keys();
		} elseif ( 'laposta' === $after || 'connect' === $after ) {
			$r = ( new RCOM_Laposta() )->test();
			set_transient( 'rcom_lp_msg', $r['message'], 60 );
			$arg['lp'] = $r['ok'] ? 'ok' : 'bad';
		}
		wp_safe_redirect( add_query_arg( $arg, admin_url( 'admin.php?page=rcom-settings' ) ) );
		exit;
	}

	/** Is a URL slug free? Checks posts of every type, terms of public taxonomies, post type archives and core paths. */
	public static function slug_free( $slug ) {
		global $wpdb;
		if ( in_array( $slug, array( 'wp-admin', 'wp-json', 'wp-login', 'wp-content', 'feed', 'embed', 'page', 'author', 'search', 'comments', 'category', 'tag', 'login', 'admin' ), true ) ) {
			return false;
		}
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status NOT IN ('trash','auto-draft','inherit') LIMIT 1", $slug ) ) ) {
			return false;
		}
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $t ) {
			if ( ( is_array( $t->rewrite ) && ( $t->rewrite['slug'] ?? '' ) === $slug ) || term_exists( $slug, $t->name ) ) {
				return false;
			}
		}
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( ( is_array( $pt->rewrite ) && ( $pt->rewrite['slug'] ?? '' ) === $slug ) || ( is_string( $pt->has_archive ) && $pt->has_archive === $slug ) ) {
				return false;
			}
		}
		return true;
	}

	/** @return array{status:string,id?:int,suggest?:string} */
	public static function create_account_page( $slug, $title ) {
		global $wpdb;
		$existing = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status IN ('publish','draft','private') AND post_content LIKE '%[rcom\\_account]%' ORDER BY ID LIMIT 1" );
		if ( $existing ) {
			return array( 'status' => 'existing', 'id' => $existing );
		}
		if ( ! self::slug_free( $slug ) ) {
			$i = 2;
			while ( ! self::slug_free( $slug . '-' . $i ) && $i < 50 ) {
				$i++;
			}
			return array( 'status' => 'conflict', 'suggest' => $slug . '-' . $i );
		}
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title ?: 'Pitmaster', 'post_name' => $slug, 'post_content' => '[rcom_account]' ), true );
		return is_wp_error( $id ) ? array( 'status' => 'error' ) : array( 'status' => 'created', 'id' => (int) $id );
	}

	/** Settings of an already configured Laposta connection on this site (Bricks Laposta plugin or constant), if readable. No hard dependency. */
	public static function existing_laposta() {
		$has = class_exists( 'Bricks_Laposta' ) && is_callable( array( 'Bricks_Laposta', 'get_api_key' ) );
		$key = $has ? (string) Bricks_Laposta::get_api_key() : '';
		if ( '' === $key ) {
			return null;
		}
		$s = ( $has && is_callable( array( 'Bricks_Laposta', 'get_settings' ) ) ) ? (array) Bricks_Laposta::get_settings() : array();
		return array( 'key' => $key, 'list' => (string) ( $s['list_id'] ?? '' ) );
	}

	/* ---------- recipe edit screen ---------- */

	public static function metabox() {
		foreach ( RCOM_Settings::post_types() as $pt ) {
			add_meta_box( 'rcom', __( 'Community reviews', 'recipe-community' ), array( __CLASS__, 'metabox_html' ), $pt, 'side', 'default' );
		}
	}

	public static function metabox_html( $post ) {
		$r = RCOM_Reviews::rating( $post->ID );
		wp_nonce_field( 'rcom_meta_' . $post->ID, 'rcom_meta' );
		echo '<p>';
		if ( $r['count'] ) {
			/* translators: 1: average rating, 2: number of reviews */
			echo '<strong>' . esc_html( number_format_i18n( $r['avg_all'], 1 ) ) . ' / 5</strong> &middot; ' . esc_html( sprintf( _n( '%s review', '%s reviews', $r['count'], 'recipe-community' ), number_format_i18n( $r['count'] ) ) ) . '<br><a href="' . esc_url( admin_url( 'admin.php?page=rcom-recipes&post=' . $post->ID ) ) . '">' . esc_html__( 'Open the reviews of this recipe', 'recipe-community' ) . '</a>';
		} else {
			esc_html_e( 'No reviews yet.', 'recipe-community' );
		}
		echo '</p><label><input type="checkbox" name="rcom_on" value="1"' . checked( ! get_post_meta( $post->ID, '_rcom_off', true ), true, false ) . '> ' . esc_html__( 'Reviews enabled for this recipe', 'recipe-community' ) . '</label>';
	}

	public static function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['rcom_meta'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rcom_meta'] ) ), 'rcom_meta_' . $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! in_array( $post->post_type, RCOM_Settings::post_types(), true ) ) {
			return;
		}
		if ( empty( $_POST['rcom_on'] ) ) {
			update_post_meta( $post_id, '_rcom_off', 1 );
		} else {
			delete_post_meta( $post_id, '_rcom_off' );
		}
	}

	public static function export_feedback() {
		self::guard();
		check_admin_referer( 'rcom_export_fb' );
		global $wpdb;
		$safe = static function ( $v ) {
			$v = (string) $v;
			return ( '' !== $v && false !== strpos( "=+-@\t\r", $v[0] ) ) ? "'" . $v : $v;
		};
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="helpful-feedback-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$o = fopen( 'php://output', 'w' );
		fwrite( $o, "\xEF\xBB\xBF" );
		fputcsv( $o, array( 'ID', 'Article', 'Vote', 'Feedback', 'Status', 'Date (UTC)' ), ';', '"', '' );
		foreach ( $wpdb->get_results( 'SELECT f.*, v.vote FROM ' . RCOM_Helpful::feedback() . ' f LEFT JOIN ' . RCOM_Helpful::votes() . ' v ON v.id = f.vote_id ORDER BY f.id' ) as $r ) {
			fputcsv( $o, array_map( $safe, array( $r->id, get_the_title( $r->post_id ), $r->vote, $r->body, $r->status, $r->created_at ) ), ';', '"', '' );
		}
		fclose( $o );
		exit;
	}

	/** CSV for Excel (NL): semicolon separated, UTF-8 BOM, formula injection neutralised. */
	public static function export() {
		self::guard();
		check_admin_referer( 'rcom_export' );
		global $wpdb;
		$safe = static function ( $v ) {
			$v = (string) $v;
			return ( '' !== $v && false !== strpos( "=+-@\t\r", $v[0] ) ) ? "'" . $v : $v;
		};
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="recipe-reviews-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$o = fopen( 'php://output', 'w' );
		fwrite( $o, "\xEF\xBB\xBF" );
		fputcsv( $o, array( 'ID', 'Recipe', 'Stars', 'Review', 'Name', 'Email', 'BBQ', 'Photo status', 'Verified', 'Newsletter (confirmed)', 'Status', 'Reply', 'Date (UTC)' ), ';', '"', '' );
		foreach ( $wpdb->get_results( 'SELECT * FROM ' . RCOM_Reviews::table() . ' WHERE is_demo = 0 ORDER BY id' ) as $r ) {
			fputcsv( $o, array_map( $safe, array( $r->id, get_the_title( $r->post_id ), $r->rating, $r->body, $r->name, $r->email, $r->bbq_type, $r->photo_status, $r->verified, ( $r->newsletter_optin && $r->verified ) ? 1 : 0, $r->status, $r->reply_text, $r->created_at ) ), ';', '"', '' );
		}
		fclose( $o );
		exit;
	}
}
