<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST routes (namespace rcom/v1). All are public by design (visitors have no account);
 * each one checks its own nonce / signed cookie / rate limit inside the callback.
 * No route ever returns an email address of another person.
 */
class RCOM_Rest {

	const MIN_SECONDS = 4;

	public static function routes() {
		$pub = array( 'permission_callback' => '__return_true' );
		register_rest_route( 'rcom/v1', '/state', $pub + array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'state' ), 'args' => array( 'post' => array( 'type' => 'integer', 'required' => true ) ) ) );
		register_rest_route( 'rcom/v1', '/reviews', $pub + array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'listing' ), 'args' => array( 'post' => array( 'type' => 'integer', 'required' => true ), 'cursor' => array( 'type' => 'string', 'default' => '' ), 'limit' => array( 'type' => 'integer', 'default' => 10 ) ) ) );
		register_rest_route( 'rcom/v1', '/reviews', $pub + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'submit' ) ) );
		register_rest_route( 'rcom/v1', '/delete', $pub + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'delete' ) ) );
		register_rest_route( 'rcom/v1', '/link', $pub + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'link' ) ) );
		register_rest_route( 'rcom/v1', '/forget', $pub + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'forget' ) ) );
	}

	private static function out( array $d, $status = 200 ) {
		$r = new WP_REST_Response( $d, $status );
		$r->header( 'Cache-Control', 'no-store, private' );
		return $r;
	}

	private static function err( $code, $msg, $status = 400 ) {
		return new WP_Error( 'rcom_' . $code, $msg, array( 'status' => $status ) );
	}

	private static function post_ok( $id ) {
		return $id && 'publish' === get_post_status( $id ) && RCOM_Settings::enabled_for( $id );
	}

	private static function nonce_ok( WP_REST_Request $q, $post_id ) {
		return (bool) wp_verify_nonce( (string) $q->get_param( 'nonce' ), 'rcom_' . (int) $post_id );
	}

	private static function stamp( $post_id ) {
		$t = time();
		return $t . '.' . hash_hmac( 'sha256', 'stamp' . $post_id . '.' . $t, wp_salt() );
	}

	private static function stamp_age( $stamp, $post_id ) {
		$p = explode( '.', (string) $stamp );
		if ( 2 !== count( $p ) || ! ctype_digit( $p[0] ) || ! hash_equals( hash_hmac( 'sha256', 'stamp' . $post_id . '.' . $p[0], wp_salt() ), $p[1] ) ) {
			return false;
		}
		return time() - (int) $p[0];
	}

	/** Fresh nonce, form stamp and (for a known visitor) a masked identity. Called when the form opens, so page caches cannot spoil them. */
	public static function state( WP_REST_Request $q ) {
		global $wpdb;
		$id = (int) $q['post'];
		if ( ! self::post_ok( $id ) ) {
			return self::err( 'post', __( 'Unknown recipe.', 'recipe-community' ), 404 );
		}
		$me  = RCOM_Identity::current();
		$own = array();
		if ( $me ) {
			$own = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . RCOM_Reviews::table() . ' WHERE post_id = %d AND email = %s', $id, $me['email'] ) ) );
		}
		return self::out( array(
			'nonce' => wp_create_nonce( 'rcom_' . $id ),
			'stamp' => self::stamp( $id ),
			'me'    => $me ? array( 'name' => $me['name'], 'hint' => RCOM_Identity::mask( $me['email'] ) ) : null,
			'own'   => $own,
		) );
	}

	public static function listing( WP_REST_Request $q ) {
		$id = (int) $q['post'];
		if ( ! self::post_ok( $id ) ) {
			return self::err( 'post', __( 'Unknown recipe.', 'recipe-community' ), 404 );
		}
		$limit = max( 1, min( 20, (int) $q['limit'] ) );
		$html  = '';
		$rows  = RCOM_Reviews::after( $id, $limit, (string) $q['cursor'] ); // cursor "unixtime.id" of the last review shown
		foreach ( $rows as $r ) {
			$html .= RCOM_Front::item( $r );
		}
		return new WP_REST_Response( array( 'html' => $html, 'count' => count( $rows ) ) );
	}

	public static function submit( WP_REST_Request $q ) {
		$id = (int) $q->get_param( 'post' );
		if ( ! self::post_ok( $id ) ) {
			return self::err( 'post', __( 'Unknown recipe.', 'recipe-community' ), 404 );
		}
		if ( ! RCOM_Settings::turnstile_ready() ) {
			return self::err( 'closed', __( 'Reviews are temporarily closed.', 'recipe-community' ), 503 );
		}
		if ( ! self::nonce_ok( $q, $id ) ) {
			return self::err( 'nonce', __( 'The form has expired. Please reload the page and try again.', 'recipe-community' ), 403 );
		}
		$thanks = self::out( array( 'ok' => true, 'message' => __( 'Thanks! Your review is online. We sent you an email: click the link in it to confirm your review.', 'recipe-community' ), 'html' => '' ) );
		if ( '' !== (string) $q->get_param( 'website' ) ) {
			return $thanks; // honeypot: pretend it worked
		}
		$age = self::stamp_age( $q->get_param( 'stamp' ), $id );
		if ( false === $age || $age > DAY_IN_SECONDS ) {
			return self::err( 'stamp', __( 'The form has expired. Please reload the page and try again.', 'recipe-community' ), 403 );
		}
		if ( $age < self::MIN_SECONDS ) {
			return self::err( 'fast', __( 'Please wait a moment and try again.', 'recipe-community' ), 429 );
		}
		if ( ! RCOM_Identity::allow( 'post', 3, false ) ) {
			return self::err( 'rate', __( 'You have posted a few reviews already. Please try again later.', 'recipe-community' ), 429 );
		}
		if ( ! RCOM_Turnstile::verify( $q->get_param( 'cf-turnstile-response' ) ) ) {
			return self::err( 'turnstile', __( 'The spam check failed. Please try again.', 'recipe-community' ), 403 );
		}

		if ( ! $q->get_param( 'terms' ) ) {
			return self::err( 'terms', __( 'Please agree to the terms to post your review.', 'recipe-community' ) );
		}
		$rating = (int) $q->get_param( 'rating' );
		$name   = trim( sanitize_text_field( (string) $q->get_param( 'name' ) ) );
		$body   = trim( sanitize_textarea_field( (string) $q->get_param( 'body' ) ) );
		$email  = sanitize_email( (string) $q->get_param( 'email' ) );
		$me     = RCOM_Identity::current();
		if ( '' === $email && $me ) {
			$email = $me['email']; // known visitor: the email field is hidden in the form
		}
		$bbq = sanitize_key( (string) $q->get_param( 'bbq' ) );
		$bbq = isset( RCOM_Front::bbq_types()[ $bbq ] ) ? $bbq : '';
		if ( $rating < 1 || $rating > 5 ) {
			return self::err( 'rating', __( 'Please choose a star rating.', 'recipe-community' ) );
		}
		if ( '' === $name || mb_strlen( $name ) > 40 ) {
			return self::err( 'name', __( 'Please enter your first name (max 40 characters).', 'recipe-community' ) );
		}
		if ( ! is_email( $email ) || strlen( $email ) > 190 ) {
			return self::err( 'email', __( 'Please enter a valid email address.', 'recipe-community' ) );
		}
		if ( mb_strlen( $body ) > 1500 ) {
			return self::err( 'body', __( 'Your text is too long (max 1500 characters).', 'recipe-community' ) );
		}

		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . RCOM_Reviews::table() . ' WHERE post_id = %d AND email = %s LIMIT 1', $id, $email ) ) ) {
			// Same answer as for a new review (no way to probe who reviewed); the owner of the address gets a manage link.
			RCOM_Identity::allow( 'post', 3 );
			RCOM_Identity::send_manage( $email, $id );
			return $thanks;
		}

		$file   = '';
		$status = 'none';
		$f      = $q->get_file_params()['photo'] ?? null;
		if ( is_array( $f ) && UPLOAD_ERR_NO_FILE !== ( $f['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$file = RCOM_Photo::process( $f );
			if ( is_wp_error( $file ) ) {
				return self::err( 'photo', $file->get_error_message() );
			}
			$status = 'pending';
		}

		RCOM_Identity::allow( 'post', 3 );
		$tp  = (int) RCOM_Settings::get( 'terms_page' ); // only read when a review is saved
		$tv  = $tp ? RCOM_Terms::current( $tp ) : '';
		$new = RCOM_Reviews::insert( array(
			'post_id' => $id, 'rating' => $rating, 'body' => $body, 'name' => $name, 'email' => $email, 'bbq_type' => $bbq,
			'terms_accepted_at' => current_time( 'mysql', true ), 'terms_page_id' => $tp, 'terms_version' => $tv, 'photo_file' => $file, 'photo_status' => $status, 'device' => RCOM_Device::detect(), 'newsletter_optin' => $q->get_param( 'newsletter' ) ? 1 : 0,
		) );
		if ( ! $new ) {
			RCOM_Photo::remove( $file );
			return self::err( 'db', __( 'Something went wrong. Please try again later.', 'recipe-community' ), 500 );
		}
		RCOM_Identity::send_confirm( $new );
		$r         = $thanks->get_data();
		$r['html'] = RCOM_Front::item( RCOM_Reviews::get( $new ) );
		return self::out( $r );
	}

	public static function delete( WP_REST_Request $q ) {
		$rv = RCOM_Reviews::get( (int) $q->get_param( 'id' ) );
		$me = RCOM_Identity::current();
		if ( ! $rv || ! $me || ! self::nonce_ok( $q, $rv->post_id ) || ! hash_equals( strtolower( $rv->email ), strtolower( $me['email'] ) ) ) {
			return self::err( 'denied', __( 'You cannot remove this review.', 'recipe-community' ), 403 );
		}
		RCOM_Reviews::delete( $rv->id );
		return self::out( array( 'ok' => true, 'message' => __( 'Your review has been removed.', 'recipe-community' ) ) );
	}

	public static function link( WP_REST_Request $q ) {
		$id    = (int) $q->get_param( 'post' );
		$email = sanitize_email( (string) $q->get_param( 'email' ) );
		if ( ! self::post_ok( $id ) || ! self::nonce_ok( $q, $id ) ) {
			return self::err( 'nonce', __( 'The form has expired. Please reload the page and try again.', 'recipe-community' ), 403 );
		}
		if ( ! is_email( $email ) ) {
			return self::err( 'email', __( 'Please enter a valid email address.', 'recipe-community' ) );
		}
		if ( ! RCOM_Identity::allow( 'link', 5 ) ) {
			return self::err( 'rate', __( 'Too many requests. Please try again later.', 'recipe-community' ), 429 );
		}
		RCOM_Identity::send_manage( $email, $id );
		return self::out( array( 'ok' => true, 'message' => __( 'If this address has reviews, a link is on its way. Check your inbox.', 'recipe-community' ) ) );
	}

	public static function forget( WP_REST_Request $q ) {
		RCOM_Identity::clear_cookie();
		return self::out( array( 'ok' => true ) );
	}
}
