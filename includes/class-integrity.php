<?php
defined( 'ABSPATH' ) || exit;

/** Light integrity check (plugin screens and a daily cron job) and cache rebuild. */
class RCOM_Integrity {

	public static function init() {
		add_action( 'rcom_daily', array( __CLASS__, 'cron' ) );
	}

	public static function schedule() { // admin only, so the front end never pays for it
		if ( ! wp_next_scheduled( 'rcom_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'rcom_daily' );
		}
	}

	public static function tables() {
		return array( RCOM_Reviews::table(), RCOM_Helpful::votes(), RCOM_Helpful::feedback(), RCOM_Terms::table() );
	}

	/** @return string[] names of missing tables (one query) */
	public static function missing() {
		global $wpdb;
		$have = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'rcom_' ) . '%' ) );
		return array_values( array_diff( self::tables(), $have ) );
	}

	public static function cron() {
		$m = self::missing();
		update_option( 'rcom_integrity', $m, false );
		RCOM_Stats::flush();
	}

	/** Recreate missing tables. Lost rows cannot be recovered. */
	public static function repair() {
		RCOM_Reviews::install();
		delete_option( 'rcom_integrity' );
	}

	/** Recompute every cached rating and helpful count from the tables. */
	public static function rebuild_cache() {
		global $wpdb;
		$ids = array_unique( array_merge(
			array_map( 'intval', $wpdb->get_col( 'SELECT DISTINCT post_id FROM ' . RCOM_Reviews::table() ) ),
			array_map( 'intval', $wpdb->get_col( 'SELECT DISTINCT post_id FROM ' . RCOM_Helpful::votes() ) )
		) );
		foreach ( $ids as $id ) {
			RCOM_Reviews::refresh( $id );
			RCOM_Helpful::refresh( $id );
		}
		return count( $ids );
	}
}
