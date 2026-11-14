<?php
defined( 'ABSPATH' ) || exit;

/** Admin screens: reviews list, edit/reply, people, settings, CSV export. Everything needs manage_options + nonce. */
class RCOM_Admin {

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_post_rcom_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_rcom_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_rcom_test_turnstile', array( __CLASS__, 'test_turnstile' ) );
		add_action( 'admin_post_rcom_test_laposta', array( __CLASS__, 'test_laposta' ) );
		add_action( 'admin_post_rcom_adopt_laposta', array( __CLASS__, 'adopt_laposta' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RCOM_FILE ), function ( $l ) {
			array_unshift( $l, '<a href="' . esc_url( admin_url( 'admin.php?page=rcom&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'recipe-community' ) . '</a>' );
			return $l;
		} );
	}

	public static function menu() {
		$n     = RCOM_Reviews::pending_photos();
		$badge = $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . $n . '</span></span>' : '';
		self::$hook = add_menu_page( __( 'Recipe community', 'recipe-community' ), __( 'Recipe community', 'recipe-community' ) . $badge, 'manage_options', 'rcom', array( __CLASS__, 'render' ), 'dashicons-star-filled', 58 );
		add_action( 'load-' . self::$hook, array( __CLASS__, 'handle' ) );
	}

	public static function notices() {
		$s = get_current_screen();
		if ( ! current_user_can( 'manage_options' ) || RCOM_Settings::turnstile_ready() || ! $s || ! in_array( $s->id, array( 'toplevel_page_rcom', 'plugins', 'dashboard' ), true ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Recipe Community:</strong> ' . esc_html__( 'the review form is hidden for visitors until you add Cloudflare Turnstile keys.', 'recipe-community' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=rcom&tab=settings' ) ) . '">' . esc_html__( 'Open settings', 'recipe-community' ) . '</a></p></div>';
	}

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'recipe-community' ), '', array( 'response' => 403 ) );
		}
	}

	/** Row and bulk actions, then redirect (runs before output). */
	public static function handle() {
		self::guard();
		$back = admin_url( 'admin.php?page=rcom' );
		$do   = isset( $_REQUEST['rcom_do'] ) ? sanitize_key( $_REQUEST['rcom_do'] ) : '';
		$ids  = array();
		if ( $do ) {
			check_admin_referer( 'rcom_admin' );
			$ids = array( (int) ( $_REQUEST['id'] ?? 0 ) );
		} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['ids'] ) ) {
			check_admin_referer( 'bulk-reviews' );
			$do  = sanitize_key( ( $_POST['action'] ?? '-1' ) !== '-1' ? $_POST['action'] : ( $_POST['action2'] ?? '' ) );
			$ids = array_map( 'intval', (array) $_POST['ids'] );
		}
		if ( ! $do || ! $ids ) {
			return;
		}
		$ret = 0;
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
					$back = add_query_arg( array( 'view' => 'edit', 'id' => $id ), $back );
					break;
				case 'delete':
					RCOM_Reviews::delete( $id );
					break;
				case 'reply':
					$txt = sanitize_textarea_field( wp_unslash( $_POST['reply_text'] ?? '' ) );
					RCOM_Reviews::update( $id, array( 'reply_text' => $txt, 'reply_at' => '' === $txt ? null : current_time( 'mysql', true ) ) );
					$back = add_query_arg( array( 'view' => 'edit', 'id' => $id ), $back );
					break;
			}
			$ret++;
		}
		wp_safe_redirect( add_query_arg( 'done', $ret, $back ) );
		exit;
	}

	public static function render() {
		self::guard();
		$tab = sanitize_key( $_GET['tab'] ?? 'reviews' );
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Recipe community', 'recipe-community' ) . '</h1> ';
		echo '<a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rcom_export' ), 'rcom_export' ) ) . '">' . esc_html__( 'Export CSV', 'recipe-community' ) . '</a><hr class="wp-header-end">';
		if ( isset( $_GET['done'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Done.', 'recipe-community' ) . '</p></div>';
		}
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( array( 'reviews' => __( 'Reviews', 'recipe-community' ), 'people' => __( 'People', 'recipe-community' ), 'settings' => __( 'Settings', 'recipe-community' ) ) as $k => $l ) {
			echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=rcom&tab=' . $k ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</h2>';
		if ( 'settings' === $tab ) {
			self::settings();
		} elseif ( 'people' === $tab ) {
			self::people();
		} elseif ( 'edit' === ( $_GET['view'] ?? '' ) ) {
			self::edit( (int) ( $_GET['id'] ?? 0 ) );
		} else {
			require_once RCOM_DIR . 'admin/class-table.php';
			$t = new RCOM_Table();
			$t->prepare_items();
			echo '<form method="get"><input type="hidden" name="page" value="rcom">';
			$t->search_box( __( 'Search reviews', 'recipe-community' ), 'rcom' );
			echo '</form><form method="post" action="' . esc_url( admin_url( 'admin.php?page=rcom' ) ) . '">';
			$t->display();
			echo '</form>';
		}
		echo '</div>';
	}

	private static function edit( $id ) {
		$r = RCOM_Reviews::get( $id );
		if ( ! $r ) {
			echo '<p>' . esc_html__( 'Review not found.', 'recipe-community' ) . '</p>';
			return;
		}
		$base = admin_url( 'admin.php?page=rcom' );
		$act  = function ( $do ) use ( $id, $base ) {
			return wp_nonce_url( add_query_arg( array( 'rcom_do' => $do, 'id' => $id ), $base ), 'rcom_admin' );
		};
		echo '<p><a href="' . esc_url( $base ) . '">&larr; ' . esc_html__( 'All reviews', 'recipe-community' ) . '</a></p>';
		echo '<table class="form-table"><tr><th>' . esc_html__( 'Recipe', 'recipe-community' ) . '</th><td><a href="' . esc_url( get_permalink( $r->post_id ) ) . '">' . esc_html( get_the_title( $r->post_id ) ) . '</a></td></tr>';
		echo '<tr><th>' . esc_html__( 'Stars', 'recipe-community' ) . '</th><td>' . (int) $r->rating . ' / 5</td></tr>';
		echo '<tr><th>' . esc_html__( 'Name', 'recipe-community' ) . '</th><td>' . esc_html( $r->name ) . ' &lt;' . esc_html( $r->email ) . '&gt;' . ( $r->verified ? ' <span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span>' : '' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Review', 'recipe-community' ) . '</th><td>' . nl2br( esc_html( $r->body ) ) . '</td></tr>';
		if ( $r->photo_file ) {
			echo '<tr><th>' . esc_html__( 'Photo', 'recipe-community' ) . '</th><td><img src="' . esc_url( RCOM_Photo::url( $r->photo_file ) ) . '" style="max-width:320px;height:auto"><br>';
			if ( 'approved' !== $r->photo_status ) {
				echo '<a class="button button-primary" href="' . esc_url( $act( 'approve_photo' ) ) . '">' . esc_html__( 'Approve photo', 'recipe-community' ) . '</a> ';
			}
			echo '<a class="button" href="' . esc_url( $act( 'delete_photo' ) ) . '">' . esc_html__( 'Delete photo', 'recipe-community' ) . '</a></td></tr>';
		}
		echo '</table><form method="post" action="' . esc_url( $base ) . '">';
		wp_nonce_field( 'rcom_admin' );
		echo '<input type="hidden" name="rcom_do" value="reply"><input type="hidden" name="id" value="' . (int) $id . '"><h2>' . esc_html__( 'Reply', 'recipe-community' ) . '</h2><textarea name="reply_text" rows="5" class="large-text" maxlength="1500">' . esc_textarea( $r->reply_text ) . '</textarea><p class="description">' . esc_html__( 'Shown under the review. Leave empty to remove the reply.', 'recipe-community' ) . '</p>';
		submit_button( __( 'Save reply', 'recipe-community' ), 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( $act( 'hidden' === $r->status ? 'show' : 'hide' ) ) . '">' . esc_html( 'hidden' === $r->status ? __( 'Show', 'recipe-community' ) : __( 'Hide', 'recipe-community' ) ) . '</a> <a class="button" style="color:#b32d2e" href="' . esc_url( $act( 'delete' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this review?', 'recipe-community' ) ) . '\')">' . esc_html__( 'Delete review', 'recipe-community' ) . '</a></form>';
	}

	private static function people() {
		global $wpdb;
		$per  = 50;
		$page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$t    = RCOM_Reviews::table();
		$tot  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT email) FROM {$t}" );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT email, MAX(name) AS name, COUNT(*) AS n, MAX(created_at) AS last, MAX(newsletter_optin AND verified) AS nl FROM {$t} GROUP BY email ORDER BY last DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) );
		echo '<table class="widefat striped" style="margin-top:1em"><thead><tr><th>' . esc_html__( 'Email', 'recipe-community' ) . '</th><th>' . esc_html__( 'Name', 'recipe-community' ) . '</th><th>' . esc_html__( 'Reviews', 'recipe-community' ) . '</th><th>' . esc_html__( 'Newsletter (confirmed)', 'recipe-community' ) . '</th><th>' . esc_html__( 'Last review', 'recipe-community' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r->email ) . '</td><td>' . esc_html( $r->name ) . '</td><td>' . (int) $r->n . '</td><td>' . ( $r->nl ? esc_html__( 'Yes', 'recipe-community' ) : esc_html__( 'No', 'recipe-community' ) ) . '</td><td>' . esc_html( get_date_from_gmt( $r->last, get_option( 'date_format' ) ) ) . '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No people yet.', 'recipe-community' ) . '</td></tr>';
		}
		echo '</tbody></table><div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => max( 1, (int) ceil( $tot / $per ) ) ) ) ) . '</div></div>';
	}

	private static function settings() {
		$o   = RCOM_Settings::all();
		$row = function ( $label, $html, $desc = '' ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . ( $desc ? '<p class="description">' . wp_kses_post( $desc ) . '</p>' : '' ) . '</td></tr>';
		};
		if ( isset( $_GET['lp'] ) ) {
			$ok = 'ok' === $_GET['lp'];
			echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . '"><p>' . esc_html( get_transient( 'rcom_lp_msg' ) ?: '' ) . '</p></div>';
		}
		if ( isset( $_GET['ts'] ) ) {
			$m = array(
				'ok'   => array( 'success', __( 'Your secret key is accepted by Cloudflare. (The site key cannot be checked from the server: test the form on the site.)', 'recipe-community' ) ),
				'bad'  => array( 'error', __( 'Cloudflare rejected the secret key. Check that you copied it completely.', 'recipe-community' ) ),
				'none' => array( 'warning', __( 'Enter and save both keys first.', 'recipe-community' ) ),
				'net'  => array( 'warning', __( 'Could not reach Cloudflare. Try again later.', 'recipe-community' ) ),
			)[ sanitize_key( $_GET['ts'] ) ] ?? null;
			if ( $m ) {
				echo '<div class="notice notice-' . esc_attr( $m[0] ) . '"><p>' . esc_html( $m[1] ) . '</p></div>';
			}
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="rcom_settings">';
		wp_nonce_field( 'rcom_settings' );
		echo '<h2>' . esc_html__( 'Spam protection (Cloudflare Turnstile)', 'recipe-community' ) . '</h2><table class="form-table">';
		$cs = RCOM_Settings::from_constant( 'site' );
		$cx = RCOM_Settings::from_constant( 'secret' );
		$row( __( 'Site key', 'recipe-community' ), $cs ? '<code>' . esc_html__( 'Set in wp-config.php (RCOM_TURNSTILE_SITE_KEY)', 'recipe-community' ) . '</code>' : '<input name="turnstile_site" class="regular-text" value="' . esc_attr( $o['turnstile_site'] ) . '">', __( 'Public key from the Cloudflare dashboard. Without both keys the review form is hidden for visitors.', 'recipe-community' ) );
		$row( __( 'Secret key', 'recipe-community' ), $cx ? '<code>' . esc_html__( 'Set in wp-config.php (RCOM_TURNSTILE_SECRET_KEY)', 'recipe-community' ) . '</code>' : '<input name="turnstile_secret" type="password" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr( $o['turnstile_secret'] ? '••••••••••••••••' : '' ) . '">', __( 'Leave empty to keep the saved key. Constants in wp-config.php take precedence over these fields.', 'recipe-community' ) );
		echo '</table><h2>' . esc_html__( 'Newsletter', 'recipe-community' ) . '</h2><table class="form-table">';
		$opts = '';
		foreach ( RCOM_Newsletter::providers() as $id => $pv ) {
			$opts .= '<option value="' . esc_attr( $id ) . '"' . selected( $o['newsletter_provider'], $id, false ) . '>' . esc_html( $pv['label'] ) . '</option>';
		}
		$row( __( 'Service', 'recipe-community' ), '<select name="newsletter_provider">' . $opts . '</select>', __( 'Only visitors who tick the newsletter box and confirm their email address are sent to the service.', 'recipe-community' ) );
		$ck = defined( 'RCOM_LAPOSTA_API_KEY' );
		$row( __( 'Laposta API key', 'recipe-community' ), $ck ? '<code>' . esc_html__( 'Set in wp-config.php (RCOM_LAPOSTA_API_KEY)', 'recipe-community' ) . '</code>' : '<input name="laposta_key" type="password" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr( $o['laposta_key'] ? '••••••••••••••••' : '' ) . '">', __( 'Leave empty to keep the saved key.', 'recipe-community' ) );
		$lists = ( 'laposta' === $o['newsletter_provider'] && RCOM_Laposta::key() ) ? ( new RCOM_Laposta() )->lists() : null;
		if ( $lists ) {
			$lo = '<option value="">&mdash;</option>';
			foreach ( $lists as $lid => $ln ) {
				$lo .= '<option value="' . esc_attr( $lid ) . '"' . selected( $o['laposta_list'], $lid, false ) . '>' . esc_html( $ln ) . '</option>';
			}
			$row( __( 'Laposta list', 'recipe-community' ), '<select name="laposta_list">' . $lo . '</select>' );
		} else {
			$row( __( 'Laposta list ID', 'recipe-community' ), '<input name="laposta_list" class="regular-text" value="' . esc_attr( $o['laposta_list'] ) . '">', __( 'Save a valid API key to pick from your lists.', 'recipe-community' ) );
		}
		$row( __( 'Field for the first name', 'recipe-community' ), '<input name="laposta_name_field" class="regular-text" value="' . esc_attr( $o['laposta_name_field'] ) . '">', __( 'Optional: the name of the Laposta custom field that receives the first name.', 'recipe-community' ) );
		$row( __( 'Confirmation', 'recipe-community' ), '<select name="laposta_doi"><option value="ours"' . selected( $o['laposta_doi'], 'ours', false ) . '>' . esc_html__( 'Our email link confirms it (no second email)', 'recipe-community' ) . '</option><option value="laposta"' . selected( $o['laposta_doi'], 'laposta', false ) . '>' . esc_html__( 'Laposta sends its own confirmation email', 'recipe-community' ) . '</option></select>', __( 'The review email always asks the visitor to confirm. The first choice reuses that confirmation, so the visitor gets one email.', 'recipe-community' ) );
		echo '</table><h2>' . esc_html__( 'General', 'recipe-community' ) . '</h2><table class="form-table">';
		$row( __( 'Label for your reply', 'recipe-community' ), '<input name="reply_label" class="regular-text" value="' . esc_attr( $o['reply_label'] ) . '" placeholder="' . esc_attr__( 'Reply from the author', 'recipe-community' ) . '">' );
		$row( __( 'Sender email for mails', 'recipe-community' ), '<input name="mail_from" type="email" class="regular-text" value="' . esc_attr( $o['mail_from'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '">', __( 'Default: the WordPress admin address.', 'recipe-community' ) );
		$row( __( 'Post types', 'recipe-community' ), '<input name="post_types" class="regular-text" value="' . esc_attr( $o['post_types'] ) . '">', __( 'Comma separated slugs of the post types that get reviews.', 'recipe-community' ) );
		$row( __( 'Credit link', 'recipe-community' ), '<label><input type="checkbox" name="show_credit" value="1"' . checked( $o['show_credit'], 1, false ) . '> ' . esc_html__( 'Show a "Powered by J-Concept" link below the reviews', 'recipe-community' ) . '</label>' );
		$row( __( 'Updates', 'recipe-community' ), '<label><input type="checkbox" name="disable_updates" value="1"' . checked( $o['disable_updates'], 1, false ) . '> ' . esc_html__( 'Do not check GitHub for plugin updates', 'recipe-community' ) . '</label>' );
		$row( __( 'Keep data on uninstall', 'recipe-community' ), '<label><input type="checkbox" name="keep_data" value="1"' . checked( $o['keep_data'], 1, false ) . '> ' . esc_html__( 'Do not delete reviews and photos when the plugin is deleted', 'recipe-community' ) . '</label>' );
		echo '</table>';
		submit_button();
		echo '</form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:1em"><input type="hidden" name="action" value="rcom_test_turnstile">';
		wp_nonce_field( 'rcom_test_turnstile' );
		submit_button( __( 'Test my keys', 'recipe-community' ), 'secondary', 'submit', false );
		echo '</form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:1em"><input type="hidden" name="action" value="rcom_test_laposta">';
		wp_nonce_field( 'rcom_test_laposta' );
		submit_button( __( 'Test Laposta connection', 'recipe-community' ), 'secondary', 'submit', false );
		echo '</form>';
		if ( self::existing_laposta() ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block"><input type="hidden" name="action" value="rcom_adopt_laposta">';
			wp_nonce_field( 'rcom_adopt_laposta' );
			submit_button( __( 'Use existing Laposta settings', 'recipe-community' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '<hr><p>' . sprintf(
			/* translators: 1: plugin version, 2: link to the author */
			esc_html__( 'Recipe Community %1$s by %2$s', 'recipe-community' ),
			esc_html( RCOM_VERSION ),
			'<a href="https://jconcept.nl" target="_blank" rel="noopener">J-Concept</a>'
		) . '</p>';
	}

	public static function save_settings() {
		self::guard();
		check_admin_referer( 'rcom_settings' );
		$old = RCOM_Settings::all();
		$sec = trim( sanitize_text_field( wp_unslash( $_POST['turnstile_secret'] ?? '' ) ) );
		$new = array(
			'turnstile_site'   => isset( $_POST['turnstile_site'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['turnstile_site'] ) ) ) : $old['turnstile_site'],
			'turnstile_secret' => '' !== $sec ? $sec : $old['turnstile_secret'],
			'reply_label'      => sanitize_text_field( wp_unslash( $_POST['reply_label'] ?? '' ) ),
			'mail_from'        => sanitize_email( wp_unslash( $_POST['mail_from'] ?? '' ) ),
			'post_types'       => implode( ',', array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( $_POST['post_types'] ?? '' ) ) ) ) ) ?: 'recipe',
			'newsletter_provider' => isset( RCOM_Newsletter::providers()[ $_POST['newsletter_provider'] ?? '' ] ) ? $_POST['newsletter_provider'] : 'none',
			'laposta_key'      => '' !== trim( sanitize_text_field( wp_unslash( $_POST['laposta_key'] ?? '' ) ) ) ? trim( sanitize_text_field( wp_unslash( $_POST['laposta_key'] ) ) ) : $old['laposta_key'],
			'laposta_list'     => isset( $_POST['laposta_list'] ) ? sanitize_text_field( wp_unslash( $_POST['laposta_list'] ) ) : $old['laposta_list'],
			'laposta_name_field' => sanitize_text_field( wp_unslash( $_POST['laposta_name_field'] ?? '' ) ),
			'laposta_doi'      => 'laposta' === ( $_POST['laposta_doi'] ?? '' ) ? 'laposta' : 'ours',
			'show_credit'      => empty( $_POST['show_credit'] ) ? 0 : 1,
			'disable_updates'  => empty( $_POST['disable_updates'] ) ? 0 : 1,
			'keep_data'        => empty( $_POST['keep_data'] ) ? 0 : 1,
		);
		update_option( 'rcom_settings', $new, false );
		delete_transient( 'rcom_update' );
		wp_safe_redirect( admin_url( 'admin.php?page=rcom&tab=settings&done=1' ) );
		exit;
	}

	public static function test_turnstile() {
		self::guard();
		check_admin_referer( 'rcom_test_turnstile' );
		wp_safe_redirect( admin_url( 'admin.php?page=rcom&tab=settings&ts=' . RCOM_Turnstile::test_keys() ) );
		exit;
	}

	public static function test_laposta() {
		self::guard();
		check_admin_referer( 'rcom_test_laposta' );
		$r = ( new RCOM_Laposta() )->test();
		set_transient( 'rcom_lp_msg', $r['message'], 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=rcom&tab=settings&lp=' . ( $r['ok'] ? 'ok' : 'bad' ) ) );
		exit;
	}

	/** Settings of an already configured Laposta connection on this site (Bricks Laposta plugin or wp-config constant), if readable. */
	private static function existing_laposta() {
		$key = ( class_exists( 'Bricks_Laposta' ) && is_callable( array( 'Bricks_Laposta', 'get_api_key' ) ) ) ? (string) Bricks_Laposta::get_api_key() : ( defined( 'LAPOSTA_API_KEY' ) ? (string) LAPOSTA_API_KEY : '' );
		if ( '' === $key ) {
			return null;
		}
		$s = ( class_exists( 'Bricks_Laposta' ) && is_callable( array( 'Bricks_Laposta', 'get_settings' ) ) ) ? (array) Bricks_Laposta::get_settings() : array();
		return array( 'key' => $key, 'list' => (string) ( $s['list_id'] ?? '' ) );
	}

	public static function adopt_laposta() {
		self::guard();
		check_admin_referer( 'rcom_adopt_laposta' );
		$e = self::existing_laposta();
		if ( $e ) {
			$o                        = RCOM_Settings::all();
			$o['newsletter_provider'] = 'laposta';
			$o['laposta_key']         = $e['key'];
			$o['laposta_list']        = $e['list'];
			update_option( 'rcom_settings', $o, false );
			delete_transient( 'rcom_lp_lists' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rcom&tab=settings&done=1' ) );
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
		foreach ( $wpdb->get_results( 'SELECT * FROM ' . RCOM_Reviews::table() . ' ORDER BY id' ) as $r ) {
			fputcsv( $o, array_map( $safe, array( $r->id, get_the_title( $r->post_id ), $r->rating, $r->body, $r->name, $r->email, $r->bbq_type, $r->photo_status, $r->verified, ( $r->newsletter_optin && $r->verified ) ? 1 : 0, $r->status, $r->reply_text, $r->created_at ) ), ';', '"', '' );
		}
		fclose( $o );
		exit;
	}
}
