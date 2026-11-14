<?php
defined( 'ABSPATH' ) || exit;

/** WordPress privacy tools: export and erase by email address. */
class RCOM_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', function ( $e ) {
			$e['recipe-community'] = array( 'exporter_friendly_name' => __( 'Recipe reviews', 'recipe-community' ), 'callback' => array( __CLASS__, 'export' ) );
			return $e;
		} );
		add_filter( 'wp_privacy_personal_data_erasers', function ( $e ) {
			$e['recipe-community'] = array( 'eraser_friendly_name' => __( 'Recipe reviews', 'recipe-community' ), 'callback' => array( __CLASS__, 'erase' ) );
			return $e;
		} );
	}

	private static function rows( $email ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RCOM_Reviews::table() . ' WHERE email = %s', $email ) );
	}

	public static function export( $email ) {
		$items = array();
		foreach ( self::rows( $email ) as $r ) {
			$d = array(
				array( 'name' => __( 'Recipe', 'recipe-community' ), 'value' => get_the_title( $r->post_id ) ),
				array( 'name' => __( 'Rating', 'recipe-community' ), 'value' => $r->rating ),
				array( 'name' => __( 'Review', 'recipe-community' ), 'value' => $r->body ),
				array( 'name' => __( 'Name', 'recipe-community' ), 'value' => $r->name ),
				array( 'name' => __( 'Email address', 'recipe-community' ), 'value' => $r->email ),
				array( 'name' => __( 'Newsletter', 'recipe-community' ), 'value' => ( $r->newsletter_optin && $r->verified ) ? __( 'Yes', 'recipe-community' ) : __( 'No', 'recipe-community' ) ),
				array( 'name' => __( 'Date', 'recipe-community' ), 'value' => $r->created_at ),
			);
			if ( $r->photo_file ) {
				$d[] = array( 'name' => __( 'Photo', 'recipe-community' ), 'value' => RCOM_Photo::url( $r->photo_file ) );
			}
			$items[] = array( 'group_id' => 'rcom-reviews', 'group_label' => __( 'Recipe reviews', 'recipe-community' ), 'item_id' => 'rcom-' . $r->id, 'data' => $d );
		}
		global $wpdb;
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT v.id, v.post_id, v.vote, v.created_at, f.body FROM ' . RCOM_Helpful::votes() . ' v LEFT JOIN ' . RCOM_Helpful::feedback() . ' f ON f.vote_id = v.id WHERE v.identity_id = %s', RCOM_Helpful::identity_for( $email ) ) ) as $v ) {
			$items[] = array( 'group_id' => 'rcom-votes', 'group_label' => __( 'Helpful votes', 'recipe-community' ), 'item_id' => 'rcom-v-' . $v->id, 'data' => array(
				array( 'name' => __( 'Article', 'recipe-community' ), 'value' => get_the_title( $v->post_id ) ),
				array( 'name' => __( 'Vote', 'recipe-community' ), 'value' => $v->vote ),
				array( 'name' => __( 'Feedback', 'recipe-community' ), 'value' => (string) $v->body ),
				array( 'name' => __( 'Date', 'recipe-community' ), 'value' => $v->created_at ),
			) );
		}
		return array( 'data' => $items, 'done' => true );
	}

	public static function erase( $email ) {
		$rows = self::rows( $email );
		foreach ( $rows as $r ) {
			RCOM_Reviews::delete( $r->id );
		}
		$votes = RCOM_Helpful::erase_identity( $email );
		return array( 'items_removed' => count( $rows ) + $votes, 'items_retained' => 0, 'messages' => array(), 'done' => true );
	}
}
