<?php
/*
Plugin Name: Docs to WP
Description: Use Google Docs to create content and move it into WordPress for publishing.
Author: William P. Davis, Travis Weston, Bangor Daily News
Author URI: http://dev.bangordailynews.com/
Plugin URI: https://github.com/bangordailynews/Docs-to-WordPress
Version: 1.0-beta
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

require_once( dirname(__FILE__) . '/wrAPI/wrAPI.php' );
require_once( dirname(__FILE__) . '/options.php' );
require_once( dirname(__FILE__) . '/options-ajax.php' );
require_once( dirname(__FILE__) . '/purifier/HTMLPurifier.standalone.php' );

class Docs_To_WP { 

	public function __construct() {

		do_action('pre_docs_to_wp_construct');

		$this->_drive = wrAPI::create('Google_Drive');
		$this->_auth = wrAPI::create('Google_Auth');

		/* If you have a hardcoded origin and destination, force that here, and update options page to reflect as much. */
		if( defined('DOCSTOWP_ORIGIN') ) 
			update_option('docs_to_wp_origin', DOCSTOWP_ORIGIN);
		
		if( defined('DOCSTOWP_DESTINATION') )
			update_option('docs_to_wp_target', DOCSTOWP_DESTINATION);

		$this->_register_hooks();

		do_action('post_docs_to_wp_construct');
		
	}

	public function registerMenu(){

		$this->_options = new Docs_To_WP_Options( $this->_auth, $this->_drive );

	}

	public function startTransfer() {

		$files = $this->_getFilesFromFolder();
		if( !is_array( $files ) )
			return array();

		$posts = array();

		foreach( $files as $file ) {

			$file = $this->_getFile( $file->id );
			$html = $this->_getHTML( $file );

			if( $html === false )
				continue;

			$content = $this->_cleanDoc( $html );

			$post_array = $this->_insertToWP( $file->title, $content, $this->_getParentName( $file->id ), $this->_getAuthorUsername( $file->owners ), $file->id );

			if( $post_array === false )
				continue;

			$posts[] = $post_array;

			$this->_moveToDestination( $file->id );

		}

		return $posts;
		
	}

	public function setupNotice() {

		if( get_option( 'docs_to_wp_client_id' ) && get_option( 'docs_to_wp_client_secret' ) )
			return;
	
		echo '<div class="error"><p>Docs To WP Requires you to <a href="https://console.developers.google.com/project">Create a Google API Project</a> and enter the details <a href="' . admin_url( 'options-general.php?page=docs_to_wp' ) . '">in the options page</a>.</p></div>';
	
	}

	/*
		Cloned from get_user_by
	*/
	private function _getWPUser( $id ){

		$userdata = WP_User::get_data_by( 'login', $id );

		if ( !$userdata )
			return false;

		$user = new WP_User;
 
		$user->init( $userdata );

		return $user;
 
	}

	private function _moveToDestination( $id ){
		
		$this->_auth();
		$this->_drive->put('/files/' . $id, array(
								'parents' => array( 
											array( 
												'id' => get_option( 'docs_to_wp_target' ) 
											) 
										)
							)
				);

	}

	private function _cleanDoc( $raw ) {

		$purifier = $this->_initPurifier();
			
		//New domDocument and xPath to get the content
		$dom= new DOMDocument();
		$dom->loadHTML( $raw );

		$xpath = new DOMXPath($dom);
		
		//Strip away the headers
		$dirty_html = $dom->saveXml( $xpath->query('/html/body')->item(0) );
		$dirty_html = apply_filters( 'pre_docs_to_wp_purify', $dirty_html );
			
		//Run that through the purifier
		if( $purifier instanceof HTMLPurifier )
			$clean_html = $purifier->purify( $dirty_html );
		else
			$clean_html = $dirty_html;

		/* 
			If you overrode the instance type in docs_to_wp_purifier_filter, 
			you will need to activate it in docs_to_wp_custom_purifier.
		*/
		return apply_filters( 'docs_to_wp_custom_purifier', $clean_html );

	}

	private function _getParentName( $id ){

		$this->_auth();
		$response = $this->_drive->get('/files/' . $id);
		
		$this->_auth();
		$response = $this->_drive->get('/files/' . $response->id );

		return $response->title;

	}

	private function _getAuthorUsername( $owners ){

		$owner = $owners[0];
		list( $owner, $devnull ) = explode('@', $owner->emailAddress);
		return $owner;

	}

	private function _insertToWP( $title, $content, $parentName, $author, $docID ) {
	
		$cats = array( $parentName );
		$post_id = $this->_publish( $title, $content, $author, $cats, array( '_gdocID' => $docID ) );

		if( $post_id === false )
			return false;

		return array( 'post_id' => $post_id, 'gdoc_id' => $docID );

	}

	private function _publish( $title, $content, $author = false, $categories = false, $custom_fields = false ) {
		
		//Find out if we are creating a draft or updating a doc
		$post_id = $this->_postExistsByMeta( '_gdocID', $custom_fields[ '_gdocID' ] );
		
		//Find out if the collections the doc is in matches any categories
		$cats = array();
		foreach ( $categories as $category ) {
			$cat = term_exists( $category, 'category' );
			if( !empty( $cat ) )
				$cats[] = $cat['term_id'];
			if( empty( $cats ) )
				$cats[] = get_option('default_link_category');
		}
			
		//If the username in gdocs matches the username in WordPress, it will automatically apply the correct username
		$author_data = $this->_getWPUser( 'login', $author );

		$author = $author_data->ID;
		
		// Array filter removes anything that is False, so if this is not an update, ID will auto-remove itself.
		// http://php.net/manual/en/function.array-filter.php
		$post_array = array_filter( 
			array(
				'ID' => $post_id,
				'post_title' => $title,
				'post_content' => $content,
				'custom_fields' => $custom_fields,
				'post_author' => $author,
				'post_category' => $cats
			) 
		);
	
	
		//If you want all posts to be auto-published, for example, you can add a filter here
		$post_array = apply_filters( 'pre_docs_to_wp_insert', $post_array );
		
		// wp_update_post returns 0 if post ID isn't there.
		if( !( $post_id = wp_update_post( $post_array, false ) ) )
			$post_id = wp_insert_post( $post_array );
		
		//Update post meta, including the _gdocID field
		foreach( $post_array['custom_fields'] as $key => $value )
			update_post_meta( $post_id, $key, $value );

		return $post_id;
		
	}

	private function _initPurifier() {
	
		$purifier = new HTMLPurifier(); // Create default Purifier.
		return apply_filters( 'docs_to_wp_purifier_filter', $purifier ); // Allow customization of purifier
	
	}

	private function _getFile( $id ) {

		$this->_auth();
		$response = $this->_drive->get('/files/' . $id);

		if( isset( $response->labels->trashed ) && !empty( $response->labels->trashed ) )
			return false;

		return $response;

	}

	private function _getHTML( $response ){

		$this->_auth();
		$page = $this->_drive->downloadFile( $response->exportLinks->{"text/html"} );

		return $page;

	}

	private function _getFilesFromFolder() {

		$this->_auth();
		$response = $this->_drive->get(( $geturl = '/files/' . get_option( 'docs_to_wp_origin' ) . '/children'));
		
		return is_array( $response->items ) ? $response->items : false;

	}

	private function _auth() {

		$this->_drive->connect( array(
			'access_token' => get_option( 'docs_to_wp_auth_token' ),
			'token_type' => 'Bearer'
		));

	}

	private function _register_hooks(){

		do_action('pre_docs_to_wp_register_hooks');

		add_action( 'admin_notices', array( $this, 'setupNotice' ) );
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );

		do_action('post_docs_to_wp_register_hooks');

	}

	private function _postExistsByMeta( $key, $value ) {
		global $wpdb;

		$query = "SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = %s AND meta_value = %s";
		$return = $wpdb->get_var( $wpdb->prepare( $query, $key, $value ) );
		return !empty( $return ) ? $return : false;

	}

}

$docs_to_wp = new Docs_To_WP();
