<?php
defined( 'ABSPATH' ) || exit;

/**
 * Account page ([rcom_account]): passwordless login by emailed link, server-side sessions,
 * "my reviews / my votes / my data". No accounts are created in WordPress.
 *
 * Security design in short: single-use 256-bit link (only its hash stored, 15 minutes), a GET never consumes it
 * (mail scanners): a small page with a POST button does. Opaque session ids (hash stored, no user agent, no IP),
 * HttpOnly + SameSite=Lax (+ Secure and __Host- on https) cookie, rotated at login. Every action checks that the object
 * belongs to the session identity. All state changes need a session-bound nonce header. Sensitive actions need a login
 * younger than 15 minutes. Same response for every login request (no user enumeration).
 */
class RCOM_Account {

	const LOGIN_TTL   = 900;
	const FRESH_TTL   = 900;
	const SESSION_TTL = 30 * DAY_IN_SECONDS;

	private static $session = false;

	/* ---------- tables ---------- */

	public static function t( $n ) {
		global $wpdb;
		return $wpdb->prefix . 'rcom_' . $n;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$s = self::t( 'sessions' );
		$k = self::t( 'tokens' );
		$a = self::t( 'audit' );
		dbDelta( "CREATE TABLE {$s} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  identity_id char(64) NOT NULL,
  sid_hash char(64) NOT NULL,
  email_hint varchar(120) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  login_at datetime NOT NULL,
  last_seen datetime NOT NULL,
  expires_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY sid_hash (sid_hash),
  KEY identity_id (identity_id),
  KEY expires_at (expires_at)
) {$c};" );
		dbDelta( "CREATE TABLE {$k} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token_hash char(64) NOT NULL,
  identity_id char(64) NOT NULL,
  email_hint varchar(120) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  used_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token_hash (token_hash),
  KEY expires_at (expires_at)
) {$c};" );
		dbDelta( "CREATE TABLE {$a} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  identity_id char(64) NOT NULL DEFAULT '',
  event varchar(30) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY identity_id (identity_id),
  KEY created_at (created_at)
) {$c};" );
	}

