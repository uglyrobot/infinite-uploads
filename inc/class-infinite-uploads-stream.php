<?php


use UglyRobot\Infinite_Uploads\GuzzleHttp;

class Infinite_Uploads_Stream {

	private static $instance;
	private $iup_instance;
	private $api;

	public function __construct() {

		$this->iup_instance = Infinite_Uploads::get_instance();
		$this->api          = Infinite_Uploads_Api_Handler::get_instance();

		add_action( 'admin_menu', [ &$this, 'admin_menu' ] );
		add_action( 'enqueue_block_editor_assets', [ &$this, 'script_enqueue' ] );
		add_action( 'wp_ajax_infinite-uploads-stream-create', [ &$this, 'ajax_create_video' ] );
		add_action( 'wp_ajax_infinite-uploads-stream-get', [ &$this, 'ajax_get_video' ] );
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
		return $this->is_stream_active() && $this->get_config( 'enabled' );
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

		$url = "https://video.bunnycdn.com/library/{$library_id}/" . ltrim( $path, '/' );

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
	 * Enqueue the block's assets for the editor.
	 *
	 * @see https://developer.wordpress.org/block-editor/tutorials/block-tutorial/applying-styles-with-stylesheets/
	 */
	function script_enqueue() {
		$data = array(
			'libraryId' => $this->get_config( 'library_id' ),
			'cdnUrl'    => $this->get_config( 'url' ), // This give us the base CDN url for the library for building media links.
			'apiKey'    => $this->get_config( 'key_read' ), //we only expose the read key to the frontend. The write key is only used via backend ajax wrappers.
			'nonce'     => wp_create_nonce( 'iup_video' ), //used to verify the request is coming from the frontend, CSRF.
		);
		wp_register_script( 'iup-dummy-js-header', '' );
		wp_enqueue_script( 'iup-dummy-js-header' );
		wp_add_inline_script( 'iup-dummy-js-header', 'const IUP_STREAM = ' . json_encode( $data ) . ';' );

	}


	/**
	 * Check permissions for a ajax request.
	 *
	 * @param $nonce
	 *
	 * @return void
	 */
	public function ajax_check_permissions( $nonce = 'iup_video' ) {
		// check caps
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		//check nonce
		check_ajax_referer( $nonce, 'nonce' );

		// return error if stream is not enabled
		if ( ! $this->is_stream_enabled() ) {
			wp_send_json_error( esc_html__( 'Infinite Uploads Stream is disabled due to an issue with your account.', 'infinite-uploads' ) );
		}
	}

	/**
	 * Create a video in the stream library, and returns the params for executing the tus upload.
	 *
	 * @see https://docs.bunny.net/reference/video_createvideo
	 * @see https://docs.bunny.net/reference/tus-resumable-uploads
	 *
	 * @return void
	 */
	public function ajax_create_video() {
		 
		$this->ajax_check_permissions();

		$result = $this->api_call( '/videos', [ 'title' => sanitize_text_field( $_REQUEST['title'] ) ] );
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

	/**
	 * Update a video in the stream library.
	 *
	 * @see https://docs.bunny.net/reference/video_updatevideo
	 *
	 * @return void
	 */
	public function ajax_update_video() {
		$this->ajax_check_permissions();

		//TODO validate and sanitize these.
		$args = $_REQUEST['params'];

		$video_id = sanitize_text_field( $args['video_id'] );

		$result = $this->api_call( "/videos/$video_id", $args );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Delete a video in the stream library.
	 *
	 * @see https://docs.bunny.net/reference/video_deletevideo
	 *
	 * @return void
	 */
	public function ajax_delete_video() {
		$this->ajax_check_permissions();

		$video_id = sanitize_text_field( $_REQUEST['video_id'] );

		$result = $this->api_call( "/videos/$video_id", [], 'DELETE' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Registers the video library page under Media.
	 */
	function admin_menu() {
		/*$page = add_media_page(
			__( 'Stream Video Library - Infinite Uploads', 'infinite-uploads' ),
			__( 'Video Library', 'infinite-uploads' ),
			$this->iup_instance->capability,
			'infinite_uploads_vids',
			[
				$this,
				'video_library_page',
			],
			1.1678 //for unique menu position above Add New.
		);*/
		$page = add_media_page(
			__( 'Stream Video Library - Infinite Uploads', 'infinite-uploads' ),
			__( 'Cloud Video Library', 'cloud-infinite-uploads' ),
			$this->iup_instance->capability,
			'cloud_video_library',
			[
				$this,
				'cloud_video_library',
			],
			//1.1678 //for unique menu position above Add New.
		);

		add_action( 'admin_print_scripts-' . $page, [ &$this, 'script_enqueue' ] );
		add_action( 'admin_print_scripts-' . $page, [ &$this, 'admin_scripts' ] );
		add_action( 'admin_print_styles-' . $page, [ &$this, 'admin_styles' ] );
	}

	/**
	 * @todo adjust for the video library page.
	 */
	function admin_scripts() {
		wp_enqueue_script( 'iup-bootstrap', plugins_url( 'assets/bootstrap/js/bootstrap.bundle.min.js', __FILE__ ), [ 'jquery' ], INFINITE_UPLOADS_VERSION );

		wp_enqueue_script( 'iup-js', plugins_url( 'assets/js/infinite-uploads.js', __FILE__ ), [ 'wp-color-picker' ], INFINITE_UPLOADS_VERSION );
		wp_register_script( 'jquery_js_query',  plugins_url('infinite-uploads/src/nodex.js'), null, null, true);

		wp_register_script( 'tus-js',  plugins_url('infinite-uploads/src/tus.js'),  null, null, true);

		/*wp_register_script( 'jquery_js_query2',  plugins_url('infinite-uploads/src/crypto-js.min.js') );*/
		
		/*wp_register_script( 'sha256-min-js', 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/sha256.min.js', null, null, true );*/

		wp_register_style( 'bootstrap_css', 'https://netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/css/bootstrap-combined.min.css', null, null, true);

		wp_enqueue_style('bootstrap');

		wp_enqueue_script('sha256-min-js');

		wp_enqueue_script( 'tus-js' );
		
		wp_enqueue_script( 'jquery_js_query' );

		wp_enqueue_script( 'jquery_js_query1' );
		wp_enqueue_script( 'jquery_js_query2' );

		$data            = [];
		$data['strings'] = [
			'leave_confirm'      => esc_html__( 'Are you sure you want to leave this tab? The current bulk action will be canceled and you will need to continue where it left off later.', 'infinite-uploads' ),
			'ajax_error'         => esc_html__( 'Too many server errors. Please try again.', 'infinite-uploads' ),
			'leave_confirmation' => esc_html__( 'If you leave this page the sync will be interrupted and you will have to continue where you left off later.', 'infinite-uploads' ),
		];

		$data['local_types'] = $this->iup_instance->get_filetypes( true );

		$api_data = $this->api->get_site_data();
		if ( $this->api->has_token() && $api_data ) {
			$data['cloud_types'] = $this->iup_instance->get_filetypes( true, $api_data->stats->site->types );
		}

		$data['nonce'] = [
			'scan'     => wp_create_nonce( 'iup_scan' ),
			'sync'     => wp_create_nonce( 'iup_sync' ),
			'delete'   => wp_create_nonce( 'iup_delete' ),
			'download' => wp_create_nonce( 'iup_download' ),
			'toggle'   => wp_create_nonce( 'iup_toggle' ),
		];

		wp_localize_script( 'iup-js', 'iup_data', $data );
	}

	/**
	 *
	 */
	function admin_styles() {
		wp_enqueue_style( 'iup-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), false, INFINITE_UPLOADS_VERSION );
		wp_enqueue_style( 'iup-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), [ 'iup-bootstrap' ], INFINITE_UPLOADS_VERSION );
	}

	/**
	 * Video library page display callback.
	 *
	 * @todo This should be adapted to all bootstrap-react in it's own template files loaded by admin_scripts().
	 */
	function cloud_video_library() { ?>
		<div id="container" class="wrap iup-background">

			<h1 class="text-muted mb-3">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-gray.svg', __FILE__ ) ); ?>" alt="Infinite Uploads Logo" height="36" width="36"/>
				<?php esc_html_e( 'Stream Video Library', 'infinite-uploads' ); ?>
			</h1>

			<div id="iup-error" class="alert alert-danger mt-1" role="alert"></div>

			<?php if ( isset( $api_data->site ) && ! $api_data->site->cdn_enabled ) { ?>
				<div class="alert alert-warning mt-1" role="alert">
					<?php printf( __( "Files can't be uploaded and your CDN is disabled due to a billing issue with your Infinite Uploads account. Please <a href='%s' class='alert-link'>visit your account page</a> to fix, or disconnect this site from the cloud. Images and links to media on your site may be broken until you take action. <a href='%s' class='alert-link' data-toggle='tooltip' title='Refresh account data'>Already fixed?</a>", 'infinite-uploads' ), esc_url( $this->api_url( '/account/billing/?utm_source=iup_plugin&utm_medium=plugin&utm_campaign=iup_plugin' ) ), esc_url( $this->settings_url( [ 'refresh' => 1 ] ) ) ); ?>
				</div>
			<?php } elseif ( isset( $api_data->site ) && ! $api_data->site->upload_writeable ) { ?>
				<div class="alert alert-warning mt-1" role="alert">
					<?php printf( __( "Files can't be uploaded and your CDN will be disabled soon due to a billing issue with your Infinite Uploads account. Please <a href='%s' class='alert-link'>visit your account page</a> to fix, or disconnect this site from the cloud. <a href='%s' class='alert-link' data-toggle='tooltip' title='Refresh account data'>Already fixed?</a>", 'infinite-uploads' ), esc_url( $this->api_url( '/account/billing/?utm_source=iup_plugin&utm_medium=plugin&utm_campaign=iup_plugin' ) ), esc_url( $this->settings_url( [ 'refresh' => 1 ] ) ) ); ?>
				</div>
			<?php } ?>

			<div class="container-fluid">
				<div class="row justify-content-between mb-4">
					<div class="form-row mb-3 col-12 col-sm-10 col-lg-8 col-xl-6">
						<div class="col input-group input-group">
							<div class="input-group-prepend">
								<span class="input-group-text" id="basic-addon1"><span class="dashicons dashicons-search"></span></span>
							</div>
							<input type="text" class="form-control" placeholder="<?php esc_attr_e( 'Search', 'infinite-uploads' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'infinite-uploads' ); ?>" aria-describedby="basic-addon1">
						</div>
						<div class="col input-group input-group">
							<div class="input-group-prepend">
								<span class="input-group-text" id="basic-addon1">Sort</span>
							</div>
							<select class="custom-select custom-select-lg" aria-describedby="basic-addon1">
								<option value="1">Name</option>
								<option value="2">Date</option>
							</select>
						</div>
					</div>
					<div class="col-12 col-sm-10 col-lg-4 col-xl-3 text-center">
						<button class="btn text-nowrap btn-info btn-lg btn-block" id="new_video" data-toggle="modal" data-target="#upload-modal1" type="button"><?php esc_html_e( 'New Video', 'infinite-uploads1' ); ?></button>
					</div>
				</div>

				
				<div class="row justify-content-start d-flex">
					<a class="card col m-3 h-100 p-0 shadow-sm text-decoration-none" role="button" data-toggle="modal" data-target="#video-modal">
						<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg" onmouseover="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/preview.webp'"
						     onmouseout="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg'" class="card-img-top" alt="video thumbnail">
						<div class="card-body">
							<h6 class="card-title text-truncate">I Will Survive Coronavirus.mp4</h6>
							<small class="row justify-content-between text-muted text-center">
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
							</small>
						</div>
					</a>
					<a class="card col m-3 h-100 p-0 shadow-sm text-decoration-none" role="button" data-toggle="modal" data-target="#video-modal">
						<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg" onmouseover="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/preview.webp'"
						     onmouseout="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg'" class="card-img-top" alt="video thumbnail">
						<div class="card-body">
							<h6 class="card-title text-truncate">I Will Survive Coronavirus.mp4</h6>
							<small class="row justify-content-between text-muted text-center">
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
							</small>
						</div>
					</a>
					<a class="card col m-3 h-100 p-0 shadow-sm text-decoration-none" role="button" data-toggle="modal" data-target="#video-modal">
						<!-- TODO force 16:9 aspect ratio of thumbnails -->
						<img src="https://vz-a8691a32-d3c.b-cdn.net/c67eb0ed-ceec-408f-8b24-15437fde12ab/thumbnail.jpg" onmouseover="this.src='https://vz-a8691a32-d3c.b-cdn.net/c67eb0ed-ceec-408f-8b24-15437fde12ab/preview.webp'"
						     onmouseout="this.src='https://vz-a8691a32-d3c.b-cdn.net/c67eb0ed-ceec-408f-8b24-15437fde12ab/thumbnail.jpg'" class="card-img-top" alt="video thumbnail">
						<div class="card-body">
							<h6 class="card-title text-truncate">Core Contributor NFT Coin</h6>
							<small class="row justify-content-between text-muted text-center">
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
							</small>
						</div>
					</a>
					<a class="card col m-3 h-100 p-0 shadow-sm text-decoration-none" role="button" data-toggle="modal" data-target="#video-modal">
						<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg" onmouseover="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/preview.webp'"
						     onmouseout="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg'" class="card-img-top" alt="video thumbnail">
						<div class="card-body">
							<h6 class="card-title text-truncate">I Will Survive Coronavirus.mp4</h6>
							<small class="row justify-content-between text-muted text-center">
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
							</small>
						</div>
					</a>
					<a class="card col m-3 h-100 p-0 shadow-sm text-decoration-none" role="button" data-toggle="modal" data-target="#video-modal">
						<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg" onmouseover="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/preview.webp'"
						     onmouseout="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg'" class="card-img-top" alt="video thumbnail">
						<div class="card-body">
							<h6 class="card-title text-truncate">I Will Survive Coronavirus.mp4</h6>
							<small class="row justify-content-between text-muted text-center">
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
							</small>
						</div>
					</a>
					<a class="card col m-3 h-100 p-0 shadow-sm text-decoration-none" role="button" data-toggle="modal" data-target="#video-modal">
						<img src="https://vz-a8691a32-d3c.b-cdn.net/67c8df65-f81e-4ac4-ae60-2d818132c3d3/thumbnail.jpg" onmouseover="this.src='https://vz-a8691a32-d3c.b-cdn.net/67c8df65-f81e-4ac4-ae60-2d818132c3d3/preview.webp'"
						     onmouseout="this.src='https://vz-a8691a32-d3c.b-cdn.net/67c8df65-f81e-4ac4-ae60-2d818132c3d3/thumbnail.jpg'" class="card-img-top" alt="video thumbnail">
						<div class="card-body">
							<h6 class="card-title text-truncate">Linkedin-ad.mp4</h6>
							<small class="row justify-content-between text-muted text-center">
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
							</small>
						</div>
					</a>
					<a class="card col m-3 h-100 p-0 shadow-sm text-decoration-none" role="button" data-toggle="modal" data-target="#video-modal">
						<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg" onmouseover="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/preview.webp'"
						     onmouseout="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg'" class="card-img-top" alt="video thumbnail">
						<div class="card-body">
							<h6 class="card-title text-truncate">I Will Survive Coronavirus.mp4</h6>
							<small class="row justify-content-between text-muted text-center">
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
							</small>
						</div>
					</a>
					<a class="card col m-3 h-100 p-0 shadow-sm text-decoration-none" role="button" data-toggle="modal" data-target="#video-modal">
						<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg" onmouseover="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/preview.webp'"
						     onmouseout="this.src='https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg'" class="card-img-top" alt="video thumbnail">
						<div class="card-body">
							<h6 class="card-title text-truncate">I Will Survive Coronavirus.mp4</h6>
							<small class="row justify-content-between text-muted text-center">
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
								<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
							</small>
						</div>
					</a>

				</div>
			</div>
		</div>
<script>
	jQuery(document).ready(function($){
		$(document).on("click", "#clickImg", function(e) {
				$("#file").trigger("click");				
		}); 
	}); 
	function encfiletobase64(element){
		 tusUpload(element);
	}
</script>		
		<div class="modal fade" id="upload-modal1" tabindex="-1" role="dialog" aria-labelledby="upload-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="upload-modal-label"><?php esc_html_e( 'Upload Video', 'infinite-uploads' ); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="container-fluid">
							<div class="row justify-content-center mb-4 mt-3">
								<div class="col text-center">
									<h4><?php esc_html_e( 'Add a New Video to the Infinite Uploads Cloud', 'infinite-uploads' ); ?></h4>
									<p class="lead"><?php esc_html_e( 'Drop your videos here to upload them to your cloud video library for encoding and embedding on your site.', 'infinite-uploads' ); ?></p>
									<div id="upload-list"></div>
									<img id="clickImg" class="mb-4" src="<?php echo esc_url( plugins_url( '/inc/assets/img/push-to-cloud.svg', dirname( __FILE__ ) ) ); ?>" alt="Upload to Cloud" height="76" width="76"/>
									<form method="POST" enctype="multipart/form-data">
										<input id="file" type="file" name="inupt_files" onchange="encfiletobase64(this)" style="display:none" >
									</form>
									<div class="progressBarCustom">
								        <div class="span8">
								          <div class="progress progress-striped progress-success">
								            <div class="bar" style="width: 0%;"></div>
								          </div>
								        </div><br/>
								        <div id="file_name"></div>
      								</div><br/>
      								<div class="encod_progress"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- Example Upload modal -->
		<div class="modal fade" id="video-modal" tabindex="-1" role="dialog" aria-labelledby="video-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-xl">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="video-modal-label"><?php esc_html_e( 'Edit Video:', 'infinite-uploads' ); ?> I Will Survive Coronavirus.mp4</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="container-fluid">
							<div class="row justify-content-center mb-4 mt-3">
								<div class="col">
									<div class="row mb-2">
										<div class="col">
											<div style="position: relative; padding-top: 56.25%;">
												<iframe src="https://iframe.mediadelivery.net/embed/26801/3aadf1e3-b76d-41db-bcfa-9e6b670b185c?autoplay=false" loading="lazy" style="border: none; position: absolute; top: 0; height: 100%; width: 100%;"allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true">
												</iframe>
											</div>
										</div>
									</div>
									<div class="row justify-content-between text-muted text-center">
										<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Video Length', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-clock"></span> 00:01:18</div>
										<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'View Count', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-welcome-view-site"></span> 2,234</div>
										<div class="col" data-toggle="tooltip" title="<?php esc_attr_e( 'Storage Size', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-media-video"></span> 112.21MB</div>
									</div>
								</div>
								<div class="col">

									<div class="row mb-2">
										<div class="col">
											<label for="video-title">Video Title</label>
											<div class="input-group mb-3">
												<input type="text" id="video-title" class="form-control" placeholder="Enter a Title" aria-label="Enter a Title" aria-describedby="button-addon2" value="I Will Survive Coronavirus.mp4">
												<div class="input-group-append">
													<button class="btn btn-primary" type="button" id="button-addon2"><span class="dashicons dashicons-saved"></span> Save</button>
												</div>
											</div>
										</div>
									</div>

									<div class="row mb-4">
										<div class="col-4">
											<h6><?php esc_html_e( 'Current Thumbnail', 'infinite-uploads' ); ?></h6>
											<div class="card bg-dark text-white w-100 p-0 mb-2" role="button">
												<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail.jpg" class="card-img" alt="...">
											</div>
										</div>
										<div class="col-8">

											<p><?php esc_html_e( 'Choose a new thumbnail to be displayed in the video player:', 'infinite-uploads' ); ?></p>
											<div class="row justify-content-start d-flex row-cols-2 row-cols-md-3">
												<div class="col mb-2">
													<a class="card bg-dark text-white h-100 p-0" role="button">
														<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail_1.jpg" class="card-img" alt="...">
														<div class="card-img-overlay">
															<div class="card-title align-middle text-center text-white"></div>
														</div>
													</a>
												</div>
												<div class="col mb-2">
													<a class="card bg-dark text-white p-0" role="button">
														<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail_2.jpg" class="card-img" alt="...">
														<div class="card-img-overlay">
															<div class="card-title align-middle text-center text-white"></div>
														</div>
													</a>
												</div>
												<div class="col mb-2">
													<a class="card bg-dark text-white p-0" role="button">
														<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail_3.jpg" class="card-img" alt="...">
														<div class="card-img-overlay">
															<div class="card-title align-middle text-center text-white"></div>
														</div>
													</a>
												</div>
												<div class="col mb-2">
													<a class="card bg-dark text-white p-0" role="button">
														<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail_4.jpg" class="card-img" alt="...">
														<div class="card-img-overlay">
															<div class="card-title align-middle text-center text-white"></div>
														</div>
													</a>
												</div>
												<div class="col mb-2">
													<a class="card bg-dark text-white p-0" role="button">
														<img src="https://vz-a8691a32-d3c.b-cdn.net/3aadf1e3-b76d-41db-bcfa-9e6b670b185c/thumbnail_5.jpg" class="card-img" alt="...">
														<div class="card-img-overlay">
															<div class="card-title align-middle text-center text-white"></div>
														</div>
													</a>
												</div>
												<div class="col mb-2">
													<div class="card bg-light text-white p-0" role="button">
														<div class="card-img-overlay">
															<div class="card-title align-middle text-center text-white"><span class="dashicons dashicons-cloud-upload"></span></div>
														</div>
													</div>
												</div>

											</div>
										</div>

									</div>

									<div class="row justify-content-between mb-3">
										<div class="col">
										</div>
										<div class="col-4">
											<button type="button" class="btn btn-info w-100">Delete Video</button>
										</div>
									</div>

								</div>
							</div>


							<div class="row justify-content-center mb-4">
								<div class="col">
									<nav id="stream-nav-tab">
										<div class="nav nav-tabs" role="tablist">
											<button class="nav-link active" id="nav-shortcode-tab" data-toggle="tab" data-target="#nav-shortcode" type="button" role="tab" aria-controls="nav-shortcode" aria-selected="false"><span class="dashicons dashicons-shortcode"></span> Embed Code</button>
											<button class="nav-link" id="nav-stats-tab" data-toggle="tab" data-target="#nav-stats" type="button" role="tab" aria-controls="nav-stats" aria-selected="true"><span class="dashicons dashicons-chart-area"></span> Stats</button>
											<button class="nav-link" id="nav-encoding-tab" data-toggle="tab" data-target="#nav-encoding" type="button" role="tab" aria-controls="nav-encoding" aria-selected="false"><span class="dashicons dashicons-format-status"></span> Captions</button>
											<button class="nav-link" id="nav-security-tab" data-toggle="tab" data-target="#nav-security" type="button" role="tab" aria-controls="nav-security" aria-selected="false"><span class="dashicons dashicons-text"></span> Chapters</button>
											<button class="nav-link" id="nav-security-tab" data-toggle="tab" data-target="#nav-security" type="button" role="tab" aria-controls="nav-security" aria-selected="false"><span class="dashicons dashicons-images-alt"></span> Moments</button>
										</div>
									</nav>
								</div>
							</div>

							<div class="tab-pane fade show" id="nav-shortcode" role="tabpanel" aria-labelledby="nav-shortcode-tab">
								<div class="row justify-content-center">
									<div class="col">
										<div class="row">
											<div class="col">
												<p><?php esc_html_e( 'Copy and paste this code into your post, page, or widget to embed the video.', 'infinite-uploads' ); ?></p>
											</div>
										</div>
										<div class="row mb-1">
											<div class="col">
												<div class="custom-control custom-switch custom-control-inline">
													<input type="checkbox" class="custom-control-input" id="customSwitch1">
													<label class="custom-control-label" for="customSwitch1">Autoplay</label>
												</div>

												<div class="custom-control custom-switch custom-control-inline">
													<input type="checkbox" class="custom-control-input" id="customSwitch2" checked>
													<label class="custom-control-label" for="customSwitch2">Preload</label>
												</div>

												<div class="custom-control custom-switch custom-control-inline">
													<input type="checkbox" class="custom-control-input" id="customSwitch3">
													<label class="custom-control-label" for="customSwitch3">Loop</label>
												</div>

												<div class="custom-control custom-switch custom-control-inline">
													<input type="checkbox" class="custom-control-input" id="customSwitch4">
													<label class="custom-control-label" for="customSwitch4">Muted</label>
												</div>

											</div>
										</div>
										<div class="row">
											<div class="col">
												<textarea class="form-control" rows="3" readonly>[infinite-uploads id="3aadf1e3-b76d-41db-bcfa-9e6b670b185c" preload="true"]</textarea>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="tab-pane fade show" id="nav-stats" role="tabpanel" aria-labelledby="nav-stats-tab">
								<div class="row justify-content-center">
									<div class="col">
										<div class="row">
											<div class="col">
												<h5><?php esc_html_e( 'Statistics', 'infinite-uploads' ); ?></h5>
												<p><?php esc_html_e( 'View the statistics for this video.', 'infinite-uploads' ); ?></p>
											</div>
										</div>
										<div class="row">
											<div class="col">
												Chart here
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php
		require_once( dirname( __FILE__ ) . '/templates/footer.php' );
	}
}