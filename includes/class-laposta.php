<?php
defined( 'ABSPATH' ) || exit;

/** Laposta (https://laposta.nl) provider. Uses the public REST API with HTTP basic auth. */
class RCOM_Laposta implements RCOM_Newsletter_Provider {

	const API = 'https://api.laposta.nl/v2/';

	/**
	 * The API key and where it comes from, in this order: RCOM_LAPOSTA_API_KEY, LAPOSTA_API_KEY (also read by Bricks Laposta,
	 * so one define serves both plugins), the key saved in this plugin, the key of Bricks Laposta (read live, never copied).
	 * @return array{0:string,1:string} key, source ('rcom_constant', 'constant', 'own', 'bricks' or '')
	 */
	public static function key_source() {
		if ( defined( 'RCOM_LAPOSTA_API_KEY' ) && '' !== (string) RCOM_LAPOSTA_API_KEY ) {
			return array( (string) RCOM_LAPOSTA_API_KEY, 'rcom_constant' );
		}
		if ( defined( 'LAPOSTA_API_KEY' ) && '' !== (string) LAPOSTA_API_KEY ) {
			return array( (string) LAPOSTA_API_KEY, 'constant' );
		}
		$own = (string) RCOM_Settings::get( 'laposta_key' );
		if ( '' !== $own ) {
			return array( $own, 'own' );
		}
		if ( class_exists( 'Bricks_Laposta' ) && method_exists( 'Bricks_Laposta', 'get_api_key' ) ) {
			$b = (string) Bricks_Laposta::get_api_key();
			if ( '' !== $b ) {
				return array( $b, 'bricks' );
			}
		}
		return array( '', '' );
	}

	public static function key() {
		return self::key_source()[0];
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

	/** @return array<string,array{name:string,members:?int}>|null list id => details (cached 10 min), null when unavailable. */
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
			$l = $row['list'] ?? array();
			if ( ! empty( $l['list_id'] ) ) {
				$out[ $l['list_id'] ] = array( 'name' => (string) ( $l['name'] ?? $l['list_id'] ), 'members' => isset( $l['members']['active_count'] ) ? (int) $l['members']['active_count'] : null );
			}
		}
		set_transient( 'rcom_lp_lists', $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** @return array<string,string>|null custom field tag => name of a list (email excluded), cached 10 min. */
	public function fields( $list_id ) {
		$k = 'rcom_lp_f_' . md5( $list_id );
		$c = get_transient( $k );
		if ( is_array( $c ) ) {
			return $c;
		}
		list( $code, $d ) = $this->api( 'GET', 'field?list_id=' . rawurlencode( $list_id ) );
		if ( 200 !== $code ) {
			return null;
		}
		$out = array();
		foreach ( (array) ( $d['data'] ?? array() ) as $row ) {
			$f = $row['field'] ?? array();
			// Member calls are keyed by custom_name; 'tag' is the campaign variable and has braces ({{voornaam}}).
			$key = (string) ( $f['custom_name'] ?? trim( (string) ( $f['tag'] ?? '' ), '{}' ) );
			if ( '' !== $key && 'email' !== $key && empty( $f['is_email'] ) && 'deleted' !== ( $f['state'] ?? '' ) ) {
				$out[ $key ] = (string) ( $f['name'] ?? $key );
			}
		}
		set_transient( $k, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** Pick the first-name field by tag or name. */
	public static function guess_name_field( array $fields ) {
		foreach ( $fields as $tag => $name ) {
			if ( preg_match( '/^(voornaam|firstname|first_name|first|naam|name)$/i', $tag ) || preg_match( '/^(voornaam|first ?name|naam|name)$/i', $name ) ) {
				return $tag;
			}
		}
		return '';
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

	/** Unsubscribe a member (needs the list id). Returns true on success. */
	public function unsubscribe( $email ) {
		$list = (string) RCOM_Settings::get( 'laposta_list' );
		if ( '' === $list || '' === self::key() ) {
			return false;
		}
		list( $code ) = $this->api( 'PUT', 'member/' . rawurlencode( $email ), array( 'list_id' => $list, 'state' => 'unsubscribed' ) );
		return 200 === $code;
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
		if ( RCOM_Settings::get( 'laposta_quiet' ) ) {
			$body['options']['suppress_email_notification'] = 'true'; // no notification email to the list owner for this sign-up
		}
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
