<?php
defined( 'ABSPATH' ) || exit;

/** Laposta (https://laposta.nl) provider. Uses the public REST API with HTTP basic auth. */
class RCOM_Laposta implements RCOM_Newsletter_Provider {

	const API = 'https://api.laposta.nl/v2/';

	public static function key() {
		return defined( 'RCOM_LAPOSTA_API_KEY' ) ? (string) RCOM_LAPOSTA_API_KEY : (string) RCOM_Settings::get( 'laposta_key' );
	}

	/** @return array{0:int,1:array,2:string} http status, data, transport error */
	private function api( $method, $path, array $body = array() ) {
		$key = self::key();
		if ( '' === $key ) {
			return array( 0, array(), 'no key' );
		}
		$res = wp_remote_request( self::API . $path, array(
			'method'      => $method,
			'timeout'     => 10,
			'redirection' => 0,
			'user-agent'  => 'recipe-community',
			'headers'     => array( 'Authorization' => 'Basic ' . base64_encode( $key . ':' ) ),
			'body'        => $body ? $body : null,
		) );
		if ( is_wp_error( $res ) ) {
			return array( 0, array(), $res->get_error_message() );
		}
		$d = json_decode( wp_remote_retrieve_body( $res ), true );
		return array( (int) wp_remote_retrieve_response_code( $res ), is_array( $d ) ? $d : array(), '' );
	}

	/** @return array<string,string>|null list id => name (cached 10 min), null when unavailable. */
	public function lists( $refresh = false ) {
		$c = get_transient( 'rcom_lp_lists' );
		if ( ! $refresh && is_array( $c ) ) {
			return $c;
		}
		list( $code, $d ) = $this->api( 'GET', 'list' );
		if ( 200 !== $code ) {
			return null;
		}
		$out = array();
		foreach ( (array) ( $d['data'] ?? array() ) as $row ) {
			if ( ! empty( $row['list']['list_id'] ) ) {
				$out[ $row['list']['list_id'] ] = (string) ( $row['list']['name'] ?? $row['list']['list_id'] );
			}
		}
		set_transient( 'rcom_lp_lists', $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	public function test() {
		if ( '' === self::key() ) {
			return array( 'ok' => false, 'message' => __( 'Enter an API key first.', 'recipe-community' ) );
		}
		list( $code, $d, $err ) = $this->api( 'GET', 'list' ); // read only: no member is created
		if ( $err ) {
			return array( 'ok' => false, 'message' => __( 'Could not reach Laposta. Try again later.', 'recipe-community' ) );
		}
		if ( 200 !== $code ) {
			return array( 'ok' => false, 'message' => __( 'Laposta rejected the API key.', 'recipe-community' ) );
		}
		delete_transient( 'rcom_lp_lists' );
		/* translators: %d: number of lists */
		return array( 'ok' => true, 'message' => sprintf( _n( 'Connected. Laposta returned %d list.', 'Connected. Laposta returned %d lists.', count( (array) ( $d['data'] ?? array() ) ), 'recipe-community' ), count( (array) ( $d['data'] ?? array() ) ) ) );
	}

	public function subscribe( $email, $name, $post_id ) {
		$list = (string) RCOM_Settings::get( 'laposta_list' );
		$ip   = RCOM_Identity::ip();
		if ( '' === $list || '' === self::key() || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		$body = array(
			'list_id'    => $list,
			'ip'         => $ip, // the visitor who just confirmed the email address (required by Laposta)
			'email'      => $email,
			'source_url' => get_permalink( $post_id ) ?: home_url( '/' ),
			'options'    => array( 'upsert' => 'true', 'suppress_reactivation' => 'true' ),
		);
		if ( 'ours' === RCOM_Settings::get( 'laposta_doi' ) ) {
			$body['options']['ignore_doubleoptin'] = 'true'; // our own email link already confirmed the address
		}
		$field = (string) RCOM_Settings::get( 'laposta_name_field' );
		if ( '' !== $field && '' !== $name ) {
			$body['custom_fields'] = array( $field => $name );
		}
		list( $code ) = $this->api( 'POST', 'member', $body );
		return in_array( $code, array( 200, 201 ), true );
	}
}
