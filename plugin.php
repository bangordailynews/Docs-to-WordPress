<?php
/*
Plugin Name: Docs to WP
Author: William P. Davis, Bangor Daily News
Author URI: http://wpdavis.com/
Version: 0.4-beta
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


class Docs_To_WP { 
	public function docs_to_wp_init( $username, $password ) {
		
		do_action( 'docs_to_wp_init' );
		
		$gdClient = new gData( $username, $password );

		return $gdClient;
	
	}
	
	public function purifier_init( $plugin_path ) {
	
		//Include and set up the HTML purifier
		include( $plugin_path . 'purifier/HTMLPurifier.standalone.php' );
		$config = HTMLPurifier_Config::createDefault();
		$purifier = new HTMLPurifier();
		return $purifier;
	
	}
	
	public function retrieve_docs_for_web( $gdClient, $folderID, $toFolder = false ) {

		//Get all the posts from the source folder in Google Docs
		$contents = $gdClient->getInfoFiles( $folderID );

		//Run all the posts through WordPress
		$posts = $this->put_docs_in_wp( $contents, $gdClient );
		
		if( empty( $posts ) || !is_array( $posts ) )
			return false;
		
		//If a destination folder is set, move the docs to the new folder
		if( $toFolder ) {
			foreach( $posts as $post ) {
				$gdClient->moveFileToFolder( $post[ 'gdoc_id' ], $toFolder );
			}
		}
		
		//Move the docs out of the source folder to make sure we aren't processing the same doc twice
		foreach( $posts as $post ) {
			$gdClient->removeFileFolder( $post[ 'gdoc_id' ], $folderID );
		}
		
		//Returns an array of post IDs and corresponding Google Docs IDs
		return $posts;
	}
	
	public function put_docs_in_wp( $feed, $gdClient ) {
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
			$content = $this->get_clean_doc( $gdClient, $purifier, $source );			
			$post_id = $this->publish_to_WordPress( $title, $content, $author, $cats, array( '_gdocID' => $docID ) );
			$posts[] = array( 'post_id' => $post_id, 'gdoc_id' => $docID );
		}
		
		return $posts;
	}
	
	
	public function get_clean_doc( $gdClient, $purifier, $uri ) {
		
		//We want to clean up each doc a bit
		$contents = $gdClient->getFile( $uri, false, "html");
		
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
		$query = "SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = %s AND meta_value = %s";
		return $wpdb->get_var( $wpdb->prepare( $query, $key, $value ) );
	}
	
	public function publish_to_WordPress ( $title, $content, $author = false, $categories = false, $custom_fields = false ) {
		
		
			//Find out if we are creating a draft or updating a doc
			$post_id = $this->post_exists_by_meta( '_gdocID', $custom_fields[ '_gdocID' ] );
		
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
			$author_data = get_userdatabylogin( $author );
			$author = $author_data->ID;
			
			$post_array = array(
				'post_title' => $title,
				'post_content' => $content,
				'custom_fields' => $custom_fields,
			);
			
			if( empty( $post_id ) ) {
				$post_array = array_merge( $post_array, array( 'post_author' => $author, 'post_category' => $cats ) );
			} else {
				$post_array = array_merge( $post_array, array( 'ID' => $post_id ) );
			}
			
			
			//If you want all posts to be auto-published, for example, you can add a filter here
			$post_array = apply_filters( 'pre_docs_to_wp_insert', $post_array );
			
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
	
}



/*
NOTE: the gData class uses CURL, not the WordPress HTTP API. This will be fixed in a later release.

*/






/**
 * PHP gData 
 *
 * For support issues please contact me by email :
 *				darkinux@gmail.com
 * or the official web site:
 *				http://www.boxcreation.com
 * ==============================================================================
 * 
 * @version $Id: gdata.class.php,v 0.5 2010/06/20 10:54:32 $
 * @copyright Copyright (c) 2007 Yassine Oussi (http://www.boxcreation.com)
 * @author Yassine Oussi <darkinux@gmail.com>
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * 
 * ==============================================================================
 */



class gData
{
    
     private $username;
     private $password;
	 private $auth;
	 private $curl;
	 private $status ="not connected";	
	 
	 //--Public methods--//
	 
	
	
	
	 /**
	 * Class constructor
	 * Set auth to google Docs 
	 * @param $email, $password
	 * @access public
	 * @return	void
	 */
	 
