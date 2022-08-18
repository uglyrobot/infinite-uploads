<?php

use UglyRobot\Infinite_Uploads\GuzzleHttp;

class Infinite_Uploads_Stream {

	private static $instance;
	private $iup_instance;
	private $api;

	public function __construct() {
		$this->iup_instance = Infinite_Uploads::get_instance();
		$this->api          = Infinite_Uploads_Api_Handler::get_instance();


		add_action( 'wp_ajax_infinite-uploads-video-create', [ &$this, 'ajax_create' ] );
		//add_action( 'wp_ajax_infinite-uploads-video-get', [ &$this, 'ajax_get' ] );
	}

	/**
	 *
	 * @return Infinite_Uploads_Video
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_Uploads_Stream();
		}

		return self::$instance;
	}


	public function ajax_create() {
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
