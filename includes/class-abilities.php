<?php
defined( 'ABSPATH' ) || exit;

/**
 * Optional WordPress Abilities API integration (WordPress 6.9+) for administrator AI assistants.
 * Off by default (Settings > "Allow AI abilities"). Every ability needs manage_options, makes no network
 * calls, and never returns email addresses unless include_email is asked for explicitly.
 */
class RCOM_Abilities {

	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) || ! RCOM_Settings::get( 'ai_abilities' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', function () {
			wp_register_ability_category( 'recipe-community', array( 'label' => 'Recipe Community', 'description' => __( 'Reviews and helpful votes of recipe pages.', 'recipe-community' ) ) );
		} );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
	}

	public static function perm() {
		return current_user_can( 'manage_options' );
	}

	private static function def( $label, $desc, array $props, array $required, $cb, $readonly = false, $destructive = false ) {
		return array(
			'label'               => $label,
			'description'         => $desc,
			'category'            => 'recipe-community',
			'input_schema'        => array_filter( array( 'type' => 'object', 'properties' => $props, 'required' => $required, 'additionalProperties' => false ) ),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => $cb,
			'permission_callback' => array( __CLASS__, 'perm' ),
			'meta'                => array( 'show_in_rest' => true, 'annotations' => array( 'readonly' => $readonly, 'destructive' => $destructive, 'idempotent' => ! $destructive ) ),
		);
	}

	public static function register() {
		$id = array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Review id' );
		wp_register_ability( 'recipe-community/get-stats', self::def( 'Get review statistics', 'Totals, average, star distribution and top recipes for a period. No personal data.', array( 'days' => array( 'type' => 'integer', 'enum' => array( 7, 30, 90, 0 ), 'default' => 30, 'description' => '0 = all time' ) ), array(), function ( $in ) {
			$s = RCOM_Stats::get( (int) ( $in['days'] ?? 30 ) );
			foreach ( array( 'top', 'best', 'worst' ) as $k ) {
				foreach ( $s[ $k ] as &$r ) {
					$r['title'] = get_the_title( $r['post_id'] );
				}
				unset( $r );
			}
			unset( $s['recent'], $s['least'] );
			return $s;
		}, true ) );
		wp_register_ability( 'recipe-community/list-reviews', self::def( 'List reviews', 'List reviews, newest first. Email addresses are only included when include_email is true.', array(
			'post_id' => array( 'type' => 'integer' ), 'rating' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ),
			'status' => array( 'type' => 'string', 'enum' => array( 'published', 'hidden' ) ), 'photo_pending' => array( 'type' => 'boolean' ),
			'search' => array( 'type' => 'string', 'maxLength' => 100 ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20 ),
			'include_email' => array( 'type' => 'boolean', 'default' => false ),
		), array(), function ( $in ) {
			global $wpdb;
			$w = array( '1=1' ); $a = array();
			if ( ! empty( $in['post_id'] ) ) { $w[] = 'post_id = %d'; $a[] = (int) $in['post_id']; }
			if ( ! empty( $in['rating'] ) ) { $w[] = 'rating = %d'; $a[] = (int) $in['rating']; }
			if ( ! empty( $in['status'] ) && in_array( $in['status'], array( 'published', 'hidden' ), true ) ) { $w[] = 'status = %s'; $a[] = $in['status']; }
			if ( ! empty( $in['photo_pending'] ) ) { $w[] = "photo_status = 'pending'"; }
			if ( ! empty( $in['search'] ) ) { $l = '%' . $wpdb->esc_like( (string) $in['search'] ) . '%'; $w[] = '(body LIKE %s OR name LIKE %s)'; array_push( $a, $l, $l ); }
			$a[] = max( 1, min( 50, (int) ( $in['limit'] ?? 20 ) ) );
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RCOM_Reviews::table() . ' WHERE ' . implode( ' AND ', $w ) . ' ORDER BY created_at DESC, id DESC LIMIT %d', $a ) );
			$out  = array();
			foreach ( $rows as $r ) {
				$o = array( 'id' => (int) $r->id, 'post_id' => (int) $r->post_id, 'recipe' => get_the_title( $r->post_id ), 'rating' => (int) $r->rating, 'text' => $r->body, 'name' => $r->name, 'verified' => (bool) $r->verified, 'status' => $r->status, 'photo_status' => $r->photo_status, 'reply' => $r->reply_text, 'date' => $r->created_at );
				if ( ! empty( $in['include_email'] ) ) {
					$o['email'] = $r->email;
				}
				$out[] = $o;
			}
			return array( 'reviews' => $out );
		}, true ) );
		wp_register_ability( 'recipe-community/reply-review', self::def( 'Reply to a review', 'Set or clear (empty text) the site owner reply shown under a review.', array( 'id' => $id, 'reply_text' => array( 'type' => 'string', 'maxLength' => 1500 ) ), array( 'id', 'reply_text' ), function ( $in ) {
			$t = sanitize_textarea_field( (string) $in['reply_text'] );
			return array( 'ok' => RCOM_Reviews::update( (int) $in['id'], array( 'reply_text' => $t, 'reply_at' => '' === $t ? null : current_time( 'mysql', true ) ) ) );
		} ) );
		wp_register_ability( 'recipe-community/approve-photo', self::def( 'Approve a review photo', 'Mark the photo of a review as approved so it shows publicly.', array( 'id' => $id ), array( 'id' ), function ( $in ) {
			$r = RCOM_Reviews::get( (int) $in['id'] );
			return array( 'ok' => ( $r && $r->photo_file ) ? RCOM_Reviews::update( $r->id, array( 'photo_status' => 'approved' ) ) : false );
		} ) );
		wp_register_ability( 'recipe-community/set-review-visibility', self::def( 'Hide or show a review', 'Hide a review from the public site or show it again.', array( 'id' => $id, 'visible' => array( 'type' => 'boolean' ) ), array( 'id', 'visible' ), function ( $in ) {
			return array( 'ok' => RCOM_Reviews::update( (int) $in['id'], array( 'status' => $in['visible'] ? 'published' : 'hidden' ) ) );
		} ) );
		wp_register_ability( 'recipe-community/delete-review', self::def( 'Delete a review', 'Permanently delete a review and its photo. Requires confirm = true.', array( 'id' => $id, 'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true' ) ), array( 'id', 'confirm' ), function ( $in ) {
			if ( true !== $in['confirm'] ) {
				return new WP_Error( 'rcom_confirm', 'confirm must be true' );
			}
			RCOM_Reviews::delete( (int) $in['id'] );
			return array( 'ok' => true );
		}, false, true ) );
	}
}
