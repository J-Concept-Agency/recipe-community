<?php
defined( 'ABSPATH' ) || exit;

/** Admin screens (HTML). Everything is wrapped in .rcom-a so the CSS never touches the rest of WordPress. */
class RCOM_Views {

	/** Localised short date (11 nov 2026), optional time. */
	public static function dt( $gmt, $time = false ) {
		return wp_date( $time ? 'j M Y H:i' : 'j M Y', strtotime( $gmt . ' UTC' ) );
	}

	private static function url( $page, array $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
	}

	private static function head( $title, $intro, $actions = '' ) {
		echo '<div class="wrap rcom-a"><header class="rcom-a-head"><div><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $intro ) . '</p></div><div class="rcom-a-actions">' . $actions . '</div></header>';
		if ( isset( $_GET['css_long'] ) ) {
			echo '<div class="rcom-a-flash err" role="alert">' . esc_html( sprintf( /* translators: %d: size in KB */ __( 'Extra CSS is longer than %d KB and was not saved. The previous CSS is still active.', 'recipe-community' ), RCOM_Style::MAX_CSS / 1024 ) ) . '</div>';
		}
		if ( isset( $_GET['done'] ) ) {
			echo '<div class="rcom-a-flash" role="status">' . esc_html__( 'Done.', 'recipe-community' ) . '</div>';
		}
	}

	/** One component for every empty list or block. $hint decides the next action. */
	public static function empty_state( $title, $text, $btn = '', $url = '', $small = false ) {
		return '<div class="rcom-empty-s' . ( $small ? ' sm' : '' ) . '"><strong>' . esc_html( $title ) . '</strong><p>' . esc_html( $text ) . '</p>' . ( $btn ? '<a class="rcom-btn-a primary" href="' . esc_url( $url ) . '">' . esc_html( $btn ) . '</a>' : '' ) . '</div>';
	}

