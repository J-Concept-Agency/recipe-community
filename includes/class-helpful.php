<?php
defined( 'ABSPATH' ) || exit;

/**
 * "Was this helpful?" domain: votes, optional feedback text, shortcode [rcom_helpful] and REST routes.
 * Anonymous by default. A visitor identified through a review confirmation link gets the vote linked to a
 * keyed hash of that identity (never the email itself), so it can be changed or removed later.
 */
class RCOM_Helpful {

	const COOKIE_DAYS = 30;

	public static function votes() {
		global $wpdb;
		return $wpdb->prefix . 'rcom_votes';
	}

	public static function feedback() {
		global $wpdb;
		return $wpdb->prefix . 'rcom_feedback';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$v = self::votes();
		$f = self::feedback();
		$c = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$v} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  vote varchar(3) NOT NULL,
  token_hash char(64) NOT NULL DEFAULT '',
  identity_id char(64) NOT NULL DEFAULT '',
  is_demo tinyint(1) unsigned NOT NULL DEFAULT 0,
  device varchar(20) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY post_vote (post_id,vote),
  KEY identity_post (identity_id,post_id),
  KEY token_hash (token_hash)
) {$c};" );
		dbDelta( "CREATE TABLE {$f} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  vote_id bigint(20) unsigned NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  body text NOT NULL,
  body_hash char(32) NOT NULL DEFAULT '',
  status varchar(10) NOT NULL DEFAULT 'published',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY post_status (post_id,status),
  KEY vote_id (vote_id)
) {$c};" );
	}

	public static function init() {
		add_shortcode( 'rcom_helpful', array( __CLASS__, 'shortcode' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_for_post' ) );
		add_action( 'wp_enqueue_scripts', function () {
			wp_register_style( 'rcom-helpful', apply_filters( 'rcom_load_default_css', true ) ? RCOM_URL . 'assets/rcom-helpful.css' : false, array(), RCOM_VERSION );
			wp_register_script( 'rcom-helpful', RCOM_URL . 'assets/rcom-helpful.js', array(), RCOM_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		} );
	}

	/* ---------- identity and cookie ---------- */

	/** Keyed hash of the identified visitor (from the review confirmation cookie), or ''. */
	public static function identity() {
		$me = RCOM_Identity::current();
		return $me ? hash_hmac( 'sha256', 'helpful|' . strtolower( $me['email'] ), wp_salt() ) : '';
	}

	public static function identity_for( $email ) {
		return hash_hmac( 'sha256', 'helpful|' . strtolower( $email ), wp_salt() );
	}

	private static function cookie_name( $post_id ) {
		return 'rcom_v_' . (int) $post_id;
	}

	private static function set_cookie( $post_id, $vote_id, $token ) {
		$p = $vote_id . '.' . $token;
		$v = $p . '.' . hash_hmac( 'sha256', $post_id . '|' . $p, wp_salt( 'auth' ) );
		$_COOKIE[ self::cookie_name( $post_id ) ] = $v;
		if ( ! headers_sent() ) {
			setcookie( self::cookie_name( $post_id ), $v, array( 'expires' => time() + self::COOKIE_DAYS * DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
	}

	private static function clear_cookie( $post_id ) {
		unset( $_COOKIE[ self::cookie_name( $post_id ) ] );
		if ( ! headers_sent() ) {
			setcookie( self::cookie_name( $post_id ), '', array( 'expires' => time() - DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
	}

	/** The visitor's own vote row for this post (by signed cookie, or by identity), or null. */
	public static function mine( $post_id ) {
		global $wpdb;
		$name = self::cookie_name( $post_id );
		if ( isset( $_COOKIE[ $name ] ) ) {
			$p = explode( '.', (string) wp_unslash( $_COOKIE[ $name ] ) );
			if ( 3 === count( $p ) && ctype_digit( $p[0] ) && hash_equals( hash_hmac( 'sha256', $post_id . '|' . $p[0] . '.' . $p[1], wp_salt( 'auth' ) ), $p[2] ) ) {
				$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::votes() . ' WHERE id = %d AND post_id = %d AND token_hash = %s', $p[0], $post_id, hash( 'sha256', $p[1] ) ) );
				if ( $r ) {
					return $r;
				}
			}
		}
		$i = self::identity();
		return $i ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::votes() . ' WHERE post_id = %d AND identity_id = %s ORDER BY id DESC LIMIT 1', $post_id, $i ) ) : null;
	}

	/* ---------- counts ---------- */

	public static function refresh( $post_id ) {
		global $wpdb;
		$y = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::votes() . " WHERE post_id = %d AND vote = 'yes'", $post_id ) ); // incl. demo: shown on the page only
		$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::votes() . " WHERE post_id = %d AND vote = 'no'", $post_id ) );
		update_post_meta( $post_id, '_rcom_helpful_yes', $y );
		update_post_meta( $post_id, '_rcom_helpful_no', $n );
		RCOM_Stats::flush();
		do_action( 'litespeed_purge_post', $post_id );
		return array( $y, $n );
	}

	public static function counts( $post_id ) {
		return array( (int) get_post_meta( $post_id, '_rcom_helpful_yes', true ), (int) get_post_meta( $post_id, '_rcom_helpful_no', true ) );
	}

	/** Public sentence, only from the threshold on. */
	public static function count_text( $yes ) {
		if ( $yes < max( 1, (int) RCOM_Settings::get( 'helpful_threshold' ) ) ) {
			return '';
		}
		/* translators: %s: number of people */
		return sprintf( _n( '%s person found this article helpful.', '%s people found this article helpful.', $yes, 'recipe-community' ), number_format_i18n( $yes ) );
	}

	public static function delete_for_post( $post_id ) {
		global $wpdb;
		$wpdb->delete( self::feedback(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
		$wpdb->delete( self::votes(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
	}

	/* ---------- shortcode ---------- */

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'post_id' => 0 ), $atts );
		$id   = $atts['post_id'] ? (int) $atts['post_id'] : (int) get_the_ID();
		if ( ! $id || ! RCOM_Settings::helpful_enabled_for( $id ) ) {
			return '';
		}
		RCOM_Style::enqueue( 'rcom-helpful' );
		wp_enqueue_script( 'rcom-helpful' );
		$yl = (string) RCOM_Settings::get( 'helpful_yes_label' );
		$nl = (string) RCOM_Settings::get( 'helpful_no_label' );
		$t  = array(
			'ask'   => __( 'Was this article helpful?', 'recipe-community' ),
			'thanks' => __( 'Thanks for your feedback.', 'recipe-community' ),
			'yes_q' => '' !== $yl ? $yl : __( 'What did you like?', 'recipe-community' ),
			'no_q'  => '' !== $nl ? $nl : __( 'What could be better?', 'recipe-community' ),
			'yes_t' => __( 'Good to hear, thank you.', 'recipe-community' ),
			'no_t'  => __( 'Sorry about that.', 'recipe-community' ),
			'sent'  => __( 'Thank you, your feedback has been sent.', 'recipe-community' ),
			'err'   => __( 'Something went wrong. Please try again.', 'recipe-community' ),
			'count' => array( 'one' => _n( '%s person found this article helpful.', '%s people found this article helpful.', 1, 'recipe-community' ), 'other' => _n( '%s person found this article helpful.', '%s people found this article helpful.', 2, 'recipe-community' ) ),
			'remove' => __( 'Remove my vote', 'recipe-community' ),
			'thr'   => max( 1, (int) RCOM_Settings::get( 'helpful_threshold' ) ),
		);
		list( $y ) = self::counts( $id );
		RCOM_Front::i18n( 'hf', $t );
		return '<div class="rcom-helpful" role="group" aria-labelledby="rcom-h' . $id . '" data-post="' . $id . '" data-ts="' . esc_attr( RCOM_Settings::get( 'helpful_ts' ) && RCOM_Settings::turnstile_ready() ? RCOM_Settings::turnstile_site() : '' ) . '" data-yes="' . $y . '"><strong id="rcom-h' . $id . '">' . esc_html( $t['ask'] ) . '</strong>'
			. '<button type="button" class="rcom-hbtn" data-v="yes" aria-pressed="false">' . esc_html__( 'Yes', 'recipe-community' ) . '</button><button type="button" class="rcom-hbtn" data-v="no" aria-pressed="false">' . esc_html__( 'No', 'recipe-community' ) . '</button>'
			. '<span class="rcom-hcount" aria-live="polite">' . esc_html( self::count_text( $y ) ) . '</span>'
			. '<form class="rcom-hform" hidden><label for="rcom-hf' . $id . '"></label><textarea id="rcom-hf' . $id . '" name="body" maxlength="1000" rows="3"></textarea><div class="rcom-hp" aria-hidden="true" style="position:absolute;left:-9999px"><input type="text" name="website" tabindex="-1" autocomplete="off"></div><div class="rcom-hts"></div><div class="rcom-hrow"><button type="submit" class="rcom-hbtn go">' . esc_html__( 'Send', 'recipe-community' ) . '</button><span class="rcom-hsmall">' . esc_html__( 'Anonymous. Optional.', 'recipe-community' ) . '</span></div></form>'
			. '<p class="rcom-hmsg" role="status" aria-live="polite"></p></div>' . RCOM_Front::credit();
	}

	/* ---------- REST ---------- */

	public static function routes() {
		$pub = array( 'permission_callback' => '__return_true' );
		register_rest_route( 'rcom/v1', '/helpful/state', $pub + array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_state' ), 'args' => array( 'post' => array( 'type' => 'integer', 'required' => true ) ) ) );
		register_rest_route( 'rcom/v1', '/helpful/vote', $pub + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_vote' ) ) );
		register_rest_route( 'rcom/v1', '/helpful/feedback', $pub + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_feedback' ) ) );
		register_rest_route( 'rcom/v1', '/helpful/remove', $pub + array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_remove' ) ) );
	}

	private static function out( array $d, $status = 200 ) {
		$r = new WP_REST_Response( $d, $status );
		$r->header( 'Cache-Control', 'no-store, private' );
		return $r;
	}

	private static function err( $code, $msg, $status = 400 ) {
		return new WP_Error( 'rcom_h_' . $code, $msg, array( 'status' => $status ) );
	}

	private static function post_id( WP_REST_Request $q ) {
		$id = (int) $q->get_param( 'post' );
		return ( $id && 'publish' === get_post_status( $id ) && RCOM_Settings::helpful_enabled_for( $id ) ) ? $id : 0;
	}

	private static function stamp_ok( WP_REST_Request $q, $id, $min ) {
		$p = explode( '.', (string) $q->get_param( 'stamp' ) );
		if ( 2 !== count( $p ) || ! ctype_digit( $p[0] ) || ! hash_equals( hash_hmac( 'sha256', 'hstamp' . $id . '.' . $p[0], wp_salt() ), $p[1] ) ) {
			return false;
		}
		$age = time() - (int) $p[0];
		return $age >= $min && $age <= DAY_IN_SECONDS;
	}

	private static function guard( WP_REST_Request $q, $id, $min = 1 ) {
		if ( ! wp_verify_nonce( (string) $q->get_param( 'nonce' ), 'rcom_h_' . $id ) || ! self::stamp_ok( $q, $id, 0 ) ) {
			return self::err( 'nonce', __( 'Something went wrong. Please try again.', 'recipe-community' ), 403 );
		}
		if ( ! self::stamp_ok( $q, $id, $min ) ) {
			return self::err( 'fast', __( 'Please wait a moment and try again.', 'recipe-community' ), 429 );
		}
		return true;
	}

	private static function state_data( $id ) {
		$m  = self::mine( $id );
		$y  = self::counts( $id )[0];
		$t  = time();
		return array(
			'nonce' => wp_create_nonce( 'rcom_h_' . $id ),
			'stamp' => $t . '.' . hash_hmac( 'sha256', 'hstamp' . $id . '.' . $t, wp_salt() ),
			'vote'  => $m ? $m->vote : '',
			'known' => (bool) ( $m && $m->identity_id ),
			'yes'   => $y,
		);
	}

	public static function r_state( WP_REST_Request $q ) {
		$id = self::post_id( $q );
		return $id ? self::out( self::state_data( $id ) ) : self::err( 'post', __( 'Something went wrong. Please try again.', 'recipe-community' ), 404 );
	}

	public static function r_vote( WP_REST_Request $q ) {
		global $wpdb;
		$id = self::post_id( $q );
		$v  = (string) $q->get_param( 'vote' );
		if ( ! $id || ! in_array( $v, array( 'yes', 'no' ), true ) ) {
			return self::err( 'post', __( 'Something went wrong. Please try again.', 'recipe-community' ) );
		}
		$g = self::guard( $q, $id );
		if ( true !== $g ) {
			return $g;
		}
		if ( '' !== (string) $q->get_param( 'website' ) ) {
			return self::out( array( 'ok' => true, 'vote' => $v ) ); // honeypot: pretend
		}
		if ( ! RCOM_Identity::allow( 'vote', 30 ) ) {
			return self::err( 'rate', __( 'Something went wrong. Please try again.', 'recipe-community' ), 429 );
		}
		$m = self::mine( $id );
		$now = current_time( 'mysql', true );
		if ( $m ) {
			if ( $m->vote !== $v ) { // an identified or cookie-holding visitor may change the choice, never vote twice
				$wpdb->update( self::votes(), array( 'vote' => $v, 'updated_at' => $now ), array( 'id' => (int) $m->id ) );
			}
			$vid = (int) $m->id;
		} else {
			$token = bin2hex( random_bytes( 16 ) );
			$wpdb->insert( self::votes(), array( 'post_id' => $id, 'vote' => $v, 'token_hash' => hash( 'sha256', $token ), 'identity_id' => self::identity(), 'device' => RCOM_Device::detect(), 'created_at' => $now, 'updated_at' => $now ) );
			$vid = (int) $wpdb->insert_id;
			self::set_cookie( $id, $vid, $token );
		}
		list( $y ) = self::refresh( $id );
		return self::out( array( 'ok' => true, 'vote' => $v, 'yes' => $y ) );
	}

	public static function r_feedback( WP_REST_Request $q ) {
		global $wpdb;
		$id = self::post_id( $q );
		if ( ! $id ) {
			return self::err( 'post', __( 'Something went wrong. Please try again.', 'recipe-community' ) );
		}
		$g = self::guard( $q, $id, 2 );
		if ( true !== $g ) {
			return $g;
		}
		$ok = self::out( array( 'ok' => true ) );
		if ( '' !== (string) $q->get_param( 'website' ) ) {
			return $ok;
		}
		$m = self::mine( $id );
		if ( ! $m ) {
			return self::err( 'vote', __( 'Something went wrong. Please try again.', 'recipe-community' ), 403 );
		}
		if ( ! RCOM_Identity::allow( 'hfb', 5 ) ) {
			return self::err( 'rate', __( 'Something went wrong. Please try again.', 'recipe-community' ), 429 );
		}
		if ( RCOM_Settings::get( 'helpful_ts' ) && RCOM_Settings::turnstile_ready() && ! RCOM_Turnstile::verify( $q->get_param( 'cf-turnstile-response' ) ) ) {
			return self::err( 'turnstile', __( 'The spam check failed. Please try again.', 'recipe-community' ), 403 );
		}
		$body = trim( wp_strip_all_tags( sanitize_textarea_field( (string) $q->get_param( 'body' ) ) ) );
		if ( '' === $body ) {
			return $ok; // nothing to store: sending without text is fine
		}
		if ( mb_strlen( $body ) > 1000 ) {
			$body = mb_substr( $body, 0, 1000 );
		}
		if ( ! RCOM_Settings::get( 'helpful_links' ) && preg_match( '~https?://|www\.|\b[a-z0-9-]+\.(com|nl|net|org|ru|xyz|top|info|io|co)\b~i', $body ) ) {
			return self::err( 'links', __( 'Please leave out links.', 'recipe-community' ) );
		}
		$h = md5( $id . '|' . mb_strtolower( $body ) );
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::feedback() . ' WHERE post_id = %d AND body_hash = %s LIMIT 1', $id, $h ) ) ) {
			$wpdb->insert( self::feedback(), array( 'vote_id' => (int) $m->id, 'post_id' => $id, 'body' => $body, 'body_hash' => $h, 'status' => 'published', 'created_at' => current_time( 'mysql', true ) ) );
		}
		return $ok;
	}

	public static function r_remove( WP_REST_Request $q ) {
		global $wpdb;
		$id = self::post_id( $q );
		$g  = $id ? self::guard( $q, $id, 0 ) : self::err( 'post', __( 'Something went wrong. Please try again.', 'recipe-community' ) );
		if ( true !== $g ) {
			return $g;
		}
		$m = self::mine( $id );
		if ( $m ) {
			self::delete_vote( (int) $m->id );
			self::clear_cookie( $id );
			self::refresh( $id );
		}
		return self::out( array( 'ok' => true, 'yes' => self::counts( $id )[0] ) );
	}

	public static function delete_vote( $vote_id ) {
		global $wpdb;
		$wpdb->delete( self::feedback(), array( 'vote_id' => (int) $vote_id ), array( '%d' ) );
		$wpdb->delete( self::votes(), array( 'id' => (int) $vote_id ), array( '%d' ) );
	}

	/** Erase all votes and feedback of an identified person (privacy eraser). @return int */
	public static function erase_identity( $email ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, post_id FROM ' . self::votes() . ' WHERE identity_id = %s', self::identity_for( $email ) ) );
		foreach ( $rows as $r ) {
			self::delete_vote( $r->id );
			self::refresh( $r->post_id );
		}
		return count( $rows );
	}
}
