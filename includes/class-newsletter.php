<?php
defined( 'ABSPATH' ) || exit;

/** A newsletter service. Add more by implementing this and using the 'rcom_newsletter_providers' filter. */
interface RCOM_Newsletter_Provider {
	/** Subscribe a confirmed address. Return true on success. */
	public function subscribe( $email, $name, $post_id );
	/** Read-only connection test. @return array{ok:bool,message:string} */
	public function test();
}

/** Connects the generic 'rcom_newsletter_optin' action to the chosen provider. */
class RCOM_Newsletter {

	public static function init() {
		add_action( 'rcom_newsletter_optin', array( __CLASS__, 'dispatch' ), 10, 3 );
	}

	/** @return array<string,array{label:string,class:string}> */
	public static function providers() {
		return (array) apply_filters( 'rcom_newsletter_providers', array(
			'none'    => array( 'label' => __( 'None', 'recipe-community' ), 'class' => '' ),
			'laposta' => array( 'label' => 'Laposta', 'class' => 'RCOM_Laposta' ),
		) );
	}

	public static function provider( $id = '' ) {
		$id = $id ? $id : (string) RCOM_Settings::get( 'newsletter_provider' );
		$p  = self::providers()[ $id ] ?? null;
		return ( $p && $p['class'] && class_exists( $p['class'] ) ) ? new $p['class']() : null;
	}

	public static function dispatch( $email, $name, $post_id ) {
		$p = self::provider();
		if ( $p && is_email( $email ) ) {
			$p->subscribe( $email, (string) $name, (int) $post_id );
		}
	}
}
