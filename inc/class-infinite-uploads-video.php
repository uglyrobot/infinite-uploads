<?php

use UglyRobot\Infinite_Uploads\GuzzleHttp;

class Infinite_Uploads_Video {

	private static $instance;
	private $iup_instance;
	private $api;

	public function __construct() {
		$this->iup_instance = Infinite_Uploads::get_instance();
		$this->api          = Infinite_Uploads_Api_Handler::get_instance();

		/*
		//for testing, override values that our API would normally provide TODO remove
		add_filter( 'infinite_uploads_video_config', function ( $return, $key, $data ) {
			if ( 'library_id' === $key ) {
				return 56793;
			} elseif ( 'url' === $key ) {
				return 'https://vz-30d13541-113.b-cdn.net';
			} elseif ( 'key_read' === $key ) {
				return BUNNY_API_KEY;
			} elseif ( 'key_write' === $key ) {
				return BUNNY_API_KEY;
			} elseif ( 'enabled' === $key ) {
				return defined( 'BUNNY_API_KEY' );
			}

			return $return;
		}, 10, 3 );
		*/

		add_action( 'wp_ajax_infinite-uploads-video-activate', [ &$this, 'ajax_activate_video' ] );

		if ( $this->is_video_active() ) {
			add_action( 'admin_menu', [ &$this, 'admin_menu' ], 20 );
			add_action( 'network_admin_menu', [ &$this, 'admin_menu' ], 20 );

			//all write API calls we make on backend to not expose write API key
			add_action( 'wp_ajax_infinite-uploads-video-create', [ &$this, 'ajax_create_video' ] );
			add_action( 'wp_ajax_infinite-uploads-video-update', [ &$this, 'ajax_update_video' ] );
			add_action( 'wp_ajax_infinite-uploads-video-delete', [ &$this, 'ajax_delete_video' ] );
			add_action( 'wp_ajax_infinite-uploads-video-settings', [ &$this, 'ajax_update_settings' ] );

			//gutenberg block
			add_action( 'init', [ &$this, 'register_block' ] );
			add_action( 'enqueue_block_editor_assets', [ &$this, 'script_enqueue' ] );
		}

		//shortcode
		add_shortcode( 'infinite-uploads-vid', [ &$this, 'shortcode' ] );
	}

	/**
	 *
	 * @return Infinite_Uploads_Video
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_Uploads_Video();
		}

		return self::$instance;
	}

	/*
	 * Check if Video is created/active for this site.
	 *
	 * @return bool
	 */
	public function is_video_active() {
		return (bool) $this->get_config( 'library_id' );
	}

	/*
	 * Check if Video uploading/viewing is enabled. (When user has billing issues,
	 * we will disable embeds and return only the read_key for viewing the library but no editing)
	 *
	 * @return bool
	 */
	public function is_video_enabled() {
		return $this->is_video_active() && $this->get_config( 'enabled' );
	}

	/**
	 * Activates Video service for this site.
	 *
	 * @return object|false
	 */
	public function activate_video() {
		$result = $this->api->call( "site/" . $this->api->get_site_id() . "/video", [], 'POST' );
		if ( $result ) {
			//cache the new creds/settings once we enable video
			return $this->get_library_settings( true );
		}

		return false;
	}

	/**
	 * Update the video library settings.
	 *
	 * @return object|false
	 */
	public function update_library_settings( $args = [] ) {
		$new_settings = $this->api->call( "site/" . $this->api->get_site_id() . "/video", $args, 'POST' );
		if ( $new_settings ) {
			//TODO don't make another api call, just update the cache
			return $this->get_library_settings( true );
		}

		return $new_settings;
	}