	 public function __construct($username, $password)
	 {
		 $this->username = $username;
         $this->password = $password;	
		
		// Construct an HTTP POST request
		 $clientlogin_url = "https://www.google.com/accounts/ClientLogin";
		 $clientlogin_post = array("accountType" => "HOSTED_OR_GOOGLE",
								  "Email" => $this->username,
								  "Passwd" =>  $this->password,
								  "service" => "writely",
								  "source" => "Gdata");

		 // Initialize the curl object
		 $this->curl = curl_init($clientlogin_url);

		 // Set some options (some for SHTTP)
		 curl_setopt($this->curl, CURLOPT_POST, true);
		 curl_setopt($this->curl, CURLOPT_POSTFIELDS, $clientlogin_post);
		 curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		 curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
		 curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		 
		 // Execute
		 $response = curl_exec($this->curl);
		
		 // Get the Auth string and save it
		 if(preg_match("/Auth=([a-z0-9_\-]+)/i", $response, $matches))
		 {
			$this->auth = $matches[1];
			$this->status = "connected";	
		 }
		 else
		 {
			preg_match("/Error=([a-z0-9_\-]+)/i", $response, $matches);
			$this->status = "not connected : ". $matches[1] ." ". curl_error($this->curl);
		 }
	 }
	
	
	
	 /**
	 * getStatus
	 * check the status
	 * @access public
	 * @return String
	 */
	
	 public function getStatus() 
	 {
		 return $this->status;
	 }
	 
	 
	 
	 /**
	 * createFolder
	 * Create a folder or subfolder
	 * @param  $name, $idFolder=0 
	 * @access public
	 * @return array
	 */
	 
	 public function createFolder($name, $idFolder = NULL)
	 {
	 	// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			"Authorization: GoogleLogin auth=" . $this->auth,
			"GData-Version: 3.0",
			"Content-Type: application/atom+xml");
		if($idFolder)
		{
			$url = "https://docs.google.com/feeds/default/private/full/folder%3A". $idFolder ."/contents";
		}
		else
		{
			$url = "https://docs.google.com/feeds/default/private/full";
		}	
		
		$xmlstr = "<?xml version='1.0' encoding='UTF-8'?>
			<atom:entry xmlns:atom=\"http://www.w3.org/2005/Atom\">
			  <atom:category scheme=\"http://schemas.google.com/g/2005#kind\" 
				  term=\"http://schemas.google.com/docs/2007#folder\" label=\"folder\"/>
			  <atom:title>". $name ."</atom:title>
			</atom:entry>";
		 
