<?php
defined( 'ABSPATH' ) || exit;

/**
 * Demo data to preview the look: reviews, one with a photo, owner replies, and votes with feedback.
 * Demo rows carry is_demo = 1: they show on the recipe/article page (average, list, helpful count) but are left out of
 * the dashboard, people, CSV export, newsletter and rcom_get_rating(). No network access; the photo is generated locally.
 */
class RCOM_Demo {

	private static function nl() {
		return 0 === strpos( determine_locale(), 'nl' );
	}

	/** rating, name, text, bbq key, days ago, photo flag, reply (0 none, 2 short, 3 long). Dutch for nl_*, English otherwise. */
	public static function sets() {
		if ( self::nl() ) {
			return array(
				array( 5, 'Anna', 'Dit in het weekend gemaakt en iedereen vroeg om het recept. De korst was perfect.', 'charcoal', 1, '', 2 ),
				array( 4, 'Mark', 'Heerlijk van smaak, de volgende keer iets meer zout.', 'gas', 3, '', 0 ),
				array( 5, 'Lotte', '', 'kamado', 5, '', 0 ),
				array( 3, 'Pieter', 'Lekker, maar mijn barbecue stond te heet en de buitenkant verbrandde voordat de binnenkant klaar was. Weer iets geleerd.', 'pellet', 8, '', 0 ),
				array( 5, 'Sanne', 'We hebben het al twee keer gemaakt. De eerste keer precies volgens het recept, de tweede keer met wat extra rook en een langere rusttijd. Beide waren fantastisch, maar de tweede was de winnaar: sappig, mals en met een diepe, rokerige korst. Ik volgde ook de tip om het vlees eerst op kamertemperatuur te laten komen, en dat maakte echt verschil voor de gelijkmatige garing. Bedankt voor de duidelijke stappen en de temperatuurgids, daardoor is dit recept makkelijk te herhalen.', 'kamado', 12, 'photo', 3 ),
				array( 2, 'Jeroen', 'Niet mijn smaak, te zwaar.', 'other', 15, '', 0 ),
				array( 4, 'Fleur', 'Makkelijk te volgen, de tijden klopten precies.', 'charcoal', 19, '', 0 ),
				array( 5, 'Daan', 'Perfect voor een koude avond buiten.', 'kamado', 24, '', 0 ),
				array( 4, 'Eva', 'Mooi resultaat. De foto laat zien hoe het bij mij is geworden.', 'gas', 30, '', 0 ),
			);
		}
		return array(
			array( 5, 'Anna', 'Made this on the weekend and everyone asked for the recipe. The crust was perfect.', 'charcoal', 1, '', 2 ),
			array( 4, 'Tom', 'Great flavour, a bit more salt next time.', 'gas', 3, '', 0 ),
			array( 5, 'Lisa', '', 'kamado', 5, '', 0 ),
			array( 3, 'Mark', 'Good, but my grill ran too hot and the outside caught before the inside was done. Lesson learned.', 'pellet', 8, '', 0 ),
			array( 5, 'Sanne', 'We cooked it twice already. The first time exactly as written, the second time with a little extra smoke and a longer rest. Both were fantastic, but the second one was the winner: juicy, tender and with a deep, smoky crust. I also followed the tip to let it come to room temperature first, and that really made a difference in how evenly it cooked. Thanks for the clear steps and the temperature guide, that is what makes this recipe easy to repeat.', 'kamado', 12, 'photo', 3 ),
			array( 2, 'Pieter', 'Not for me, too heavy.', 'other', 15, '', 0 ),
			array( 4, 'Fleur', 'Easy to follow, the timing was spot on.', 'charcoal', 19, '', 0 ),
			array( 5, 'Daan', 'Perfect for a cold evening outside.', 'kamado', 24, '', 0 ),
			array( 4, 'Eva', 'Nice result. The photo shows how mine turned out.', 'gas', 30, '', 0 ),
		);
	}

	private static function replies() {
		return self::nl()
			? array( 2 => 'Bedankt Anna, fijn om te horen. Laat het vlees de volgende keer iets langer rusten, dan wordt het nog sappiger.', 3 => 'Wat een mooi verslag, Sanne, bedankt! De extra rook en de langere rusttijd zou ik zelf ook doen. Een tip voor de volgende keer: probeer een reverse sear op een hete rooster voor de laatste twee minuten, dat geeft die krokante, gekarameliseerde rand zonder dat de binnenkant uitdroogt. En fijn dat de temperatuurgids hielp. Stuur me gerust een foto van de volgende!' )
			: array( 2 => 'Thank you Anna, lovely to hear. Rest it a little longer next time and it gets even juicier.', 3 => 'What a great write-up, Sanne, thank you! The extra smoke and the longer rest are exactly what I would do as well. A tip for next time: try a reverse sear on a hot grate for the last two minutes, it adds that crisp, caramelised edge without drying out the inside. And great that the temperature guide helped. Please send me a photo of the next one!' );
	}

