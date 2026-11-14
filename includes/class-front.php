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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'wp_head', array( __CLASS__, 'canonical' ), 0 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'rm_robots' ) );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'rm_canonical' ) );
	}

	/** Register only; the shortcodes enqueue when they actually render, so nothing loads elsewhere. */
	public static function register() {
		wp_register_style( 'rcom', RCOM_URL . 'assets/rcom.css', array(), RCOM_VERSION );
		wp_register_script( 'rcom', RCOM_URL . 'assets/rcom.js', array(), RCOM_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** ?rcom_page=N variants stay out of the index; the canonical remains the plain recipe URL. */
	public static function robots( $r ) {
		if ( isset( $_GET['rcom_page'] ) ) {
			$r['noindex'] = true;
			$r['follow']  = true;
			unset( $r['max-image-preview'] );
		}
		return $r;
	}

	/** Plain recipe URL as canonical of ?rcom_page=N. The head output is checked once: only when nobody printed a canonical (Rank Math skips it on noindex pages) we add ours, so there is never a duplicate. */
	public static function canonical() {
		if ( ! isset( $_GET['rcom_page'] ) || ! is_singular( RCOM_Settings::post_types() ) ) {
			return;
		}
		ob_start();
		add_action( 'wp_head', static function () {
			$h = ob_get_clean();
			echo $h; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! preg_match( '/rel=["\']canonical["\']/i', $h ) ) {
				echo '<link rel="canonical" href="' . esc_url( get_permalink() ) . '" />' . "\n";
			}
		}, 9999 );
	}

	public static function rm_canonical( $c ) {
		return ( isset( $_GET['rcom_page'] ) && is_singular( RCOM_Settings::post_types() ) ) ? get_permalink() : $c;
	}

	public static function rm_robots( $r ) {
		return isset( $_GET['rcom_page'] ) ? array_merge( (array) $r, array( 'index' => 'noindex', 'follow' => 'follow' ) ) : $r;
	}

	private static function enqueue() {
		if ( apply_filters( 'rcom_load_assets', true ) ) {
			wp_enqueue_style( 'rcom' );
			wp_enqueue_script( 'rcom' );
		}
	}

	/** Post id for a shortcode, or 0 when it is not an allowed post type. */
	private static function pid( $atts ) {
		$id = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : (int) get_the_ID();
		if ( $id && RCOM_Settings::enabled_for( $id ) ) {
			self::enqueue();
			return $id;
		}
		return 0;
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

	public static function symbol() {
		if ( self::$symbol ) {
			return '';
		}
		self::$symbol = true;
		return '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><symbol id="rcom-s" viewBox="0 0 24 24"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></symbol></svg>';
	}

	public static function star( $on ) {
		return '<span class="rcom-s' . ( $on ? ' on' : '' ) . '"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><use href="#rcom-s"/></svg></span>';
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

	public static function credit() {
		return RCOM_Settings::get( 'show_credit' ) ? '<p class="rcom-small rcom-credit"><a href="https://jconcept.nl" rel="noopener">' . esc_html__( 'Powered by J-Concept', 'recipe-community' ) . '</a></p>' : '';
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
			'del' => __( 'Delete', 'recipe-community' ),
			'mine' => __( 'Your review', 'recipe-community' ),
			'done' => __( 'Thanks for your review.', 'recipe-community' ),
			'sure'      => __( 'Remove your review?', 'recipe-community' ),
			'err'       => __( 'Something went wrong. Please try again.', 'recipe-community' ),
			'pchange'   => __( 'Change photo', 'recipe-community' ),
			'padd'      => __( 'Add photo', 'recipe-community' ),
			'ptype'     => __( 'Please choose a JPG, PNG or WebP image.', 'recipe-community' ),
			'pbig'      => __( 'This photo is too large (max 8 MB).', 'recipe-community' ),
		);
		$h  = self::symbol() . '<form class="rcom-form" method="post" action="#" data-post="' . $id . '" data-api="' . esc_url( rest_url( 'rcom/v1/' ) ) . '" data-ts="' . esc_attr( RCOM_Settings::turnstile_site() ) . '" data-t="' . esc_attr( wp_json_encode( $t ) ) . '">';
		$h .= '<p class="rcom-ft">' . esc_html__( 'Rate this recipe', 'recipe-community' ) . '</p><div class="rcom-msg" role="status" aria-live="polite"></div>';
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
		$h .= '</select></div><div class="rcom-f rcom-photo-f"><span class="rcom-lbl">' . esc_html__( 'Photo', 'recipe-community' ) . ' <small>(' . esc_html__( 'optional', 'recipe-community' ) . ')</small></span><input id="rcom' . $n . '-p" class="rcom-file" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><label for="rcom' . $n . '-p" class="rcom-fbtn"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg><span class="rcom-fbtn-t">' . esc_html__( 'Add photo', 'recipe-community' ) . '</span></label><div class="rcom-prev" hidden><img alt="" width="56" height="56"><span class="rcom-pn"></span><button type="button" class="rcom-del">' . esc_html__( 'Remove', 'recipe-community' ) . '</button></div><p class="rcom-ferr" role="alert"></p></div></div>';
		$h .= '<label class="rcom-check"><input type="checkbox" name="newsletter" value="1"> <span>' . esc_html( '' !== (string) RCOM_Settings::get( 'newsletter_label' ) ? RCOM_Settings::get( 'newsletter_label' ) : __( 'Yes, send me the newsletter', 'recipe-community' ) ) . '</span></label>';
		$tp  = (int) RCOM_Settings::get( 'terms_page' ) ?: (int) RCOM_Settings::get( 'privacy_page' );
		$tl  = $tp ? '<a href="' . esc_url( get_permalink( $tp ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'terms', 'recipe-community' ) . '</a>' : esc_html__( 'terms', 'recipe-community' );
		$ts  = (string) RCOM_Settings::get( 'terms_label' ); // optional custom sentence, %s marks the link
		/* translators: %1$s: the word "terms", linked to the terms page */
		$tx  = '' !== $ts ? sprintf( esc_html( $ts ), $tl ) : sprintf( esc_html__( 'I agree to the %1$s', 'recipe-community' ), $tl );
		$h  .= '<label class="rcom-check"><input type="checkbox" name="terms" value="1" required> <span>' . $tx . '</span></label>';
		$h .= '<div class="rcom-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
		$h .= '<input type="hidden" name="nonce"><input type="hidden" name="stamp"><div class="rcom-ts"></div>';
		$h .= '<div><button type="submit" class="rcom-btn">' . esc_html__( 'Post review', 'recipe-community' ) . '</button></div>';
		$pp = (int) RCOM_Settings::get( 'privacy_page' );
		if ( $pp ) {
			/* translators: %1$s: the words "privacy policy", linked to the privacy page */
			$h .= '<p class="rcom-small rcom-priv">' . sprintf( esc_html__( 'Your email address is never visible and only used for the confirmation (and for the newsletter if you tick it). More in the %1$s.', 'recipe-community' ), '<a href="' . esc_url( get_permalink( $pp ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'recipe-community' ) . '</a>' ) . '</p>';
		} else {
			$h .= '<p class="rcom-small rcom-priv">' . esc_html__( 'Your email address is never visible and only used for the confirmation (and for the newsletter if you tick it).', 'recipe-community' ) . '</p>';
		}
		return $h . '<noscript><p class="rcom-small">' . esc_html__( 'JavaScript is needed to post a review.', 'recipe-community' ) . '</p></noscript></form>';
	}

	/** One review as HTML. Never contains the email address. */
	public static function item( $r ) {
		$bbq  = self::bbq_types()[ $r->bbq_type ][1] ?? '';
		$meta = wp_date( get_option( 'date_format' ), strtotime( $r->created_at . ' UTC' ) ) . ( $bbq ? ' · ' . $bbq : '' );
		/* translators: %d: stars */
		$h    = '<article class="rcom-item" data-id="' . (int) $r->id . '" data-c="' . (int) strtotime( $r->created_at . ' UTC' ) . '.' . (int) $r->id . '"><header>' . self::stars( (int) $r->rating ) . '<strong class="rcom-name">' . esc_html( $r->name ) . '</strong><span class="rcom-sr">' . esc_html( sprintf( __( '%d out of 5 stars.', 'recipe-community' ), $r->rating ) ) . '</span><span class="rcom-small">' . esc_html( $meta ) . '</span></header>';
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
		$n    = RCOM_Reviews::rating( $id )['count']; // from the cache, no count query
		$pg   = isset( $_GET['rcom_page'] ) ? max( 1, min( 20, (int) $_GET['rcom_page'] ) ) : 1; // no-JS fallback: 10 more per step
		$show = $per + ( $pg - 1 ) * 10;
		$open = '<div class="rcom-list" id="rcom-list" data-post="' . $id . '" data-api="' . esc_url( rest_url( 'rcom/v1/' ) ) . '" data-total="' . $n . '" data-t="' . esc_attr( wp_json_encode( array( 'more' => __( 'Show more (%s left)', 'recipe-community' ), 'loaded' => __( 'More reviews loaded.', 'recipe-community' ) ) ) ) . '">';
		if ( ! $n ) {
			return $open . '</div>';
		}
		$h = self::symbol() . $open;
		foreach ( RCOM_Reviews::list( $id, $show ) as $r ) {
			$h .= self::item( $r );
		}
		if ( $n > $show ) {
			/* translators: %s: number of reviews */
			$h .= '<a class="rcom-more" rel="nofollow" href="' . esc_url( add_query_arg( 'rcom_page', $pg + 1 ) . '#rcom-list' ) . '">' . esc_html( sprintf( __( 'Show all %s reviews', 'recipe-community' ), number_format_i18n( $n ) ) ) . '</a>';
		}
		$h .= '<p class="rcom-sr" role="status" aria-live="polite"></p>';
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
		// A future [rcom_account] page can reuse the REST route POST /rcom/v1/link (RCOM_Identity::send_manage); no link on the recipe page.
		$h .= self::credit();
		return $h . '</section>';
	}
}
