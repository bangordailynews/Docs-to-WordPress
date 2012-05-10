<?php
/*
Plugin Name: Docs to WP
Author: William P. Davis, Bangor Daily News
Author URI: http://wpdavis.com/
Version: 0.4-beta
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


class Docs_To_WP {

	private $username;
	private $password;
	private $auth;
	private $status = 'not connected';
	var $source_folder;
	var $destination_folder;


	public function docs_to_wp_init( $username, $password ) {
		
		do_action( 'docs_to_wp_init' );
		
		$this->auth( $username, $password );
		
		return null;
	
	}

	
	public function purifier_init( $plugin_path ) {
	
		//Include and set up the HTML purifier
		require( $plugin_path . 'purifier/HTMLPurifier.standalone.php' );
		$config = HTMLPurifier_Config::createDefault();
		$purifier = new HTMLPurifier();
		return $purifier;
	
	}
	
	public function retrieve_docs_for_web( $gdClient = null, $folder_id = false, $toFolder = false ) {

		//Get all the posts from the source folder in Google Docs
		$contents = $this->docs_get_files( $folder_id );

		//Run all the posts through WordPress
		$posts = $this->put_docs_in_wp( $contents );
		
		if( empty( $posts ) || !is_array( $posts ) )
			return false;
		
		//If a destination folder is set, move the docs to the new folder
		if( $toFolder ) {
			foreach( $posts as $post ) {
				$this->docs_move_file( $post[ 'gdoc_id' ], $toFolder );
			}
		}
		
		//Move the docs out of the source folder to make sure we aren't processing the same doc twice
		foreach( $posts as $post ) {
			$this->docs_remove_file( $post[ 'gdoc_id' ], $folderID );
		}
		
		//Returns an array of post IDs and corresponding Google Docs IDs
		return $posts;
	}
	
	public function put_docs_in_wp( $feed ) {
		global $plugin_path;
		
		//Init the purifier
		$purifier = $this->purifier_init( $plugin_path );
		
		//Start with an empty array of posts
		$posts = array();
		
		//If we didn't get a proper feed, bail
		if( empty( $feed ) || !is_array( $feed ) )
			return false;
		
		//Foreach post in the feed, loop through it, clean it and publish it to WordPress
		foreach ($feed as $entry) {
			$cats = $entry[ 'folders' ];
			$author = (string) $entry[ 'author' ];
			$docID = $entry[ 'id' ];
			$title = (string) $entry[ 'name' ];
			$source = (string) $entry[ 'down' ];
			$content = $this->get_clean_doc( $purifier, $source );			
			$post_id = $this->publish_to_WordPress( array( 'title' => $title, 'content' => $content, 'author' => $author, 'categories' => $cats, 'custom_fields' => array( '_gdocID' => $docID ) ) );
			$posts[] = array( 'post_id' => $post_id, 'gdoc_id' => $docID );
		}
		
		return $posts;
	}
	
	
	public function get_clean_doc( $purifier, $uri ) {
		
		//We want to clean up each doc a bit
		$contents = $this->docs_get_file( $uri );
		
		$contents = apply_filters( 'pre_docs_to_wp_strip', $contents );
		
		//New domDocument and xPath to get the content
		$dom= new DOMDocument();
		$dom->loadHTML( $contents[ 'contents' ] );
		$xpath = new DOMXPath($dom);
		
		//Strip away the headers
		$body = $xpath->query('/html/body');
		//This is our dirty HTML
		$dirty_html = $dom->saveXml($body->item(0));
		
		$dirty_html = apply_filters( 'pre_docs_to_wp_purify', $dirty_html );
		
		//Run that through the purifier
		$clean_html = $purifier->purify( $dirty_html );
		
		//Return that clean shit
		return $clean_html;
	
	}
	
	//Checks if there is an earlier version of the article
	public function post_exists_by_meta( $key, $value ) {

		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s AND meta_value = %s', $key, $value ) );

	}
	
	public function publish_to_WordPress ( $args = array() ) {
		
		
			//Find out if we are creating a draft or updating a doc
			$post_id = $this->post_exists_by_meta( '_gdocID', $args[ 'custom_fields' ][ '_gdocID' ] );
		
			//Find out if the collections the doc is in matches any categories
			$cats = array();
			foreach ( $args[ 'categories' ] as $category ) {
				$cat = term_exists( $category, 'category' );
				if( !empty( $cat ) )
					$cats[] = $cat['term_id'];
				if( empty( $cats ) )
					$cats[] = get_option('default_link_category');
			}
			
			//If the username in gdocs matches the username in WordPress, it will automatically apply the correct username
			$author_data = get_userdatabylogin( $args[ 'author' ] );
			$author = $author_data->ID;
			
			$post_array = array(
				'post_title' => $args[ 'title' ],
				'post_content' => $args[ 'content' ],
				'custom_fields' => $args[ 'custom_fields' ],
			);
			
			if( empty( $post_id ) ) {
				$post_array = array_merge( $post_array, array( 'post_author' => $author, 'post_category' => $cats ) );
			} else {
				$post_array = array_merge( $post_array, array( 'ID' => $post_id ) );
			}
			
			
			//If you want all posts to be auto-published, for example, you can add a filter here
			$post_array = apply_filters( 'pre_docs_to_wp_insert', $post_array, $args );
			
			//Add or update
			if( empty( $post_id ) ) {
				$post_id = wp_insert_post( $post_array );
			} else {
				$post_id = wp_update_post( $post_array );
			}
			
			//Update post meta, including the _gdocID field
			foreach( $post_array['custom_fields'] as $key => $value )
				update_post_meta( $post_id, $key, $value );
				
			return $post_id;
			
	}


	/**
	* auth
	* Set auth to google Docs 
	* @param $email, $password
	* @access public
	* @return void
	*/
	 
	public function auth( $username, $password ) {

		$this->username = $username;
		$this->password = $password;	
		
		// Construct an HTTP POST request using the WordPress API
		$clientlogin_url = "https://www.google.com/accounts/ClientLogin";
		$clientlogin_post = array(
			'body' => array(
				"accountType" => "HOSTED_OR_GOOGLE",
				"Email" => $this->username,
				"Passwd" =>  $this->password,
				"service" => "writely",
				"source" => "Gdata",
				"sslverify" => false
			)
		);
		 
		// Execute Request
		$response = wp_remote_post( $clientlogin_url, $clientlogin_post );
		
		// Get the Auth string and save it
		if( preg_match( "/Auth=([a-z0-9_\-]+)/i", $response['body'], $matches ) ) {
			$this->auth = $matches[1];
			$this->status = "connected";	
		} else {
			preg_match("/Error=([a-z0-9_\-]+)/i", $response['body'], $matches);
			$this->status = "not connected : ". $matches[1] /*." ". curl_error($this->curl)*/;
		}

	}	
	
	
	/**
	* docs_get_files
	* Gets the files for a user or in a folder
	* @param $folder_id
	* @access public
	* @return array
	*/
	
	public function docs_get_files( $folder_id = NULL ) {
	
		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			'Authorization' => 'GoogleLogin auth=' . $this->auth,
			'GData-Version' => '3.0'
		);
		
		$url = 'https://docs.google.com/feeds/default/private/full';

		if( $folder_id )
			$url .= '/folder%3A' . $folder_id . '/contents';

		// Make the request
		$response = wp_remote_get( $url, array( 'headers' => $headers ) );

		
		// Parse the response
		$response = simplexml_load_string( $response[ 'body' ] );
		
		// Get files
		$size = sizeOf($response);
		for( $i=0; $i<$size; $i++ ) {
			if( $response->entry[$i]->title ) {
				$arr[$i]["name"] = $response->entry[$i]->title ;
				$arr[$i]["type"] = $response->entry[$i]->content["type"];
				$arr[$i]["down"] = $response->entry[$i]->content["src"];
				list( $not_needed, $id ) = explode( "?", (string) $response->entry[$i]->content["src"] );
				$arr[$i]["link"] = $response->entry[$i]->link[2]["href"];
				$arr[$i]["author"] = $response->entry[$i]->author->name;
				foreach( $response->entry[$i]->link as $folder ) {
					$title = (string) $folder["title"];
					if( !empty( $title ) )
						$arr[$i]["folders"][] = $title;
				}
				$arr[$i]["id"] = str_replace( "id=", "", $id );
			} 
		}
	 	return $arr;

	}
	
	




	/**
	* docs_get_file
	* Gets the contents of a Google Doc
	* @param $id, $filename, $format
	* @access public
	* @return array
	*/
	
	public function docs_get_file( $link ) {

		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			'Authorization' => 'GoogleLogin auth=' . $this->auth,
			'GData-Version' => '3.0'
		);
		
		$url = $link . '&exportFormat=html&format=html';
		
		$response = wp_remote_get( $url, array( 'headers' => $headers ) );

		return array( 'contents' => $response[ 'body' ] );
		
	}
	
	
	
	/**
	 * docs_remove_file
	 * Removes a file from a folder
	 * @param $file_id, $folder_id
	 * @access public
	 * @return void
	 */
	 
	 public function docs_remove_file( $file_id = false, $folder_id = false ) {

		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			'GData-Version' => '3.0',
			'If-Match' => '* ',
			'Authorization' => 'GoogleLogin auth=' . $this->auth,
		);
		
		$url = 'https://docs.google.com/feeds/default/private/full/folder%3A' . $folder_id . '/contents/document%3A' . $file_id;
		
		wp_remote_request( $url,
			array(
				'headers' => $headers,
				'method' => 'DELETE'
			)
		);

	}
	
	
	
	/**
	 * docs_move_file
	 * Moves file to a collection
	 * @param $file_id, $folder_id
	 * @access public
	 * @return void
	 */
	 
	 public function docs_move_file( $file_id, $folder_id ) {

		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			'Authorization' => 'GoogleLogin auth=' . $this->auth,
			'GData-Version' => '3.0',
			'Content-Type' => 'application/atom+xml'
		);

		

		$xmlstr = '<?xml version="1.0" encoding="UTF-8"?>
			<entry xmlns="http://www.w3.org/2005/Atom">
				<id>https://docs.google.com/feeds/default/private/full/document%3A'. $file_id .'</id>
			</entry>';

		
		// Make the request
		wp_remote_post( $url,
			array(
				'headers' => $headers,
				'body' => $xmlstr
			)
		);
		
	}
	
}