	/** Neutral placeholder photo (generated locally, no download). */
	private static function photo() {
		RCOM_Photo::ensure_dir();
		$im = imagecreatetruecolor( 1200, 800 );
		for ( $y = 0; $y < 800; $y++ ) {
			$c = imagecolorallocate( $im, 196 - (int) ( $y / 14 ), 120 - (int) ( $y / 18 ), 70 - (int) ( $y / 24 ) );
			imageline( $im, 0, $y, 1200, $y, $c );
		}
		imagefilledellipse( $im, 600, 400, 520, 360, imagecolorallocate( $im, 236, 214, 190 ) );
		imagefilledellipse( $im, 600, 400, 380, 250, imagecolorallocate( $im, 170, 96, 56 ) );
		$name = bin2hex( random_bytes( 16 ) ) . '.webp';
		imagewebp( $im, RCOM_Photo::dir() . '/' . $name, 78 );
		$t = imagescale( $im, 200 );
		imagewebp( $t, RCOM_Photo::dir() . '/' . substr( $name, 0, -5 ) . '-t.webp', 70 );
		return $name;
	}

	/** Add demo reviews to a recipe (and votes if the post type uses Helpful). @return int reviews added */
	public static function add( $post_id ) {
		global $wpdb;
		$n = 0;
		$i = 0;
		foreach ( self::sets() as $s ) {
			$i++;
			list( $rating, $name, $text, $bbq, $days, $photo, $reply ) = $s;
			$ts   = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS - $i * 3600 );
			$file = 'photo' === $photo || 9 === $i ? self::photo() : '';
			$rep  = self::replies()[ $reply ] ?? '';
			$wpdb->insert( RCOM_Reviews::table(), array(
				'post_id' => (int) $post_id, 'rating' => $rating, 'body' => $text, 'name' => $name, 'email' => 'demo' . $i . '@demo.invalid', 'bbq_type' => $bbq,
				'photo_file' => $file, 'photo_status' => $file ? 'approved' : 'none', 'status' => 'published', 'verified' => $i % 2, 'newsletter_optin' => 0,
				'reply_text' => $rep, 'reply_at' => $rep ? $ts : null, 'is_demo' => 1, 'created_at' => $ts, 'updated_at' => $ts,
			) );
			$n++;
		}
		RCOM_Reviews::refresh( (int) $post_id );
		return $n;
	}

	/** Demo votes and feedback for an article (Helpful). */
	public static function add_votes( $post_id ) {
		global $wpdb;
		$now   = gmdate( 'Y-m-d H:i:s' );
		$texts = self::nl() ? array( 'Duidelijk en compleet, bedankt.', 'Ik mis een stuk over de hoeveelheid houtskool.', 'De tekeningen hielpen enorm.' ) : array( 'Clear and complete, thank you.', 'Would love a section on charcoal quantities.', 'The diagrams helped a lot.' );
		for ( $i = 0; $i < 10; $i++ ) {
			$wpdb->insert( RCOM_Helpful::votes(), array( 'post_id' => (int) $post_id, 'vote' => $i < 8 ? 'yes' : 'no', 'token_hash' => 'demo' . $i, 'is_demo' => 1, 'created_at' => $now, 'updated_at' => $now ) );
			if ( $i < 3 ) {
				$wpdb->insert( RCOM_Helpful::feedback(), array( 'vote_id' => (int) $wpdb->insert_id, 'post_id' => (int) $post_id, 'body' => $texts[ $i ], 'body_hash' => md5( 'demo' . $post_id . $i ), 'status' => 'published', 'created_at' => $now ) );
			}
		}
		RCOM_Helpful::refresh( (int) $post_id );
		return 10;
	}

	/** Remove every demo row, its photos and the cached numbers of the affected posts. @return int rows removed */
	public static function clear() {
		global $wpdb;
		$posts = array();
		$n     = 0;
		foreach ( $wpdb->get_results( 'SELECT id, post_id FROM ' . RCOM_Reviews::table() . ' WHERE is_demo = 1' ) as $r ) {
			RCOM_Reviews::delete( $r->id );
			$posts[ (int) $r->post_id ] = 1;
			$n++;
		}
		foreach ( $wpdb->get_results( 'SELECT id, post_id FROM ' . RCOM_Helpful::votes() . ' WHERE is_demo = 1' ) as $v ) {
			RCOM_Helpful::delete_vote( $v->id );
			$posts[ (int) $v->post_id ] = 1;
			$n++;
		}
		foreach ( array_keys( $posts ) as $p ) {
			RCOM_Reviews::refresh( $p );
			RCOM_Helpful::refresh( $p );
		}
		return $n;
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RCOM_Reviews::table() . ' WHERE is_demo = 1' ) + (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RCOM_Helpful::votes() . ' WHERE is_demo = 1' );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/** Demo data for Recipe Community. */
	class RCOM_Demo_CLI {
		/**
		 * Add demo reviews (and demo votes for Helpful post types) to a post.
		 *
		 * ## OPTIONS
		 *
		 * --post=<id>
		 * : Post ID.
		 */
		public function add( $args, $assoc ) {
			$id = (int) ( $assoc['post'] ?? 0 );
			if ( ! $id || ! get_post( $id ) ) {
				WP_CLI::error( 'Unknown post.' );
			}
			$r = $v = 0;
			if ( RCOM_Settings::enabled_for( $id ) ) {
				$r = RCOM_Demo::add( $id );
			}
			if ( RCOM_Settings::helpful_enabled_for( $id ) ) {
				$v = RCOM_Demo::add_votes( $id );
			}
			WP_CLI::success( "Added $r demo reviews and $v demo votes." );
		}

		/** Remove all demo data. */
		public function clear() {
			WP_CLI::success( 'Removed ' . RCOM_Demo::clear() . ' demo rows.' );
		}
	}
	WP_CLI::add_command( 'rcom demo', 'RCOM_Demo_CLI' );
}
