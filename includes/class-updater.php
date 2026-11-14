<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tiny updater: asks the public GitHub Releases API for the latest release and plugs into the
 * WordPress update screens. Sends nothing about the site or its users (plain HTTP request with a fixed user agent).
 * Disable with define( 'RCOM_DISABLE_UPDATE_CHECK', true ) or the setting.
 */
class RCOM_Updater {

	const REPO = 'J-Concept-Agency/recipe-community';
	const SLUG = 'recipe-community';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 10, 3 );
	}

	public static function disabled() {
		return ( defined( 'RCOM_DISABLE_UPDATE_CHECK' ) && RCOM_DISABLE_UPDATE_CHECK ) || (bool) RCOM_Settings::get( 'disable_updates' );
	}

	/** @return array|null array( version, package, notes, url ) or null. Cached 12 hours (1 hour after an error). */
	public static function latest() {
		if ( self::disabled() ) {
			return null;
		}
		$c = get_transient( 'rcom_update' );
		if ( is_array( $c ) ) {
			return $c ? $c : null;
		}
		$res = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/releases/latest', array(
			'timeout'    => 8,
			'user-agent' => 'recipe-community-updater',
			'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
		) );
		$rel = ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) ? json_decode( wp_remote_retrieve_body( $res ), true ) : null;
		$out = array();
		if ( is_array( $rel ) && ! empty( $rel['tag_name'] ) && empty( $rel['draft'] ) && empty( $rel['prerelease'] ) ) {
			foreach ( (array) ( $rel['assets'] ?? array() ) as $a ) {
				$u = (string) ( $a['browser_download_url'] ?? '' );
				// Only a zip that really lives in this repository's releases.
				if ( preg_match( '/\.zip$/', $u ) && 0 === strpos( $u, 'https://github.com/' . self::REPO . '/releases/download/' ) ) {
					$out = array( 'version' => ltrim( (string) $rel['tag_name'], 'vV' ), 'package' => $u, 'notes' => (string) ( $rel['body'] ?? '' ), 'url' => 'https://github.com/' . self::REPO );
					break;
				}
			}
		}
		set_transient( 'rcom_update', $out, $out ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $out ? $out : null;
	}

	public static function inject( $t ) {
		if ( ! is_object( $t ) ) {
			return $t;
		}
		$base = plugin_basename( RCOM_FILE );
		$l    = self::latest();
		$item = (object) array( 'id' => 'github.com/' . self::REPO, 'slug' => self::SLUG, 'plugin' => $base, 'new_version' => $l ? $l['version'] : RCOM_VERSION, 'url' => 'https://github.com/' . self::REPO, 'package' => $l ? $l['package'] : '' );
		if ( $l && version_compare( $l['version'], RCOM_VERSION, '>' ) ) {
			$t->response[ $base ] = $item;
		} else {
			$t->no_update[ $base ] = $item;
		}
		return $t;
	}

	public static function info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug || ! ( $l = self::latest() ) ) {
			return $res;
		}
		return (object) array(
			'name'          => 'Recipe Community',
			'slug'          => self::SLUG,
			'version'       => $l['version'],
			'author'        => '<a href="https://jconcept.nl">J-Concept</a>',
			'homepage'      => $l['url'],
			'download_link' => $l['package'],
			'requires'      => '6.2',
			'requires_php'  => '8.0',
			'sections'      => array(
				'description' => esc_html__( 'Community features for recipe sites: star reviews with photos, email-verified reviewers, no WordPress comments.', 'recipe-community' ),
				'changelog'   => wp_kses_post( wpautop( esc_html( $l['notes'] ) ) ),
			),
		);
	}
}