		// Make the request
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->curl, CURLOPT_POST, true);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $xmlstr);
		curl_setopt($this->curl, CURLOPT_VERBOSE, true);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
  		curl_setopt($this->curl, CURLOPT_FAILONERROR, true);
		
		// Execute
		$response = curl_exec($this->curl);
		
		// Get error
		if(curl_errno($this->curl)) 
			throw new Exception(curl_error($this->curl));
		
		$response = simplexml_load_string($response);
		
		if(preg_match("/folder%3A([a-z0-9_\-]+)/i",$response->id[0], $matches))
		{
			$arr["name"] = $response->title;
			$arr["type"] = $response->content["type"];
			$arr["down"] = $response->content["src"];
			$arr["link"] = $response->link[2]["href"];
			$arr["id"] = $matches[1];
			
			return $arr;
		}	
	 }
	 
	
	
	/**
	* getInfoFile
	* Remove a folder
	* @param $idFolder, $next
	* @access public
	* @return array
	*/
	
	public function getInfoFiles($idFolder = NULL) {
	
		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			"Authorization: GoogleLogin auth=" . $this->auth,
			"GData-Version: 3.0",
		);
		
		$url = "https://docs.google.com/feeds/default/private/full";
		if( $idFolder )
			$url .= "/folder%3A" . $idFolder . "/contents";
		// Make the request
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->curl, CURLOPT_VERBOSE, true);
		curl_setopt($this->curl, CURLOPT_POST, false);
		
		// Execute 
		$response = curl_exec($this->curl);
		
		// Get error
		if(curl_errno($this->curl)) 
			throw new Exception(curl_error($this->curl));
		
		// Parse the response
		$response = simplexml_load_string($response);
		
		// Get files
		$size = sizeOf($response);
		for ($i=0; $i<$size; $i++) 
		{
			if( $response->entry[$i]->title )
			{
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
				//$arr[$i]["dump"] = $response->entry[$i];
			} 
		}	
	 	return $arr;
	}
	
	
	
	/**
	* getFolders
	* get a list of folder
	* @access public
	* @return array
	*/
	
	public function getInfoFolders() {
	
		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			"Authorization: GoogleLogin auth=" . $this->auth,
			"GData-Version: 3.0",
		);
		
		$url = "https://docs.google.com/feeds/default/private/full/-/folder";
		
		// Make the request
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->curl, CURLOPT_VERBOSE, true);
		curl_setopt($this->curl, CURLOPT_POST, false);
		 
		// Execute
		$response = curl_exec($this->curl);
		
		// Get error
		if(curl_errno($this->curl)) 
			throw new Exception(curl_error($this->curl));
		
		// Parse the response
		$response = simplexml_load_string($response);
		
		// Get folders  
		$size = sizeOf($response);
		for ($i=0; $i<$size; $i++) 
		{
			if($response->entry[$i]->title && preg_match("/folder%3A([a-z0-9_\-]+)/i", $response->entry[$i]->content[0]["src"], $matches))
			{
				$arr[$i]["name"] = $response->entry[$i]->title;
				$arr[$i]["link"] = $response->entry[$i]->content["src"];
				$arr[$i]["author"] = $response->entry[$i]->author->name;
				$arr[$i]["id"] = $matches[1];
			}	
		}	

	 	return $arr;
	}
	
	
	
	/**
	* uploadFile
	* Upload file
	* @param $file, $name, $idFolder
	* @access public
	* @return array
	*/ 
	
	public function uploadFile($file, $name, $idFolder = null)
	{
	 	
	 	// Include the Auth string in the headers
		// Together with the API version being used	
		if(!@filesize($file))
		{
			throw new Exception("File not found!");
		} 
		else
		{
		
			$headers = array(
				"Authorization: GoogleLogin auth=" . $this->auth,
				"GData-Version: 3.0",
				"Content-Length: ". filesize($file),
				"Content-Type: ". $this->returnMIMEType($file),
				"Slug: ". $name);
		
		
			if($idFolder)
			{
				$url = "https://docs.google.com/feeds/default/private/full/folder%3A". $idFolder ."/contents";
			}
			else
			{
				$url = "https://docs.google.com/feeds/default/private/full";
			}		
		
			// Make the request
			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($this->curl, CURLOPT_POST, true);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, file_get_contents($file));
			curl_setopt($this->curl, CURLOPT_VERBOSE, true);
		
			// Execute
			$response = curl_exec($this->curl);
		
			// Get error
			if(curl_errno($this->curl)) 
				throw new Exception(curl_error($this->curl));
		
			// Parse the response
			$response = simplexml_load_string($response);
	
			if(preg_match("/%3A([a-z0-9_\-]+)/i", $response->link[2]["href"], $matches))
			{
				$arr["name"] = $response->title;
				$arr["type"] = $response->content["type"];
				$arr["down"] = $response->content["src"];
				$arr["link"] = $response->link[2]["href"];
				$arr["id"] = $matches[1];
				return $arr;
			}
			else
			{
				return false;
			}
		}
	}



	/**
	* getFile
	* Download file from google docs
	* @param $id, $filename, $format
	* @access public
	* @return array
	*/
	
	public function getFile($link, $filename = false, $format)
	{
		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			"Authorization: GoogleLogin auth=" . $this->auth,
			"GData-Version: 3.0",
		);
		
		// Make the request
		curl_setopt($this->curl, CURLOPT_HTTPGET, true);
		curl_setopt($this->curl, CURLOPT_URL, $link ."&exportFormat=". $format . "&format=". $format);
		
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->curl, CURLOPT_VERBOSE, true); 
		curl_setopt($this->curl, CURLOPT_HEADER, false); 		
		
		// Execute 
		$response = curl_exec($this->curl);
		
		// Get error
		if(curl_errno($this->curl)) 
			throw new Exception(curl_error($this->curl));
		
		if( $filename ) {	
			// save the file 	
			$out = fopen($filename, 'wb');
			fwrite($out, $response);  
			fclose($out);
		} else {
			$arr[ "contents" ] = $response;
		}
		
		$arr["filename"] = $filename;
		//$arr["content"] = $this->returnMIMEType($filename);
		
		return $arr;
	}
	
	
	
	/**
	 * removeFile
	 * Remove a file
	 * @param $idFile
	 * @access public
	 * @return void
	 */
	 
	 public function removeFileFolder( $idFile, $idFolder = false )
	 {
		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			"GData-Version: 3.0",
			"If-Match: * ",
			"Authorization: GoogleLogin auth=" . $this->auth,
		);
		
		$url = "https://docs.google.com/feeds/default/private/full/";
		if( $idFolder ) {
			$url .= "folder%3A" . $idFolder . "/contents/document%3A" . $idFile;
		} else {
			//$url .= $idFile ."?delete=true
		}
		
		// Make the request
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($this->curl, CURLOPT_URL, $url );
		curl_setopt($this->curl, CURLOPT_VERBOSE, true);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, false);
  		curl_setopt($this->curl, CURLOPT_FAILONERROR, true);
  		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		
		// Execute
		curl_exec($this->curl);
		
		// Get error
		if(curl_errno($this->curl)) 
			throw new Exception(curl_error($this->curl));
	}
	
	
	
	/**
	 * moveFileFolder
	 * move file to Folder
	 * @param $id, $idFolder, $type
	 * @access public
	 * @return void
	 */
	 
	 public function  moveFileToFolder($id, $idFolder, $type="file")
	 {
		// Include the Auth string in the headers
		// Together with the API version being used
		$headers = array(
			"Authorization: GoogleLogin auth=" . $this->auth,
			"GData-Version: 3.0",
			"Content-Type: application/atom+xml");
		
		if($type == "file")
		{
			$xmlstr = "<?xml version='1.0' encoding='UTF-8'?>
						<entry xmlns=\"http://www.w3.org/2005/Atom\">
						  <id>https://docs.google.com/feeds/default/private/full/document%3A". $id ."</id>
						</entry>";
		}
		elseif($type == "folder")
		{
			$xmlstr = "<?xml version='1.0' encoding='UTF-8'?>
						<entry xmlns=\"http://www.w3.org/2005/Atom\">
						  <id>https://docs.google.com/feeds/default/private/full/folder%3A". $id ."</id>
						</entry>";
		
		}
		
		// Make the request
		curl_setopt($this->curl, CURLOPT_URL, "https://docs.google.com/feeds/default/private/full/folder%3A". $idFolder ."/contents");
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->curl, CURLOPT_POST, true);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $xmlstr);
		curl_setopt($this->curl, CURLOPT_VERBOSE, true);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
  		curl_setopt($this->curl, CURLOPT_FAILONERROR, true);
		
		// Execute
		curl_exec($this->curl);
		
		// Get error
		if(curl_errno($this->curl)) 
			throw new Exception(curl_error($this->curl));
	}
	
	
	
	
	/**
	* closeConnection
	* Curl close
	* @access public
	* @return void
	*/
	
	public function closeConnection()
	{
		curl_close($this->curl);
	}
	
	
	
	//--Private methods--//
	
	/**
	* returnMIMEType
	* Return mime type
	* @param $filename
	* @access private
	* @return string
	*/
	
	private function returnMIMEType($filename)
    {
        preg_match("|\.([a-z0-9]{2,4})$|i", $filename, $fileSuffix);

        switch(strtolower($fileSuffix[1]))
        {
            case "js" :
                return "application/x-javascript";

            case "json" :
                return "application/json";

            case "jpg" :
            case "jpeg" :
            case "jpe" :
                return "image/jpg";

            case "png" :
            case "gif" :
            case "bmp" :
            case "tiff" :
                return "image/".strtolower($fileSuffix[1]);

            case "css" :
                return "text/css";

            case "xml" :
                return "application/xml";

            case "doc" :
            case "docx" :
                return "application/msword";

            case "xls" :
            case "xlt" :
            case "xlm" :
            case "xld" :
            case "xla" :
            case "xlc" :
            case "xlw" :
            case "xll" :
                return "application/vnd.ms-excel";

            case "ppt" :
            case "pps" :
                return "application/vnd.ms-powerpoint";

            case "rtf" :
                return "application/rtf";

            case "pdf" :
                return "application/pdf";

            case "html" :
            case "htm" :
            case "php" :
                return "text/html";

            case "txt" :
                return "text/plain";

            case "mpeg" :
            case "mpg" :
            case "mpe" :
                return "video/mpeg";

            case "mp3" :
                return "audio/mpeg3";

            case "wav" :
                return "audio/wav";

            case "aiff" :
            case "aif" :
                return "audio/aiff";

            case "avi" :
                return "video/msvideo";

            case "wmv" :
                return "video/x-ms-wmv";

            case "mov" :
                return "video/quicktime";

            case "zip" :
                return "application/zip";

            case "tar" :
                return "application/x-tar";

            case "swf" :
                return "application/x-shockwave-flash";

            default :
            if(function_exists("mime_content_type"))
            {
                $fileSuffix = mime_content_type($filename);
            }

            return "unknown/" . trim($fileSuffix[0], ".");
        }
    }

}