	/**
	 * Get the video library settings. They are cached 12hrs in the options table from the regular get_site_data call by default.
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
	 * Get the video configuration value for a given key. They are cached 12hrs in the options table from the regular get_site_data call by default.
	 *
	 * @param string $key           The key to get (enabled, library_id, key_write, key_read, url).
	 * @param bool   $force_refresh Force a refresh of the credentials.
	 *
	 * @return mixed
	 */
	public function get_config( $key, $force_refresh = false ) {
		$data = $this->api->get_site_data( $force_refresh );
		if ( $override = apply_filters( 'infinite_uploads_video_config', null, $key, $data ) ) {
			return $override;
		}
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
			case 'post_file':
				$args['body']                    = $data;
				$args['headers']['Content-Type'] = 'application/binary';
				$args['method']                  = 'POST';

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
			error_log( "Bunny API error: " . wp_remote_retrieve_response_code( $response ) . " " . wp_remote_retrieve_body( $response ) );

			if ( isset( $body->ErrorKey ) ) {
				return new WP_Error( $body->ErrorKey, $body->Message, [ 'status' => wp_remote_retrieve_response_code( $response ) ] );
			} else {
				return new WP_Error( 'bunny_api_error', wp_remote_retrieve_response_message( $response ), [ 'status' => wp_remote_retrieve_response_code( $response ) ] );
			}
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
			'libraryId'   => $this->get_config( 'library_id' ),
			'cdnUrl'      => 'https://' . $this->get_config( 'url' ), // This give us the base CDN url for the library for building media links.
			'apiKey'      => $this->get_config( 'key_write' ), //we only expose the read key to the frontend. The write key is only used via backend ajax wrappers.
			'settings'    => $this->get_library_settings(),
			'nonce'       => wp_create_nonce( 'iup_video' ), //used to verify the request is coming from the frontend, CSRF.
			'assetBase'   => plugins_url( 'assets', __FILE__ ),
			'settingsUrl' => $this->settings_url(),
			'libraryUrl'  => $this->library_url(),
		);
		wp_register_script( 'iup-dummy-js-header', '' );
		wp_enqueue_script( 'iup-dummy-js-header' );
		wp_add_inline_script( 'iup-dummy-js-header', 'const IUP_VIDEO = ' . json_encode( $data ) . ';' );
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
		if ( ! check_ajax_referer( $nonce, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		// return error if video is not enabled
		if ( $this->is_video_active() && ! $this->is_video_enabled() ) {
			wp_send_json_error( esc_html__( 'Infinite Uploads Video is disabled due to an issue with your account.', 'infinite-uploads' ) );
		}
	}

	/**
	 * Create a video in the video library, and returns the params for executing the tus upload.
	 *
	 * @see https://docs.bunny.net/reference/video_createvideo
	 * @see https://docs.bunny.net/reference/tus-resumable-uploads
	 *
	 * @return void
	 */
	public function ajax_create_video() {
		$this->ajax_check_permissions();

		$result = $this->api_call( '/videos', [ 'title' => sanitize_text_field( wp_unslash( $_REQUEST['title'] ) ) ] );
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
	 * Update a video in the video library.
	 *
	 * @see https://docs.bunny.net/reference/video_updatevideo
	 *
	 * @return void
	 */
	public function ajax_update_video() {
		$this->ajax_check_permissions();

		$video_id = sanitize_text_field( $_REQUEST['video_id'] );

		if ( isset( $_REQUEST['title'] ) ) {
			$title  = sanitize_text_field( wp_unslash( $_REQUEST['title'] ) );
			$result = $this->api_call( "/videos/$video_id", compact( 'title' ) );
		} elseif ( isset( $_REQUEST['thumbnail'] ) ) {
			$thumbnail = sanitize_text_field( $_REQUEST['thumbnail'] );
			$result    = $this->api_call( "/videos/$video_id/thumbnail?thumbnailUrl=" . $thumbnail );
		} elseif ( isset( $_FILES['thumbnailFile'] ) ) {
			$result = $this->api_call( "/videos/$video_id/thumbnail", file_get_contents( $_FILES['thumbnailFile']['tmp_name'] ), 'POST_FILE' );
		} else {
			wp_send_json_error( esc_html__( 'Invalid request', 'infinite-uploads' ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Delete a video in the video library.
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
	 * Update video library settings via Infinite Uploads API.
	 *
	 * @see https://docs.bunny.net/reference/video_updatevideo
	 *
	 * @return void
	 */
	public function ajax_update_settings() {
		$this->ajax_check_permissions();

		//this is proxied to the Infinite Uploads API, and is sanitized/validated there.
		$settings = json_decode( wp_unslash( $_REQUEST['settings'] ) );
		$result   = $this->update_library_settings( $settings );

		if ( ! $result ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Update video library settings via Infinite Uploads API.
	 *
	 * @see https://docs.bunny.net/reference/video_updatevideo
	 *
	 * @return void
	 */
	public function ajax_activate_video() {
		$this->ajax_check_permissions();

		$result = $this->activate_video();

		if ( ! $result ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Registers the video library page under Media.
	 */
	function admin_menu() {
		$page = add_media_page(
			__( 'Video Library - Infinite Uploads', 'infinite-uploads' ),
			__( 'Video Library', 'infinite-uploads' ),
			'upload_files',
			'infinite_uploads_vids',
			[
				$this,
				'library_page',
			],
			1.1678 //for unique menu position above Add New.
		);

		add_action( 'admin_print_scripts-' . $page, [ &$this, 'script_enqueue' ] );
		add_action( 'admin_print_scripts-' . $page, [ &$this, 'admin_scripts' ] );
		add_action( 'admin_print_styles-' . $page, [ &$this, 'admin_styles' ] );

		//video settings page.
		$page = add_submenu_page(
			'infinite_uploads',
			__( 'Infinite Uploads Video', 'infinite-uploads' ),
			__( 'Video Cloud', 'infinite-uploads' ),
			$this->iup_instance->capability,
			'infinite_uploads_video_settings',
			[
				$this,
				'settings_page',
			]
		);

		add_action( 'admin_print_scripts-' . $page, [ &$this, 'script_enqueue' ] );
		add_action( 'admin_print_scripts-' . $page, [ &$this, 'admin_scripts' ] );
		add_action( 'admin_print_styles-' . $page, [ &$this, 'admin_styles' ] );
	}


	/**
	 * Get the settings url with optional url args.
	 *
	 * @param array $args Optional. Same as for add_query_arg()
	 *
	 * @return string Unescaped url to settings page.
	 */
	function settings_url( $args = [] ) {
		if ( is_multisite() ) {
			$base = network_admin_url( 'admin.php?page=infinite_uploads_video_settings' );
		} else {
			$base = admin_url( 'admin.php?page=infinite_uploads_video_settings' );
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Get the settings url with optional url args.
	 *
	 * @param array $args Optional. Same as for add_query_arg()
	 *
	 * @return string Unescaped url to settings page.
	 */
	function library_url( $args = [] ) {
		$base = admin_url( 'upload.php?page=infinite_uploads_vids' );

		return add_query_arg( $args, $base );
	}

	function register_block() {
		register_block_type( __DIR__ . '/video/block' );
	}

	/**
	 * @todo adjust for the video library page.
	 */
	function admin_scripts() {
		wp_enqueue_script( 'iup-admin', plugins_url( 'build/admin.js', __DIR__ ), array( 'wp-element', 'wp-i18n' ), ( wp_get_environment_type() !== 'production' ? time() : INFINITE_UPLOADS_VERSION ), false );
	}

	/**
	 *
	 */
	function admin_styles() {
		wp_enqueue_style( 'iup-uppy', plugins_url( 'build/style-block.css', __DIR__ ), false, INFINITE_UPLOADS_VERSION ); //Have no idea why webpack is putting uppy css in this file.
		wp_enqueue_style( 'iup-admin', plugins_url( 'build/admin.css', __DIR__ ), false, INFINITE_UPLOADS_VERSION );
	}

	/**
	 * Video library page display callback.
	 */
	function library_page() {
		?>
		<div id="iup-videos-page" class="wrap iup-background">
		</div>

		<?php
		require_once( dirname( __FILE__ ) . '/templates/footer.php' );
	}

	/**
	 * Video library page display callback.
	 */
	function settings_page() {
		?>
		<div id="iup-video-settings-page" class="wrap iup-background">
		</div>

		<?php
		require_once( dirname( __FILE__ ) . '/templates/footer.php' );
	}

	/**
	 * Video embed shortcode.
	 */
	function shortcode( $atts ) {
		//hide shortcode when not logged in.
		if ( ! $this->is_video_active() ) {
			return '';
		}

		$atts = shortcode_atts(
			[
				'id'       => '',
				'autoplay' => false,
				'loop'     => false,
				'muted'    => false,
				'preload'  => true,
			],
			$atts,
			'infinite-uploads-vid'
		);

		$video_url = esc_url_raw( sprintf( 'https://iframe.mediadelivery.net/embed/%d/%s', $this->get_config( 'library_id' ), $atts['id'] ) );

		unset( $atts['id'] );
		$video_url = add_query_arg(
			$atts,
			$video_url
		);

		//fully escape now
		$video_url = esc_url( $video_url );

		return <<<HTML
		<figure class="wp-block-video">
			<div style="position: relative;padding-top: 56.25%;min-width: var(--wp--style--global--content-size);"><iframe src="$video_url" loading="lazy" style="border: none; position: absolute; top: 0; height: 100%; width: 100%;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true"></iframe></div>
		</figure>
		HTML;

	}

	/**
	 * Video library page display callback.
	 *
	 * @todo This should be adapted to all bootstrap-react in it's own template files loaded by admin_scripts().
	 */
	function video_library_page2() {
		?>
		<div id="container" class="wrap iup-background">

			<h1 class="text-muted mb-3">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-gray.svg', __FILE__ ) ); ?>" alt="Infinite Uploads Logo" height="36" width="36"/>
				<?php esc_html_e( 'Video Library', 'infinite-uploads' ); ?>
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
		</div>

		<!-- Example Upload modal -->
		<div class="modal fade" id="upload-modal" tabindex="-1" role="dialog" aria-labelledby="upload-modal-label" aria-hidden="true">
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

									<img class="mb-4" src="<?php echo esc_url( plugins_url( '/inc/assets/img/push-to-cloud.svg', dirname( __FILE__ ) ) ); ?>" alt="Upload to Cloud" height="76" width="76"/>

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
