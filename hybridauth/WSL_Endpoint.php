<?php
/*!
* WordPress Social Login
*
* https://miled.github.io/wordpress-social-login | http://github.com/miled/wordpress-social-login
*  (c) 2011-2014 Mohamed Mrassi and contributors | http://wordpress.org/extend/plugins/wordpress-social-login/
*/

class WSL_Hybrid_Endpoint extends Hybrid_Endpoint
{
	public static function process( $request = NULL )
	{
		// Setup request variable
		Hybrid_Endpoint::$request = $request;

		if ( is_null(Hybrid_Endpoint::$request) ){
			// Fix a strange behavior when some provider call back ha endpoint
			// with /index.php?hauth.done={provider}?{args}... 
			// >here we need to recreate the $_REQUEST
			if ( strrpos( $_SERVER["QUERY_STRING"], '?' ) ) {
				$_SERVER["QUERY_STRING"] = str_replace( "?", "&", $_SERVER["QUERY_STRING"] );

				parse_str( $_SERVER["QUERY_STRING"], $_REQUEST );
			}

			Hybrid_Endpoint::$request = $_REQUEST;
		}

		// If we get a hauth.start
		if ( isset( WSL_Hybrid_Endpoint::$request["hauth_start"] ) && WSL_Hybrid_Endpoint::$request["hauth_start"] ) {
			WSL_Hybrid_Endpoint::processAuthStart();
		} 
		// Else if hauth.done
		elseif ( isset( WSL_Hybrid_Endpoint::$request["hauth_done"] ) && WSL_Hybrid_Endpoint::$request["hauth_done"] ) {
			WSL_Hybrid_Endpoint::processAuthDone();
		}
		
		print_r( Hybrid_Endpoint::$request );
	}

	public static function processAuthStart()
	{
		WSL_Hybrid_Endpoint::authInit();

		parent::processAuthStart();
	}

	public static function processAuthDone()
	{
		WSL_Hybrid_Endpoint::authInit();

		parent::processAuthDone();
	}

	public static function authInit()
	{
		$storage = new Hybrid_Storage();

		header( 'X-Hybridauth-Version: ' . $storage->config( "version" ) . '/' . PHP_VERSION );
		header( 'X-Hybridauth-Session: ' . $storage->config( "php_session_id" ) . '/' . session_id() );
		header( 'X-Hybridauth-Config: '  . strlen( json_encode( $storage->config( "CONFIG" ) ) ) );
		header( 'X-Hybridauth-Error: '   . ( (bool) $storage->get( "hauth_session.error.status" ) ? $storage->get( "hauth_session.error.code" ) : 'No' ) );

		if ( ! WSL_Hybrid_Endpoint::$initDone ){
			WSL_Hybrid_Endpoint::$initDone = TRUE;

			// Check if Hybrid_Auth session already exist
			if ( ! $storage->config( "CONFIG" ) ) {
				if ( ! $storage->config( "php_session_id" ) ) {
					header( 'HTTP/1.0 401 Unauthorized' ); 
					WSL_Hybrid_Endpoint::dieError( "You cannot access this page directly." );
				}

				header( 'HTTP/1.0 406 Not Acceptable' );
				WSL_Hybrid_Endpoint::dieError( "Hybridauth config was not found in storage. You have either get to this page directly or PHP sessions ain't working properly." );
			}

			# Init Hybrid_Auth
			try{
				Hybrid_Auth::initialize( $storage->config( "CONFIG" ) ); 
			}
			catch ( Exception $e ){
				header( 'HTTP/1.0 500 Internal Server Error' );
				WSL_Hybrid_Endpoint::dieError( 'An error occurred while attempting to initialize Hybridauth' );
			}
		}
	}

	public static function dieError( $message )
	{
		#{{{
		# This should be executed only once every three millennium
		# It means either : 1. Php Sessions ain't working as expected or expired. 2. A crawler got lost. 3. Someone is having fun forging urls.
		# If wp-load.php is not included in the index.php, then do it manually. From now on, you're on your own. Goodbye.
		if( function_exists( 'get_option' ) ){
			if( get_option( 'wsl_settings_development_mode_enabled' ) ){
				wsl_display_dev_mode_debugging_area();
			}
		}
		#}}}

		die( $message );
	}
}
