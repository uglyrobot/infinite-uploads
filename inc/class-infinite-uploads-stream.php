<?php

use UglyRobot\Infinite_Uploads\GuzzleHttp;

class Infinite_Uploads_Stream {

	private static $instance;
	private $iup_instance;
	private $api;

	private $video_creds;

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
	 * Check if Stream is enabled.
	 *
	 * @return bool
	 */
	public function is_stream_enabled() {
		$creds = $this->get_creds();

		return (bool) $creds->api_key;
	}

	/**
	 * Enable Stream.
	 *
	 * @return object|false
	 */
	public function enable_stream() {
		$result = $this->api->call( "site/" . $this->api->get_site_id() . "/stream", [], 'POST' );
		if ( $result ) {
			return $this->get_creds( true );
		}

		return false;
	}

	/**
	 * Update the stream library settings.
	 *
	 * @return object|false
	 */
	public function update_library_settings( $args = [] ) {
		$result = $this->api->call( "site/" . $this->api->get_site_id() . "/stream", $args, 'POST' );
		if ( $result ) {
			return json_decode( $result );
		}

		return false;
	}

	/**
	 * Returns the video API credentials.
	 *
	 * @param bool $force Force a refresh of the credentials.
	 *
	 * @return object
	 */
	public function get_creds( $force = false ) {
		if ( ! $force && isset( $this->video_creds ) ) {
			return $this->video_creds;
		}

		$this->video_creds = (object) [
			'api_key'    => false,
			'library_id' => false,
		];

		$data = $this->api->get_site_data( $force );

		if ( isset( $data->video_api_key ) ) {
			$this->video_creds->api_key = $data->video_api_key;
		}
		if ( isset( $data->video_library_id ) ) {
			$this->video_creds->library_id = $data->video_library_id;
		}

		return $this->video_creds;
	}


	public function ajax_create_video() {
		global $wpdb;

		// check caps
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$library_id = '';
		$api_key    = '';


		$client = new GuzzleHttp\Client();

		$response = $client->request( 'POST', 'https://video.bunnycdn.com/library/libraryId/videos', [
			'headers' => [
				'Accept'       => 'application/json',
				'AccessKey'    => 'fdsfsfsdfsdfsdf',
				'Content-Type' => 'application/*+json',
			],
		] );

		echo $response->getBody();

		wp_send_json_success( $data );
	}


}
