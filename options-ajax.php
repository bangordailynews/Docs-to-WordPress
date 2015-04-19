<?php
/*
Docs To WP Options Ajax Class
*/
class Docs_To_WP_Options_Ajax {

	public function __construct() {

		add_action( 'wp_ajax_docs_to_wp_save_options', array( $this, 'saveSettings' ) );

	}

	public function saveSettings() {

		$client_id = $_POST['d2w_cid'];
		$client_secret = $_POST['d2w_secret'];
		$origin_folder = defined('DOCSTOWP_ORIGIN') ? DOCSTOWP_ORIGIN : $_POST['d2w_origin'];
		$target_folder = defined('DOCSTOWP_DESTINATION') ? DOCSTOWP_DESTINATION : $_POST['d2w_dest'];

		update_option( 'docs_to_wp_client_id', $client_id );
		update_option( 'docs_to_wp_client_secret', $client_secret );
		update_option( 'docs_to_wp_origin', $origin_folder );
		update_option( 'docs_to_wp_target', $target_folder );

		wp_send_json( array('success' => true ) );

	}

}

add_action('admin_init', function() {

	if( !defined( 'DOING_AJAX' ) || !DOING_AJAX )
		return;

	$ajax = new Docs_To_WP_Options_Ajax();

});
