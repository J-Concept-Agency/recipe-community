<?php
// wp eval-file: Recipe Community Laposta-provider met nagebootste API (geen echte aanroepen, geen DB-schrijfacties)
define( 'RCOM_LAPOSTA_API_KEY', 'SECRET-TESTKEY-123' );
$GLOBALS['fails'] = 0; $GLOBALS['calls'] = [];
function t( $n, $c, $i = '' ) { echo ( $c ? 'OK   ' : 'FAIL ' ) . $n . ( $c ? '' : "  $i" ) . "\n"; if ( ! $c ) { $GLOBALS['fails']++; } }
add_filter( 'pre_transient_rcom_lp_f_' . md5( 'L1' ), '__return_false' );
add_filter( 'pre_set_transient_rcom_lp_f_' . md5( 'L1' ), function ( $v ) { return $v; } );
add_filter( 'pre_http_request', function ( $pre, $a, $url ) {
	$GLOBALS['calls'][] = [ $a['method'], $url, $a['body'] ];
	if ( false !== strpos( $url, '/field' ) ) {
		$body = [ 'data' => [
			[ 'field' => [ 'tag' => '{{email}}', 'custom_name' => 'email', 'name' => 'E-mail', 'is_email' => true, 'state' => 'active' ] ],
			[ 'field' => [ 'tag' => '{{voornaam}}', 'custom_name' => 'voornaam', 'name' => 'Voornaam', 'state' => 'active' ] ],
			[ 'field' => [ 'tag' => '{{oud}}', 'custom_name' => 'oud', 'name' => 'Oud', 'state' => 'deleted' ] ] ] ];
	} else { $body = []; }
	return [ 'response' => [ 'code' => 200, 'message' => '' ], 'body' => wp_json_encode( $body ), 'headers' => [], 'cookies' => [] ];
}, 10, 3 );
delete_transient( 'rcom_lp_f_' . md5( 'L1' ) );
$f = ( new RCOM_Laposta() )->fields( 'L1' );
delete_transient( 'rcom_lp_f_' . md5( 'L1' ) ); // the provider caches in the DB; leave nothing behind
t( 'velden: sleutel is custom_name (zonder accolades), e-mail en verwijderd veld uitgesloten', $f === [ 'voornaam' => 'Voornaam' ], json_encode( $f ) );
t( 'voornaamveld wordt herkend', 'voornaam' === RCOM_Laposta::guess_name_field( (array) $f ) );
echo $GLOBALS['fails'] ? "\n{$GLOBALS['fails']} FOUT(EN)\n" : "\nALLES OK\n";
