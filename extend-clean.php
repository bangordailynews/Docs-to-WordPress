<?php
/*
Plugin Name: Docs to WordPress extender - clean content, strip comments
Author: William P. Davis, Bangor Daily News
Author URI: http://wpdavis.com/
Version: 0.4-beta
*/

//Image settings
$add_gdocs_images = TRUE;
$image_settings = array(
  'image_size' => 'medium',
  'image_alignment' => 'right', // align inserted images left or right
  'gdocs_image_folder' => 'gdocs/' // subfolder of wp-content/uploads/ 
);  

require_once(ABSPATH . 'wp-admin/includes/media.php');//MF
require_once(ABSPATH . 'wp-admin/includes/image.php');//MF

add_filter( 'pre_docs_to_wp_strip', 'dtwp_extract_styles', 10, 1 );
function dtwp_extract_styles( $contents ) {
        gdocs_log($contents,"debug");

		//PHP doesn't honor lazy matches very well, apparently, so add newlines
		$contents[ 'contents' ] = str_replace( '}', "}\r\n", $contents[ 'contents' ] );

		preg_match_all( '#.c(?P<digit>\d+){(.*?)font-weight:bold(.*?)}#', $contents[ 'contents' ], $boldmatches );
		preg_match_all('#.c(?P<digit>\d+){(.*?)font-style:italic(.*?)}#', $contents[ 'contents' ], $italicmatches);
        preg_match_all('#.c(?P<digit>\d+){(.*?)list-style-type:disc(.*?)}#', $contents[ 'contents' ], $listmatches);
		
		if( !empty( $boldmatches[ 'digit' ] ) ) {
		
			foreach( $boldmatches[ 'digit' ] as $boldclass ) {
				$contents[ 'contents' ] = preg_replace( '#<span class="(.*?)c' . $boldclass . '(.*?)">(.*?)</span>#s', '<span class="$1c' . $boldclass . '$2"><strong>$3</strong></span>', $contents[ 'contents' ] );
			}
		
		}
		
		if( !empty( $italicmatches[ 'digit' ] ) ) {
		
			foreach( $italicmatches[ 'digit' ] as $italicclass ) {
				$contents[ 'contents' ] = preg_replace( '#<span class="(.*?)c' . $italicclass . '(.*?)">(.*?)</span>#s', '<span class="$1c' . $italicclass . '$2"><em>$3</em>', $contents[ 'contents' ] );
			}
		
		}
	
        if( !empty( $listmatches[ 'digit' ] ) ) {
		
			foreach( $listmatches[ 'digit' ] as $listclass ) {
				$contents[ 'contents' ] = preg_replace( '#<ol class="(.*?)c' . $listclass . '(.*?)">(.*?)</ol>#s', '<ul class="$1c' . $listclass . '$2">$3</ul>', $contents[ 'contents' ] );
			}
		
		}
        		
		return $contents;

}

add_filter( 'pre_docs_to_wp_insert', 'dtwp_clean_content_filter', 10, 1 );
function dtwp_clean_content_filter( $post_array ) {	
	$original_content = $post_array[ 'post_content' ];
	$cleaned_content = dtwp_clean_content( $original_content );
	$post_array[ 'post_content' ] = $cleaned_content[ 'content' ];
	$post_array['custom_fields'] = array_merge( $post_array['custom_fields'], array( '_gdocs_comments' => $cleaned_content[ 'comments' ] ) );
	return $post_array;
}
    
if($add_gdocs_images)
	add_filter( 'attach_images_docs_to_wp_insert', 'dwtp_attach_images', 10, 1 );
	
