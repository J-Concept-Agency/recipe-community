<?php
defined( 'ABSPATH' ) || exit;

/**
 * Coarse device category from the request headers. The User-Agent is parsed in memory only;
 * only one of a few category names is ever stored. Bots give '' (nothing is stored).
 *
 * Limit: an iPad in "desktop mode" reports itself as a Mac and is counted as 'mac'.
 */
class RCOM_Device {

	const LABELS = array( 'iphone' => 'iPhone', 'ipad' => 'iPad', 'android_phone' => 'Android phone', 'android_tablet' => 'Android tablet', 'windows' => 'Windows', 'mac' => 'Mac', 'linux' => 'Linux', 'other' => 'Other' );

	public static function detect() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|curl|wget|python|headless|lighthouse|preview|monitor|HeadlessChrome/i', $ua ) ) {
			return '';
		}
		$plat   = isset( $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ) ? strtolower( trim( (string) wp_unslash( $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ), " \"'" ) ) : '';
		$mobile = isset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) && '?1' === trim( (string) $_SERVER['HTTP_SEC_CH_UA_MOBILE'] );
		if ( preg_match( '/iPhone|iPod/i', $ua ) ) {
			$d = 'iphone';
		} elseif ( preg_match( '/iPad/i', $ua ) ) {
			$d = 'ipad';
		} elseif ( preg_match( '/Android/i', $ua ) || 'android' === $plat ) {
			$d = ( $mobile || preg_match( '/Mobile/i', $ua ) ) ? 'android_phone' : 'android_tablet';
		} elseif ( preg_match( '/Windows/i', $ua ) || 'windows' === $plat ) {
			$d = 'windows';
		} elseif ( preg_match( '/Macintosh|Mac OS X/i', $ua ) || 'macos' === $plat ) {
			$d = 'mac';
		} elseif ( preg_match( '/Linux|X11|CrOS/i', $ua ) || in_array( $plat, array( 'linux', 'chrome os' ), true ) ) {
			$d = 'linux';
		} else {
			$d = 'other';
		}
		return $d;
	}

	/** @return array<string,int> category => count for one table (aggregated, never per person). */
	public static function counts( $table ) {
		global $wpdb;
		$out = array();
		foreach ( $wpdb->get_results( "SELECT device, COUNT(*) AS c FROM {$table} WHERE device <> '' GROUP BY device ORDER BY c DESC" ) as $r ) {
			$out[ $r->device ] = (int) $r->c;
		}
		return $out;
	}
}
