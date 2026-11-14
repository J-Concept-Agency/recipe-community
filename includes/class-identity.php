<?php
defined( 'ABSPATH' ) || exit;

/** Identity without accounts: one-time email links, signed cookie, rate limits, mail. */
class RCOM_Identity {

	const COOKIE    = 'rcom_id';
	const DAYS      = 30;
	const TOKEN_TTL = 7 * DAY_IN_SECONDS;

	/* ---------- rate limit (hashed IP, never stored readable) ---------- */

	public static function ip() {
		$ip = apply_filters( 'rcom_client_ip', $_SERVER['REMOTE_ADDR'] ?? '' );
		return is_string( $ip ) ? $ip : '';
	}

	/** False when the visitor is over the limit; otherwise counts this hit (unless $count is false). */
	public static function allow( $bucket, $max, $count = true ) {
		$key = 'rcom_rl_' . $bucket . '_' . substr( hash_hmac( 'sha256', self::ip(), wp_salt() ), 0, 32 );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return false;
		}
		if ( $count ) {
			set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		}
		return true;
	}

	/* ---------- signed cookie ---------- */

	private static function sign( $payload ) {
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	public static function set_cookie( $email, $name ) {
		$payload = rtrim( strtr( base64_encode( wp_json_encode( array( 'e' => $email, 'n' => $name, 'x' => time() + self::DAYS * DAY_IN_SECONDS ) ) ), '+/', '-_' ), '=' );
		$value   = $payload . '.' . self::sign( $payload );
		$_COOKIE[ self::COOKIE ] = $value;
		if ( headers_sent() ) {
			return;
		}
		setcookie( self::COOKIE, $value, array(
			'expires' => time() + self::DAYS * DAY_IN_SECONDS, 'path' => '/', 'domain' => '',
			'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax',
		) );
	}

	public static function clear_cookie() {
		unset( $_COOKIE[ self::COOKIE ] );
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, '', array( 'expires' => time() - DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
	}

	/** @return array{email:string,name:string}|null */
	public static function current() {
		$c = isset( $_COOKIE[ self::COOKIE ] ) ? (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) : '';
		$p = explode( '.', $c );
		if ( 2 !== count( $p ) || ! hash_equals( self::sign( $p[0] ), $p[1] ) ) {
			return null;
		}
		$d = json_decode( base64_decode( strtr( $p[0], '-_', '+/' ) ), true );
		if ( ! is_array( $d ) || empty( $d['e'] ) || ( $d['x'] ?? 0 ) < time() || ! is_email( $d['e'] ) ) {
			return null;
		}
		return array( 'email' => $d['e'], 'name' => (string) ( $d['n'] ?? '' ) );
	}

	public static function mask( $email ) {
		list( $u, $d ) = explode( '@', $email, 2 ) + array( '', '' );
		return mb_substr( $u, 0, 1 ) . '***@' . $d;
	}

	/* ---------- mail ---------- */

	public static function send( $to, $subject, $body ) {
		$from = sanitize_email( (string) RCOM_Settings::get( 'mail_from' ) );
		$from = is_email( $from ) ? $from : get_option( 'admin_email' );
		$f1   = static fn() => $from;
		$f2   = static fn() => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		add_filter( 'wp_mail_from', $f1, 99 );
		add_filter( 'wp_mail_from_name', $f2, 99 );
		$ok = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		remove_filter( 'wp_mail_from', $f1, 99 );
		remove_filter( 'wp_mail_from_name', $f2, 99 );
		return $ok;
	}

	private static function token() {
		return bin2hex( random_bytes( 24 ) );
	}

	private static function link( $token ) {
		return add_query_arg( 'rcom_t', $token, home_url( '/' ) );
	}

	/** Confirmation mail for a new review (verifies the review and confirms the newsletter choice). */
	public static function send_confirm( $review_id ) {
		$r = RCOM_Reviews::get( $review_id );
		if ( ! $r ) {
			return;
		}
		$t = self::token();
		RCOM_Reviews::update( $review_id, array( 'token_hash' => hash( 'sha256', $t ) ) );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: %s: site name */
		$subject = sprintf( __( 'Confirm your review on %s', 'recipe-community' ), $site );
		/* translators: 1: first name, 2: recipe title, 3: link, 4: days */
		$body = sprintf( __( "Hi %1\$s,\n\nThanks for your review of \"%2\$s\". It is already online. Click this link to confirm that this is your email address and to manage your review:\n\n%3\$s\n\nThe link works once and expires in %4\$d days. After confirming you can remove your review yourself on this device (look for \"Your review\" and \"Delete\" next to it).", 'recipe-community' ), $r->name, wp_strip_all_tags( get_the_title( $r->post_id ) ), self::link( $t ), 7 );
		if ( $r->newsletter_optin ) {
			$body .= "\n\n" . __( 'You also ticked the newsletter box. Clicking the link confirms your newsletter signup too. If you did not mean to sign up, simply do not click the link.', 'recipe-community' );
		}
		if ( RCOM_Account::url() ) {
			/* translators: 1: name of the account page, 2: link */
			$body .= "\n\n" . sprintf( __( 'Go to your %1$s page to manage your reviews and data: %2$s (you get a login link by email).', 'recipe-community' ), RCOM_Account::label(), RCOM_Account::url() );
		}
		$body .= "\n\n" . __( 'Did not write this review? Ignore this email.', 'recipe-community' );
		self::send( $r->email, $subject, $body );
	}

	/** Link to manage reviews on this device. Silent when the address has no reviews. */
	public static function send_manage( $email, $post_id ) {
		global $wpdb;
		$h = hash( 'sha256', strtolower( $email ) );
		if ( get_transient( 'rcom_m_' . $h ) ) {
			return; // one mail per address per 10 minutes, so nobody can mail-bomb someone else
		}
		set_transient( 'rcom_m_' . $h, 1, 10 * MINUTE_IN_SECONDS );
		$name = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . RCOM_Reviews::table() . ' WHERE email = %s ORDER BY id DESC LIMIT 1', $email ) );
		if ( null === $name ) {
			return;
		}
		$t = self::token();
		set_transient( 'rcom_t_' . hash( 'sha256', $t ), array( $email, (int) $post_id ), self::TOKEN_TTL );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: %s: site name */
		$subject = sprintf( __( 'Manage your reviews on %s', 'recipe-community' ), $site );
		/* translators: 1: first name, 2: link */
		$body = sprintf( __( "Hi %1\$s,\n\nUse this link to manage your reviews in this browser for the next 30 days:\n\n%2\$s\n\nThe link works once and expires in 7 days. Did not ask for this? Ignore this email.", 'recipe-community' ), $name, self::link( $t ) );
		self::send( $email, $subject, $body );
	}

	/** Handle ?rcom_t=TOKEN on any front-end request. */
	public static function handle_link() {
		global $wpdb;
		if ( is_admin() || empty( $_GET['rcom_t'] ) || ! is_string( $_GET['rcom_t'] ) || ! preg_match( '/^[a-f0-9]{48}$/', $_GET['rcom_t'] ) ) {
			return;
		}
		$h       = hash( 'sha256', $_GET['rcom_t'] );
		$r       = $wpdb->get_row( $wpdb->prepare( 'SELECT id, post_id, email, name, newsletter_optin, created_at FROM ' . RCOM_Reviews::table() . ' WHERE token_hash = %s', $h ) );
		$post_id = 0;
		$m       = '';
		if ( $r && strtotime( $r->created_at . ' UTC' ) + self::TOKEN_TTL > time() ) {
			RCOM_Reviews::update( $r->id, array( 'verified' => 1, 'token_hash' => '' ) );
			self::set_cookie( $r->email, $r->name );
			$post_id = (int) $r->post_id;
			$m       = 'confirmed';
			if ( $r->newsletter_optin ) {
				/**
				 * The visitor confirmed their email address and had ticked the newsletter box.
				 *
				 * @param string $email   Confirmed email address.
				 * @param string $name    First name.
				 * @param int    $post_id Recipe the review belongs to.
				 */
				do_action( 'rcom_newsletter_optin', $r->email, $r->name, $post_id );
			}
		} elseif ( ( $t = get_transient( 'rcom_t_' . $h ) ) && is_array( $t ) ) {
			delete_transient( 'rcom_t_' . $h );
			$name = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . RCOM_Reviews::table() . ' WHERE email = %s ORDER BY id DESC LIMIT 1', $t[0] ) );
			self::set_cookie( $t[0], $name );
			$post_id = (int) $t[1];
			$m       = 'manage';
		}
		if ( ! $m ) {
			wp_die( esc_html__( 'This link has expired or was already used.', 'recipe-community' ), '', array( 'response' => 410, 'back_link' => true ) );
		}
		$url = ( $post_id && in_array( get_post_type( $post_id ), RCOM_Settings::post_types(), true ) ) ? get_permalink( $post_id ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'rcom', $m, $url ) . '#rcom-reviews' );
		exit;
	}
}
