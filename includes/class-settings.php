<?php
defined( 'ABSPATH' ) || exit;

/** Settings (one option, not autoloaded) and wp-config constants. */
class RCOM_Settings {

	// Cloudflare's official test keys: always pass. Only used with RCOM_ALLOW_DEV_BYPASS.
	const TEST_SITE   = '1x00000000000000000000AA';
	const TEST_SECRET = '1x0000000000000000000000000000000AA';

	public static function defaults() {
		return array(
			'turnstile_site'   => '',
			'turnstile_secret' => '',
			'reply_label'      => '',
			'mail_from'        => '',
			'keep_data'        => 0,
			'post_types'       => 'recept',
			'show_credit'      => 1,
			'newsletter_provider' => 'none',
			'laposta_key'      => '',
			'laposta_list'     => '',
			'laposta_name_field' => '',
			'laposta_doi'      => 'ours',
			'laposta_quiet'    => 0,
			'disable_updates'  => 0,
			'ai_abilities'     => 0,
			'account_page'     => 0,
			'account_label'    => '',
			'terms_snapshots'  => 1,
			'on_delete'        => 'delete',
			'newsletter_label' => '',
			'terms_page'       => 0,
			'privacy_page'     => 0,
			'terms_label'      => '',
			'helpful_types'    => 'kennis',
			'helpful_threshold' => 5,
			'helpful_ts'       => 0,
			'helpful_links'    => 0,
			'helpful_yes_label' => '',
			'helpful_no_label' => '',
			'skin'             => 'neutral',
			'extra_css'        => '',
		);
	}

	public static function all() {
		$o = get_option( 'rcom_settings', array() );
		return wp_parse_args( is_array( $o ) ? $o : array(), self::defaults() );
	}

	public static function get( $key ) {
		return self::all()[ $key ] ?? '';
	}

	public static function post_types() {
		$t = array_filter( array_map( 'sanitize_key', explode( ',', (string) self::get( 'post_types' ) ) ) );
		return (array) apply_filters( 'rcom_post_types', $t );
	}

	public static function helpful_types() {
		return (array) apply_filters( 'rcom_helpful_post_types', array_filter( array_map( 'sanitize_key', explode( ',', (string) self::get( 'helpful_types' ) ) ) ) );
	}

	public static function helpful_enabled_for( $post_id ) {
		return in_array( get_post_type( $post_id ), self::helpful_types(), true );
	}

	/** Reviews active for this post: allowed post type, published or viewable, and not switched off on the post. */
	public static function enabled_for( $post_id ) {
		return in_array( get_post_type( $post_id ), self::post_types(), true ) && ! get_post_meta( $post_id, '_rcom_off', true );
	}

	/** True when a wp-config.php constant supplies the value. */
	public static function from_constant( $which ) {
		return 'site' === $which ? defined( 'RCOM_TURNSTILE_SITE_KEY' ) : defined( 'RCOM_TURNSTILE_SECRET_KEY' );
	}

	private static function bypass() {
		return defined( 'RCOM_ALLOW_DEV_BYPASS' ) && RCOM_ALLOW_DEV_BYPASS;
	}

	/**
	 * Turnstile key and where it comes from, read in this order: wp-config constant, key saved in this plugin,
	 * the Turnstile keys of the Bricks settings (read only, never copied; no hard dependency on Bricks).
	 * @param string $which 'site' or 'secret'
	 * @return array{0:string,1:string} value, source ('constant', 'own', 'bricks' or '')
	 */
	public static function turnstile_key( $which ) {
		$const = 'site' === $which ? 'RCOM_TURNSTILE_SITE_KEY' : 'RCOM_TURNSTILE_SECRET_KEY';
		if ( defined( $const ) && '' !== (string) constant( $const ) ) {
			return array( (string) constant( $const ), 'constant' );
		}
		$own = (string) self::get( 'site' === $which ? 'turnstile_site' : 'turnstile_secret' );
		if ( '' !== $own ) {
			return array( $own, 'own' );
		}
		if ( defined( 'BRICKS_VERSION' ) ) {
			$g = get_option( 'bricks_global_settings', array() );
			$b = is_array( $g ) ? (string) ( $g['site' === $which ? 'apiKeyTurnstile' : 'apiSecretKeyTurnstile'] ?? '' ) : '';
			if ( '' !== $b ) {
				return array( $b, 'bricks' );
			}
		}
		return array( '', '' );
	}

	public static function turnstile_site() {
		$v = self::turnstile_key( 'site' )[0];
		return ( '' === $v && self::bypass() ) ? self::TEST_SITE : $v;
	}

	public static function turnstile_secret() {
		$v = self::turnstile_key( 'secret' )[0];
		return ( '' === $v && self::bypass() ) ? self::TEST_SECRET : $v;
	}

	public static function turnstile_ready() {
		return '' !== self::turnstile_site() && '' !== self::turnstile_secret();
	}
}
