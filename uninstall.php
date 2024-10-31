<?php
// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
} else {
    unregister_setting( 'playSMS-settings', 'playSMS-apiKey' );
    unregister_setting( 'playSMS-settings', 'playSMS-apiPass' );
    unregister_setting( 'playSMS-settings', 'playSMS-apiHeader' );
    unregister_setting( 'playSMS-settings', 'playSMS-settings' );
        
    delete_option( 'playSMS-apiKey' );
    delete_option( 'playSMS-apiPass' );
    delete_option( 'playSMS-apiHeader' );
    delete_option( 'playSMS-settings' );

    global $wpdb;
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}playsms_events" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}playsms_errors" );
}