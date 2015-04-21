<?php
ini_set('display_errors', true);
/*
Docs To WP Options Page
TO-DO:
	- Make this work with Web Delegation
		~ https://developers.google.com/drive/web/delegation
*/
class Docs_To_WP_Options {

	public function __construct( &$auth, &$drive ) {

		do_action( 'pre_docs_to_wp_options_init' );

		$this->_auth = &$auth;
		$this->_drive = &$drive;

		$hook = add_options_page( 'Docs To WP', 'Docs To WP', 'manage_options', 'docs_to_wp', array( $this, 'settingsPage' ) );
		add_action( 'load-' . $hook, array( $this, 'verifyAuth') );
		add_action( 'admin_enqueue_scripts', function( $hook ) {

			if( strpos( $hook, "docs_to_wp" ) === false )
				return;

			do_action( 'pre_docs_to_wp_enqueue_scripts' );

			wp_enqueue_script('jquery');

			wp_register_script( 'd2woptions', plugins_url('/js/options.js', __FILE__) );
			wp_enqueue_script('d2woptions');
			
			do_action( 'post_docs_to_wp_enqueue_scripts' );
			
		});

		do_action( 'post_docs_to_wp_options_init' );

	}

	public function settingsPage() {

		do_action( 'pre_docs_to_wp_options_page' );
		?>
		<!-- Begin Docs to WP Options Page -->
		<div id="docs_to_wp_options_page_wrapper">
			<div id="docs_to_wp_client_id_wrapper">
				<label for="docs_to_wp_client_id">Client ID:</label>
				<input type="text" name="docs_to_wp_client_id" id="docs_to_wp_client_id" placeholder="123456789000-abc12adf3jakafjglad8h4hlkjasdfjj.apps.googleusercontent.com" size="65" value="<?php echo get_option( 'docs_to_wp_client_id' ); ?>" />
			</div>
			<div id="docs_to_wp_client_secret_wrapper">
				<label for="docs_to_wp_client_secret">Client Secret:</label>
				<input type="text" name="docs_to_wp_client_secret" id="docs_to_wp_client_secret" placeholder="AbcDEfgHIjkLmNOPq5RS_TUv" size="25" value="<?php echo get_option('docs_to_wp_client_secret'); ?>" />
			</div>
			
		<?php
		if( get_option( 'docs_to_wp_refresh_token' ) ){
		?>
			<div id="docs_to_wp_auth_wrapper">
				<label for="docs_to_wp_auth_token">Auth Token:</label>
				<input type="text" name="docs_to_wp_auth_token" id="docs_to_wp_auth_token" value="<?php echo get_option( 'docs_to_wp_auth_token' ); ?>" />
			</div>
			<div id="docs_to_wp_refresh_wrapper">
				<label for="docs_to_wp_refresh_token">Refresh Token:</label>
				<input type="text" name="docs_to_wp_refresh_token" id="docs_to_wp_refresh_token" value="<?php echo get_option( 'docs_to_wp_refresh_token' ); ?>" />
			</div>
		<?php
		}
		?>
			<div id="docs_to_wp_origin_wrapper">
				<label for="docs_to_wp_origin_share_link">Origin Folder Share Link:</label>
				<input type="text" name="docs_to_wp_origin_share_link" id="docs_to_wp_origin_share_link" placeholder="https://drive.google.com/folderview?id=0AvbaiFDF9adfs8ALJDfadsf9JLKSDFjavadvasdf&usp=sharing" value="<?php echo (defined('DOCSTOWP_ORIGIN') ? DOCSTOWP_ORIGIN : get_option( 'docs_to_wp_origin' ) ); ?>" size="80" <?php if(defined('DOCSTOWP_ORIGIN')){ echo 'disabled="DISABLED" '; }?>/><span id="docs_to_wp_origin"></span>
			</div>
			<div id="docs_to_wp_destination_wrapper">
				<label for="docs_to_wp_destination_share_link">Destination Folder Share Link:</label>
				<input type="text" name="docs_to_wp_destination_share_link" id="docs_to_wp_destination_share_link" placeholder="https://drive.google.com/folderview?id=0AvbaiFDF9adfs8ALJDfadsf9JLKSDFjavadvasdf&usp=sharing" value="<?php echo (defined('DOCSTOWP_DESTINATION') ? DOCSTOWP_DESTINATION : get_option( 'docs_to_wp_target' ) ); ?>" size="80" <?php if(defined('DOCSTOWP_DESTINATION')){ echo 'disabled="DISABLED"'; } ?>/><span id="docs_to_wp_destination"></span>
			</div>
			<div id="docs_to_wp_connect_wrapper">
				<span>Connection Status:</span>
				<span id="docs_to_wp_connect_status" style="font-weight: bolder;"><?php echo ($this->_verifyConnection()) ? 'Connected' : 'Error! <a href="' . admin_url( 'options-general.php?page=docs_to_wp&force_reconnect=true' ) .'">Reconnect</a>'; ?></span>
			</div>
			<div id="docs_to_wp_submit_wrapper">
				<input type="button" id="docs_to_wp_options_submit" name="docs_to_wp_options_submit" value="Save" />
			</div>
		<!-- End Docs to WP Options Page -->
		</div>
		<?php
		do_action( 'post_docs_to_wp_options_page' );

	}

	public function verifyAuth() {

		if( 'docs_to_wp' != filter_input( INPUT_GET, 'page' ) )
			return;

		// Either this is the first time we've been authed, or we're unable to properly reauth, so force a new screen.

		if( !get_option( 'docs_to_wp_client_id' ) || !( get_option( 'docs_to_wp_client_secret' ) ) )
			return; // Oops, this is our first load. We need the App details before we do anything.

		if( isset( $_GET['force_reconnect'] ) )
			return $this->_initAuth();


		if( isset( $_GET['code'] ) ){
			$result = $this->_auth->post(
							'/token', 
							array(
								'code' => $_GET['code'],
								'client_id' => get_option( 'docs_to_wp_client_id' ),
								'client_secret' => get_option( 'docs_to_wp_client_secret' ),
								'redirect_uri' => admin_url( 'options-general.php?page=docs_to_wp' ),
								'grant_type' => 'authorization_code' 
							)
						);

			update_option( 'docs_to_wp_auth_token', $result->access_token );
			update_option( 'docs_to_wp_refresh_token', $result->refresh_token );

		}

		if( get_option( 'docs_to_wp_refresh_token' ) )
			return $this->_reAuth();

		$this->_initAuth();

	}

	private function _initAuth() {

		$this->_auth->connect( 
			array(
				'response_type' => 'code',
				'client_id' => get_option( 'docs_to_wp_client_id' ),
				'redirect_uri' => admin_url( 'options-general.php?page=docs_to_wp' ),
				'access_type' => 'offline', // We're looking for the refresh token, so require offline access.
				'approval_prompt' => 'force', // We're going to walk through ALL the steps, so force the consent screen.
				'scope' => 'https://www.googleapis.com/auth/drive' // ONE SCOPE TO RULE THEM ALL!
			)
		);

	}

	private function _reAuth() {

		$response = $this->_auth->refresh( get_option( 'docs_to_wp_refresh_token' ), get_option( 'docs_to_wp_client_id' ), get_option( 'docs_to_wp_client_secret' ) );
		update_option( 'docs_to_wp_auth_token', $response->access_token );

	}

	private function _verifyConnection() {

		$this->_drive->connect( 
					array(
						'access_token' => get_option( 'docs_to_wp_auth_token' ),
						'token_type' => 'Bearer'
					) 
				);

		$response = $this->_drive->get(	'/about' );
		return !isset($response->error);
		
	}

}
