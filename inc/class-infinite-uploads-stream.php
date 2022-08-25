<?php

use UglyRobot\Infinite_Uploads\GuzzleHttp;

class Infinite_Uploads_Stream {

	private static $instance;
	private $iup_instance;
	private $api;

	public function __construct() {
		$this->iup_instance = Infinite_Uploads::get_instance();
		$this->api          = Infinite_Uploads_Api_Handler::get_instance();

		add_action( 'wp_ajax_infinite-uploads-stream-create', [ &$this, 'ajax_create_video' ] );
		//add_action( 'wp_ajax_infinite-uploads-stream-get', [ &$this, 'ajax_get_video' ] );
	}

	/**
	 *
	 * @return Infinite_Uploads_Stream
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_Uploads_Stream();
		}

		return self::$instance;
	}

	/*
	 * Check if Stream is created/active for this site.
	 *
	 * @return bool
	 */
	public function is_stream_active() {
		return (bool) $this->get_config( 'library_id' );
	}

	/*
	 * Check if Stream uploading/viewing is enabled. (When user has billing issues,
	 * we will disable embeds and return only the read_key for viewing the library but no editing)
	 *
	 * @return bool
	 */
	public function is_stream_enabled() {
		return (bool) $this->get_config( 'enabled' );
	}

	/**
	 * Activates Stream service for this site.
	 *
	 * @return object|false
	 */
	public function activate_stream() {
		$result = $this->api->call( "site/" . $this->api->get_site_id() . "/stream", [], 'POST' );
		if ( $result ) {
			//cache the new creds/settings once we enable stream
			return $this->get_library_settings( true );
		}

		return false;
	}

	/**
	 * Update the stream library settings.
	 *
	 * @return object|false
	 */
	public function update_library_settings( $args = [] ) {
		return $this->api->call( "site/" . $this->api->get_site_id() . "/stream", $args, 'POST' );
	}

	/**
	 * Get the stream library settings. They are cached 12hrs in the options table from the regular get_site_data call by default.
	 *
	 * @param bool $force_refresh Force a refresh of the settings from api.
	 *
	 * @return object|null
	 */
	public function get_library_settings( $force_refresh = false ) {
		$data = $this->api->get_site_data( $force_refresh );

		if ( isset( $data->video->settings ) ) {
			return $data->video->settings;
		}

		return null;
	}

	/**
	 * Get the stream configuration value for a given key. They are cached 12hrs in the options table from the regular get_site_data call by default.
	 *
	 * @param string $key           The key to get (enabled, library_id, key_write, key_read, url).
	 * @param bool   $force_refresh Force a refresh of the credentials.
	 *
	 * @return mixed
	 */
	public function get_config( $key, $force_refresh = false ) {
		$data = $this->api->get_site_data( $force_refresh );
		if ( isset( $data->video->{$key} ) ) {
			return $data->video->{$key};
		} else {
			return null;
		}
	}

	/**
	 * Perform an API request to Bunny Video API.
	 *
	 * @param string $path   API path.
	 * @param array  $data   Data array.
	 * @param string $method Method. Default: POST.
	 *
	 * @return object|WP_Error
	 */
	private function api_call( $path, $data = [], $method = 'POST' ) {
		$library_id = $this->get_config( 'library_id' );

		$url = "https://video.bunnycdn.com/library/{$library_id}/" . $path;

		$headers = array(
			'Accept'       => 'application/json',
			'AccessKey'    => $this->get_config( 'key_write' ),
			'Content-Type' => 'application/json',
		);

		$args = array(
			'headers'   => $headers,
			'sslverify' => true,
			'method'    => strtoupper( $method ),
			'timeout'   => 30,
		);

		switch ( strtolower( $method ) ) {
			case 'post':
				$args['body'] = wp_json_encode( $data );

				$response = wp_remote_post( $url, $args );
				break;
			case 'get':
				if ( ! empty( $data ) ) {
					$url = add_query_arg( $data, $url );
				}

				$response = wp_remote_get( $url, $args );
				break;
			default:
				if ( ! empty( $data ) ) {
					$args['body'] = wp_json_encode( $data );
				}
				$response = wp_remote_request( $url, $args );
				break;
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201, 202, 204, 204 ], true ) ) {
			return new WP_Error( $body->ErrorKey, $body->Message, [ 'status' => wp_remote_retrieve_response_code( $response ) ] );
		}

		return $body;
	}

	/**
	 * Create a video in the stream library, and returns the params for executing the tus upload.
	 *
	 * @return void
	 */
	public function ajax_create_video() {
		global $wpdb;

		// check caps
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$result = $this->api_call( 'videos', [ 'title' => sanitize_text_field( $_REQUEST['title'] ) ] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result );
		}

		//generate the signature params for a tus upload.
		$expiration = time() + ( 6 * HOUR_IN_SECONDS );
		$response   = [
			'AuthorizationSignature' => hash( 'sha256', $result->videoLibraryId . $this->get_config( 'key_write' ) . $expiration . $result->guid ), // SHA256 signature (library_id + api_key + expiration_time + video_id)
			'AuthorizationExpire'    => $expiration, // Expiration time as in the signature,
			'VideoId'                => $result->guid, // The guid of a previously created video object through the Create Video API call
			'LibraryId'              => $result->videoLibraryId,
		];

		wp_send_json_success( $response );
	}


}
