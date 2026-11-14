<?php
defined( 'ABSPATH' ) || exit;

/** Front end: shortcodes and assets. Nothing runs (no queries, no assets) outside a single recipe. */
class RCOM_Front {

	private static $forms  = 0;
	private static $symbol = false;

	public static function init() {
		foreach ( array( 'stars', 'summary', 'form', 'list', 'reviews' ) as $s ) {
			add_shortcode( 'rcom_' . $s, array( __CLASS__, 'sc_' . $s ) );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		if ( ! is_singular( RCOM_Settings::post_types() ) || ! apply_filters( 'rcom_load_assets', true ) ) {
			return;
		}
		wp_enqueue_style( 'rcom', RCOM_URL . 'assets/rcom.css', array(), RCOM_VERSION );
		wp_enqueue_script( 'rcom', RCOM_URL . 'assets/rcom.js', array(), RCOM_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** Post id for a shortcode, or 0 when it is not an allowed post type. */
	private static function pid( $atts ) {
		$id = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : (int) get_the_ID();
		return ( $id && in_array( get_post_type( $id ), RCOM_Settings::post_types(), true ) ) ? $id : 0;
	}

	public static function bbq_types() {
		return (array) apply_filters( 'rcom_bbq_types', array(
			'charcoal' => array( __( 'Charcoal', 'recipe-community' ), __( 'made on charcoal', 'recipe-community' ) ),
			'gas'      => array( __( 'Gas grill', 'recipe-community' ), __( 'made on a gas grill', 'recipe-community' ) ),
			'kamado'   => array( __( 'Kamado', 'recipe-community' ), __( 'made on a kamado', 'recipe-community' ) ),
			'pellet'   => array( __( 'Pellet grill', 'recipe-community' ), __( 'made on a pellet grill', 'recipe-community' ) ),
			'other'    => array( __( 'Something else', 'recipe-community' ), '' ),
		) );
	}

	private static function symbol() {
		if ( self::$symbol ) {
			return '';
		}
		self::$symbol = true;
		return '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><symbol id="rcom-s" viewBox="0 0 24 24"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></symbol></svg>';
	}

	public static function star( $on ) {
		return '<span class="rcom-s' . ( $on ? ' on' : '' ) . '"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#rcom-s"/></svg></span>';
	}

	public static function stars( $n ) {
		$h = '<span class="rcom-stars" aria-hidden="true">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$h .= self::star( $i <= $n );
		}
		return $h . '</span>';
	}

	private static function fmt( $f ) {
		return number_format_i18n( round( $f, 1 ), 1 );
	}

	private static function count_label( $n ) {
		/* translators: %s: number of reviews */
		return sprintf( _n( '%s review', '%s reviews', $n, 'recipe-community' ), number_format_i18n( $n ) );
	}

	private static function stars_label( $s ) {
		/* translators: %d: number of stars */
		return sprintf( _n( '%d star', '%d stars', $s, 'recipe-community' ), $s );
	}

	/* ---------- shortcodes ---------- */

	public static function sc_stars( $atts ) {
		$atts = shortcode_atts( array( 'post_id' => 0, 'href' => '#rcom-reviews' ), $atts );
		$id   = self::pid( $atts );
		if ( ! $id ) {
			return '';
		}
		$r    = RCOM_Reviews::rating( $id );
		$href = esc_url( $atts['href'] );
		if ( ! $r['count'] ) {
			return self::symbol() . '<p class="rcom-line"><a href="' . $href . '">' . esc_html__( 'No reviews yet. Be the first to rate this recipe.', 'recipe-community' ) . '</a></p>';
		}
		/* translators: %s: average rating, e.g. 4,5 */
		$of = sprintf( __( '%s out of 5', 'recipe-community' ), self::fmt( $r['avg_all'] ) );
		return self::symbol() . '<p class="rcom-line">' . self::stars( (int) round( $r['avg_all'] ) ) . '<a href="' . $href . '">' . esc_html( $of . ' · ' . self::count_label( $r['count'] ) ) . '</a></p>';
	}

	public static function sc_summary( $atts ) {
		$id = self::pid( (array) $atts );
		if ( ! $id ) {
			return '';
		}
		$r = RCOM_Reviews::rating( $id );
		$n = $r['count'];
		if ( ! $n ) {
			return '<p class="rcom-lead">' . esc_html__( 'This recipe has no reviews yet. Did you make it? Let me know what you thought.', 'recipe-community' ) . '</p>';
		}
		$h = self::symbol() . '<div class="rcom-sum"><div class="rcom-score"><strong>' . esc_html( self::fmt( $r['avg_all'] ) ) . '</strong>' . self::stars( (int) round( $r['avg_all'] ) ) . '<p>' . esc_html( self::count_label( $n ) ) . '</p></div><ul class="rcom-bars" aria-label="' . esc_attr__( 'Distribution of the reviews', 'recipe-community' ) . '">';
		for ( $s = 5; $s >= 1; $s-- ) {
			$h .= '<li><span>' . esc_html( self::stars_label( $s ) ) . '</span><span class="rcom-bar"><span style="width:' . round( $r['dist'][ $s ] / $n * 100 ) . '%"></span></span><span>' . (int) $r['dist'][ $s ] . '</span></li>';
		}
		return $h . '</ul></div>';
	}

	public static function sc_form( $atts ) {
		$id = self::pid( (array) $atts );
		if ( ! $id ) {
			return '';
		}
		if ( ! RCOM_Settings::turnstile_ready() ) {
			// Never show a captcha-less form to visitors; tell the site owner instead.
			return current_user_can( 'manage_options' ) ? '<p class="rcom-msg err">' . esc_html__( 'Recipe Community: the review form is hidden because the Turnstile keys are missing. Add them under Recipe community > Settings (only you can see this message).', 'recipe-community' ) . '</p>' : '';
		}
		$n = ++self::$forms;
		$t = array(
			'confirmed' => __( 'Thanks, your email address is confirmed.', 'recipe-community' ),
			'manage'    => __( 'Done. You can now remove your review on this device for 30 days.', 'recipe-community' ),
			/* translators: %s: masked email address */
			'as'        => __( 'Posting with %s.', 'recipe-community' ),
			'notyou'    => __( 'Not you?', 'recipe-community' ),
			'del'       => __( 'Remove my review', 'recipe-community' ),
			'sure'      => __( 'Remove your review?', 'recipe-community' ),
			'err'       => __( 'Something went wrong. Please try again.', 'recipe-community' ),
		);
		$h  = self::symbol() . '<form class="rcom-form" method="post" action="#" data-post="' . $id . '" data-api="' . esc_url( rest_url( 'rcom/v1/' ) ) . '" data-ts="' . esc_attr( RCOM_Settings::turnstile_site() ) . '" data-t="' . esc_attr( wp_json_encode( $t ) ) . '">';
		$h .= '<h3>' . esc_html__( 'Rate this recipe', 'recipe-community' ) . '</h3><div class="rcom-msg" role="status" aria-live="polite"></div>';
		$h .= '<fieldset class="rcom-rate"><legend>' . esc_html__( 'Your rating', 'recipe-community' ) . '</legend><div class="rcom-row">';
		for ( $s = 5; $s >= 1; $s-- ) {
			$h .= '<input type="radio" id="rcom' . $n . '-r' . $s . '" name="rating" value="' . $s . '" required><label for="rcom' . $n . '-r' . $s . '"><span class="rcom-sr">' . esc_html( self::stars_label( $s ) ) . '</span>' . self::star( false ) . '</label>';
		}
		$h .= '</div></fieldset>';
		$h .= '<div class="rcom-f"><label for="rcom' . $n . '-t">' . esc_html__( 'What did you think? (optional)', 'recipe-community' ) . '</label><textarea id="rcom' . $n . '-t" name="body" maxlength="1500" placeholder="' . esc_attr__( 'How did it go and what did you cook it on?', 'recipe-community' ) . '"></textarea></div>';
		$h .= '<div class="rcom-two"><div class="rcom-f"><label for="rcom' . $n . '-n">' . esc_html__( 'First name', 'recipe-community' ) . '</label><input id="rcom' . $n . '-n" name="name" type="text" required maxlength="40" autocomplete="given-name"></div>';
		$h .= '<div class="rcom-f rcom-email"><label for="rcom' . $n . '-e">' . esc_html__( 'Email address (never shown)', 'recipe-community' ) . '</label><input id="rcom' . $n . '-e" name="email" type="email" required maxlength="190" autocomplete="email"></div></div>';
		$h .= '<div class="rcom-two"><div class="rcom-f"><label for="rcom' . $n . '-b">' . esc_html__( 'Which BBQ? (optional)', 'recipe-community' ) . '</label><select id="rcom' . $n . '-b" name="bbq"><option value="">' . esc_html__( 'Choose your BBQ', 'recipe-community' ) . '</option>';
		foreach ( self::bbq_types() as $k => $v ) {
			$h .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $v[0] ) . '</option>';
		}
		$h .= '</select></div><div class="rcom-f"><label for="rcom' . $n . '-p">' . esc_html__( 'Photo (optional)', 'recipe-community' ) . '</label><input id="rcom' . $n . '-p" name="photo" type="file" accept="image/jpeg,image/png,image/webp"></div></div>';
		$h .= '<label class="rcom-check"><input type="checkbox" name="newsletter" value="1"> <span>' . esc_html__( 'Yes, send me the newsletter', 'recipe-community' ) . '</span></label>';
		$h .= '<div class="rcom-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
		$h .= '<input type="hidden" name="nonce"><input type="hidden" name="stamp"><div class="rcom-ts"></div>';
		$h .= '<p class="rcom-small">' . esc_html__( 'Your email address is never shown. We only use it to send you one confirmation link. Your review does not depend on the newsletter.', 'recipe-community' ) . '</p>';
		$h .= '<div><button type="submit" class="rcom-btn">' . esc_html__( 'Post review', 'recipe-community' ) . '</button></div>';
		$h .= '<details class="rcom-manage"><summary>' . esc_html__( 'Already reviewed? Manage your review', 'recipe-community' ) . '</summary><div class="rcom-f"><label for="rcom' . $n . '-m">' . esc_html__( 'Send me a link to manage my review', 'recipe-community' ) . '</label><div class="rcom-inline"><input id="rcom' . $n . '-m" name="manage_email" type="email" maxlength="190" autocomplete="email"><button type="button" class="rcom-btn rcom-link">' . esc_html__( 'Send link', 'recipe-community' ) . '</button></div></div></details>';
		return $h . '<noscript><p class="rcom-small">' . esc_html__( 'JavaScript is needed to post a review.', 'recipe-community' ) . '</p></noscript></form>';
	}

	/** One review as HTML. Never contains the email address. */
	public static function item( $r ) {
		$bbq  = self::bbq_types()[ $r->bbq_type ][1] ?? '';
		$meta = wp_date( get_option( 'date_format' ), strtotime( $r->created_at . ' UTC' ) ) . ( $bbq ? ' · ' . $bbq : '' );
		/* translators: %d: stars */
		$h    = '<article class="rcom-item" data-id="' . (int) $r->id . '"><header>' . self::stars( (int) $r->rating ) . '<h3>' . esc_html( $r->name ) . '</h3><span class="rcom-sr">' . esc_html( sprintf( __( '%d out of 5 stars.', 'recipe-community' ), $r->rating ) ) . '</span><span class="rcom-small">' . esc_html( $meta ) . '</span></header>';
		if ( '' !== $r->body ) {
			$h .= '<p>' . esc_html( $r->body ) . '</p>';
		}
		if ( 'approved' === $r->photo_status && $r->photo_file ) {
			$path = RCOM_Photo::dir() . '/' . $r->photo_file;
			$dim  = is_file( $path ) ? getimagesize( $path ) : false;
			if ( $dim ) {
				/* translators: %s: first name */
				$h .= '<img class="rcom-photo" src="' . esc_url( RCOM_Photo::url( $r->photo_file ) ) . '" width="' . (int) $dim[0] . '" height="' . (int) $dim[1] . '" loading="lazy" decoding="async" alt="' . esc_attr( sprintf( __( 'Photo of the dish by %s', 'recipe-community' ), $r->name ) ) . '">';
			}
		} elseif ( 'pending' === $r->photo_status ) {
			$h .= '<p class="rcom-note">' . esc_html__( 'Photo is being checked', 'recipe-community' ) . '</p>';
		}
		if ( '' !== $r->reply_text ) {
			$label = (string) RCOM_Settings::get( 'reply_label' );
			$h    .= '<div class="rcom-reply"><strong>' . esc_html( '' !== $label ? $label : __( 'Reply from the author', 'recipe-community' ) ) . '</strong><span>' . esc_html( $r->reply_text ) . '</span></div>';
		}
		return $h . '</article>';
	}

	public static function sc_list( $atts ) {
		$atts = shortcode_atts( array( 'post_id' => 0, 'per_page' => 3 ), $atts );
		$id   = self::pid( $atts );
		if ( ! $id ) {
			return '';
		}
		$per  = max( 1, min( 50, (int) $atts['per_page'] ) );
		$n    = RCOM_Reviews::rating( $id )['count'];
		$open = '<div class="rcom-list" data-post="' . $id . '" data-api="' . esc_url( rest_url( 'rcom/v1/' ) ) . '" data-per="' . $per . '" data-total="' . $n . '">';
		if ( ! $n ) {
			return $open . '</div>';
		}
		$h = self::symbol() . $open;
		foreach ( RCOM_Reviews::list( $id, $per ) as $r ) {
			$h .= self::item( $r );
		}
		if ( $n > $per ) {
			/* translators: %s: number of reviews */
			$h .= '<button type="button" class="rcom-more">' . esc_html( sprintf( __( 'Read all %s reviews', 'recipe-community' ), number_format_i18n( $n ) ) ) . '</button>';
		}
		return $h . '</div>';
	}

	public static function sc_reviews( $atts ) {
		$atts = shortcode_atts( array( 'post_id' => 0, 'per_page' => 3, 'heading' => '1' ), $atts );
		$id   = self::pid( $atts );
		if ( ! $id ) {
			return '';
		}
		$a = array( 'post_id' => $id, 'per_page' => $atts['per_page'] );
		$h = '<section class="rcom" id="rcom-reviews"' . ( '0' !== $atts['heading'] ? ' aria-labelledby="rcom-h"><h2 id="rcom-h">' . esc_html__( 'Reviews', 'recipe-community' ) . '</h2>' : '>' ) . self::sc_summary( $a ) . self::sc_form( $a ) . self::sc_list( $a );
		if ( RCOM_Settings::get( 'show_credit' ) ) {
			$h .= '<p class="rcom-small rcom-credit"><a href="https://jconcept.nl" rel="noopener">' . esc_html__( 'Powered by J-Concept', 'recipe-community' ) . '</a></p>';
		}
		return $h . '</section>';
	}
}
