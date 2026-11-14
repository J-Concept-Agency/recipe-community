<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$rcom_opt = get_option( 'rcom_settings', array() );
if ( ! empty( $rcom_opt['keep_data'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rcom_reviews" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_rcom_avg','_rcom_count','_rcom_vcount','_rcom_dist')" );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_rcom_' ) . '%', $wpdb->esc_like( '_transient_timeout_rcom_' ) . '%' ) );
delete_option( 'rcom_settings' );
delete_option( 'rcom_db_version' );

$rcom_u   = wp_upload_dir( null, false );
$rcom_dir = trailingslashit( $rcom_u['basedir'] ) . 'rcom';
if ( is_dir( $rcom_dir ) ) {
	foreach ( (array) glob( $rcom_dir . '/{,.}*', GLOB_BRACE ) as $rcom_f ) {
		if ( is_file( $rcom_f ) ) {
			wp_delete_file( $rcom_f );
		}
	}
	rmdir( $rcom_dir );
}
