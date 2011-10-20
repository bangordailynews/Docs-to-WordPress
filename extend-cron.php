<?php
/*
Plugin Name: Docs to WordPress extender - Run on Cron (every minute)
Author: William P. Davis, Bangor Daily News
Author URI: http://wpdavis.com/
Version: 0.4-beta
*/

register_activation_hook( __FILE__, 'dtwp_schedule_event' );

//First, allow the cron to run every minute
add_filter('cron_schedules', 'dtwp_more_reccurences');
function dtwp_more_reccurences() {
        return array( 'min' => array( 'interval' => 60, 'display' => 'Every Minute' ) );
}

add_action( 'dtwp_cronjob', 'dtwp_check_gdocs' );
function dtwp_schedule_event() {
        wp_schedule_event( time(), 'min', 'dtwp_cronjob' );
}


//Deactivation
register_deactivation_hook(__FILE__, 'dtwp_deactivate_cron');
function dtwp_deactivate_cron() {
        wp_clear_scheduled_hook( 'dtwp_cronjob' );
}

function dtwp_check_gdocs( ) {
	//Init the Docs to WP
	$docs_to_wp = new Docs_To_WP();
	
	//Set these variables in your wp-config
	$gdClient = $docs_to_wp->docs_to_wp_init( DOCSTOWP_USER, DOCSTOWP_PASS );
	
	//We're just going to call one function:
	$docs_to_wp->retrieve_docs_for_web( $gdClient, DOCSTOWP_ORIGIN, DOCSTOWP_DESTINATION );

}