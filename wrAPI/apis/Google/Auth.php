<?php
class Google_Auth extends Abstract_Api implements Api_Interface {
	
	public function __call( $name, $arguments ) {

		return $this->_curl( 
					strtolower( $name ), 
					'https://accounts.google.com/o/oauth2' . $arguments[ 0 ], 
					$arguments[ 1 ] 
				);

	}
	
	public function connect( $params ){

		header('Location: https://accounts.google.com/o/oauth2/auth?' . http_build_query( $params ) );

	}

	/* Only useful for Offline access */
	public function refresh( $token, $client_id, $client_secret ){
		$data = array(
				'refresh_token' => $token,
				'client_id' => $client_id,
				'client_secret' => $client_secret,
				'grant_type' => 'refresh_token'
			);

		return $this->_curl( 'post', 'https://www.googleapis.com/oauth2/v3/token', http_build_query( $data ) );

	}

}
