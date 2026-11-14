<?php
// Read order of the Laposta key and the Turnstile keys, and the notification option. Mock data only.
// Run through tests/run-config-tests.sh (constants cannot be undefined, so each case is its own process).
$c = getenv( 'CASE' ); $own = getenv( 'OWN' ) ?: ''; $out = array();
if ( getenv( 'T_RCOM' ) ) { define( 'RCOM_LAPOSTA_API_KEY', 'RC-KEY-1111' ); }
if ( getenv( 'T_LP' ) )   { define( 'LAPOSTA_API_KEY', 'LP-KEY-2222' ); }
if ( getenv( 'T_TS' ) )   { define( 'RCOM_TURNSTILE_SITE_KEY', 'CONST-SITE' ); define( 'RCOM_TURNSTILE_SECRET_KEY', 'CONST-SECRET' ); }
if ( getenv( 'T_BRICKS' ) && ! class_exists( 'Bricks_Laposta' ) ) { eval( 'class Bricks_Laposta { static function get_api_key() { return "BRICKS-KEY-3333"; } static function get_settings() { return array( "list_id" => "BRLIST" ); } }' ); }
add_filter( 'pre_option_rcom_settings', function () use ( $own ) { return array( 'laposta_key' => $own, 'turnstile_site' => $own ? 'OWN-SITE' : '', 'turnstile_secret' => $own ? 'OWN-SECRET' : '', 'laposta_list' => getenv( 'NOLIST' ) ? '' : 'L1', 'laposta_quiet' => (int) getenv( 'QUIET' ), 'laposta_doi' => 'laposta' ); } );
add_filter( 'pre_option_bricks_global_settings', function () { return getenv( 'T_BRICKS' ) ? array( 'apiKeyTurnstile' => 'BR-SITE', 'apiSecretKeyTurnstile' => 'BR-SECRET' ) : array(); } );
echo implode( '|', array( RCOM_Laposta::key_source()[1], RCOM_Laposta::key_source()[0], RCOM_Settings::turnstile_key( 'site' )[1], RCOM_Settings::turnstile_site(), RCOM_Settings::turnstile_key( 'secret' )[1] ) ) . "\n";
if ( getenv( 'LISTS' ) ) { echo implode( '|', RCOM_Laposta::list_source() ) . "\n"; }
if ( getenv( 'SUB' ) ) {
	$_SERVER['REMOTE_ADDR'] = '203.0.113.7'; $seen = null;
	add_filter( 'pre_http_request', function ( $p, $a ) use ( &$seen ) { $seen = $a; return array( 'response' => array( 'code' => 201, 'message' => '' ), 'body' => '{}', 'headers' => array(), 'cookies' => array() ); }, 10, 2 );
	( new RCOM_Laposta() )->subscribe( 'a@example.nl', 'Jan', 0 );
	echo 'OPT:' . wp_json_encode( $seen['body']['options'] ?? null ) . "\n";
}
if ( getenv( 'HTML' ) ) { // no secret or full key in the settings screen
	wp_set_current_user( 1 ); require_once RCOM_DIR . 'admin/class-admin.php'; require_once RCOM_DIR . 'admin/class-views.php'; ob_start(); RCOM_Views::settings(); $h = ob_get_clean();
	foreach ( array( 'BR-SECRET', 'OWN-SECRET', 'CONST-SECRET', 'RC-KEY-1111', 'LP-KEY-2222', 'BRICKS-KEY-3333' ) as $s ) { if ( false !== strpos( $h, $s ) ) { echo "LEAK:$s\n"; } }
	echo 'HTMLOK:' . ( false !== strpos( $h, '••••' ) ? 'masked' : 'nomask' ) . "\n";
}
