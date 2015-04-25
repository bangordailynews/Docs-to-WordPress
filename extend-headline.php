<?php
/*
Plugin Name: Docs to WordPress extender - Headline delimiter
Author: William P. Davis, Bangor Daily News
Author URI: http://wpdavis.com/
Version: 1.1
*/
 
add_filter( 'pre_docs_to_wp_insert', 'bdn_split_post' );
function bdn_split_post( $post_array = array() ) {
 
    $exploded_fields = explode( '|', $post_array[ 'post_content' ] );
    
    $post_array[ 'custom_fields' ] = array_merge( $post_array[ 'custom_fields' ], array( '_slug' => $post_array[ 'post_title' ] ) );
    
    //Sometimes people forget a pipe, and we don't want to put the entire post in the headline
    if( is_array( $exploded_fields ) && count( $exploded_fields ) >= 2 && count( $exploded_fields ) > 0 ) {
 
        //Save the old title in case you want to do something with it
        $old_title = $post_array[ 'post_title' ];
 
        //Set the title to the first occurance.
        $post_array[ 'post_title' ] = strip_tags( $exploded_fields[ 0 ] );
         
        //Unset the title
        unset( $exploded_fields[ 0 ] );
         
        //Now restore the post content and save it
        $post_array[ 'post_content' ] = implode( '|', $exploded_fields );
         
    }
 
    return $post_array;
 
}
