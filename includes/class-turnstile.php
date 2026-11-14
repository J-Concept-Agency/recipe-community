<?php
defined( 'ABSPATH' ) || exit;

/** Cloudflare Turnstile server-side verification. */
class RCOM_Turnstile {

	const VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/** @return array|WP_Error Decoded siteverify response. */
	private static function call( $secret, $token ) {
		$res = wp_remote_post( self::VERIFY, array( 'timeout' => 8, 'body' => array( 'secret' => $secret, 'response' => $token ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$d = json_decode( wp_remote_retrieve_body( $res ), true );
		return is_array( $d ) ? $d : new WP_Error( 'rcom_ts', 'bad response' );
	}

	/** Verify a visitor token. Fails closed. */
	public static function verify( $token ) {
		$token = (string) $token;
		if ( '' === $token || strlen( $token ) > 2048 || ! RCOM_Settings::turnstile_ready() ) {
			return false;
		}
		$d = self::call( RCOM_Settings::turnstile_secret(), $token );
		return ! is_wp_error( $d ) && ! empty( $d['success'] );
	}

	/**
	 * Check the secret key with a dummy token. Cloudflare answers 'invalid-input-secret' for a wrong secret
	 * and 'invalid-input-response' (or success for the test secret) for a good one. The site key cannot be checked server-side.
	 *
	 * @return string ok|bad|none|net
	 */
	public static function test_keys() {
		if ( ! RCOM_Settings::turnstile_ready() ) {
			return 'none';
		}
		$d = self::call( RCOM_Settings::turnstile_secret(), 'XXXX.DUMMY.TOKEN.XXXX' );
		if ( is_wp_error( $d ) ) {
			return 'net';
		}
		$codes = (array) ( $d['error-codes'] ?? array() );
		return ( ! empty( $d['success'] ) || ( $codes && ! in_array( 'invalid-input-secret', $codes, true ) && ! in_array( 'missing-input-secret', $codes, true ) ) ) ? 'ok' : 'bad';
	}
}
