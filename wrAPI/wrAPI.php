<?php

require_once( dirname( __FILE__ ).'/apis/Api_Interface.php' );
require_once( dirname( __FILE__ ).'/apis/Abstract_Api.php' );

function wrAPI_autoload( $class ) {

	if( strpos( $class, 'Exception' ) === false )
		return false;

	if( !file_exists( dirname( __FILE__ ).'/exceptions/'.$class.'.php' ) ){
	
		// Not a wrAPI class.
		return false;

	}

	require_once( dirname( __FILE__ ).'/exceptions/'.$class.'.php' );

};

spl_autoload_register('wrAPI_autoload');

class wrAPI {
	
	/** LOCK OUT INSTANTIATION **/
	private function __construct(){}

	public static function create( $api_name ){

		$name = explode( '_', $api_name );

		$name = array_map( function($a) {
			return preg_replace( '/[^a-zA-Z0-9]/', null, $a );
		}, $name);

		if( count( $name ) > 1 ){
			$filename = implode('/', $name);
		}else{
			$filename = $name[ 0 ] . '/' . $name[ 0 ];
		}

		if( !file_exists( dirname( __FILE__ ) . '/apis/' . $filename . '.php' ) ){

			throw new FileNotFoundException("Error: $api_name was not found.");

		}

		require_once( dirname( __FILE__ ) . '/apis/' . $filename . '.php' );

		return new $api_name();

	}

}
