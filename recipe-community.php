<?php
/**
 * Plugin Name:       Recipe Community
 * Plugin URI:        https://github.com/J-Concept-Agency/recipe-community
 * Description:       Light, fast and safe community features for recipe sites. Version 0.1: star reviews with photos, no WordPress comments, no account needed, email-verified reviewers.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            J-Concept
 * Author URI:        https://jconcept.nl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       recipe-community
 * Domain Path:       /languages
 * GitHub Plugin URI: J-Concept-Agency/recipe-community
 * Primary Branch:    main
 *
 * Copyright (C) 2026 J-Concept
 */

defined( 'ABSPATH' ) || exit;

define( 'RCOM_VERSION', '0.1.0' );
define( 'RCOM_FILE', __FILE__ );
define( 'RCOM_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCOM_URL', plugin_dir_url( __FILE__ ) );

require_once RCOM_DIR . 'includes/class-settings.php';
require_once RCOM_DIR . 'includes/class-reviews.php';
require_once RCOM_DIR . 'includes/class-device.php';
require_once RCOM_DIR . 'includes/class-terms.php';
require_once RCOM_DIR . 'includes/class-integrity.php';
require_once RCOM_DIR . 'includes/class-photo.php';
require_once RCOM_DIR . 'includes/class-stats.php';
require_once RCOM_DIR . 'includes/class-identity.php';
require_once RCOM_DIR . 'includes/class-turnstile.php';
require_once RCOM_DIR . 'includes/class-newsletter.php';
require_once RCOM_DIR . 'includes/class-laposta.php';
require_once RCOM_DIR . 'includes/class-helpful.php';
require_once RCOM_DIR . 'includes/class-abilities.php';
require_once RCOM_DIR . 'includes/class-account.php';
require_once RCOM_DIR . 'includes/class-front.php';
require_once RCOM_DIR . 'includes/class-rest.php';
require_once RCOM_DIR . 'includes/class-privacy.php';
require_once RCOM_DIR . 'includes/class-updater.php';

register_activation_hook( __FILE__, array( 'RCOM_Reviews', 'install' ) );

/**
 * Public helper, e.g. for a later Recipe schema integration.
 *
 * 'avg' is the average of verified reviews only, 'count' all published reviews,
 * 'verified_count' the verified published reviews, 'avg_all' the average of all published ones.
 *
 * @return array{avg:float,count:int,verified_count:int,avg_all:float,dist:int[]}
 */
function rcom_get_rating( $post_id ) {
	return RCOM_Reviews::rating( (int) $post_id );
}

add_action( 'init', function () {
	load_plugin_textdomain( 'recipe-community', false, dirname( plugin_basename( RCOM_FILE ) ) . '/languages' );
	RCOM_Reviews::maybe_upgrade();
	RCOM_Identity::handle_link();
} );
add_action( 'before_delete_post', array( 'RCOM_Reviews', 'delete_for_post' ) );
add_action( 'rest_api_init', array( 'RCOM_Rest', 'routes' ) );
add_action( 'rest_api_init', array( 'RCOM_Helpful', 'routes' ) );
add_action( 'rest_api_init', array( 'RCOM_Account', 'routes' ) );
RCOM_Front::init();
RCOM_Account::init();
RCOM_Helpful::init();
RCOM_Privacy::init();
RCOM_Updater::init();
RCOM_Integrity::init();
RCOM_Newsletter::init();
RCOM_Abilities::init();

if ( is_admin() ) {
	require_once RCOM_DIR . 'admin/class-admin.php';
	RCOM_Admin::init();
}
