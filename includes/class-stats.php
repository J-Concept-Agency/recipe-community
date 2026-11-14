<?php
defined( 'ABSPATH' ) || exit;

/** Dashboard statistics: a few SQL aggregates, cached for 10 minutes per date range. Admin only. */
class RCOM_Stats {

	const RANGES = array( 7, 30, 90, 0 ); // days, 0 = everything

	public static function flush() {
		foreach ( self::RANGES as $d ) {
			delete_transient( 'rcom_stats_' . $d );
		}
	}

	public static function get( $days ) {
		$days = in_array( (int) $days, self::RANGES, true ) ? (int) $days : 30;
		$c    = get_transient( 'rcom_stats_' . $days );
		if ( is_array( $c ) ) {
			return $c;
		}
		global $wpdb;
		$t     = RCOM_Reviews::table();
		$where = $days ? $wpdb->prepare( 'WHERE created_at >= %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) : '';
		$k     = $wpdb->get_row( "SELECT COUNT(*) AS n, AVG(rating) AS avg, SUM(body <> '') AS txt, SUM(photo_file <> '') AS photo, SUM(verified) AS ver, SUM(newsletter_optin) AS opt, SUM(newsletter_optin AND verified) AS optok FROM {$t} {$where}", ARRAY_A );
		$n     = (int) $k['n'];
		$dist  = array_fill( 1, 5, 0 );
		foreach ( $wpdb->get_results( "SELECT rating, COUNT(*) AS c FROM {$t} {$where} GROUP BY rating" ) as $r ) {
			$dist[ (int) $r->rating ] = (int) $r->c;
		}
		// Reviews per week, last 12 weeks (independent of the range, so the chart always has a shape).
		$weeks = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$weeks[ gmdate( 'o-W', time() - $i * WEEK_IN_SECONDS ) ] = 0;
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(created_at, '%%x-%%v') AS w, COUNT(*) AS c FROM {$t} WHERE created_at >= %s GROUP BY w", gmdate( 'Y-m-d', time() - 12 * WEEK_IN_SECONDS ) ) ) as $r ) {
			if ( isset( $weeks[ $r->w ] ) ) {
				$weeks[ $r->w ] = (int) $r->c;
			}
		}
		$prev = null;
		if ( $days ) {
			$prev = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, AVG(rating) AS avg FROM {$t} WHERE created_at >= %s AND created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 2 * $days * DAY_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ), ARRAY_A );
		}
		$pct = static function ( $x ) use ( $n ) {
			return $n ? round( 100 * (int) $x / $n ) : 0;
		};
		$out = array(
			'n'       => $n,
			'prev_n'  => $prev ? (int) $prev['n'] : null,
			'prev_avg' => ( $prev && (int) $prev['n'] ) ? round( (float) $prev['avg'], 2 ) : null,
			'avg'     => $n ? round( (float) $k['avg'], 2 ) : 0,
			'txt'     => $pct( $k['txt'] ),
			'photo'   => $pct( $k['photo'] ),
			'ver'     => $pct( $k['ver'] ),
			'opt'     => $pct( $k['opt'] ),
			'optok'   => (int) $k['optok'],
			'pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE photo_status = 'pending'" ),
			'dist'    => $dist,
			'weeks'   => $weeks,
			'top'     => $wpdb->get_results( "SELECT post_id, COUNT(*) AS n, AVG(rating) AS avg FROM {$t} {$where} GROUP BY post_id ORDER BY n DESC LIMIT 5", ARRAY_A ),
			'best'    => $wpdb->get_results( "SELECT post_id, COUNT(*) AS n, AVG(rating) AS avg FROM {$t} {$where} GROUP BY post_id ORDER BY avg DESC, n DESC LIMIT 5", ARRAY_A ),
			'worst'   => $wpdb->get_results( "SELECT post_id, COUNT(*) AS n, AVG(rating) AS avg FROM {$t} {$where} GROUP BY post_id ORDER BY avg ASC, n DESC LIMIT 5", ARRAY_A ),
			'least'   => $wpdb->get_results( "SELECT post_id, SUM(vote = 'yes') AS y, SUM(vote = 'no') AS n FROM " . RCOM_Helpful::votes() . ' GROUP BY post_id HAVING (y + n) >= 1 ORDER BY (n / (y + n)) DESC, n DESC LIMIT 5', ARRAY_A ),
			'dev_r'   => RCOM_Device::counts( $t ),
			'dev_v'   => RCOM_Device::counts( RCOM_Helpful::votes() ),
			'recent'  => $wpdb->get_results( "SELECT id, post_id, rating, name, body, created_at FROM {$t} ORDER BY created_at DESC, id DESC LIMIT 5", ARRAY_A ),
		);
		set_transient( 'rcom_stats_' . $days, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}
}