// Attaches images from Gdocs by downloading them and attaching them to the article created
function dwtp_attach_images($post_array) {
	global $image_settings;

	$post_id = $post_array['ID'];
	$post_content = $post_array['post_content'];
	if(empty($post_id)) {
      gdocs_log("Post ID was null when trying to insert images", "error");
	  return false;
	}
	
	$attached_guids = array();
	if(!empty($post_id) && $images = get_posts(array(
		'post_parent'	=> $post_id,
		'post_type'		=> 'attachment',
		'numberposts'	=> -1, //show all
		'post_status'	=> null,
		'post_mime_type'=> 'image',
		'orderby'		=> 'menu_order',
		'order'			=> 'ASC',
	))) {
		foreach($images as $image) {
		
		$attached_images[] = 
			get_image_send_to_editor(
				$image->ID,
				'',
				$image->post_title,
				$image_settings['image_alignment'],
				wp_get_attachment_url($image->ID),
				FALSE,
				$image_settings['image_size'],
				$image->post_content
			);
			  if(preg_match('/gdocs.{10}_/',$image->guid, $guid_match)>0) { //match guids with the gdocs id inserted
				  $attached_guids[] = $guid_match[0]; //for identifying gdocs images added since before
			  } else {
				  $attached_guids[] = $image->guid;
			  }
		}
	}
	//MF	
	preg_match_all('/<img(.*?)>/', $post_content, $doc_imgs, PREG_OFFSET_CAPTURE);

    gdocs_log("Image GUIDs: ".implode($attached_guids), "debug");

	$upload_dir = wp_upload_dir();
	$path = $upload_dir['path'].'/'.$image_settings['gdocs_image_folder']; //assumed subdir of upload
	if(!file_exists($path)) {
		mkdir($path);
	}
	$replace_offset = 0;
	foreach($doc_imgs[0] as $doc_img) { //$doc_imgs[0] because we only need first pattern, not the subpattern
		preg_match('/src="https(.*?)"/', $doc_img[0], $src_match); //Use doc_img[0] as we also match position in index [1]
		$img_hash = 'gdocs'.substr($src_match[1], -10).'_'; // Pick file name hash from last 10 of gdocs user content hash (hope it's static!)
		$new_img_tag = '';
		$existing_img = array_search($img_hash,$attached_guids); // see if we already have this img as attachment
		if($existing_img===FALSE) {
			// Gdocs defaults to HTTPSing their images, but we can go with normal http for simplicity
			$headers = wp_get_http('http'.$src_match[1],$path.$img_hash);
			$contentdisposition = explode('"', $headers["content-disposition"]); //pattern is: inline;filename="xxx.jpg"
			if(count($contentdisposition)>1) {
				$filename = urldecode($contentdisposition[1]); // filename may include URL characters that mess up later
				$file_ext = strripos($filename,'.'); // look for extension from end of string
				$name = $file_ext > 0 ? substr($filename, 0, $file_ext) : $filename; //strip out file type from name
			} else {
				$filename = 'unknown.jpg';
				$name = "unknown";
			}
			
			$filename = $img_hash.$filename; // for uniqueness combine with hash
			$newpath = $path.$filename;
			rename($path.$img_hash,$newpath);
			$wp_filetype = wp_check_filetype(basename($newpath), null);
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => $name,
				'post_name' => $name,
				'guid' => $upload_dir['baseurl'].'/gdocs/'.$filename,
				'post_content' => '',
				'post_status' => 'inherit'
			);
			$attach_id = wp_insert_attachment($attachment, $newpath, $post_id);
			$attach_data = wp_generate_attachment_metadata($attach_id, $newpath);
			wp_update_attachment_metadata($attach_id, $attach_data);
            
            gdocs_log("Inserted attachment ".implode($attachment)." to post $post_id and generated metadata:", "debug");
			
            $new_img_tag = get_image_send_to_editor(
				$attach_id,
				'',
				$name,
				$image_settings['image_alignment'],
				wp_get_attachment_url($image->ID),
				FALSE,
				$image_settings['image_size'],
				''
			);
            
            gdocs_log("Downloaded https".$src_match[1]." as $filename (content-disp: ".$headers["content-disposition"].", hash $img_hash not found, index $existing_img)\n", "debug");

		} else {
			$new_img_tag = $attached_images[$existing_img];
            
            gdocs_log("$img_hash already downloaded (index in guid: $existing_img)\n", "debug");
            
		}
		// as we replace in post_content the originally matched positions will be offsetted, so we compensate
		$post_content = substr_replace($post_content, $new_img_tag, $doc_img[1]+$replace_offset, strlen($doc_img[0]));
		$replace_offset = $replace_offset + (strlen($new_img_tag)-strlen($doc_img[0]));
	}
	$post_array['post_content'] = $post_content;
	return $post_array;
}


function dtwp_clean_content($post_content) {
		$post_content = str_replace( array( "\r\n", "\n\n", "\r\r", "\n\r" ), "\n", $post_content );
		$post_content = preg_replace('/<div(.*?)>/', '<div>', $post_content);
		$post_content = preg_replace('/<p(.*?)>/', '<p>', $post_content);
		
		//Match all the comments into an array. We're doing this before anything else because the </div> is importqnt
		preg_match_all( '/<div><p><a href="#cmnt_ref[\d]">\[[\w]\]<\/a>(.*?)<\/div>/', $post_content, $comments, PREG_PATTERN_ORDER);
		$comments = implode( "\r\n\r\n", $comments[1] );
		
		//Take out the comments
		$post_content = preg_replace( '/<div><p><a href="#cmnt_ref(.*?)<\/div>/', '', $post_content );
		//Take out the comment refers
		$post_content = preg_replace( '/<a href="#cmnt(.*?)<\/a>/', '', $post_content );
		
		$post_content = str_replace( '<div>','<p>',$post_content );
		$post_content = str_replace( '</div>', '</p>',$post_content );
		$post_content = strip_tags($post_content, '<img><b><i><em><strong><a><u><br><p><ol><ul><li><h1><h2><h3><h4><h5><h6>' ); //MF
		$post_content = str_replace( '--','&mdash;',$post_content );
		$post_content = str_replace( '<br><br>','<p>',$post_content );
		$post_content = str_replace( '<br>&nbsp;&nbsp;&nbsp;', '\n\n', $post_content );
		$post_content = str_replace( '<br>
&nbsp;&nbsp;&nbsp;','\n\n',$post_content);
		$post_content = str_replace( '<br><br>', '\n\n', $post_content );
		$post_content = trim( $post_content );
		$pees = explode( '<p>', $post_content );
		$trimmed = array();
		foreach( $pees as $p )
			$trimmed[] = trim( $p );
		$post_content = implode( '<p>', $trimmed );
		$post_content = preg_replace( "/<p><\/p>/", '', $post_content );
		
		return array( 'content' => $post_content, 'comments' => $comments );
}


//Add the comments meta box
add_action( 'add_meta_boxes', 'dtwp_add_comments_meta_box' );
function dtwp_add_comments_meta_box( ) {
    add_meta_box( 'dtwp_comments_meta_box', __( 'Comments from gDocs', 'dtwp' ), 'dtwp_comments_meta_box', 'post' );
}

//Display the comments meta box
function dtwp_comments_meta_box( $post ) {
	$gdocID = get_post_meta( $post->ID, '_gdocID', true );
	if( !empty( $gdocID ) ) {
		?>
			<h5><a href="http://docs.google.com/document/d/<?php echo $gdocID; ?>/edit" target=_blank>Edit this doc</a></h5>
		<?php
	} else { ?>
		<h5>No doc attached.</h5>
	<?php }

	$comments = get_post_meta( $post->ID, '_gdocs_comments', true );
	echo apply_filters( 'the_content', $comments );
  
}