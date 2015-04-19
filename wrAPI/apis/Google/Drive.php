<?php
class Google_Drive extends Abstract_Api implements Api_Interface {
	
	public function __call( $name, $arguments ) {

		if( strtolower( $name ) != 'get' && !is_string( $arguments[ 1 ] ) )
			$data = json_encode( $arguments[ 1 ] );
		else
			$data = $arguments[ 1 ];

		$this->_addCustomHeader( 'Content-Length: ' . strlen( $data ) );

		return $this->_curl( 
					strtolower( $name ), 
					'https://www.googleapis.com/drive/v2' . $arguments[ 0 ], 
					$data,
					!isset( $arguments[2] )
				);

	}
	
	public function connect( $params ){

		$this->_addCustomHeader( 'Authorization: '.$params['token_type'].' '.$params['access_token'] );
		$this->_addCustomHeader( 'Content-Type: application/json' );

	}

	public function downloadFile( $url ){

		error_log( $url );

		return $this->_curl( 'get', $url, array(), false );

	}

	public function moveFile( $url ) {

		

	}

}