	/** Next step for an empty review list: depends on what is not set up yet. */
	private static function reviews_hint( $small = false ) {
		if ( ! RCOM_Settings::turnstile_ready() ) {
			return self::empty_state( __( 'Set up spam protection first', 'recipe-community' ), __( 'The review form stays hidden for visitors until the Cloudflare Turnstile keys are added.', 'recipe-community' ), __( 'Open settings', 'recipe-community' ), self::url( 'rcom-settings' ), $small );
		}
		$p = get_posts( array( 'post_type' => RCOM_Settings::post_types(), 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( ! $p ) {
			return self::empty_state( __( 'No recipes yet', 'recipe-community' ), __( 'Reviews are active on the post types chosen in the settings, but none of them has content yet.', 'recipe-community' ), __( 'Open settings', 'recipe-community' ), self::url( 'rcom-settings' ), $small );
		}
		return self::empty_state( __( 'No reviews yet', 'recipe-community' ), __( 'As soon as someone rates a recipe it shows up here, with the average and the distribution. Make sure [rcom_reviews] is on your recipe template.', 'recipe-community' ), __( 'View a recipe page', 'recipe-community' ), get_permalink( $p[0] ), $small );
	}

	/** Same footer under every list: count left, pagination right. */
	public static function foot( $total, $per, $page, $left = '' ) {
		/* translators: %s: number of items */
		$count = sprintf( _n( '%s item', '%s items', $total, 'recipe-community' ), number_format_i18n( $total ) );
		$pages = (int) ceil( $total / $per );
		return '<div class="rcom-fbar foot"><div class="rcom-fbar-l">' . ( $left ? $left : '<span class="rcom-muted">' . esc_html( $count ) . '</span>' ) . '</div><div class="rcom-fbar-r">' . ( $left ? '<span class="rcom-muted">' . esc_html( $count ) . '</span>' : '' ) . ( $pages > 1 ? '<span class="rcom-pager">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => $pages, 'prev_text' => '&lsaquo;', 'next_text' => '&rsaquo;' ) ) ) . '</span>' : '' ) . '</div></div>';
	}

	private static function pill( $kind, $text ) {
		return '<span class="rcom-pill ' . esc_attr( $kind ) . '">' . esc_html( $text ) . '</span>';
	}

	private static function stars( $n ) {
		$n = (int) round( $n );
		return '<span class="rcom-a-stars" aria-label="' . esc_attr( $n . ' / 5' ) . '">' . str_repeat( '★', $n ) . '<i>' . str_repeat( '★', 5 - $n ) . '</i></span>';
	}

	private static function initials( $name ) {
		return esc_html( mb_strtoupper( mb_substr( trim( $name ), 0, 1 ) ) ?: '?' );
	}

	private static function title( $post_id ) {
		return get_the_title( $post_id ) ?: sprintf( /* translators: %d: post id */ __( '#%d (deleted)', 'recipe-community' ), $post_id );
	}

	private static function thumb( $r ) {
		if ( ! $r->photo_file ) {
			return '';
		}
		$u = esc_url( RCOM_Photo::url( $r->photo_file ) );
		return '<a class="rcom-th" href="' . $u . '" target="_blank" rel="noopener" tabindex="0"><img src="' . esc_url( RCOM_Photo::thumb_url( $r->photo_file ) ) . '" width="44" height="44" loading="lazy" alt=""><img class="big" src="' . $u . '" loading="lazy" alt=""></a>';
	}

	private static function status_pills( $r ) {
		$h = $r->verified ? '<span class="rcom-ok">' . esc_html__( 'Verified', 'recipe-community' ) . '</span>' : '';
		if ( 'pending' === $r->photo_status ) {
			$h .= self::pill( 'warn', __( 'Photo waiting', 'recipe-community' ) );
		}
		if ( 'hidden' === $r->status ) {
			$h .= self::pill( 'off', __( 'Hidden', 'recipe-community' ) );
		}
		return $h;
	}

	/* ---------- Dashboard ---------- */

	public static function dashboard() {
		RCOM_Admin::guard();
		$range = in_array( (int) ( $_GET['range'] ?? 30 ), RCOM_Stats::RANGES, true ) ? (int) ( $_GET['range'] ?? 30 ) : 30;
		$s     = RCOM_Stats::get( $range );
		$days  = $range ? array( 'days' => $range ) : array();
		$tabs  = '';
		foreach ( RCOM_Stats::RANGES as $d ) {
			/* translators: %d: number of days */
			$tabs .= '<a class="rcom-seg' . ( $d === $range ? ' on' : '' ) . '" href="' . esc_url( self::url( 'rcom', array( 'range' => $d ) ) ) . '">' . esc_html( $d ? sprintf( __( '%d days', 'recipe-community' ), $d ) : __( 'All time', 'recipe-community' ) ) . '</a>';
		}
		self::head( __( 'Community', 'recipe-community' ), __( 'How your recipes are rated and what needs your attention.', 'recipe-community' ), '<div class="rcom-segs">' . $tabs . '</div>' );
		$rev  = function ( $a = array() ) use ( $days ) {
			return esc_url( self::url( 'rcom-reviews', $a + $days ) );
		};
		$kpi = function ( $label, $value, $sub, $url, $cls = '' ) {
			return '<a class="rcom-kpi ' . $cls . '" href="' . $url . '"><span class="rcom-k-l">' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong><span class="rcom-k-s">' . esc_html( $sub ) . '</span></a>';
		};
		$delta = '';
		if ( null !== $s['prev_n'] ) {
			$d = $s['n'] - $s['prev_n'];
			/* translators: %s: signed number of reviews compared with the previous period */
			$delta = sprintf( __( '%s vs previous period', 'recipe-community' ), ( $d > 0 ? '+' : '' ) . $d );
		}
		echo '<div class="rcom-kpis">';
		echo $kpi( __( 'Reviews', 'recipe-community' ), number_format_i18n( $s['n'] ), $delta, $rev() );
		$ad = ( null !== $s['prev_avg'] && $s['n'] ) ? sprintf( /* translators: %s: previous average */ __( 'was %s', 'recipe-community' ), number_format_i18n( $s['prev_avg'], 1 ) ) : '';
		echo $kpi( __( 'Average rating', 'recipe-community' ), $s['n'] ? number_format_i18n( $s['avg'], 1 ) : '–', $ad, $rev( array( 'orderby' => 'rating', 'order' => 'desc' ) ) );
		echo $kpi( __( 'With text', 'recipe-community' ), $s['txt'] . '%', '', $rev( array( 'has_text' => 1 ) ) );
		echo $kpi( __( 'With photo', 'recipe-community' ), $s['photo'] . '%', '', $rev( array( 'has_photo' => 1 ) ) );
		echo $kpi( __( 'Verified', 'recipe-community' ), $s['ver'] . '%', '', $rev( array( 'verified' => 1 ) ) );
		/* translators: %d: confirmed newsletter signups */
		echo $kpi( __( 'Newsletter opt-in', 'recipe-community' ), $s['opt'] . '%', sprintf( __( '%d confirmed', 'recipe-community' ), $s['optok'] ), $rev( array( 'optin' => 1 ) ) );
		echo $kpi( __( 'Photos waiting', 'recipe-community' ), (string) $s['pending'], '', esc_url( self::url( 'rcom-reviews', array( 'photo' => 1 ) ) ), $s['pending'] ? 'alert' : '' );
		echo '</div><div class="rcom-grid">';

		// Star distribution
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Star distribution', 'recipe-community' ) . '</h2>';
		$max = max( 1, max( $s['dist'] ) );
		for ( $i = 5; $i >= 1; $i-- ) {
			echo '<a class="rcom-dist" href="' . $rev( array( 'rating' => $i ) ) . '"><span>' . $i . ' ★</span><span class="rcom-bar"><i style="width:' . round( 100 * $s['dist'][ $i ] / $max ) . '%"></i></span><b>' . (int) $s['dist'][ $i ] . '</b></a>';
		}
		echo '</section>';

		// Reviews per week (inline SVG, y axis with ticks, values on hover)
		$w  = $s['weeks'];
		$mx = max( 4, (int) ceil( max( $w ) / 2 ) * 2 );
		$bw = 440 / count( $w );
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Reviews per week', 'recipe-community' ) . '</h2><svg class="rcom-chart" viewBox="0 0 480 160" role="img" aria-label="' . esc_attr__( 'Reviews per week, last 12 weeks', 'recipe-community' ) . '">';
		foreach ( array( 0, $mx / 2, $mx ) as $tick ) {
			$y = 130 - round( 110 * $tick / $mx );
			echo '<line x1="34" y1="' . $y . '" x2="480" y2="' . $y . '" class="rcom-grid-l"/><text x="28" y="' . ( $y + 4 ) . '" text-anchor="end" class="rcom-ax">' . (int) $tick . '</text>';
		}
		$i = 0;
		foreach ( $w as $wk => $c ) {
			$h = $c ? max( 2, round( 110 * $c / $mx ) ) : 0;
			$x = 40 + $i * $bw;
			echo '<g><title>' . esc_html( sprintf( /* translators: 1: ISO week, 2: number of reviews */ __( 'Week %1$s: %2$d', 'recipe-community' ), substr( $wk, -2 ), $c ) ) . '</title><rect x="' . $x . '" y="0" width="' . round( $bw, 1 ) . '" height="130" fill="transparent"/><rect x="' . round( $x + 4, 1 ) . '" y="' . ( 130 - $h ) . '" width="' . round( $bw - 8, 1 ) . '" height="' . $h . '" class="rcom-rect"/><text x="' . round( $x + $bw / 2, 1 ) . '" y="148" text-anchor="middle" class="rcom-ax">' . esc_html( substr( $wk, -2 ) ) . '</text></g>';
			$i++;
		}
		echo '</svg><p class="rcom-muted">' . esc_html__( 'ISO week number on the axis, last 12 weeks. Hover a bar for its value.', 'recipe-community' ) . '</p></section>';

		// Top lists
		$list = function ( $title, $rows, $show ) {
			echo '<section class="rcom-card"><h2>' . esc_html( $title ) . '</h2>';
			if ( ! $rows ) {
				echo self::empty_state( __( 'Nothing to show yet', 'recipe-community' ), __( 'This fills as reviews come in.', 'recipe-community' ), '', '', true );
			}
			foreach ( $rows as $r ) {
				echo '<a class="rcom-row" href="' . esc_url( self::url( 'rcom-recipes', array( 'post' => $r['post_id'] ) ) ) . '"><span class="rcom-grow">' . esc_html( self::title( $r['post_id'] ) ) . '</span>' . ( 'n' === $show ? '<b>' . (int) $r['n'] . '</b>' : self::stars( $r['avg'] ) . '<b>' . esc_html( number_format_i18n( $r['avg'], 1 ) ) . '</b>' ) . '</a>';
			}
			echo '</section>';
		};
		$list( __( 'Most reviewed recipes', 'recipe-community' ), $s['top'], 'n' );
		$list( __( 'Highest rated', 'recipe-community' ), $s['best'], 'avg' );
		$list( __( 'Lowest rated', 'recipe-community' ), $s['worst'], 'avg' );

		// Least helpful articles
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Least helpful articles', 'recipe-community' ) . '</h2>';
		if ( ! $s['least'] ) {
			echo self::empty_state( __( 'No votes yet', 'recipe-community' ), __( 'Put [rcom_helpful] on your article template.', 'recipe-community' ), '', '', true );
		}
		foreach ( $s['least'] as $r ) {
			$tot = max( 1, $r['y'] + $r['n'] );
			echo '<a class="rcom-row" href="' . esc_url( self::url( 'rcom-helpful', array( 'post' => $r['post_id'] ) ) ) . '"><span class="rcom-grow">' . esc_html( self::title( $r['post_id'] ) ) . '</span><span class="rcom-muted">' . (int) $r['n'] . ' / ' . (int) $tot . '</span><b>' . round( 100 * $r['y'] / $tot ) . '%</b></a>';
		}
		echo '</section>';

		// Devices (aggregated only)
		$dev = function ( $title, $rows ) {
			echo '<section class="rcom-card"><h2>' . esc_html( $title ) . '</h2>';
			$tot = array_sum( $rows );
			if ( ! $tot ) {
				echo self::empty_state( __( 'No data yet', 'recipe-community' ), __( 'Counts appear as visitors reply.', 'recipe-community' ), '', '', true );
			}
			foreach ( $rows as $k => $c ) {
				echo '<div class="rcom-dist wide"><span>' . esc_html( RCOM_Device::LABELS[ $k ] ?? $k ) . '</span><span class="rcom-bar"><i style="width:' . round( 100 * $c / max( $rows ) ) . '%"></i></span><b>' . (int) $c . ' · ' . round( 100 * $c / $tot ) . '%</b></div>';
			}
			echo '</section>';
		};
		$dev( __( 'Devices, reviews', 'recipe-community' ), $s['dev_r'] );
		$dev( __( 'Devices, helpful votes', 'recipe-community' ), $s['dev_v'] );

		// Recent
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Recent reviews', 'recipe-community' ) . '</h2>';
		if ( ! $s['recent'] ) {
			echo self::reviews_hint( true );
		}
		foreach ( $s['recent'] as $r ) {
			echo '<a class="rcom-row" href="' . esc_url( self::url( 'rcom-recipes', array( 'post' => $r['post_id'] ) ) . '#rcom-r' . $r['id'] ) . '"><span class="rcom-grow"><b>' . esc_html( $r['name'] ) . '</b> ' . self::stars( $r['rating'] ) . '<br><span class="rcom-muted">' . esc_html( self::title( $r['post_id'] ) . ( '' !== $r['body'] ? ' · ' . mb_substr( $r['body'], 0, 70 ) : '' ) ) . '</span></span></a>';
		}
		echo '</section></div></div>';
	}

	/* ---------- Reviews ---------- */

	private static function terms_view( $sid ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RCOM_Terms::table() . ' WHERE id = %d', $sid ) );
		self::head( $r ? $r->title : __( 'Terms', 'recipe-community' ), $r ? sprintf( /* translators: %s: date */ __( 'Terms text as it was on %s (read-only copy).', 'recipe-community' ), self::dt( $r->version_gmt, true ) ) : '', '<a class="rcom-btn-a" href="' . esc_url( self::url( 'rcom-reviews' ) ) . '">&larr; ' . esc_html__( 'All reviews', 'recipe-community' ) . '</a>' );
		echo '<section class="rcom-card rcom-terms">' . ( $r ? wp_kses_post( $r->content ) : self::empty_state( __( 'Not found', 'recipe-community' ), '', '', '', true ) ) . '</section></div>';
	}

	public static function reviews() {
		RCOM_Admin::guard();
		if ( 'terms' === ( $_GET['view'] ?? '' ) ) {
			self::terms_view( (int) ( $_GET['sid'] ?? 0 ) );
			return;
		}
		require_once RCOM_DIR . 'admin/class-table.php';
		$t = new RCOM_Table();
		$t->prepare_items();
		self::head( __( 'Reviews', 'recipe-community' ), __( 'Every review, newest first. Filter, search and handle several at once.', 'recipe-community' ), '<a class="rcom-btn-a" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rcom_export' ), 'rcom_export' ) ) . '">' . esc_html__( 'Export CSV', 'recipe-community' ) . '</a>' );
		$cnt = static function ( $t, $single, $plural, $n ) {
			return '%s item' === $single ? ( 1 === $n ? '%s ' . __( 'review', 'recipe-community' ) : '%s ' . __( 'reviews', 'recipe-community' ) ) : $t;
		};
		add_filter( 'ngettext', $cnt, 10, 4 ); // "13 items" becomes "13 reviews" inside this table only
		$any = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . RCOM_Reviews::table() . ' LIMIT 1' );
		if ( ! $any ) {
			echo '<section class="rcom-card flush">' . self::reviews_hint() . '</section></div>';
			remove_filter( 'ngettext', $cnt, 10 );
			return;
		}
		echo '<section class="rcom-card flush"><form method="get" class="rcom-filterform"><input type="hidden" name="page" value="rcom-reviews">';
		$t->filters();
		echo '</form><form method="post" action="' . esc_url( self::url( 'rcom-reviews' ) ) . '">';
		$t->display();
		echo '</form></section></div>';
		remove_filter( 'ngettext', $cnt, 10 );
	}

	/* ---------- By recipe ---------- */

	public static function recipes() {
		RCOM_Admin::guard();
		$pid = (int) ( $_GET['post'] ?? 0 );
		if ( $pid ) {
			self::recipe_detail( $pid );
			return;
		}
		global $wpdb;
		$t     = RCOM_Reviews::table();
		$per   = 25;
		$page  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$t} WHERE is_demo = 0" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, COUNT(*) AS n, AVG(rating) AS avg, MAX(created_at) AS last, SUM(photo_status = 'pending') AS pend, SUM(rating = 1) AS d1, SUM(rating = 2) AS d2, SUM(rating = 3) AS d3, SUM(rating = 4) AS d4, SUM(rating = 5) AS d5 FROM {$t} WHERE is_demo = 0 GROUP BY post_id ORDER BY last DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) );
		_prime_post_caches( array_map( 'intval', wp_list_pluck( $rows, 'post_id' ) ), false, false );
		self::head( __( 'By recipe', 'recipe-community' ), __( 'Which recipes get reviews, and how they score.', 'recipe-community' ) );
		if ( ! $rows ) {
			echo '<section class="rcom-card flush">' . self::reviews_hint() . '</section></div>';
			return;
		}
		echo '<section class="rcom-card flush"><div class="rcom-table">';
		{
			echo '<div class="rcom-tr head"><span class="rcom-grow">' . esc_html__( 'Recipe', 'recipe-community' ) . '</span><span class="num">' . esc_html__( 'Reviews', 'recipe-community' ) . '</span><span class="num">' . esc_html__( 'Average', 'recipe-community' ) . '</span><span class="rcom-hide-s">' . esc_html__( 'Distribution', 'recipe-community' ) . '</span><span class="rcom-hide-s">' . esc_html__( 'Last review', 'recipe-community' ) . '</span><span>' . esc_html__( 'Photos', 'recipe-community' ) . '</span></div>';
		}
		foreach ( $rows as $r ) {
			$mx   = max( 1, $r->d1, $r->d2, $r->d3, $r->d4, $r->d5 );
			$mini = '<span class="rcom-mini" aria-hidden="true">';
			for ( $i = 1; $i <= 5; $i++ ) {
				$mini .= '<i style="height:' . max( 2, round( 22 * $r->{'d' . $i} / $mx ) ) . 'px"></i>';
			}
			echo '<a class="rcom-tr" href="' . esc_url( self::url( 'rcom-recipes', array( 'post' => $r->post_id ) ) ) . '"><span class="rcom-grow"><b>' . esc_html( self::title( $r->post_id ) ) . '</b></span><span class="num">' . (int) $r->n . '</span><span class="num">' . esc_html( number_format_i18n( $r->avg, 1 ) ) . '</span><span class="rcom-hide-s">' . $mini . '</span></span><span class="rcom-hide-s rcom-muted">' . esc_html( self::dt( $r->last ) ) . '</span><span>' . ( $r->pend ? self::pill( 'warn', (string) $r->pend ) : '–' ) . '</span></a>';
		}
		echo '</div>' . self::foot( $total, $per, $page ) . '</section></div>';
	}

	private static function recipe_detail( $pid ) {
		global $wpdb;
		$back = self::url( 'rcom-recipes', array( 'post' => $pid ) );
		$r    = RCOM_Reviews::rating( $pid );
		self::head( self::title( $pid ), __( 'All reviews of this recipe.', 'recipe-community' ), '<a class="rcom-btn-a" href="' . esc_url( self::url( 'rcom-recipes' ) ) . '">&larr; ' . esc_html__( 'All recipes', 'recipe-community' ) . '</a> <a class="rcom-btn-a primary" href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View on site', 'recipe-community' ) . '</a>' );
		$reviews = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RCOM_Reviews::table() . ' WHERE post_id = %d ORDER BY created_at DESC, id DESC LIMIT 200', $pid ) );
		$all     = count( $reviews );
		echo '<section class="rcom-card rcom-sumcard"><div><span class="rcom-k-l">' . esc_html__( 'Average rating', 'recipe-community' ) . '</span><strong class="rcom-big">' . ( $r['count'] ? esc_html( number_format_i18n( $r['avg_all'], 1 ) ) : '–' ) . '</strong>' . self::stars( $r['avg_all'] ) . '<p class="rcom-muted">' . esc_html( sprintf( _n( '%s review', '%s reviews', $r['count'], 'recipe-community' ), number_format_i18n( $r['count'] ) ) ) . ' · ' . esc_html( sprintf( /* translators: %d: verified reviews */ __( '%d verified', 'recipe-community' ), $r['verified_count'] ) ) . '</p></div><div>';
		$max = max( 1, max( $r['dist'] ) );
		for ( $i = 5; $i >= 1; $i-- ) {
			echo '<div class="rcom-dist"><span>' . $i . ' ★</span><span class="rcom-bar"><i style="width:' . round( 100 * $r['dist'][ $i ] / $max ) . '%"></i></span><b>' . (int) $r['dist'][ $i ] . '</b></div>';
		}
		echo '</div></section>';
		if ( ! $all ) {
			echo '<section class="rcom-card">' . self::empty_state( __( 'No reviews for this recipe yet', 'recipe-community' ), __( 'Reviews appear here as soon as visitors post them.', 'recipe-community' ), __( 'View on site', 'recipe-community' ), get_permalink( $pid ), true ) . '</section>';
		}
		foreach ( $reviews as $rv ) {
			$act = static function ( $do ) use ( $rv, $back ) {
				return esc_url( RCOM_Admin::action_url( $do, $rv->id, $back ) );
			};
			echo '<article class="rcom-card rcom-rv" id="rcom-r' . (int) $rv->id . '"><header><span class="rcom-ava">' . self::initials( $rv->name ) . '</span><div class="rcom-grow"><b>' . esc_html( $rv->name ) . '</b> <span class="rcom-muted">' . esc_html( $rv->email ) . '</span><br>' . self::stars( $rv->rating ) . ' <span class="rcom-muted">' . esc_html( self::dt( $rv->created_at, true ) ) . '</span></div><div class="rcom-pills">' . self::status_pills( $rv ) . '</div></header>';
			if ( $rv->terms_accepted_at ) {
				$ver = $rv->terms_version ? self::dt( $rv->terms_version, true ) : '';
				$lnk = '';
				if ( $rv->terms_page_id && $rv->terms_version ) {
					$snap = RCOM_Terms::get( (int) $rv->terms_page_id, $rv->terms_version );
					if ( $snap ) {
						$lnk = ' <a href="' . esc_url( self::url( 'rcom-reviews', array( 'view' => 'terms', 'sid' => $snap->id ) ) ) . '">' . esc_html__( 'View text', 'recipe-community' ) . '</a>';
					} else {
						foreach ( wp_get_post_revisions( (int) $rv->terms_page_id, array( 'order' => 'DESC', 'orderby' => 'post_modified_gmt' ) ) as $rev ) { // fallback when snapshots were off
							if ( $rev->post_modified_gmt <= $rv->terms_version ) {
								$lnk = ' <a href="' . esc_url( admin_url( 'revision.php?revision=' . (int) $rev->ID ) ) . '">' . esc_html__( 'View revision', 'recipe-community' ) . '</a>';
								break;
							}
						}
					}
				}
				/* translators: 1: date of agreement, 2: date of the terms version */
				echo '<p class="rcom-muted">' . ( $ver ? sprintf( esc_html__( 'Agreed on %1$s to the terms of %2$s', 'recipe-community' ), esc_html( self::dt( $rv->terms_accepted_at, true ) ), esc_html( $ver ) ) : sprintf( esc_html__( 'Agreed on %s', 'recipe-community' ), esc_html( self::dt( $rv->terms_accepted_at, true ) ) ) ) . $lnk . '</p>';
			}
			echo ( '' !== $rv->body ? '<p>' . nl2br( esc_html( $rv->body ) ) . '</p>' : '<p class="rcom-muted">' . esc_html__( '(stars only)', 'recipe-community' ) . '</p>' );
			if ( $rv->photo_file ) {
				echo '<div class="rcom-photo-row">' . self::thumb( $rv ) . '<div>' . ( 'approved' !== $rv->photo_status ? '<a class="rcom-btn-a primary" href="' . $act( 'approve_photo' ) . '">' . esc_html__( 'Approve photo', 'recipe-community' ) . '</a> ' : self::pill( 'ok', __( 'Photo approved', 'recipe-community' ) ) . ' ' ) . '<a class="rcom-btn-a" href="' . $act( 'delete_photo' ) . '">' . esc_html__( 'Delete photo', 'recipe-community' ) . '</a></div></div>';
			}
			echo '<form method="post" action="' . esc_url( self::url( 'rcom-reviews' ) ) . '" class="rcom-reply">';
			wp_nonce_field( 'rcom_admin' );
			echo '<input type="hidden" name="rcom_do" value="reply"><input type="hidden" name="id" value="' . (int) $rv->id . '"><input type="hidden" name="rcom_back" value="' . esc_attr( rawurlencode( $back ) ) . '"><label for="rp' . (int) $rv->id . '">' . esc_html__( 'Your reply', 'recipe-community' ) . '</label><textarea id="rp' . (int) $rv->id . '" name="reply_text" rows="2" maxlength="1500" placeholder="' . esc_attr__( 'Write a reply, shown under the review. Leave empty for none.', 'recipe-community' ) . '">' . esc_textarea( $rv->reply_text ) . '</textarea><div class="rcom-btns"><button class="rcom-btn-a primary">' . esc_html__( 'Save reply', 'recipe-community' ) . '</button><a class="rcom-btn-a" href="' . $act( 'hidden' === $rv->status ? 'show' : 'hide' ) . '">' . esc_html( 'hidden' === $rv->status ? __( 'Show', 'recipe-community' ) : __( 'Hide', 'recipe-community' ) ) . '</a><a class="rcom-btn-a danger" href="' . $act( 'delete' ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this review?', 'recipe-community' ) ) . '\')">' . esc_html__( 'Delete', 'recipe-community' ) . '</a></div></form></article>';
		}
		echo '</div>';
	}

	/* ---------- Helpful ---------- */

	public static function helpful() {
		RCOM_Admin::guard();
		global $wpdb;
		$pid = (int) ( $_GET['post'] ?? 0 );
		$v   = RCOM_Helpful::votes();
		$f   = RCOM_Helpful::feedback();
		if ( $pid ) {
			$back = self::url( 'rcom-helpful', array( 'post' => $pid ) );
			list( $y, $n ) = RCOM_Helpful::counts( $pid );
			self::head( self::title( $pid ), __( 'Votes and feedback for this article.', 'recipe-community' ), '<a class="rcom-btn-a" href="' . esc_url( self::url( 'rcom-helpful' ) ) . '">&larr; ' . esc_html__( 'All articles', 'recipe-community' ) . '</a> <a class="rcom-btn-a primary" href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View on site', 'recipe-community' ) . '</a>' );
			echo '<div class="rcom-kpis"><span class="rcom-kpi"><span class="rcom-k-l">' . esc_html__( 'Yes', 'recipe-community' ) . '</span><strong>' . $y . '</strong></span><span class="rcom-kpi"><span class="rcom-k-l">' . esc_html__( 'No', 'recipe-community' ) . '</span><strong>' . $n . '</strong></span><span class="rcom-kpi"><span class="rcom-k-l">' . esc_html__( 'Helpful', 'recipe-community' ) . '</span><strong>' . ( $y + $n ? round( 100 * $y / ( $y + $n ) ) . '%' : '–' ) . '</strong></span></div>';
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT f.*, v.vote, v.identity_id FROM {$f} f LEFT JOIN {$v} v ON v.id = f.vote_id WHERE f.post_id = %d ORDER BY f.id DESC LIMIT 200", $pid ) );
			if ( ! $rows ) {
				echo '<section class="rcom-card">' . self::empty_state( __( 'No feedback texts yet', 'recipe-community' ), __( 'Visitors can leave an optional text after they vote. It shows up here.', 'recipe-community' ), '', '', true ) . '</section>';
			}
			foreach ( $rows as $r ) {
				$act = static function ( $do ) use ( $r, $back ) {
					return esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'rcom-helpful', 'rcom_do' => $do, 'id' => $r->id, 'rcom_back' => rawurlencode( $back ) ), admin_url( 'admin.php' ) ), 'rcom_admin' ) );
				};
				echo '<article class="rcom-card"><div class="rcom-pills"><b>' . esc_html( 'yes' === $r->vote ? __( 'Yes', 'recipe-community' ) : __( 'No', 'recipe-community' ) ) . '</b><span class="rcom-muted">' . esc_html( self::dt( $r->created_at, true ) ) . '</span>' . ( 'handled' === $r->status ? '<span class="rcom-ok">' . esc_html__( 'Handled', 'recipe-community' ) . '</span>' : '' ) . ( 'hidden' === $r->status ? self::pill( 'off', __( 'Hidden', 'recipe-community' ) ) : '' ) . '</div><p>' . nl2br( esc_html( $r->body ) ) . '</p><div class="rcom-btns">' . ( 'handled' !== $r->status ? '<a class="rcom-btn-a primary" href="' . $act( 'fb_handled' ) . '">' . esc_html__( 'Mark as handled', 'recipe-community' ) . '</a>' : '' ) . '<a class="rcom-btn-a" href="' . $act( 'hidden' === $r->status ? 'fb_show' : 'fb_hide' ) . '">' . esc_html( 'hidden' === $r->status ? __( 'Show', 'recipe-community' ) : __( 'Hide', 'recipe-community' ) ) . '</a><a class="rcom-btn-a danger" href="' . $act( 'fb_delete' ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this feedback?', 'recipe-community' ) ) . '\')">' . esc_html__( 'Delete', 'recipe-community' ) . '</a></div></article>';
			}
			echo '</div>';
			return;
		}
		$hper   = 25;
		$hpage  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$having = array();
		if ( ! empty( $_GET['min_no'] ) ) {
			$having[] = 'n > y';
		}
		if ( ! empty( $_GET['has_text'] ) ) {
			$having[] = 'fb > 0';
		}
		$rows = $wpdb->get_results( "SELECT v.post_id, SUM(v.vote = 'yes') AS y, SUM(v.vote = 'no') AS n, MAX(v.created_at) AS last, SUM(v.created_at >= '" . gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) . "') AS recent, (SELECT COUNT(*) FROM {$f} x WHERE x.post_id = v.post_id) AS fb FROM {$v} v WHERE v.is_demo = 0 GROUP BY v.post_id" . ( $having ? ' HAVING ' . implode( ' AND ', $having ) : '' ) . ' ORDER BY (n / (y + n)) DESC, n DESC LIMIT ' . $hper . ' OFFSET ' . ( ( $hpage - 1 ) * $hper ) );
		_prime_post_caches( array_map( 'intval', wp_list_pluck( $rows, 'post_id' ) ), false, false );
		self::head( __( 'Helpful', 'recipe-community' ), __( 'Was this article helpful? Votes and feedback per article, least helpful first.', 'recipe-community' ), '<a class="rcom-btn-a' . ( ! empty( $_GET['min_no'] ) ? ' primary' : '' ) . '" href="' . esc_url( self::url( 'rcom-helpful', empty( $_GET['min_no'] ) ? array( 'min_no' => 1 ) : array() ) ) . '">' . esc_html__( 'More No than Yes', 'recipe-community' ) . '</a> <a class="rcom-btn-a' . ( ! empty( $_GET['has_text'] ) ? ' primary' : '' ) . '" href="' . esc_url( self::url( 'rcom-helpful', empty( $_GET['has_text'] ) ? array( 'has_text' => 1 ) : array() ) ) . '">' . esc_html__( 'With feedback text', 'recipe-community' ) . '</a> <a class="rcom-btn-a" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rcom_export_fb' ), 'rcom_export_fb' ) ) . '">' . esc_html__( 'Export CSV', 'recipe-community' ) . '</a>' );
		if ( ! $rows ) {
			echo '<section class="rcom-card flush">' . self::empty_state( __( 'No votes yet', 'recipe-community' ), __( 'Place [rcom_helpful] on your article template. Votes and feedback texts per article show up here, least helpful first.', 'recipe-community' ), __( 'Open settings', 'recipe-community' ), self::url( 'rcom-settings' ) ) . '</section></div>';
			return;
		}
		echo '<section class="rcom-card flush"><div class="rcom-table">';
		{
			echo '<div class="rcom-tr head"><span class="rcom-grow">' . esc_html__( 'Article', 'recipe-community' ) . '</span><span class="num">' . esc_html__( 'Yes', 'recipe-community' ) . '</span><span class="num">' . esc_html__( 'No', 'recipe-community' ) . '</span><span class="num">' . esc_html__( 'Helpful', 'recipe-community' ) . '</span><span class="num rcom-hide-s">' . esc_html__( 'Feedback', 'recipe-community' ) . '</span><span class="num rcom-hide-s">' . esc_html__( 'Last 30 days', 'recipe-community' ) . '</span></div>';
		}
		foreach ( $rows as $r ) {
			echo '<a class="rcom-tr" href="' . esc_url( self::url( 'rcom-helpful', array( 'post' => $r->post_id ) ) ) . '"><span class="rcom-grow"><b>' . esc_html( self::title( $r->post_id ) ) . '</b></span><span class="num">' . (int) $r->y . '</span><span class="num">' . (int) $r->n . '</span><span class="num">' . round( 100 * $r->y / max( 1, $r->y + $r->n ) ) . '%</span><span class="num rcom-hide-s">' . (int) $r->fb . '</span><span class="num rcom-hide-s">' . (int) $r->recent . '</span></a>';
		}
		$htotal = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$v} WHERE is_demo = 0" );
		echo '</div>' . self::foot( $htotal, $hper, $hpage ) . '</section></div>';
	}

	/* ---------- People ---------- */

	public static function people() {
		RCOM_Admin::guard();
		global $wpdb;
		$per  = 40;
		$page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$t    = RCOM_Reviews::table();
		$tot  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT email) FROM {$t} WHERE is_demo = 0" );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT email, MAX(name) AS name, COUNT(*) AS n, MAX(created_at) AS last, MAX(newsletter_optin AND verified) AS nl, MAX(verified) AS ver FROM {$t} WHERE is_demo = 0 GROUP BY email ORDER BY last DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) );
		$vc = array();
		if ( $rows ) {
			$map = array();
			foreach ( $rows as $r ) {
				$map[ RCOM_Helpful::identity_for( $r->email ) ] = $r->email;
			}
			$in = implode( ',', array_fill( 0, count( $map ), '%s' ) );
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT identity_id, COUNT(*) AS c FROM ' . RCOM_Helpful::votes() . " WHERE identity_id IN ({$in}) GROUP BY identity_id", array_keys( $map ) ) ) as $x ) {
				$vc[ $map[ $x->identity_id ] ] = (int) $x->c;
			}
		}
		$sess = array();
		if ( $rows ) {
			$ids = array();
			foreach ( $rows as $r ) {
				$ids[ RCOM_Helpful::identity_for( $r->email ) ] = 1;
			}
			$in = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT identity_id, COUNT(*) AS c FROM ' . RCOM_Account::t( 'sessions' ) . " WHERE identity_id IN ({$in}) AND expires_at > %s GROUP BY identity_id", array_merge( array_keys( $ids ), array( gmdate( 'Y-m-d H:i:s' ) ) ) ) ) as $x ) {
				$sess[ $x->identity_id ] = (int) $x->c;
			}
		}
		self::head( __( 'People', 'recipe-community' ), __( 'Everyone who left a review, one row per email address.', 'recipe-community' ), '<a class="rcom-btn-a" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rcom_export' ), 'rcom_export' ) ) . '">' . esc_html__( 'Export CSV', 'recipe-community' ) . '</a>' );
		if ( ! $rows ) {
			echo '<section class="rcom-card flush">' . self::empty_state( __( 'No people yet', 'recipe-community' ), __( 'Everyone who leaves a review appears here once, with their number of reviews and votes. Email addresses are only visible in the admin.', 'recipe-community' ), __( 'Open settings', 'recipe-community' ), self::url( 'rcom-settings' ) ) . '</section></div>';
			return;
		}
		echo '<section class="rcom-card flush"><div class="rcom-table">';
		foreach ( $rows as $r ) {
			echo '<div class="rcom-tr"><span class="rcom-ava">' . self::initials( $r->name ) . '</span><span class="rcom-grow"><a href="' . esc_url( self::url( 'rcom-reviews', array( 's' => $r->email ) ) ) . '"><b>' . esc_html( $r->name ) . '</b></a><br><span class="rcom-muted">' . esc_html( $r->email ) . '</span></span><span>' . esc_html( sprintf( _n( '%s review', '%s reviews', $r->n, 'recipe-community' ), $r->n ) ) . '</span><span>' . ( $r->ver ? '<span class="rcom-ok">' . esc_html__( 'Verified', 'recipe-community' ) . '</span>' : '–' ) . '</span><span>' . ( $r->nl ? esc_html__( 'Newsletter', 'recipe-community' ) : '–' ) . '</span><span class="rcom-hide-s">' . ( ! empty( $sess[ RCOM_Helpful::identity_for( $r->email ) ] ) ? '<a class="rcom-btn-a" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'rcom-people', 'rcom_do' => 'revoke', 'identity' => RCOM_Helpful::identity_for( $r->email ) ), admin_url( 'admin.php' ) ), 'rcom_admin' ) ) . '">' . esc_html( sprintf( /* translators: %d: number of sessions */ _n( 'End %d session', 'End %d sessions', $sess[ RCOM_Helpful::identity_for( $r->email ) ], 'recipe-community' ), $sess[ RCOM_Helpful::identity_for( $r->email ) ] ) ) . '</a>' : '&ndash;' ) . '</span><span class="rcom-hide-s">' . esc_html( sprintf( /* translators: %d: helpful votes */ _n( '%d vote', '%d votes', $vc[ $r->email ] ?? 0, 'recipe-community' ), $vc[ $r->email ] ?? 0 ) ) . '</span><span class="rcom-hide-s rcom-muted">' . esc_html( self::dt( $r->last ) ) . '</span></div>';
		}
		echo '</div>' . self::foot( $tot, $per, $page ) . '</section>';
		$aud = $wpdb->get_results( 'SELECT identity_id, event, created_at FROM ' . RCOM_Account::t( 'audit' ) . ' ORDER BY id DESC LIMIT 25' );
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Account security log', 'recipe-community' ) . '</h2><p class="rcom-muted">' . esc_html__( 'Last 25 events, kept for 90 days. No email addresses or IP addresses, only a short identity code.', 'recipe-community' ) . '</p>';
		if ( ! $aud ) {
			echo self::empty_state( __( 'No events yet', 'recipe-community' ), __( 'Login links, sessions and deletions show up here once the account page is used.', 'recipe-community' ), '', '', true );
		}
		foreach ( $aud as $e ) {
			echo '<div class="rcom-row"><span class="rcom-grow">' . esc_html( $e->event ) . '</span><code>' . esc_html( $e->identity_id ? substr( $e->identity_id, 0, 8 ) : '-' ) . '</code><span class="rcom-muted">' . esc_html( self::dt( $e->created_at, true ) ) . '</span></div>';
		}
		echo '</section></div>';
	}

	/* ---------- Settings ---------- */

	private static function field( $label, $control, $help = '' ) {
		return '<div class="rcom-field"><div class="rcom-fl">' . esc_html( $label ) . '</div><div class="rcom-fc">' . $control . ( $help ? '<p class="rcom-muted">' . wp_kses_post( $help ) . '</p>' : '' ) . '</div></div>';
	}

	public static function settings() {
		RCOM_Admin::guard();
		$o = RCOM_Settings::all();
		self::head( __( 'Settings', 'recipe-community' ), __( 'Spam protection, newsletter and where reviews are active.', 'recipe-community' ) );
		$msg = array(
			'ts' => array(
				'ok'   => array( 'ok', __( 'Your secret key is accepted by Cloudflare. (The site key cannot be checked from the server: test the form on the site.)', 'recipe-community' ) ),
				'bad'  => array( 'err', __( 'Cloudflare rejected the secret key. Check that you copied it completely.', 'recipe-community' ) ),
				'none' => array( 'err', __( 'Enter and save both keys first.', 'recipe-community' ) ),
				'net'  => array( 'err', __( 'Could not reach Cloudflare. Try again later.', 'recipe-community' ) ),
			),
		);
		if ( isset( $_GET['ts'], $msg['ts'][ $_GET['ts'] ] ) ) {
			echo '<div class="rcom-a-flash ' . esc_attr( $msg['ts'][ $_GET['ts'] ][0] ) . '">' . esc_html( $msg['ts'][ $_GET['ts'] ][1] ) . '</div>';
		}
		if ( isset( $_GET['acc'] ) ) {
			$am = array(
				'created'  => array( 'ok', __( 'The account page was created and chosen.', 'recipe-community' ) ),
				'existing' => array( 'ok', __( 'A page with [rcom_account] already exists; it was chosen instead of creating a second one.', 'recipe-community' ) ),
				'conflict' => array( 'err', sprintf( /* translators: %s: suggested slug */ __( 'That slug is already in use. Suggestion: %s. Press "Create page" again to use it.', 'recipe-community' ), isset( $_GET['acc_suggest'] ) ? sanitize_title( wp_unslash( $_GET['acc_suggest'] ) ) : '' ) ),
				'error'    => array( 'err', __( 'The page could not be created.', 'recipe-community' ) ),
			)[ sanitize_key( $_GET['acc'] ) ] ?? null;
			if ( $am ) {
				echo '<div class="rcom-a-flash ' . esc_attr( $am[0] ) . '">' . esc_html( $am[1] ) . '</div>';
			}
		}
		if ( isset( $_GET['lp'] ) ) {
			echo '<div class="rcom-a-flash ' . ( 'ok' === $_GET['lp'] ? 'ok' : 'err' ) . '">' . esc_html( get_transient( 'rcom_lp_msg' ) ?: '' ) . '</div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php?action=rcom_settings' ) ) . '" class="rcom-sform">';
		wp_nonce_field( 'rcom_settings' );

		// Spam protection
		list( $ts_site, $ts_site_src ) = RCOM_Settings::turnstile_key( 'site' );
		list( $ts_sec, $ts_sec_src )   = RCOM_Settings::turnstile_key( 'secret' );
		$cs = 'constant' === $ts_site_src;
		$cx = 'constant' === $ts_sec_src;
		$src_note = function ( $src, $key, $const ) {
			if ( '' === $src ) {
				return '';
			}
			$t = array(
				/* translators: %s: constant name */
				'constant' => sprintf( __( 'Set via %s in wp-config.php', 'recipe-community' ), $const ),
				'own'      => __( 'Saved in this plugin', 'recipe-community' ),
				'bricks'   => __( 'Taken from the Bricks settings (read only)', 'recipe-community' ),
			);
			return '<span class="rcom-muted">' . esc_html( $t[ $src ] . ' · ••••' . substr( $key, -4 ) ) . '</span>';
		};
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Spam protection (Cloudflare Turnstile)', 'recipe-community' ) . '</h2><p class="rcom-muted">' . esc_html__( 'Without both keys the review form is hidden for visitors. Create a Turnstile widget in the Cloudflare dashboard (mode Managed, your hostname) and paste the keys here, or define them in wp-config.php.', 'recipe-community' ) . '</p>';
		echo self::field( __( 'Site key', 'recipe-community' ), $cs ? '<code>RCOM_TURNSTILE_SITE_KEY</code> <span class="rcom-muted">wp-config.php</span>' : '<input name="turnstile_site" value="' . esc_attr( $o['turnstile_site'] ) . '" autocomplete="off">' . ( 'bricks' === $ts_site_src ? '<br>' . $src_note( 'bricks', $ts_site, '' ) : '' ) );
		echo self::field( __( 'Secret key', 'recipe-community' ), $cx ? '<code>RCOM_TURNSTILE_SECRET_KEY</code> <span class="rcom-muted">wp-config.php</span>' : '<input name="turnstile_secret" type="password" autocomplete="new-password" placeholder="' . esc_attr( $o['turnstile_secret'] ? '••••' . substr( $o['turnstile_secret'], -4 ) : '' ) . '">' . ( 'bricks' === $ts_sec_src ? '<br>' . $src_note( 'bricks', $ts_sec, '' ) : '' ), __( 'Leave empty to keep the saved key. Fill in your own key to override the Bricks one.', 'recipe-community' ) );
		echo '<div class="rcom-btns"><button class="rcom-btn-a" name="rcom_after" value="turnstile">' . esc_html__( 'Test my keys', 'recipe-community' ) . '</button> ' . ( RCOM_Settings::turnstile_ready() ? self::pill( 'ok', __( 'Keys present', 'recipe-community' ) ) : self::pill( 'warn', __( 'Keys missing', 'recipe-community' ) ) ) . '</div></section>';

		// Content
		$pts = RCOM_Settings::post_types();
		$cb  = '';
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue;
			}
			$cb .= '<label class="rcom-chk"><input type="checkbox" name="post_types[]" value="' . esc_attr( $pt->name ) . '"' . checked( in_array( $pt->name, $pts, true ), true, false ) . '> ' . esc_html( $pt->labels->name ) . '</label>';
		}
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Reviews', 'recipe-community' ) . '</h2>';
		echo '<p class="rcom-muted">' . wp_kses_post( sprintf( /* translators: %s: link to the General settings */ __( 'The credit link below this block is set under %s.', 'recipe-community' ), '<a href="#rcom-general">' . esc_html__( 'General', 'recipe-community' ) . '</a>' ) ) . '</p>';

		echo self::field( __( 'Active on', 'recipe-community' ), '<div class="rcom-chks">' . $cb . '</div>', __( 'The shortcodes only work on these post types. CSS and JS load only where a shortcode renders.', 'recipe-community' ) );
		echo self::field( __( 'Label for your reply', 'recipe-community' ), '<input name="reply_label" value="' . esc_attr( $o['reply_label'] ) . '" placeholder="' . esc_attr__( 'Reply from the author', 'recipe-community' ) . '">' );
		echo '</section>';

		// Terms
		$pages = '<option value="0">&mdash;</option>';
		$pp    = '<option value="0">&mdash;</option>';
		foreach ( get_pages() as $pg ) {
			$pages .= '<option value="' . (int) $pg->ID . '"' . selected( (int) $o['terms_page'], (int) $pg->ID, false ) . '>' . esc_html( $pg->post_title ) . '</option>';
			$pp    .= '<option value="' . (int) $pg->ID . '"' . selected( (int) $o['privacy_page'], (int) $pg->ID, false ) . '>' . esc_html( $pg->post_title ) . '</option>';
		}
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Terms and consent', 'recipe-community' ) . '</h2><p class="rcom-muted">' . esc_html__( 'Posting a review requires ticking the terms box. The moment and the terms version are stored with the review.', 'recipe-community' ) . '</p>';
		echo self::field( __( 'Terms page', 'recipe-community' ), '<select name="terms_page">' . $pages . '</select>', ( (int) $o['terms_page'] || (int) $o['privacy_page'] ) ? '' : __( 'No page chosen: the checkbox shows plain text without a link.', 'recipe-community' ) );
		echo self::field( __( 'Privacy page', 'recipe-community' ), '<select name="privacy_page">' . $pp . '</select>', __( 'Linked in a small line under the review button, and used for the checkbox link when no terms page is chosen. Without a privacy page that line is not shown.', 'recipe-community' ) );
		echo self::field( __( 'Checkbox text', 'recipe-community' ), '<input name="terms_label" value="' . esc_attr( $o['terms_label'] ) . '" placeholder="' . esc_attr__( 'I agree to the %s', 'recipe-community' ) . '">', __( 'Optional. Use %s where the link to the terms should go. Leave empty for the default sentence.', 'recipe-community' ) );
		$tp = (int) $o['terms_page'];
		$rv = ! $o['terms_snapshots'] && ( ( defined( 'WP_POST_REVISIONS' ) && ( false === WP_POST_REVISIONS || 0 === WP_POST_REVISIONS ) ) || ( $tp && 0 === wp_revisions_to_keep( get_post( $tp ) ) ) );
		echo self::field( __( 'Terms version', 'recipe-community' ), '<span class="rcom-muted">' . esc_html( $tp ? sprintf( /* translators: %s: date */ __( 'Automatic: last modified %s. Every edit of the page counts as a new version.', 'recipe-community' ), self::dt( get_post_field( 'post_modified_gmt', $tp ), true ) ) : __( 'Automatic, from the last modified date of the terms page.', 'recipe-community' ) ) . '</span>', $rv ? __( 'Snapshots are off and post revisions are disabled: you cannot look up later which text applied.', 'recipe-community' ) : '' );
		echo self::field( __( 'Keep a copy of the terms', 'recipe-community' ), '<label class="rcom-chk"><input type="checkbox" name="terms_snapshots" value="1"' . checked( $o['terms_snapshots'], 1, false ) . '> ' . esc_html__( 'Store a text copy per version (independent of post revisions)', 'recipe-community' ) . '</label>', __( 'Copies are never deleted automatically while reviews refer to them.', 'recipe-community' ) );
		echo self::field( __( 'Newsletter checkbox text', 'recipe-community' ), '<input name="newsletter_label" value="' . esc_attr( $o['newsletter_label'] ) . '" placeholder="' . esc_attr__( 'Yes, send me the newsletter', 'recipe-community' ) . '">' );
		echo '</section>';

		// Appearance
		$sk = RCOM_Style::current();
		$so = '';
		foreach ( RCOM_Style::skins() as $k => $l ) {
			$so .= '<option value="' . esc_attr( $k ) . '"' . selected( $sk, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$hint = RCOM_Style::framework_detected() && 'core-framework' !== $sk ? ' ' . self::pill( 'warn', __( 'Core Framework found on this site', 'recipe-community' ) ) : '';
		$docs = '<a href="https://github.com/J-Concept-Agency/recipe-community/blob/main/docs/theming.md" target="_blank" rel="noopener">' . esc_html__( 'How theming works (docs/theming.md)', 'recipe-community' ) . '</a>';
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Appearance', 'recipe-community' ) . '</h2><p class="rcom-muted">' . esc_html__( 'The plugin ships structure and a neutral style built on CSS variables (--rcom-accent, --rcom-text, ...). Pick a ready-made skin for your framework, or style it yourself.', 'recipe-community' ) . '</p>';
		echo self::field( __( 'Style', 'recipe-community' ), '<select name="skin">' . $so . '</select>' . $hint, __( 'Core Framework links the variables to your Core Framework tokens. None loads no plugin CSS at all.', 'recipe-community' ) );
		echo self::field( __( 'Extra CSS', 'recipe-community' ), '<textarea class="rcom-css" name="extra_css" rows="8" maxlength="' . (int) RCOM_Style::MAX_CSS . '" spellcheck="false" placeholder=":root { --rcom-star-size-lg: 1.5rem; }">' . esc_textarea( RCOM_Settings::get( 'extra_css' ) ) . '</textarea>', sprintf( /* translators: %d: size in KB */ __( 'Added after the plugin CSS, only on pages that show reviews, votes or the account. Max %d KB. Tags, @import and script-like values are removed. ', 'recipe-community' ), RCOM_Style::MAX_CSS / 1024 ) . $docs );
		echo '</section>';

		// Account
		$ap = '<option value="0">&mdash;</option>';
		foreach ( get_pages() as $pg ) {
			$ap .= '<option value="' . (int) $pg->ID . '"' . selected( (int) $o['account_page'], (int) $pg->ID, false ) . '>' . esc_html( $pg->post_title ) . '</option>';
		}
		$sug = isset( $_GET['acc_suggest'] ) ? sanitize_title( wp_unslash( $_GET['acc_suggest'] ) ) : 'pitmaster';
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Account page', 'recipe-community' ) . '</h2><p class="rcom-muted">' . esc_html__( 'Visitors manage their reviews, votes and data on one page with the shortcode [rcom_account]. Without a page there are no links to it anywhere. [rcom_account_link] prints a small link, for example for the footer.', 'recipe-community' ) . '</p>';
		echo self::field( __( 'Account page', 'recipe-community' ), '<select name="account_page">' . $ap . '</select>', __( 'Exclude this page from page caches (it is sent with no-store headers and noindex).', 'recipe-community' ) );
		echo self::field( __( 'Name of the account', 'recipe-community' ), '<input name="account_label" value="' . esc_attr( $o['account_label'] ) . '" placeholder="' . esc_attr__( 'Pitmaster', 'recipe-community' ) . '">', __( 'Used in the link text and in the emails ("Go to your Pitmaster page").', 'recipe-community' ) );
		echo self::field( __( 'Create the page', 'recipe-community' ), '<div class="rcom-create"><div class="rcom-cf"><label for="rcom-slug">' . esc_html__( 'Page URL', 'recipe-community' ) . '</label><div class="rcom-pre"><span>' . esc_html( trailingslashit( home_url( '/' ) ) ) . '</span><input id="rcom-slug" name="account_slug" value="' . esc_attr( $sug ) . '"><span>/</span></div><p class="rcom-muted">' . esc_html__( 'Letters, numbers and hyphens. Checked before the page is created.', 'recipe-community' ) . '</p></div><div class="rcom-cf"><label for="rcom-title">' . esc_html__( 'Page title', 'recipe-community' ) . '</label><input id="rcom-title" name="account_title" value="' . esc_attr( __( 'Pitmaster', 'recipe-community' ) ) . '"><p class="rcom-muted">' . esc_html__( 'Shown as the heading of the page.', 'recipe-community' ) . '</p></div></div><div class="rcom-btns"><button class="rcom-btn-a" name="rcom_after" value="create_account">' . esc_html__( 'Create page', 'recipe-community' ) . '</button></div>', __( 'Creates a published page with [rcom_account]. An existing page with the shortcode is chosen instead of making a second one.', 'recipe-community' ) );
		echo '</section>';

		// Helpful
		$hts = RCOM_Settings::helpful_types();
		$hb  = '';
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' !== $pt->name ) {
				$hb .= '<label class="rcom-chk"><input type="checkbox" name="helpful_types[]" value="' . esc_attr( $pt->name ) . '"' . checked( in_array( $pt->name, $hts, true ), true, false ) . '> ' . esc_html( $pt->labels->name ) . '</label>';
			}
		}
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Helpful? (was this article helpful)', 'recipe-community' ) . '</h2>';
		echo '<p class="rcom-muted">' . wp_kses_post( sprintf( /* translators: %s: link to the General settings */ __( 'The credit link below this block is set under %s.', 'recipe-community' ), '<a href="#rcom-general">' . esc_html__( 'General', 'recipe-community' ) . '</a>' ) ) . '</p>';

		echo self::field( __( 'Active on', 'recipe-community' ), '<div class="rcom-chks">' . $hb . '</div>', __( 'Shortcode [rcom_helpful]. Votes are anonymous unless the visitor is identified through a review link.', 'recipe-community' ) );
		echo self::field( __( 'Show the count from', 'recipe-community' ), '<input name="helpful_threshold" type="number" min="1" value="' . (int) $o['helpful_threshold'] . '" style="max-width:100px">', __( 'The public sentence appears once this many people voted Yes.', 'recipe-community' ) );
		echo self::field( __( 'Question after Yes', 'recipe-community' ), '<input name="helpful_yes_label" value="' . esc_attr( $o['helpful_yes_label'] ) . '" placeholder="' . esc_attr__( 'What did you like?', 'recipe-community' ) . '">' );
		echo self::field( __( 'Question after No', 'recipe-community' ), '<input name="helpful_no_label" value="' . esc_attr( $o['helpful_no_label'] ) . '" placeholder="' . esc_attr__( 'What could be better?', 'recipe-community' ) . '">' );
		echo self::field( __( 'Feedback text', 'recipe-community' ), '<label class="rcom-chk"><input type="checkbox" name="helpful_ts" value="1"' . checked( $o['helpful_ts'], 1, false ) . '> ' . esc_html__( 'Require Turnstile for the feedback text (votes never use a captcha)', 'recipe-community' ) . '</label><br><label class="rcom-chk"><input type="checkbox" name="helpful_links" value="1"' . checked( $o['helpful_links'], 1, false ) . '> ' . esc_html__( 'Allow links in the text', 'recipe-community' ) . '</label>' );
		echo '</section>';

		// Newsletter
		$opts = '';
		foreach ( RCOM_Newsletter::providers() as $id => $pv ) {
			$opts .= '<option value="' . esc_attr( $id ) . '"' . selected( $o['newsletter_provider'], $id, false ) . '>' . esc_html( $pv['label'] ) . '</option>';
		}
		list( $key, $key_src ) = RCOM_Laposta::key_source();
		$hint = ( $key && 'own' === $key_src ) ? '••••' . substr( $key, -4 ) : '';
		$key_note = '';
		if ( $key ) {
			$kt = array(
				'rcom_constant' => sprintf( /* translators: %s: constant name */ __( 'Set via %s in wp-config.php', 'recipe-community' ), 'RCOM_LAPOSTA_API_KEY' ),
				'constant'      => sprintf( /* translators: %s: constant name */ __( 'Set via %s in wp-config.php', 'recipe-community' ), 'LAPOSTA_API_KEY' ),
				'own'           => __( 'Saved in this plugin', 'recipe-community' ),
				'bricks'        => __( 'Taken from Bricks Laposta (read live, not copied)', 'recipe-community' ),
			);
			$key_note = '<br><span class="rcom-muted">' . esc_html( $kt[ $key_src ] . ' · ••••' . substr( $key, -4 ) ) . '</span>';
		}
		$const_set = in_array( $key_src, array( 'rcom_constant', 'constant' ), true );
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Newsletter', 'recipe-community' ) . '</h2><p class="rcom-muted">' . esc_html__( 'Only visitors who tick the newsletter box and confirm their email address are sent to the service.', 'recipe-community' ) . '</p>';
		echo self::field( __( 'Service', 'recipe-community' ), '<select name="newsletter_provider" id="rcom-nl">' . $opts . '</select>' );
		echo '<div id="rcom-lp" data-for="laposta">';
		echo self::field( __( 'Laposta API key', 'recipe-community' ), ( $const_set ? '' : '<input name="laposta_key" type="password" autocomplete="new-password" placeholder="' . esc_attr( $hint ) . '">' ) . ' <button class="rcom-btn-a" name="rcom_after" value="connect">' . esc_html__( 'Connect', 'recipe-community' ) . '</button>' . $key_note, __( 'Fill this in only if the key is not set in wp-config.php (LAPOSTA_API_KEY works for both this plugin and Bricks Laposta). Only the last 4 characters are ever shown.', 'recipe-community' ) );
		$lp    = new RCOM_Laposta();
		$lists = $key ? $lp->lists() : null;
		if ( $key && null === $lists ) {
			echo '<p class="rcom-a-flash err">' . esc_html__( 'Could not load your lists: the API key is invalid or Laposta is unreachable.', 'recipe-community' ) . '</p>';
		}
		if ( $lists ) {
			$lo = '<option value="">&mdash;</option>';
			foreach ( $lists as $lid => $li ) {
				$lo .= '<option value="' . esc_attr( $lid ) . '"' . selected( $o['laposta_list'], $lid, false ) . '>' . esc_html( $li['name'] . ( null !== $li['members'] ? ' (' . number_format_i18n( $li['members'] ) . ')' : '' ) ) . '</option>';
			}
			echo self::field( __( 'Laposta list', 'recipe-community' ), '<select name="laposta_list">' . $lo . '</select>', ( $o['laposta_list'] && ! isset( $lists[ $o['laposta_list'] ] ) ) ? __( 'The saved list was not found in this account.', 'recipe-community' ) : __( 'Pick a list and save: the first-name field is then suggested automatically.', 'recipe-community' ) );
			$fl = $o['laposta_list'] ? $lp->fields( $o['laposta_list'] ) : null;
			if ( $fl ) {
				$fo = '<option value="">&mdash;</option>';
				foreach ( $fl as $tag => $nm ) {
					$fo .= '<option value="' . esc_attr( $tag ) . '"' . selected( $o['laposta_name_field'], $tag, false ) . '>' . esc_html( $nm ) . '</option>';
				}
				echo self::field( __( 'Field for the first name', 'recipe-community' ), '<select name="laposta_name_field">' . $fo . '</select>', __( 'The email address is always sent. Change the suggestion if needed.', 'recipe-community' ) );
			}
		} elseif ( ! $key ) {
			echo self::field( __( 'Laposta list ID', 'recipe-community' ), '<input name="laposta_list" value="' . esc_attr( $o['laposta_list'] ) . '">', __( 'Connect an API key to pick from your lists.', 'recipe-community' ) );
		}
		echo self::field( __( 'Confirmation', 'recipe-community' ), '<select name="laposta_doi"><option value="ours"' . selected( $o['laposta_doi'], 'ours', false ) . '>' . esc_html__( 'We confirm: the review email link counts, no second email', 'recipe-community' ) . '</option><option value="laposta"' . selected( $o['laposta_doi'], 'laposta', false ) . '>' . esc_html__( 'Laposta sends its own confirmation email', 'recipe-community' ) . '</option></select>', __( 'With the first choice the visitor gets one email in total; with the second a second email from Laposta that must be confirmed too.', 'recipe-community' ) );
		echo self::field( __( 'Notification email', 'recipe-community' ), '<label class="rcom-chk"><input type="checkbox" name="laposta_quiet" value="1"' . checked( $o['laposta_quiet'], 1, false ) . '> ' . esc_html__( 'Do not send the Laposta notification email', 'recipe-community' ) . '</label>', __( 'Laposta can email you for every new sign-up through the API. This switches that email off for sign-ups from this site.', 'recipe-community' ) );
		echo '<div class="rcom-btns"><button class="rcom-btn-a" name="rcom_after" value="laposta">' . esc_html__( 'Test connection', 'recipe-community' ) . '</button>' . ( 'bricks' === $key_src ? ' <button class="rcom-btn-a" name="rcom_after" value="adopt">' . esc_html__( 'Copy to this plugin', 'recipe-community' ) . '</button>' : '' ) . '</div></div>';
		echo '<script>(function(){var s=document.getElementById("rcom-nl"),b=document.getElementById("rcom-lp");function t(){b.hidden=s.value!=="laposta"}s.addEventListener("change",t);t()})()</script></section>';

		// Email, updates, data
		echo '<section class="rcom-card" id="rcom-general"><h2>' . esc_html__( 'General', 'recipe-community' ) . '</h2>';
		echo self::field( __( 'Credit link', 'recipe-community' ), '<label class="rcom-chk"><input type="checkbox" name="show_credit" value="1"' . checked( $o['show_credit'], 1, false ) . '> ' . esc_html__( 'Show "Powered by J-Concept" below the reviews and below Helpful?', 'recipe-community' ) . '</label>', __( 'One switch for both blocks. Anyone may switch it off.', 'recipe-community' ) );
		echo self::field( __( 'Sender email for mails', 'recipe-community' ), '<input name="mail_from" type="email" value="' . esc_attr( $o['mail_from'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '">', __( 'Default: the WordPress admin address.', 'recipe-community' ) );
		echo self::field( __( 'Updates', 'recipe-community' ), '<label class="rcom-chk"><input type="checkbox" name="disable_updates" value="1"' . checked( $o['disable_updates'], 1, false ) . '> ' . esc_html__( 'Do not check GitHub for plugin updates', 'recipe-community' ) . '</label>' );
		if ( function_exists( 'wp_register_ability' ) ) {
			echo self::field( __( 'AI abilities', 'recipe-community' ), '<label class="rcom-chk"><input type="checkbox" name="ai_abilities" value="1"' . checked( $o['ai_abilities'], 1, false ) . '> ' . esc_html__( 'Allow AI abilities (WordPress Abilities API, administrators only)', 'recipe-community' ) . '</label>', __( 'Lets an AI assistant read statistics and reviews and reply, approve photos or hide reviews. Off by default.', 'recipe-community' ) );
		}
		echo self::field( __( 'When a recipe is deleted for good', 'recipe-community' ), '<select name="on_delete"><option value="delete"' . selected( $o['on_delete'], 'delete', false ) . '>' . esc_html__( 'Delete its reviews and photos', 'recipe-community' ) . '</option><option value="keep"' . selected( $o['on_delete'], 'keep', false ) . '>' . esc_html__( 'Keep them hidden (filter "Recipe deleted")', 'recipe-community' ) . '</option></select>', __( 'Emptying the trash deletes recipes for good. Choose "keep" to protect reviews against an accidentally emptied trash.', 'recipe-community' ) );
		echo self::field( __( 'Cached ratings', 'recipe-community' ), '<button class="rcom-btn-a" name="rcom_after" value="rebuild">' . esc_html__( 'Recalculate cache', 'recipe-community' ) . '</button>' . ( isset( $_GET['rebuilt'] ) ? ' <span class="rcom-ok">' . esc_html( sprintf( /* translators: %d: number of posts */ __( '%d posts recalculated', 'recipe-community' ), (int) $_GET['rebuilt'] ) ) . '</span>' : '' ), __( 'Averages and counts are cached in post meta and can always be rebuilt from the tables.', 'recipe-community' ) );
		echo self::field( __( 'Keep data on uninstall', 'recipe-community' ), '<label class="rcom-chk"><input type="checkbox" name="keep_data" value="1"' . checked( $o['keep_data'], 1, false ) . '> ' . esc_html__( 'Do not delete reviews and photos when the plugin is deleted', 'recipe-community' ) . '</label>' );
		echo '</section><div class="rcom-savebar"><button class="rcom-btn-a primary big">' . esc_html__( 'Save changes', 'recipe-community' ) . '</button></div></form></div>';
	}

	/* ---------- About ---------- */

	public static function about() {
		RCOM_Admin::guard();
		self::head( __( 'About', 'recipe-community' ), '' );
		$chk = function ( $label, $ok, $note = '' ) {
			return '<div class="rcom-row">' . ( $ok ? self::pill( 'ok', __( 'OK', 'recipe-community' ) ) : self::pill( 'warn', __( 'Check', 'recipe-community' ) ) ) . '<span class="rcom-grow">' . esc_html( $label ) . '</span><span class="rcom-muted">' . esc_html( $note ) . '</span></div>';
		};
		$ed   = _wp_image_editor_choose( array( 'mime_type' => 'image/webp' ) );
		$up   = wp_upload_dir( null, false );
		echo '<section class="rcom-card"><h2>Recipe Community ' . esc_html( RCOM_VERSION ) . '</h2><p>' . sprintf(
			/* translators: %s: link to the author */
			esc_html__( 'Recipe Community by %s', 'recipe-community' ),
			'<a href="https://jconcept.nl" target="_blank" rel="noopener">J-Concept</a>'
		) . '</p><p class="rcom-muted">GPL-2.0-or-later &middot; Copyright (C) 2026 J-Concept &middot; <a href="https://github.com/J-Concept-Agency/recipe-community" target="_blank" rel="noopener">GitHub</a></p></section>';
		echo '<section class="rcom-card"><h2>' . esc_html__( 'System check', 'recipe-community' ) . '</h2>';
		echo $chk( __( 'PHP 8.0 or newer', 'recipe-community' ), version_compare( PHP_VERSION, '8.0', '>=' ), PHP_VERSION );
		echo $chk( __( 'Photo processing with WebP', 'recipe-community' ), (bool) $ed, $ed ? str_replace( 'WP_Image_Editor_', '', $ed ) : '' );
		echo $chk( __( 'Photo folder writable', 'recipe-community' ), wp_is_writable( $up['basedir'] ) );
		echo $chk( __( 'Turnstile keys present', 'recipe-community' ), RCOM_Settings::turnstile_ready() );
		echo $chk( __( 'Newsletter service', 'recipe-community' ), 'none' !== RCOM_Settings::get( 'newsletter_provider' ), RCOM_Newsletter::providers()[ RCOM_Settings::get( 'newsletter_provider' ) ]['label'] ?? '' );
		echo $chk( __( 'Update check', 'recipe-community' ), ! RCOM_Updater::disabled(), RCOM_Updater::disabled() ? __( 'disabled', 'recipe-community' ) : __( 'GitHub releases', 'recipe-community' ) );
		echo '</section>';
		$posts = get_posts( array( 'post_type' => array_values( array_unique( array_merge( RCOM_Settings::post_types(), RCOM_Settings::helpful_types() ) ) ), 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
		$opts  = '';
		foreach ( $posts as $p ) {
			$opts .= '<option value="' . (int) $p->ID . '">' . esc_html( $p->post_title ) . '</option>';
		}
		echo '<section class="rcom-card"><h2>' . esc_html__( 'Demo data', 'recipe-community' ) . '</h2><p class="rcom-muted">' . esc_html__( 'Adds example reviews (with a photo and replies) or votes to one post so you can preview the look. Demo data shows on that page only: it is left out of the dashboard, people, exports, newsletter and rcom_get_rating(). Command line: wp rcom demo add --post=ID and wp rcom demo clear.', 'recipe-community' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php?action=rcom_demo' ) ) . '" class="rcom-btns">';
		wp_nonce_field( 'rcom_demo' );
		echo '<select name="post" aria-label="' . esc_attr__( 'Post', 'recipe-community' ) . '">' . $opts . '</select> <button class="rcom-btn-a primary" name="do" value="add">' . esc_html__( 'Add demo data', 'recipe-community' ) . '</button> <button class="rcom-btn-a" name="do" value="clear">' . esc_html__( 'Remove demo data', 'recipe-community' ) . '</button>';
		echo ' <span class="rcom-muted">' . esc_html( sprintf( /* translators: %d: number of demo rows */ __( '%d demo rows present', 'recipe-community' ), RCOM_Demo::count() ) ) . '</span></form></section></div>';
	}
}
