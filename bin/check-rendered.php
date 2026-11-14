<?php
/**
 * Rendered-output translation check.
 *
 * The static check (check-translations.sh) proves the .po is complete. This one proves the compiled .mo is
 * what WordPress really loads and that the shortcode output, including the JS strings, is in the right
 * language. Run from the WordPress root: wp eval-file wp-content/plugins/recipe-community/bin/check-rendered.php [post_id]
 *
 * nl_NL: no English source string may appear in the output. en_US: no Dutch translation may appear.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
require_once ABSPATH . WPINC . '/pomo/po.php';

$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
if ( ! $post_id ) {
	$ids     = get_posts( array( 'post_type' => (array) RCOM_Settings::get( 'post_types' ), 'numberposts' => 1, 'fields' => 'ids' ) );
	$post_id = $ids ? (int) $ids[0] : 0;
}
if ( ! $post_id ) {
	WP_CLI::error( 'No post found to render. Pass a post ID.' );
}
$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
setup_postdata( $GLOBALS['post'] );

$po = new PO();
$po->import_from_file( RCOM_DIR . 'languages/recipe-community-nl_NL.po' );
$pairs = array();
foreach ( $po->entries as $e ) {
	$id = $e->singular;
	$tr = isset( $e->translations[0] ) ? $e->translations[0] : '';
	if ( $e->is_plural || strlen( $id ) < 8 || '' === $tr || $tr === $id ) {
		continue;
	}
	$pairs[ $id ] = $tr;
}

/** Regex that matches a source string with its sprintf placeholders. */
$rx = function ( $s ) {
	$q = preg_quote( $s, '/' );
	return '/' . preg_replace( '/%(\d+\$)?[sd]/', '.{0,40}?', str_replace( array( '\%', '%' ), '%', $q ) ) . '/u';
};

$render = function () use ( $post_id ) {
	ob_start();
	$h  = do_shortcode( '[rcom_reviews post="' . $post_id . '"][rcom_helpful post="' . $post_id . '"][rcom_account]' );
	RCOM_Front::print_i18n();
	$h .= ob_get_clean();
	$h  = preg_replace_callback( '#(<script type="application/json" id="rcom-i18n">)(.*?)(</script>)#s', function ( $m ) {
		return $m[1] . implode( "\n", array_map( 'strval', iterator_to_array( new RecursiveIteratorIterator( new RecursiveArrayIterator( (array) json_decode( $m[2], true ) ) ), false ) ) ) . $m[3];
	}, $h );
	return html_entity_decode( $h, ENT_QUOTES, 'UTF-8' );
};

$fail = 0;
foreach ( array( 'nl_NL', 'en_US' ) as $loc ) {
	switch_to_locale( $loc );
	// Fresh render per locale: the i18n collector is static, so reset it by reflection.
	$ref = new ReflectionProperty( 'RCOM_Front', 'i18n' );
	$ref->setAccessible( true );
	$ref->setValue( null, array() );
	$out   = $render();
	$bad   = array();
	$found = 0;
	foreach ( $pairs as $id => $tr ) {
		if ( 'nl_NL' === $loc ) {
			if ( preg_match( $rx( $id ), $out ) ) {
				$bad[] = $id;
			}
			if ( false !== mb_strpos( $out, $tr ) || ( false !== strpos( $tr, '%' ) && preg_match( $rx( $tr ), $out ) ) ) {
				++$found;
			}
		} else {
			if ( false !== mb_strpos( $out, $tr ) ) {
				$bad[] = $tr;
			}
			if ( preg_match( $rx( $id ), $out ) ) {
				++$found;
			}
		}
	}
	if ( $found < 10 ) {
		WP_CLI::warning( "$loc: only $found known strings recognised in the output; the check is not meaningful (wrong post, or .mo not loaded)." );
		$fail = 1;
	}
	if ( $bad ) {
		WP_CLI::warning( "$loc: " . count( $bad ) . ' string(s) in the wrong language:' );
		foreach ( array_slice( $bad, 0, 15 ) as $b ) {
			WP_CLI::log( '  - ' . $b );
		}
		$fail = 1;
	} else {
		WP_CLI::log( "$loc: OK ($found known strings found in " . strlen( $out ) . ' bytes of output)' );
	}
	restore_previous_locale();
}
if ( $fail ) {
	WP_CLI::error( 'Rendered output has translation problems.' );
}
WP_CLI::success( 'Rendered output is in the right language (nl_NL and en_US).' );
