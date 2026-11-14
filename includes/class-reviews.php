<?php
defined( 'ABSPATH' ) || exit;

/** Reviews domain: table, CRUD and cached rating stats. All queries are prepared. */
class RCOM_Reviews {

	const DB_VERSION = '4';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rcom_reviews';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta( "CREATE TABLE {$t} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  rating tinyint(1) unsigned NOT NULL,
  body text NOT NULL,
  name varchar(80) NOT NULL DEFAULT '',
  email varchar(190) NOT NULL DEFAULT '',
  bbq_type varchar(40) NOT NULL DEFAULT '',
  photo_file varchar(80) NOT NULL DEFAULT '',
  photo_status varchar(10) NOT NULL DEFAULT 'none',
  status varchar(10) NOT NULL DEFAULT 'published',
  verified tinyint(1) unsigned NOT NULL DEFAULT 0,
  newsletter_optin tinyint(1) unsigned NOT NULL DEFAULT 0,
  token_hash char(64) NOT NULL DEFAULT '',
  device varchar(20) NOT NULL DEFAULT '',
  terms_accepted_at datetime DEFAULT NULL,
  terms_page_id bigint(20) unsigned NOT NULL DEFAULT 0,
  terms_version varchar(40) NOT NULL DEFAULT '',
  reply_text text NOT NULL,
  reply_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY post_status (post_id,status),
  KEY email (email),
  KEY token_hash (token_hash)
) " . $wpdb->get_charset_collate() . ';' );
		RCOM_Helpful::install();
		update_option( 'rcom_db_version', self::DB_VERSION, true ); // autoloaded: read on every request
		RCOM_Photo::ensure_dir();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'rcom_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	public static function insert( array $d ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = wp_parse_args( $d, array(
			'body' => '', 'name' => '', 'email' => '', 'bbq_type' => '', 'photo_file' => '', 'photo_status' => 'none',
			'status' => 'published', 'verified' => 0, 'newsletter_optin' => 0, 'token_hash' => '', 'device' => '', 'reply_text' => '',
		) );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		if ( ! $wpdb->insert( self::table(), $row ) ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		self::refresh( (int) $row['post_id'] );
		return $id;
	}

	public static function update( $id, array $d ) {
		global $wpdb;
		$r = self::get( $id );
		if ( ! $r ) {
			return false;
		}
		$d['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( self::table(), $d, array( 'id' => (int) $id ) );
		self::refresh( (int) $r->post_id );
		return true;
	}

	/** Delete one review and its photo. */
	public static function delete( $id ) {
		global $wpdb;
		$r = self::get( $id );
		if ( ! $r ) {
			return;
		}
		RCOM_Photo::remove( $r->photo_file );
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		self::refresh( (int) $r->post_id );
	}

	public static function delete_for_post( $post_id ) {
		global $wpdb;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE post_id = %d LIMIT 1', $post_id ) ) ) {
			return;
		}
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT photo_file FROM ' . self::table() . " WHERE post_id = %d AND photo_file <> ''", $post_id ) ) as $f ) {
			RCOM_Photo::remove( $f );
		}
		$wpdb->delete( self::table(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
		self::refresh( (int) $post_id );
	}

	/** Published reviews of a post, newest first. No email address in the result. */
	public static function list( $post_id, $limit, $offset = 0 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT id, rating, body, name, bbq_type, photo_file, photo_status, reply_text, reply_at, created_at FROM ' . self::table() . " WHERE post_id = %d AND status = 'published' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
			$post_id, $limit, $offset
		) );
	}

	/** Recompute the cached values in postmeta. Called after every change. */
	public static function refresh( $post_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT rating, verified, COUNT(*) AS n FROM ' . self::table() . " WHERE post_id = %d AND status = 'published' GROUP BY rating, verified",
			$post_id
		) );
		$dist = array_fill( 1, 5, 0 );
		$vsum = $vcnt = 0;
		foreach ( $rows as $r ) {
			$dist[ (int) $r->rating ] += (int) $r->n;
			if ( $r->verified ) {
				$vsum += $r->rating * $r->n;
				$vcnt += $r->n;
			}
		}
		update_post_meta( $post_id, '_rcom_avg', $vcnt ? round( $vsum / $vcnt, 2 ) : 0 );
		update_post_meta( $post_id, '_rcom_count', array_sum( $dist ) );
		update_post_meta( $post_id, '_rcom_vcount', $vcnt );
		update_post_meta( $post_id, '_rcom_dist', $dist );
		delete_transient( 'rcom_pending' );
		RCOM_Stats::flush();
		do_action( 'rcom_reviews_changed', $post_id );
		do_action( 'litespeed_purge_post', $post_id );
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}
	}

	public static function rating( $post_id ) {
		$dist = get_post_meta( $post_id, '_rcom_dist', true );
		$dist = is_array( $dist ) ? $dist : array_fill( 1, 5, 0 );
		$n    = array_sum( $dist );
		$sum  = 0;
		foreach ( $dist as $s => $c ) {
			$sum += $s * $c;
		}
		return array(
			'avg'            => (float) get_post_meta( $post_id, '_rcom_avg', true ),
			'count'          => (int) $n,
			'verified_count' => (int) get_post_meta( $post_id, '_rcom_vcount', true ),
			'avg_all'        => $n ? round( $sum / $n, 2 ) : 0.0,
			'dist'           => $dist,
		);
	}

	public static function pending_photos() {
		global $wpdb;
		$n = get_transient( 'rcom_pending' );
		if ( false === $n ) {
			$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE photo_status = 'pending'" );
			set_transient( 'rcom_pending', $n, DAY_IN_SECONDS );
		}
		return (int) $n;
	}
}
