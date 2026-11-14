<?php
/**
 * Style choice (Settings > Appearance): the bundled default, a bundled skin for a CSS framework, or nothing,
 * plus a small "Extra CSS" field for site finishing. Everything is attached inline to the plugin's own handles
 * at the moment a shortcode renders, so pages without plugin output load nothing and read no setting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RCOM_Style {

	const MAX_CSS = 20480; // bytes

	private static $done = array();

	public static function skins() {
		return array(
			'neutral'        => __( 'Neutral (default)', 'recipe-community' ),
			'core-framework' => __( 'Core Framework', 'recipe-community' ),
			'none'           => __( 'None (no plugin CSS, I style it myself)', 'recipe-community' ),
		);
	}

	public static function framework_detected() {
		return defined( 'CORE_FRAMEWORK_VERSION' );
	}

	public static function current() {
		$s = RCOM_Settings::get( 'skin' );
		return isset( self::skins()[ $s ] ) ? $s : 'neutral';
	}

	/** Strip everything that could leave the style element or load code. Runs on save, and again on output. */
	public static function clean( $css ) {
		$css = str_replace( array( '<', "\0" ), '', (string) $css );
		$css = preg_replace( '#@import[^;]*;?#i', '', $css );
		$css = preg_replace( '#expression\s*\(|javascript\s*:|vbscript\s*:|behaviou?r\s*:|-moz-binding|url\s*\(\s*[\'"]?\s*(?!https://|/|data:image/)[a-z][a-z0-9+.-]*:#i', '/* removed */(', $css );
		return trim( $css );
	}

	/** Enqueue a plugin stylesheet and add the skin and the extra CSS right after it (so the extra CSS wins on equal specificity). */
	public static function enqueue( $handle ) {
		if ( isset( self::$done[ $handle ] ) ) {
			return wp_enqueue_style( $handle );
		}
		self::$done[ $handle ] = true;
		$skin = self::current();
		if ( 'none' === $skin && 1 === count( self::$done ) ) {
			foreach ( array( 'rcom' => array(), 'rcom-helpful' => array(), 'rcom-account' => array( 'rcom' ) ) as $h => $deps ) {
				wp_deregister_style( $h );
				wp_register_style( $h, false, $deps, RCOM_VERSION ); // handles stay, so the extra CSS still prints
			}
		}
		$css = 'core-framework' === $skin ? (string) file_get_contents( RCOM_DIR . 'assets/skins/core-framework.css' ) : '';
		$css .= self::clean( RCOM_Settings::get( 'extra_css' ) );
		if ( 'rcom-account' !== $handle && '' !== trim( $css ) ) { // the account stylesheet depends on 'rcom', which carries it
			wp_add_inline_style( $handle, $css );
		}
		wp_enqueue_style( $handle );
	}
}
