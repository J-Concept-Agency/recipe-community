<?php
defined( 'ABSPATH' ) || exit;

/** Own copy of the terms text per version, so agreement stays provable without relying on post revisions. */
class RCOM_Terms {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rcom_terms_versions';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta( "CREATE TABLE {$t} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  page_id bigint(20) unsigned NOT NULL,
  version_gmt datetime NOT NULL,
  content_hash char(32) NOT NULL,
  title varchar(255) NOT NULL DEFAULT '',
  content longtext NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY page_version (page_id,version_gmt),
  KEY page_hash (page_id,content_hash)
) " . $wpdb->get_charset_collate() . ';' );
	}

	/**
	 * Make sure the current version of the terms page has a snapshot; returns the version (post_modified_gmt).
	 * Only called when a review is saved. Identical text (same hash) is not stored twice.
	 */
	public static function current( $page_id ) {
		$page = $page_id ? get_post( $page_id ) : null;
		if ( ! $page ) {
			return '';
		}
		$v = (string) $page->post_modified_gmt;
		if ( ! RCOM_Settings::get( 'terms_snapshots' ) ) {
			return $v;
		}
		global $wpdb;
		$GLOBALS['post'] = $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $page );
		$html = wp_kses_post( (string) apply_filters( 'the_content', $page->post_content ) ); // scripts and event handlers are stripped
		wp_reset_postdata();
		$hash = md5( $page->post_title . '|' . $html );
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE page_id = %d AND content_hash = %s LIMIT 1', $page_id, $hash ) ) ) {
			$wpdb->insert( self::table(), array( 'page_id' => (int) $page_id, 'version_gmt' => $v, 'content_hash' => $hash, 'title' => mb_substr( $page->post_title, 0, 255 ), 'content' => $html, 'created_at' => current_time( 'mysql', true ) ) );
		}
		return $v;
	}

	/** Snapshot valid for a version: the newest one not newer than it. */
	public static function get( $page_id, $version ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE page_id = %d AND version_gmt <= %s ORDER BY version_gmt DESC LIMIT 1', $page_id, $version ) );
	}
}
