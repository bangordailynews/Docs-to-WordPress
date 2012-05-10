<?php
/*
Plugin Name: Docs to WordPress extender - Delimiter
Author: William P. Davis, Bangor Daily News
Author URI: http://wpdavis.com/
Version: 0.4-beta
*/

if( !defined( 'DOCSTOWP_DELIM' ) )
	define( 'DOCSTOWP_DELIM', '|' );

add_filter( 'pre_docs_to_wp_insert', 'dtwp_split_post' );
function dtwp_split_post( $post_array = array() ) {
 
    $exploded_fields = explode( DOCSTOWP_DELIM, $post_array[ 'post_content' ] );
 
    //Sometimes people forget a pipe, and we don't want to put the entire post in the headline
    if( is_array( $exploded_fields ) && count( $exploded_fields ) >= 2 ) {
 
        //Save the old title in case you want to do something with it
        $old_title = $post_array[ 'post_title' ];
 
        //Set the title to the first occurance.
        $post_array[ 'post_title' ] = strip_tags( $exploded_fields[ 0 ] );
 
        //Unset the title
        unset( $exploded_fields[ 0 ] );
 
        //Now restore the post content and save it
        $post_array[ 'post_content' ] = implode( DOCSTOWP_DELIM, $exploded_fields );
        $post_array[ 'custom_fields' ][ '_doc_name' ] = $old_title;
 
    }
 
    return $post_array;
 
}