	/** Remove expired sessions and tokens, and audit rows older than 90 days. Called by the daily cron job. */
	public static function cleanup() {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'sessions' ) . ' WHERE expires_at < %s', $now ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'tokens' ) . ' WHERE expires_at < %s', gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'audit' ) . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
	}

	/** Security event without IP or email: identity hash and type only. */
	public static function log( $identity, $event ) {
		global $wpdb;
		$wpdb->insert( self::t( 'audit' ), array( 'identity_id' => $identity, 'event' => $event, 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );
	}

	/** Delete everything held for an identity: sessions, tokens, audit. */
	public static function purge( $identity ) {
		global $wpdb;
		foreach ( array( 'sessions', 'tokens', 'audit' ) as $t ) {
			$wpdb->delete( self::t( $t ), array( 'identity_id' => $identity ), array( '%s' ) );
		}
	}

	public static function revoke( $identity ) {
		global $wpdb;
		$n = (int) $wpdb->delete( self::t( 'sessions' ), array( 'identity_id' => $identity ), array( '%s' ) );
		self::log( $identity, 'sessions_revoked' );
		return $n;
	}

	/* ---------- page and hooks ---------- */

	public static function page_id() {
		return (int) get_option( 'rcom_account_pid', 0 ); // tiny autoloaded copy: no settings query on every front-end request
	}

	/** Name of the account, e.g. "Pitmaster" (editable in the settings). */
	public static function label() {
		$l = (string) RCOM_Settings::get( 'account_label' );
		return '' !== $l ? $l : __( 'Pitmaster', 'recipe-community' );
	}

	public static function url() {
		$id = self::page_id();
		return ( $id && 'publish' === get_post_status( $id ) ) ? get_permalink( $id ) : '';
	}

	private static function is_page() {
		$id = self::page_id();
		return $id && is_page( $id );
	}

	public static function init() {
		add_shortcode( 'rcom_account', array( __CLASS__, 'sc_account' ) );
		add_shortcode( 'rcom_account_link', array( __CLASS__, 'sc_link' ) );
		add_action( 'init', array( __CLASS__, 'landing' ) );
		add_action( 'template_redirect', array( __CLASS__, 'headers' ), 1 );
		add_filter( 'wp_robots', function ( $r ) {
			if ( self::is_page() ) {
				$r['noindex'] = true;
				$r['nofollow'] = true;
			}
			return $r;
		} );
		add_action( 'wp_enqueue_scripts', function () {
			wp_register_style( 'rcom-account', apply_filters( 'rcom_load_default_css', true ) ? RCOM_URL . 'assets/rcom-account.css' : false, array( 'rcom' ), RCOM_VERSION );
			wp_register_script( 'rcom-account', RCOM_URL . 'assets/rcom-account.js', array(), RCOM_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		} );
	}

	/** The account page must never be cached or indexed. */
	public static function headers() {
		if ( ! self::is_page() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: same-origin' );
		do_action( 'litespeed_control_set_nocache', 'rcom account' );
	}

	public static function sc_link() {
		$u = self::url();
		return $u ? '<a class="rcom-small rcom-acclink" href="' . esc_url( $u ) . '">' . esc_html( self::label() ) . '</a>' : '';
	}

	/* ---------- sessions ---------- */

	private static function cookie_name() {
		return is_ssl() ? '__Host-rcom_s' : 'rcom_s';
	}

	/** Current session row or null. Touches last_seen at most every 5 minutes. */
	public static function session() {
		if ( false !== self::$session ) {
			return self::$session;
		}
		global $wpdb;
		self::$session = null;
		$v = isset( $_COOKIE[ self::cookie_name() ] ) ? (string) wp_unslash( $_COOKIE[ self::cookie_name() ] ) : '';
		if ( preg_match( '/^[a-f0-9]{64}$/', $v ) ) {
			$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'sessions' ) . ' WHERE sid_hash = %s AND expires_at > %s', hash( 'sha256', $v ), gmdate( 'Y-m-d H:i:s' ) ) );
			if ( $r ) {
				if ( strtotime( $r->last_seen . ' UTC' ) < time() - 300 ) {
					$wpdb->update( self::t( 'sessions' ), array( 'last_seen' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $r->id ) );
				}
				self::$session = $r;
			}
		}
		return self::$session;
	}

	private static function set_cookie( $sid ) {
		self::$session = false;
		$_COOKIE[ self::cookie_name() ] = $sid;
		if ( ! headers_sent() ) {
			setcookie( self::cookie_name(), $sid, array( 'expires' => $sid ? time() + self::SESSION_TTL : time() - DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
	}

	/** Session-bound CSRF nonce. */
	public static function nonce( $s ) {
		return hash_hmac( 'sha256', 'acct|' . $s->sid_hash, wp_salt( 'auth' ) );
	}

	public static function fresh( $s ) {
		return strtotime( $s->login_at . ' UTC' ) > time() - self::FRESH_TTL;
	}

	private static function end_session( $s, $all = false ) {
		global $wpdb;
		if ( $all ) {
			self::revoke( $s->identity_id );
		} else {
			$wpdb->delete( self::t( 'sessions' ), array( 'id' => (int) $s->id ), array( '%d' ) );
		}
		self::set_cookie( '' );
	}

	/* ---------- login link ---------- */

	private static function email_of( $identity ) {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ' . RCOM_Reviews::table() . ' WHERE identity_id = %s ORDER BY id DESC LIMIT 1', $identity ) );
	}

	private static function new_token( $identity, $hint ) {
		global $wpdb;
		$t = bin2hex( random_bytes( 32 ) ); // 256 bit
		$wpdb->insert( self::t( 'tokens' ), array( 'token_hash' => hash( 'sha256', $t ), 'identity_id' => $identity, 'email_hint' => $hint, 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::LOGIN_TTL ) ) );
		return $t;
	}

	private static function send_login( $email ) {
		$identity = RCOM_Helpful::identity_for( $email );
		$t        = self::new_token( $identity, RCOM_Identity::mask( $email ) );
		self::log( $identity, 'link_requested' );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: %s: site name */
		$subject = sprintf( __( 'Your login link for %s', 'recipe-community' ), $site );
		/* translators: 1: login link, 2: minutes */
		$body = sprintf( __( "Use this link to log in to your reviews and data:\n\n%1\$s\n\nThe link works once and expires in %2\$d minutes. Did not ask for this? Ignore this email, nothing happens.", 'recipe-community' ), add_query_arg( 'rcom_login', $t, home_url( '/' ) ), 15 );
		RCOM_Identity::send( $email, $subject, $body );
	}

	/** GET shows a small confirmation page and consumes nothing; POST consumes the token and starts a session. */
	public static function landing() {
		global $wpdb;
		if ( is_admin() ) {
			return;
		}
		$post = isset( $_POST['rcom_login_token'] ) && is_string( $_POST['rcom_login_token'] );
		$tok  = $post ? (string) wp_unslash( $_POST['rcom_login_token'] ) : ( isset( $_GET['rcom_login'] ) && is_string( $_GET['rcom_login'] ) ? (string) wp_unslash( $_GET['rcom_login'] ) : '' );
		if ( '' === $tok ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		$h    = hash( 'sha256', $tok );
		$bad  = ! preg_match( '/^[a-f0-9]{64}$/', $tok );
		$now  = gmdate( 'Y-m-d H:i:s' );
		$mine = hash_hmac( 'sha256', 'login|' . $h, wp_salt() );
		if ( $post && ! $bad ) {
			$n = isset( $_POST['rcom_login_nonce'] ) ? (string) wp_unslash( $_POST['rcom_login_nonce'] ) : '';
			if ( ! hash_equals( $mine, $n ) || ! RCOM_Identity::allow( 'ause', 20 ) ) {
				$bad = true;
			} elseif ( 1 === (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t( 'tokens' ) . ' SET used_at = %s WHERE token_hash = %s AND used_at IS NULL AND expires_at > %s', $now, $h, $now ) ) ) {
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT identity_id, email_hint FROM ' . self::t( 'tokens' ) . ' WHERE token_hash = %s', $h ) );
				$old = self::session();
				if ( $old ) { // rotation: never keep a session id from before the login
					$wpdb->delete( self::t( 'sessions' ), array( 'id' => (int) $old->id ), array( '%d' ) );
				}
				$sid = bin2hex( random_bytes( 32 ) );
				$wpdb->insert( self::t( 'sessions' ), array( 'identity_id' => $row->identity_id, 'sid_hash' => hash( 'sha256', $sid ), 'email_hint' => $row->email_hint, 'created_at' => $now, 'login_at' => $now, 'last_seen' => $now, 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::SESSION_TTL ) ) );
				self::set_cookie( $sid );
				self::log( $row->identity_id, 'link_used' );
				self::log( $row->identity_id, 'session_created' );
				wp_safe_redirect( self::url() ? self::url() : home_url( '/' ), 303 ); // clean URL, no token
				exit;
			} else {
				$bad = true;
			}
		} elseif ( ! $post && ! $bad ) {
			$bad = ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t( 'tokens' ) . ' WHERE token_hash = %s AND used_at IS NULL AND expires_at > %s', $h, $now ) );
		}
		status_header( $bad ? 410 : 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		$title = esc_html( get_bloginfo( 'name' ) );
		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . $title . '</title><style>body{font:16px/1.5 system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f6f6f6;color:#252525}main{background:#fff;border:1px solid #e7e2de;border-radius:6px;padding:32px;max-width:420px;text-align:center}button{font:inherit;font-weight:600;background:#252525;color:#fff;border:0;border-radius:6px;padding:12px 28px;cursor:pointer}p{margin:0 0 20px}</style></head><body><main>';
		if ( $bad ) {
			echo '<p>' . esc_html__( 'This link has expired or was already used. Request a new one on the account page.', 'recipe-community' ) . '</p>';
			if ( self::url() ) {
				echo '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Go to the account page', 'recipe-community' ) . '</a>';
			}
		} else {
			echo '<p>' . esc_html__( 'Log in to your reviews and data?', 'recipe-community' ) . '</p><form method="post" action="' . esc_url( home_url( '/' ) ) . '"><input type="hidden" name="rcom_login_token" value="' . esc_attr( $tok ) . '"><input type="hidden" name="rcom_login_nonce" value="' . esc_attr( $mine ) . '"><button type="submit">' . esc_html__( 'Log in', 'recipe-community' ) . '</button></form>';
		}
		echo '</main></body></html>';
		exit;
	}

	/* ---------- sections (extensible) ---------- */

	/** @return array<string,array{label:string,cb:callable}> A future module (favourites) adds a section with this filter. */
	public static function sections() {
		return (array) apply_filters( 'rcom_account_sections', array(
			'reviews' => array( 'label' => __( 'My reviews', 'recipe-community' ), 'cb' => array( __CLASS__, 'sec_reviews' ) ),
			'votes'   => array( 'label' => __( 'My votes', 'recipe-community' ), 'cb' => array( __CLASS__, 'sec_votes' ) ),
			'data'    => array( 'label' => __( 'My data', 'recipe-community' ), 'cb' => array( __CLASS__, 'sec_data' ) ),
		) );
	}

	private static function title( $id ) {
		$t = get_the_title( $id );
		return '' !== $t ? $t : '#' . (int) $id;
	}

	public static function sec_reviews( $s ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RCOM_Reviews::table() . ' WHERE identity_id = %s ORDER BY created_at DESC LIMIT 200', $s->identity_id ) );
		if ( ! $rows ) {
			return '<p class="rcom-small">' . esc_html__( 'You have not posted a review yet.', 'recipe-community' ) . '</p>';
		}
		$h = '<ul class="rcom-items">';
		foreach ( $rows as $r ) {
			$h .= '<li class="rcom-item" data-id="' . (int) $r->id . '"><article class="rcom-review"><div class="rcom-head">' . RCOM_Front::stars( (int) $r->rating ) . '<strong class="rcom-name"><a href="' . esc_url( get_permalink( $r->post_id ) ) . '">' . esc_html( self::title( $r->post_id ) ) . '</a></strong><span class="rcom-small"><time datetime="' . esc_attr( gmdate( 'Y-m-d', strtotime( $r->created_at . ' UTC' ) ) ) . '">' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $r->created_at . ' UTC' ) ) ) . '</time></span>' . ( 'published' !== $r->status ? '<span class="rcom-own">' . esc_html__( 'Hidden', 'recipe-community' ) . '</span>' : '' ) . '<button type="button" class="rcom-del" data-act="del-review">' . esc_html__( 'Delete', 'recipe-community' ) . '</button></div>';
			if ( '' !== $r->body ) {
				$h .= '<p>' . esc_html( $r->body ) . '</p>';
			}
			if ( $r->photo_file ) {
				$h .= 'approved' === $r->photo_status ? '<img class="rcom-photo" src="' . esc_url( RCOM_Photo::url( $r->photo_file ) ) . '" width="320" height="240" loading="lazy" alt="">' : '<p class="rcom-note">' . esc_html__( 'Photo is being checked', 'recipe-community' ) . '</p>';
			}
			$h .= '</article></li>';
		}
		return $h . '</ul>';
	}

	public static function sec_votes( $s ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, post_id, vote FROM ' . RCOM_Helpful::votes() . ' WHERE identity_id = %s ORDER BY id DESC LIMIT 200', $s->identity_id ) );
		if ( ! $rows ) {
			return '<p class="rcom-small">' . esc_html__( 'You have not voted yet.', 'recipe-community' ) . '</p>';
		}
		$h = '';
		foreach ( $rows as $r ) {
			$h .= '<div class="rcom-vrow" data-post="' . (int) $r->post_id . '"><a href="' . esc_url( get_permalink( $r->post_id ) ) . '">' . esc_html( self::title( $r->post_id ) ) . '</a><span class="rcom-vbtns"><button type="button" class="rcom-hbtn" data-act="vote" data-v="yes" aria-pressed="' . ( 'yes' === $r->vote ? 'true' : 'false' ) . '">' . esc_html__( 'Yes', 'recipe-community' ) . '</button><button type="button" class="rcom-hbtn" data-act="vote" data-v="no" aria-pressed="' . ( 'no' === $r->vote ? 'true' : 'false' ) . '">' . esc_html__( 'No', 'recipe-community' ) . '</button><button type="button" class="rcom-del" data-act="vote" data-v="remove">' . esc_html__( 'Remove my vote', 'recipe-community' ) . '</button></span></div>';
		}
		return $h;
	}

	private static function subscribed( $identity ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . RCOM_Reviews::table() . ' WHERE identity_id = %s AND newsletter_optin = 1 AND verified = 1 LIMIT 1', $identity ) );
	}

	public static function sec_data( $s ) {
		$sub = self::subscribed( $s->identity_id );
		$p   = RCOM_Newsletter::provider();
		$h   = '<p>' . esc_html( sprintf( /* translators: %s: masked email address */ __( 'Logged in as %s.', 'recipe-community' ), $s->email_hint ) ) . '</p>';
		$h  .= '<p class="rcom-small">' . esc_html( $sub ? __( 'Newsletter: subscribed.', 'recipe-community' ) : __( 'Newsletter: not subscribed.', 'recipe-community' ) ) . '</p><div class="rcom-accbtns">';
		if ( $sub && $p && method_exists( $p, 'unsubscribe' ) ) {
			$h .= '<button type="button" class="rcom-btn" data-act="unsub">' . esc_html__( 'Unsubscribe from the newsletter', 'recipe-community' ) . '</button>';
		}
		$h .= '<button type="button" class="rcom-btn" data-act="export">' . esc_html__( 'Download my data', 'recipe-community' ) . '</button><button type="button" class="rcom-btn" data-act="logout-all">' . esc_html__( 'Log out on all devices', 'recipe-community' ) . '</button><button type="button" class="rcom-btn" data-act="logout">' . esc_html__( 'Log out', 'recipe-community' ) . '</button></div>';
		$h  .= '<div class="rcom-danger"><strong>' . esc_html__( 'Delete everything', 'recipe-community' ) . '</strong><p class="rcom-small">' . esc_html__( 'Removes all your reviews, photos, votes and sessions for good. This cannot be undone.', 'recipe-community' ) . '</p><label class="rcom-check"><input type="checkbox" data-confirm> <span>' . esc_html__( 'Yes, delete everything', 'recipe-community' ) . '</span></label><button type="button" class="rcom-btn" data-act="delete-all">' . esc_html__( 'Delete everything', 'recipe-community' ) . '</button></div>';
		return $h;
	}

	/* ---------- shortcode ---------- */

	public static function sc_account() {
		if ( ! self::page_id() || ! RCOM_Settings::turnstile_ready() ) {
			return current_user_can( 'manage_options' ) ? '<p class="rcom-msg err">' . esc_html__( 'Recipe Community: the account page needs the Turnstile keys and must be chosen under Settings > Account page (only you can see this message).', 'recipe-community' ) . '</p>' : '';
		}
		RCOM_Style::enqueue( 'rcom' );
		RCOM_Style::enqueue( 'rcom-account' );
		wp_enqueue_script( 'rcom-account' );
		RCOM_Front::i18n( 'ac', array(
			'err'     => __( 'Something went wrong. Please try again.', 'recipe-community' ),
			'sure'    => __( 'Delete this review?', 'recipe-community' ),
			'sureall' => __( 'Delete everything for good?', 'recipe-community' ),
			'stale'   => __( 'For your safety please log in again with a fresh link first.', 'recipe-community' ),
			'newlink' => __( 'Send me a new login link', 'recipe-community' ),
			'sent'    => __( 'A new login link is on its way.', 'recipe-community' ),
			'confirm' => __( 'Tick the box to confirm.', 'recipe-community' ),
			'gone'    => __( 'Done.', 'recipe-community' ),
		) );
		$s = self::session();
		if ( ! $s ) {
			return '<form class="rcom-form rcom-accform" method="post" action="#" data-ts="' . esc_attr( RCOM_Settings::turnstile_site() ) . '"><p class="rcom-ft">' . esc_html__( 'Log in to your reviews', 'recipe-community' ) . '</p><div class="rcom-msg" role="status" aria-live="polite"></div><div class="rcom-f"><label for="rcom-ae">' . esc_html__( 'Email address', 'recipe-community' ) . '</label><input id="rcom-ae" name="email" type="email" required maxlength="190" autocomplete="email"></div><div class="rcom-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><input type="hidden" name="nonce"><input type="hidden" name="stamp"><div class="rcom-ts"></div><p class="rcom-small">' . esc_html__( 'We email you a login link. No password needed.', 'recipe-community' ) . '</p><div><button type="submit" class="rcom-btn">' . esc_html__( 'Send me a link', 'recipe-community' ) . '</button></div></form>';
		}
		$secs = self::sections();
		$nav  = '';
		$body = '';
		$i    = 0;
		foreach ( $secs as $id => $sec ) {
			$nav  .= '<button type="button" role="tab" class="rcom-tab" id="rcom-tab-' . esc_attr( $id ) . '" aria-controls="rcom-sec-' . esc_attr( $id ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '">' . esc_html( $sec['label'] ) . '</button>';
			$body .= '<section class="rcom-sec" role="tabpanel" id="rcom-sec-' . esc_attr( $id ) . '" aria-labelledby="rcom-tab-' . esc_attr( $id ) . '"><h2 class="rcom-ft">' . esc_html( $sec['label'] ) . '</h2>' . call_user_func( $sec['cb'], $s ) . '</section>';
			$i++;
		}
		return '<div class="rcom-acc" data-nonce="' . esc_attr( self::nonce( $s ) ) . '"><div class="rcom-msg" role="status" aria-live="polite"></div><div class="rcom-tabs" role="tablist">' . $nav . '</div>' . $body . '</div>';
	}

	/* ---------- REST ---------- */

	public static function routes() {
		$pub  = array( 'permission_callback' => '__return_true' );
		$auth = array( 'permission_callback' => array( __CLASS__, 'perm' ) );
		register_rest_route( 'rcom/v1', '/account/state', $pub + array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_state' ) ) );
		register_rest_route( 'rcom/v1', '/account/login-request', $pub + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_request' ) ) );
		foreach ( array( 'review-delete', 'vote', 'export', 'delete-all', 'logout', 'logout-all', 'reauth', 'unsubscribe' ) as $r ) {
			register_rest_route( 'rcom/v1', '/account/' . $r, $auth + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_' . str_replace( '-', '_', $r ) ) ) );
		}
	}

	/** Session plus session-bound nonce header. No state-changing GET exists. */
	public static function perm( WP_REST_Request $q ) {
		$s = self::session();
		if ( ! $s || ! hash_equals( self::nonce( $s ), (string) $q->get_header( 'x_rcom_nonce' ) ) ) {
			return new WP_Error( 'rcom_auth', __( 'Please log in again.', 'recipe-community' ), array( 'status' => 401 ) );
		}
		return true;
	}

	private static function out( array $d, $status = 200 ) {
		$r = new WP_REST_Response( $d, $status );
		$r->header( 'Cache-Control', 'private, no-store' );
		return $r;
	}

	private static function err( $code, $msg, $status = 400 ) {
		return new WP_Error( 'rcom_a_' . $code, $msg, array( 'status' => $status ) );
	}

	private static function need_fresh( $s ) {
		return self::fresh( $s ) ? null : self::err( 'stale', __( 'For your safety please log in again with a fresh link first.', 'recipe-community' ), 403 );
	}

	public static function r_state() {
		$t = time();
		return self::out( array( 'nonce' => wp_create_nonce( 'rcom_acct_login' ), 'stamp' => $t . '.' . hash_hmac( 'sha256', 'astamp.' . $t, wp_salt() ) ) );
	}

	/** Always the same answer and about the same duration; the mail is sent after the response when possible. */
	public static function r_request( WP_REST_Request $q ) {
		$start = microtime( true );
		$same  = self::out( array( 'ok' => true, 'message' => __( 'If this address has reviews or votes, a login link is on its way.', 'recipe-community' ) ) );
		if ( ! RCOM_Settings::turnstile_ready() || ! wp_verify_nonce( (string) $q->get_param( 'nonce' ), 'rcom_acct_login' ) ) {
			return self::err( 'nonce', __( 'The form has expired. Please reload the page and try again.', 'recipe-community' ), 403 );
		}
		$p = explode( '.', (string) $q->get_param( 'stamp' ) );
		if ( 2 !== count( $p ) || ! ctype_digit( $p[0] ) || ! hash_equals( hash_hmac( 'sha256', 'astamp.' . $p[0], wp_salt() ), $p[1] ) || time() - (int) $p[0] > DAY_IN_SECONDS ) {
			return self::err( 'nonce', __( 'The form has expired. Please reload the page and try again.', 'recipe-community' ), 403 );
		}
		if ( '' !== (string) $q->get_param( 'website' ) ) {
			return $same;
		}
		if ( ! RCOM_Identity::allow( 'alogin', 6, false ) ) {
			return self::err( 'rate', __( 'Too many requests. Please try again later.', 'recipe-community' ), 429 );
		}
		if ( ! RCOM_Turnstile::verify( $q->get_param( 'cf-turnstile-response' ) ) ) {
			return self::err( 'turnstile', __( 'The spam check failed. Please try again.', 'recipe-community' ), 403 );
		}
		RCOM_Identity::allow( 'alogin', 6 );
		$email = sanitize_email( (string) $q->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return self::err( 'email', __( 'Please enter a valid email address.', 'recipe-community' ) );
		}
		global $wpdb;
		$hh     = hash_hmac( 'sha256', 'alink|' . strtolower( $email ), wp_salt() );
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . RCOM_Reviews::table() . ' WHERE email = %s LIMIT 1', $email ) )
			|| (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . RCOM_Helpful::votes() . ' WHERE identity_id = %s LIMIT 1', RCOM_Helpful::identity_for( $email ) ) );
		$count  = (int) get_transient( 'rcom_ae_' . $hh );
		$send   = $exists && $count < 3 && ! get_transient( 'rcom_ag_' . $hh ); // per email: max 3 per hour, one per 2 minutes
		if ( $send ) {
			set_transient( 'rcom_ae_' . $hh, $count + 1, HOUR_IN_SECONDS );
			set_transient( 'rcom_ag_' . $hh, 1, 2 * MINUTE_IN_SECONDS );
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . RCOM_Reviews::table() . " SET identity_id = %s WHERE email = %s AND identity_id = ''", RCOM_Helpful::identity_for( $email ), $email ) );
		}
		$work = static function () use ( $send, $email, $start ) {
			if ( $send ) {
				RCOM_Account::send_login_public( $email );
			}
			$left = 0.7 - ( microtime( true ) - $start ); // equal duration for known and unknown addresses
			if ( $left > 0 ) {
				usleep( (int) ( $left * 1000000 ) );
			}
		};
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			add_action( 'shutdown', static function () use ( $work ) {
				fastcgi_finish_request();
				$work();
			}, 0 );
		} else {
			$work();
		}
		return $same;
	}

	public static function send_login_public( $email ) {
		self::send_login( $email );
	}

	private static function own_review( $s, $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RCOM_Reviews::table() . ' WHERE id = %d AND identity_id = %s', $id, $s->identity_id ) );
	}

	public static function r_review_delete( WP_REST_Request $q ) {
		$s = self::session();
		if ( ! RCOM_Identity::allow( 'aact', 60 ) ) {
			return self::err( 'rate', __( 'Too many requests. Please try again later.', 'recipe-community' ), 429 );
		}
		$r = self::own_review( $s, (int) $q->get_param( 'id' ) );
		if ( ! $r ) {
			return self::err( 'nf', __( 'Not found.', 'recipe-community' ), 404 ); // same answer for "not yours" and "does not exist"
		}
		RCOM_Reviews::delete( $r->id );
		self::log( $s->identity_id, 'review_deleted' );
		return self::out( array( 'ok' => true ) );
	}

	public static function r_vote( WP_REST_Request $q ) {
		global $wpdb;
		$s = self::session();
		if ( ! RCOM_Identity::allow( 'aact', 60 ) ) {
			return self::err( 'rate', __( 'Too many requests. Please try again later.', 'recipe-community' ), 429 );
		}
		$post = (int) $q->get_param( 'post' );
		$v    = (string) $q->get_param( 'vote' );
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . RCOM_Helpful::votes() . ' WHERE post_id = %d AND identity_id = %s ORDER BY id DESC LIMIT 1', $post, $s->identity_id ) );
		if ( ! $row || ! in_array( $v, array( 'yes', 'no', 'remove' ), true ) ) {
			return self::err( 'nf', __( 'Not found.', 'recipe-community' ), 404 );
		}
		if ( 'remove' === $v ) {
			RCOM_Helpful::delete_vote( (int) $row->id );
		} else {
			$wpdb->update( RCOM_Helpful::votes(), array( 'vote' => $v, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $row->id ) );
		}
		RCOM_Helpful::refresh( $post );
		return self::out( array( 'ok' => true ) );
	}

	public static function r_export() {
		global $wpdb;
		$s = self::session();
		if ( $e = self::need_fresh( $s ) ) {
			return $e;
		}
		$rev = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RCOM_Reviews::table() . ' WHERE identity_id = %s ORDER BY id', $s->identity_id ) ) as $r ) {
			$rev[] = array( 'recipe' => get_the_title( $r->post_id ), 'rating' => (int) $r->rating, 'text' => $r->body, 'name' => $r->name, 'bbq' => $r->bbq_type, 'photo' => $r->photo_file ? RCOM_Photo::url( $r->photo_file ) : '', 'verified' => (bool) $r->verified, 'newsletter' => (bool) $r->newsletter_optin, 'status' => $r->status, 'created' => $r->created_at, 'terms_accepted' => $r->terms_accepted_at );
		}
		$votes = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT v.post_id, v.vote, v.created_at, f.body FROM ' . RCOM_Helpful::votes() . ' v LEFT JOIN ' . RCOM_Helpful::feedback() . ' f ON f.vote_id = v.id WHERE v.identity_id = %s ORDER BY v.id', $s->identity_id ) ) as $v ) {
			$votes[] = array( 'article' => get_the_title( $v->post_id ), 'vote' => $v->vote, 'feedback' => (string) $v->body, 'created' => $v->created_at );
		}
		self::log( $s->identity_id, 'data_exported' );
		return self::out( array( 'ok' => true, 'email' => $s->email_hint, 'reviews' => $rev, 'votes' => $votes ) );
	}

	public static function r_delete_all( WP_REST_Request $q ) {
		global $wpdb;
		$s = self::session();
		if ( $e = self::need_fresh( $s ) ) {
			return $e;
		}
		if ( '1' !== (string) $q->get_param( 'confirm' ) ) {
			return self::err( 'confirm', __( 'Tick the box to confirm.', 'recipe-community' ) );
		}
		$posts = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, post_id FROM ' . RCOM_Reviews::table() . ' WHERE identity_id = %s', $s->identity_id ) ) as $r ) {
			RCOM_Reviews::delete( $r->id );
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, post_id FROM ' . RCOM_Helpful::votes() . ' WHERE identity_id = %s', $s->identity_id ) ) as $v ) {
			RCOM_Helpful::delete_vote( $v->id );
			$posts[ $v->post_id ] = 1;
		}
		foreach ( array_keys( $posts ) as $p ) {
			RCOM_Helpful::refresh( $p );
		}
		$id = $s->identity_id;
		self::purge( $id ); // sessions, tokens and audit of this identity
		self::log( '', 'data_deleted' ); // anonymous trace only
		self::set_cookie( '' );
		return self::out( array( 'ok' => true ) );
	}

	public static function r_logout() {
		self::end_session( self::session(), false );
		self::log( '', 'session_ended' );
		return self::out( array( 'ok' => true ) );
	}

	public static function r_logout_all() {
		self::end_session( self::session(), true );
		return self::out( array( 'ok' => true ) );
	}

	/** Fresh login link for the logged-in identity (needed before sensitive actions). */
	public static function r_reauth() {
		$s = self::session();
		if ( ! RCOM_Identity::allow( 'areauth', 3 ) ) {
			return self::err( 'rate', __( 'Too many requests. Please try again later.', 'recipe-community' ), 429 );
		}
		$email = self::email_of( $s->identity_id );
		if ( $email ) {
			self::send_login( $email );
		}
		return self::out( array( 'ok' => true, 'message' => __( 'A new login link is on its way.', 'recipe-community' ) ) );
	}

	public static function r_unsubscribe() {
		global $wpdb;
		$s = self::session();
		if ( $e = self::need_fresh( $s ) ) {
			return $e;
		}
		$p     = RCOM_Newsletter::provider();
		$email = self::email_of( $s->identity_id );
		if ( ! $p || ! method_exists( $p, 'unsubscribe' ) || ! $email || ! $p->unsubscribe( $email ) ) {
			return self::err( 'unsub', __( 'Something went wrong. Please try again.', 'recipe-community' ), 502 );
		}
		$wpdb->update( RCOM_Reviews::table(), array( 'newsletter_optin' => 0 ), array( 'identity_id' => $s->identity_id ) );
		self::log( $s->identity_id, 'newsletter_off' );
		return self::out( array( 'ok' => true ) );
	}
}
