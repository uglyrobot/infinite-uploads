<?php

use UglyRobot\Infinite_Uploads\Aws\S3\S3Client;
use UglyRobot\Infinite_Uploads\Aws\Multipart\UploadState;
use UglyRobot\Infinite_Uploads\Aws\ResultInterface;

class Infinite_Uploads {

	private static $instance;
	public $original_upload_dir;
	public $bucket; //includes customer prefix
	public $bucket_url;
	public $capability;
	private $key;
	private $secret;
	private $region;
	private $admin;
	private $api;
	public $stream_api_call_count = [];
	public $stream_plugin_api_call_count = [];
	public $stream_file_cache = [];

	public function __construct() {
		/**
		 * Filters the capability that is checked for access to Infinite Uploads settings page.
		 *
		 * @param  {string}  $capability  The capability checked for access and editing settings. Default `manage_network_options` or `manage_options` depending on if multisite.
		 *
		 * @return {string}  $capability  The capability checked for access and editing settings.
		 * @since  1.0
		 * @hook   infinite_uploads_settings_capability
		 *
		 */
		$this->capability = apply_filters( 'infinite_uploads_settings_capability', ( is_multisite() ? 'manage_network_options' : 'manage_options' ) );

		$this->stream_api_call_count = [ 'total' => 0, 'commands' => [] ];
	}

	/**
	 *
	 * @return Infinite_Uploads
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_Uploads();
		}

		return self::$instance;
	}

	/**
	 * Creates an UploadState object for a multipart upload by querying
	 * for the specified uploads' information. This allows us to continue a
	 * multipart upload across multiple requests if we store the UploadId.
	 *
	 * @param string $key       Object key for the multipart upload.
	 * @param string $upload_id Upload ID for the multipart upload.
	 *
	 * @return UploadState
	 */
	public function get_multipart_upload_state( $key, $upload_id ) {
		$state = new UploadState( [ 'Bucket' => $this->get_s3_bucket(), 'Key' => $key, 'UploadId' => $upload_id ] );
		foreach ( $this->s3()->getPaginator( 'ListParts', $state->getId() ) as $result ) {
			// Get the part size from the first part in the first result.
			if ( ! $state->getPartSize() ) {
				$state->setPartSize( $result->search( 'Parts[0].Size' ) );
			}
			// Mark all the parts returned by ListParts as uploaded.
			foreach ( $result['Parts'] as $part ) {
				$state->markPartAsUploaded( $part['PartNumber'], [ 'PartNumber' => $part['PartNumber'], 'ETag' => $part['ETag'] ] );
			}
		}
		$state->setStatus( UploadState::INITIATED );

		return $state;
	}

	/**
	 * Parses filename for the filelist db table from an AWS upload result.
	 *
	 * @param ResultInterface $result AWS result object.
	 *
	 * @return string
	 */
	public function get_file_from_result( ResultInterface $result ) {
		return '/' . urldecode( strstr( substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], $this->bucket ) + strlen( $this->bucket ) ) ), '?', true ) ?: substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], $this->bucket ) + strlen( $this->bucket ) ) ) );
	}

	/**
	 * Setup the hooks, urls filtering etc for Infinite Uploads
	 */
	public function setup() {

		$this->admin = Infinite_Uploads_Admin::get_instance();
		$this->api   = Infinite_Uploads_Api_Handler::get_instance();

		//Add cloud permissions if present
		$api_data = $this->api->get_site_data();
		if ( $api_data && isset( $api_data->site ) && isset( $api_data->site->upload_key ) ) {
			$this->bucket     = $api_data->site->upload_bucket;
			$this->key        = $api_data->site->upload_key;
			$this->secret     = $api_data->site->upload_secret;
			$this->bucket_url = $api_data->site->cdn_url;
			$this->region     = $api_data->site->upload_region;

			add_filter( 'infinite_uploads_s3_client_params', function ( $params ) use ( $api_data ) {
				$params['endpoint']                = $api_data->site->upload_endpoint;
				$params['use_path_style_endpoint'] = true;
				//$params['debug'] = [
				//	'logfn'        => 'error_log',
				//	'stream_size'  => 0,
				//];
				return $params;
			} );
		} else { //if we don't have cloud data we have to disable everything to avoid errors
			//turn of enabled flag
			if ( infinite_uploads_enabled() ) {
				$this->toggle_cloud( false );
			}
		}

		// don't register all this until we've enabled rewriting.
		if ( ! infinite_uploads_enabled() ) {
			add_action( 'admin_notices', [ $this, 'setup_notice' ] );
			add_action( 'network_admin_notices', [ $this, 'setup_notice' ] );

			return true;
		}

		$this->register_stream_wrapper();
		add_action( 'shutdown', [ $this, 'stream_wrapper_debug' ] );

		$uploads_url = $this->get_original_upload_dir(); //prime the cached value before filtering
		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );

		//block uploads if permissions are only read/delete
		if ( ! $api_data->site->upload_writeable ) {
			add_filter( 'pre-upload-ui', [ $this, 'blocked_uploads_header' ] );
			add_filter( 'wp_handle_upload_prefilter', [ $this, 'block_uploads' ] );
			add_filter( 'rest_pre_dispatch', [ $this, 'block_rest_upload' ], 10, 3 );
			add_filter( 'wp_save_image_editor_file', '__return_false' );
		}

		//block uploads if permissions are only read/delete
		if ( ! $api_data->site->cdn_enabled ) {
			add_filter( 'admin_notices', [ $this, 'cdn_disabled_header' ] );
			add_filter( 'network_admin_notices', [ $this, 'cdn_disabled_header' ] );
		}

		add_filter( 'wp_image_editors', [ $this, 'filter_editors' ], 9 );
		add_action( 'delete_attachment', [ $this, 'delete_attachment_files' ] );
		add_filter( 'wp_read_image_metadata', [ $this, 'wp_filter_read_image_metadata' ], 10, 2 );
		add_filter( 'wp_update_attachment_metadata', [ $this, 'update_attachment_metadata' ], 10, 2 );
		add_filter( 'wp_get_attachment_metadata', [ $this, 'get_attachment_metadata' ] );
		add_filter( 'wp_resource_hints', [ $this, 'wp_filter_resource_hints' ], 10, 2 );
		remove_filter( 'admin_notices', 'wpthumb_errors' );

		add_filter( 'pre_wp_unique_filename_file_list', [ $this, 'get_files_for_unique_filename_file_list' ], 10, 3 );

		// Add filters to "wrap" the wp_privacy_personal_data_export_file function call as we need to
		// switch out the personal_data directory to a local temp folder, and then upload after it's
		// complete, as Core tries to write directly to the ZipArchive which won't work with the
		// IU streamWrapper.
		add_action( 'wp_privacy_personal_data_export_file', [ $this, 'before_export_personal_data', 9 ] );
		add_action( 'wp_privacy_personal_data_export_file', [ $this, 'after_export_personal_data', 11 ] );
		add_action( 'wp_privacy_personal_data_export_file_created', [ $this, 'move_temp_personal_data_to_s3', 1000 ] );

		$this->plugin_compatibility();

		if ( ( ! defined( 'INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) && $api_data->site->cdn_enabled ) {
			//makes this work with pre 3.5 MU ms_files rewriting (ie domain.com/files/filename.jpg)
			$original_root_dirs = $this->get_original_upload_dir_root();
			$replacements       = [ $original_root_dirs['baseurl'] ];
			//if we have a custom domain add original cdn url for replacement
			if ( $this->get_s3_url() !== 'https://' . $api_data->site->cname ) {
				$replacements[] = 'https://' . $api_data->site->cname;
			}

			//makes this work with pre 3.5 MU ms_files rewriting (ie domain.com/files/filename.jpg)
			if ( is_multisite() && substr_compare( $original_root_dirs['baseurl'], '/files', - strlen( '/files' ) ) === 0 ) {
				$new_dirs = wp_get_upload_dir();
				$cdn_url  = str_replace( 'iu://' . untrailingslashit( $this->bucket ), $api_data->site->cname, $new_dirs['basedir'] );
			} else {
				$cdn_url = $this->get_s3_url();
			}
			new Infinite_Uploads_Rewriter( $original_root_dirs['baseurl'], $replacements, $cdn_url );
		}
	}

	/**
	 * Enable or disable cloud stream wrapper and url rewriting.
	 *
	 * @param bool $enabled
	 */
	public function toggle_cloud( $enabled ) {
		if ( is_multisite() ) {
			update_site_option( 'iup_enabled', $enabled );
		} else {
			update_option( 'iup_enabled', $enabled, true );
		}
		if ( $enabled ) {

			//ping the API to let them know we've enabled the site
			$this->api->call( "site/" . $this->api->get_site_id() . "/enable", [], 'POST', [
				'timeout'  => 0.01,
				'blocking' => false,
			] );

			//not ideal but such a dramatic change of replacing upload dirs and urls can break some plugins/themes
			wp_cache_flush();

			//Hummingbird plugin
			do_action( 'wphb_clear_page_cache' );

			//WP rocket plugin
			if ( function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
			}
		}
	}

	/**
	 * Register the stream wrapper for s3
	 */
	public function register_stream_wrapper() {
		/**
		 * INFINITE_UPLOADS_USE_LOCAL define. If true will use the local stream wrapper to write files to local directory instead of cloud.
		 *
		 * @constant {boolean} INFINITE_UPLOADS_USE_LOCAL
		 * @default false
		 */
		if ( defined( 'INFINITE_UPLOADS_USE_LOCAL' ) && INFINITE_UPLOADS_USE_LOCAL ) {
			stream_wrapper_register( 'iu', 'Infinite_Uploads_Local_Stream_Wrapper', STREAM_IS_URL );
		} else {
			Infinite_Uploads_Stream_Wrapper::register( $this->s3() );
			/**
			 * INFINITE_UPLOADS_OBJECT_ACL define. If set will override the object ACL for new objects stored in the cloud.
			 *
			 * @constant {string} INFINITE_UPLOADS_OBJECT_ACL
			 * @default `public-read`
			 */
			$objectAcl = defined( 'INFINITE_UPLOADS_OBJECT_ACL' ) ? INFINITE_UPLOADS_OBJECT_ACL : 'public-read';
			stream_context_set_option( stream_context_get_default(), 'iu', 'ACL', $objectAcl );

			stream_context_set_option( stream_context_get_default(), 'iu', 'iup_instance', $this );
		}

		stream_context_set_option( stream_context_get_default(), 'iu', 'seekable', true );
	}

	/**
	 * Writes total info to debug log if feature is defined.
	 */
	public function stream_wrapper_debug() {
		if ( $this->stream_api_call_count['total'] ) {
			error_log( sprintf( "[INFINITE_UPLOADS Stream Debug] Stream wrapper API calls in %ss: %s", timer_stop(), json_encode( $this->stream_api_call_count, JSON_PRETTY_PRINT ) ) );
		}
		if ( count( $this->stream_plugin_api_call_count ) ) {
			error_log( sprintf( "[INFINITE_UPLOADS Stream Debug] Stream wrapper API calls by plugin: %s", json_encode( $this->stream_plugin_api_call_count, JSON_PRETTY_PRINT ) ) );
		}
	}

	/**
	 * @return UglyRobot\Infinite_Uploads\Aws\S3\S3Client
	 */
	public function s3() {

		if ( ! empty( $this->s3 ) ) {
			return $this->s3;
		}

		$params = [ 'version' => 'latest' ];

		if ( $this->key && $this->secret ) {
			$params['credentials']['key']    = $this->key;
			$params['credentials']['secret'] = $this->secret;
		}

		if ( $this->region ) {
			$params['signature'] = 'v4';
			$params['region']    = $this->region;
		}

		if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
			$proxy_auth    = '';
			$proxy_address = WP_PROXY_HOST . ':' . WP_PROXY_PORT;

			if ( defined( 'WP_PROXY_USERNAME' ) && defined( 'WP_PROXY_PASSWORD' ) ) {
				$proxy_auth = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@';
			}

			$params['request.options']['proxy'] = $proxy_auth . $proxy_address;
		}

		/**
		 * Filter the parameters passed when creating the Aws\S3\S3Client via the AWS PHP SDK.
		 * See; https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html
		 *
		 * @param  {array} $params S3Client::_construct() parameters.
		 *
		 * @return {array} $params S3Client::_construct() parameters.
		 * @since  1.0
		 * @hook   infinite_uploads_s3_client_params
		 *
		 */
		$params   = apply_filters( 'infinite_uploads_s3_client_params', $params );
		$this->s3 = new S3Client( $params );

		return $this->s3;
	}

	/*
	 *
	 */
	public function get_original_upload_dir() {
		if ( empty( $this->original_upload_dir ) ) {
			$this->original_upload_dir = wp_get_upload_dir();
		}

		return $this->original_upload_dir;
	}

	/**
	 * Get root upload dir for multisite. Based on _wp_upload_dir().
	 *
	 * @return array See wp_upload_dir()
	 */
	public function get_original_upload_dir_root() {
		$siteurl     = get_option( 'siteurl' );
		$upload_path = trim( get_option( 'upload_path' ) );

		if ( empty( $upload_path ) || 'wp-content/uploads' === $upload_path ) {
			$dir = WP_CONTENT_DIR . '/uploads';
		} elseif ( 0 !== strpos( $upload_path, ABSPATH ) ) {
			// $dir is absolute, $upload_path is (maybe) relative to ABSPATH.
			$dir = path_join( ABSPATH, $upload_path );
		} else {
			$dir = $upload_path;
		}

		$url = get_option( 'upload_url_path' );
		if ( ! $url ) {
			if ( empty( $upload_path ) || ( 'wp-content/uploads' === $upload_path ) || ( $upload_path == $dir ) ) {
				$url = WP_CONTENT_URL . '/uploads';
			} else {
				$url = trailingslashit( $siteurl ) . $upload_path;
			}
		}

		/*
		 * Honor the value of UPLOADS. This happens as long as ms-files rewriting is disabled.
		 * We also sometimes obey UPLOADS when rewriting is enabled -- see the next block.
		 */
		if ( defined( 'UPLOADS' ) && ! ( is_multisite() && get_site_option( 'ms_files_rewriting' ) ) ) {
			$dir = ABSPATH . UPLOADS;
			$url = trailingslashit( $siteurl ) . UPLOADS;
		}

		// If multisite (and if not the main site in a post-MU network).
		if ( is_multisite() && ! ( is_main_network() && is_main_site() && defined( 'MULTISITE' ) ) ) {

			if ( get_site_option( 'ms_files_rewriting' ) && defined( 'UPLOADS' ) && ! ms_is_switched() ) {
				/*
				 * Handle the old-form ms-files.php rewriting if the network still has that enabled.
				 * When ms-files rewriting is enabled, then we only listen to UPLOADS when:
				 * 1) We are not on the main site in a post-MU network, as wp-content/uploads is used
				 *    there, and
				 * 2) We are not switched, as ms_upload_constants() hardcodes these constants to reflect
				 *    the original blog ID.
				 *
				 * Rather than UPLOADS, we actually use BLOGUPLOADDIR if it is set, as it is absolute.
				 * (And it will be set, see ms_upload_constants().) Otherwise, UPLOADS can be used, as
				 * as it is relative to ABSPATH. For the final piece: when UPLOADS is used with ms-files
				 * rewriting in multisite, the resulting URL is /files. (#WP22702 for background.)
				 */

				$dir = ABSPATH . untrailingslashit( UPLOADBLOGSDIR );
				$url = trailingslashit( $siteurl ) . 'files';
			}
		}

		$basedir = $dir;
		$baseurl = $url;

		return array(
			'basedir' => $basedir,
			'baseurl' => $baseurl,
		);
	}

	public function setup_notice() {
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}

		if ( get_current_screen()->id == 'media_page_infinite_uploads' || get_current_screen()->id == 'settings_page_infinite_uploads-network' ) {
			return;
		}
		?>
		<div class="notice notice-info" style="white-space: nowrap;padding: 10px 15px 10px 10px;">
			<span style="display: inline-block;vertical-align: middle;white-space: normal;width: 80%;font-size: 15px;">
				<strong><?php esc_html_e( 'Infinite Uploads is almost ready!', 'infinite-uploads' ); ?></strong>
				<?php
				if ( $this->api->has_token() ) {
					esc_html_e( 'Finish syncing your images, audio, video, and documents to the cloud to enable.', 'infinite-uploads' );
				} else {
					esc_html_e( 'Create or connect your account to move your images, audio, video, and documents to the cloud - with a click!', 'infinite-uploads' );
				}
				?>
			</span>
			<span style="display: inline-block;vertical-align: middle;width: 20%;text-align: right;">
				<a class="button button-primary" href="<?php echo esc_url( $this->admin->settings_url() ); ?>" style="font-size: 15px;"><?php echo $this->api->has_token() ? esc_html__( 'Finish Sync', 'infinite-uploads' ) : esc_html__( 'Connect', 'infinite-uploads' ); ?></a>
			</span>
		</div>
		<?php
	}

	/**
	 * Tear down the hooks, url filtering etc for Infinite Uploads
	 */
	public function tear_down() {

		stream_wrapper_unregister( 'iu' );
		remove_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
		remove_filter( 'wp_image_editors', [ $this, 'filter_editors' ], 9 );
	}

	public function get_sync_stats() {
		global $wpdb;

		$total     = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE 1" );
		$local     = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE deleted = 0" );
		$synced    = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1" );
		$deletable = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 0" );
		$deleted   = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 1" );

		$progress = (array) get_site_option( 'iup_files_scanned' );

		return array_merge( $progress, [
			'is_data'         => (bool) $total->files,
			'total_files'     => number_format_i18n( (int) $total->files ),
			'total_size'      => size_format( (int) $total->size, 2 ),
			'local_files'     => number_format_i18n( (int) $local->files ),
			'local_size'      => size_format( (int) $local->size, 2 ),
			'cloud_files'     => number_format_i18n( (int) $synced->files ),
			'cloud_size'      => size_format( (int) $synced->size, 2 ),
			'deletable_files' => number_format_i18n( (int) $deletable->files ),
			'deletable_size'  => size_format( (int) $deletable->size, 2 ),
			'deleted_files'   => number_format_i18n( (int) $deleted->files ),
			'deleted_size'    => size_format( (int) $deleted->size, 2 ),
			'remaining_files' => number_format_i18n( max( $total->files - $synced->files, 0 ) ),
			'remaining_size'  => size_format( max( $total->size - $total->transferred, 0 ), 2 ),
			'pcnt_complete'   => ( $local->size ? min( 100, round( ( $total->transferred / $total->size ) * 100, 2 ) ) : 0 ),
			'pcnt_downloaded' => ( $synced->size ? min( 100, round( 100 - ( ( $deleted->size / $synced->size ) * 100 ), 2 ) ) : 0 ),
		] );
	}

	public function get_filetypes( $is_chart = false, $cloud_types = false ) {
		global $wpdb;

		if ( false !== $cloud_types ) {
			if ( empty( $cloud_types ) ) { //estimate if sync was fresh
				$types = $wpdb->get_results( "SELECT type, count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 GROUP BY type ORDER BY size DESC" );
			} else {
				$types = $cloud_types;
			}
		} else {
			$types = $wpdb->get_results( "SELECT type, count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE deleted = 0 GROUP BY type ORDER BY size DESC" );
		}

		$data = [];
		foreach ( $types as $type ) {
			$data[ $type->type ] = (object) [
				'color' => $this->get_file_type_format( $type->type, 'color' ),
				'label' => $this->get_file_type_format( $type->type, 'label' ),
				'size'  => $type->size,
				'files' => $type->files,
			];
		}

		$chart = [];
		if ( $is_chart ) {
			foreach ( $data as $item ) {
				$chart['datasets'][0]['data'][]            = $item->size;
				$chart['datasets'][0]['backgroundColor'][] = $item->color;
				$chart['labels'][]                         = $item->label . ": " . sprintf( _n( '%s file totalling %s', '%s files totalling %s', $item->files, 'infinite-uploads' ), number_format_i18n( $item->files ), size_format( $item->size, 1 ) );
			}

			$total_size     = array_sum( wp_list_pluck( $data, 'size' ) );
			$total_files    = array_sum( wp_list_pluck( $data, 'files' ) );
			$chart['total'] = sprintf( _n( '%s / %s File', '%s / %s Files', $total_files, 'infinite-uploads' ), size_format( $total_size, 2 ), number_format_i18n( $total_files ) );

			return $chart;
		}

		return $data;
	}

	public function get_file_type_format( $type, $key ) {
		$labels = [
			'image'    => [ 'color' => '#26A9E0', 'label' => esc_html__( 'Images', 'infinite-uploads' ) ],
			'audio'    => [ 'color' => '#00A167', 'label' => esc_html__( 'Audio', 'infinite-uploads' ) ],
			'video'    => [ 'color' => '#C035E2', 'label' => esc_html__( 'Video', 'infinite-uploads' ) ],
			'document' => [ 'color' => '#EE7C1E', 'label' => esc_html__( 'Documents', 'infinite-uploads' ) ],
			'archive'  => [ 'color' => '#EC008C', 'label' => esc_html__( 'Archives', 'infinite-uploads' ) ],
			'code'     => [ 'color' => '#EFED27', 'label' => esc_html__( 'Code', 'infinite-uploads' ) ],
			'other'    => [ 'color' => '#F1F1F1', 'label' => esc_html__( 'Other', 'infinite-uploads' ) ],
		];

		if ( isset( $labels[ $type ] ) ) {
			return $labels[ $type ][ $key ];
		} else {
			return $labels['other'][ $key ];
		}
	}

	public function get_file_type( $filename ) {
		$extensions = [
			'image'    => [ 'jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp', 'tif', 'tiff', 'ico', 'svg', 'svgz', 'webp' ],
			'audio'    => [ 'aac', 'ac3', 'aif', 'aiff', 'flac', 'm3a', 'm4a', 'm4b', 'mka', 'mp1', 'mp2', 'mp3', 'ogg', 'oga', 'ram', 'wav', 'wma' ],
			'video'    => [ '3g2', '3gp', '3gpp', 'asf', 'avi', 'divx', 'dv', 'flv', 'm4v', 'mkv', 'mov', 'mp4', 'mpeg', 'mpg', 'mpv', 'ogm', 'ogv', 'qt', 'rm', 'vob', 'wmv', 'webm' ],
			'document' => [
				'log',
				'asc',
				'csv',
				'tsv',
				'txt',
				'doc',
				'docx',
				'docm',
				'dotm',
				'odt',
				'pages',
				'pdf',
				'xps',
				'oxps',
				'rtf',
				'wp',
				'wpd',
				'psd',
				'xcf',
				'swf',
				'key',
				'ppt',
				'pptx',
				'pptm',
				'pps',
				'ppsx',
				'ppsm',
				'sldx',
				'sldm',
				'odp',
				'numbers',
				'ods',
				'xls',
				'xlsx',
				'xlsm',
				'xlsb',
			],
			'archive'  => [ 'bz2', 'cab', 'dmg', 'gz', 'rar', 'sea', 'sit', 'sqx', 'tar', 'tgz', 'zip', '7z', 'data', 'bin', 'bak' ],
			'code'     => [ 'css', 'htm', 'html', 'php', 'js', 'md' ],
		];

		$ext = preg_replace( '/^.+?\.([^.]+)$/', '$1', $filename );
		if ( ! empty( $ext ) ) {
			$ext = strtolower( $ext );
			foreach ( $extensions as $type => $exts ) {
				if ( in_array( $ext, $exts, true ) ) {
					return $type;
				}
			}
		}

		return 'other';
	}

	/**
	 * Override the files used for wp_unique_filename() comparisons
	 *
	 * @param array|null $files
	 * @param string     $dir
	 *
	 * @return array
	 */
	public function get_files_for_unique_filename_file_list( $files, $dir, $filename ) {
		$name = pathinfo( $filename, PATHINFO_FILENAME );
		// The iu:// streamwrapper support listing by partial prefixes with wildcards.
		// For example, scandir( iu://bucket/2019/06/my-image* )
		return scandir( trailingslashit( $dir ) . $name . '*' );
	}

	public function filter_upload_dir( $dirs ) {
		$root_dirs = $this->get_original_upload_dir_root();

		$dirs['path']    = str_replace( $root_dirs['basedir'], 'iu://' . untrailingslashit( $this->bucket ), $dirs['path'] );
		$dirs['basedir'] = str_replace( $root_dirs['basedir'], 'iu://' . untrailingslashit( $this->bucket ), $dirs['basedir'] );

		if ( ! defined( 'INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) {

			if ( defined( 'INFINITE_UPLOADS_USE_LOCAL' ) && INFINITE_UPLOADS_USE_LOCAL ) {
				$dirs['url']     = str_replace( 'iu://' . untrailingslashit( $this->bucket ), $dirs['baseurl'] . '/iu/' . $this->bucket, $dirs['path'] );
				$dirs['baseurl'] = str_replace( 'iu://' . untrailingslashit( $this->bucket ), $dirs['baseurl'] . '/iu/' . $this->bucket, $dirs['basedir'] );
			} else {
				$dirs['url']     = str_replace( 'iu://' . untrailingslashit( $this->bucket ), $this->get_s3_url(), $dirs['path'] );
				$dirs['baseurl'] = str_replace( 'iu://' . untrailingslashit( $this->bucket ), $this->get_s3_url(), $dirs['basedir'] );
			}
		}

		return $dirs;
	}

	public function get_s3_url() {
		if ( $this->bucket_url ) {
			return 'https://' . $this->bucket_url;
		}

		$bucket = strtok( $this->bucket, '/' );
		$path   = substr( $this->bucket, strlen( $bucket ) );

		return apply_filters( 'infinite_uploads_bucket_url', 'https://' . $bucket . '.s3.amazonaws.com' . $path );
	}

	/**
	 * Delete all attachment files from S3 when an attachment is deleted.
	 *
	 * WordPress Core's handling of deleting files for attachments via
	 * wp_delete_attachment_files is not compatible with remote streams, as
	 * it makes many assumptions about local file paths. The hooks also do
	 * not exist to be able to modify their behavior. As such, we just clean
	 * up the s3 files when an attachment is removed, and leave WordPress to try
	 * a failed attempt at mangling the iu:// urls.
	 *
	 * UPDATE deletes seem to get issued properly now, only use this for purging from CDN.
	 *
	 * @param $post_id
	 */
	public function delete_attachment_files( $post_id ) {
		$meta = wp_get_attachment_metadata( $post_id );
		$file = get_attached_file( $post_id );

		$to_purge = [];
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $sizeinfo ) {
				$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
				//wp_delete_file( $intermediate_file );
				$to_purge[] = $intermediate_file;
			}
		}

		wp_delete_file( $file );
		$to_purge[] = $file;

		$dirs = wp_get_upload_dir();
		foreach ( $to_purge as $key => $file ) {
			$to_purge[ $key ] = str_replace( $dirs['basedir'], $dirs['baseurl'], $file );
		}

		//purge these from CDN cache
		$this->api->purge( $to_purge );
	}

	/**
	 * Get the S3 bucket name
	 *
	 * @return string
	 */
	public function get_s3_bucket() {
		return $bucket = strtok( $this->bucket, '/' );
	}

	/**
	 * Get the S3 bucket name
	 *
	 * @return string
	 */
	public function get_s3_prefix() {
		return untrailingslashit( str_replace( $this->get_s3_bucket() . '/', '', $this->bucket ) );
	}

	/**
	 * Ge the S3 bucket region
	 *
	 * @return string
	 */
	public function get_s3_bucket_region() {
		return $this->region;
	}

	/**
	 * Show error on uploads screen when readonly.
	 */
	public function blocked_uploads_header() {
		if ( current_user_can( $this->capability ) ) {
			?>
			<div class="notice notice-error">
			<p><?php printf( __( "Files can't be uploaded due to a billing issue with your Infinite Uploads account. <a href='%s'>Please resolve the issue</a> to resume uploading.", 'infinite-uploads' ), esc_url( $this->admin->api_url( '/account/billing/' ) ) ); ?></p></div><?php
		} else {
			?>
			<div class="notice notice-error"><p><?php esc_html_e( "Files can't be uploaded due to a billing issue with your Infinite Uploads account.", 'infinite-uploads' ); ?></p></div><?php
		}
	}

	/**
	 * Show error on all screens.
	 */
	public function cdn_disabled_header() {
		if ( current_user_can( $this->capability ) ) {

			if ( get_current_screen()->id == 'media_page_infinite_uploads'
			     || get_current_screen()->id == 'settings_page_infinite_uploads-network'
			     || ( get_current_screen()->id == 'media' && get_current_screen()->action == 'add' ) ) {
				return;
			}
			?>
			<div class="notice notice-error">
			<p><?php printf( __( "Files can't be uploaded and your CDN is disabled due to a billing issue with your Infinite Uploads account. <a href='%s'>Please resolve the issue</a> to resume uploading. <a href='%s'>Already fixed?</a>", 'infinite-uploads' ), esc_url( $this->admin->api_url( '/account/billing/' ) ), esc_url( $this->admin->settings_url( [ 'refresh' => 1 ] ) ) ); ?></p>
			</div><?php
		}
	}

	/**
	 * Return an error to display before trying to save newly uploaded media.
	 *
	 * @param $file
	 *
	 * @return array
	 */
	public function block_uploads( $file ) {
		$file['error'] = esc_html__( "Files can't be uploaded due to a billing issue with your Infinite Uploads account.", 'infinite-uploads' );

		return $file;
	}

	/**
	 * Block editing media in Gutenberg WP 5.5+ block.
	 *
	 * @param                 $result null
	 * @param WP_REST_Server  $server
	 * @param WP_REST_Request $request
	 *
	 * @return mixed|WP_Error
	 */
	function block_rest_upload( $result, $server, $request ) {
		//if route matches media edit return error
		if ( preg_match( '%/wp/v2/media/\d+/edit%', $request->get_route() ) ) {
			$result = new WP_Error(
				'rest_cant_upload',
				__( "Files can't be uploaded due to a billing issue with your Infinite Uploads account.", 'infinite-uploads' ),
				[ 'status' => 403 ]
			);
		}

		return $result;
	}

	public function filter_editors( $editors ) {

		if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, 'Infinite_Uploads_Image_Editor_Imagick' );

		return $editors;
	}

	/**
	 * Filters wp_read_image_metadata. exif_read_data() doesn't work on
	 * file streams so we need to make a temporary local copy to extract
	 * exif data from.
	 *
	 * @param array  $meta
	 * @param string $file
	 *
	 * @return array|bool
	 */
	public function wp_filter_read_image_metadata( $meta, $file ) {
		remove_filter( 'wp_read_image_metadata', [ $this, 'wp_filter_read_image_metadata' ], 10 );
		$temp_file = $this->copy_image_from_s3( $file );
		$meta      = wp_read_image_metadata( $temp_file );
		add_filter( 'wp_read_image_metadata', [ $this, 'wp_filter_read_image_metadata' ], 10, 2 );
		unlink( $temp_file );

		return $meta;
	}

	/**
	 * Get a local copy of the file.
	 *
	 * @param string $file
	 *
	 * @return string
	 */
	public function copy_image_from_s3( $file ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}
		$temp_filename = wp_tempnam( $file );
		copy( $file, $temp_filename );

		return $temp_filename;
	}

	/**
	 * Filters the attachment meta data. wp_prepare_attachment_for_js triggers a HeadObject to get filesize, usually uncached
	 * on media grid and sometimes on frontend with some things, increasing TTFB a lot. Instead cache it when attachment is updated or created.
	 *
	 * @param array $data          Array of updated attachment meta data.
	 * @param int   $attachment_id Attachment post ID.
	 *
	 * @return array
	 */
	function update_attachment_metadata( $data, $attachment_id ) {
		$attached_file = get_attached_file( $attachment_id );
		if ( file_exists( $attached_file ) ) {
			$data['filesize'] = filesize( $attached_file );
		}

		return $data;
	}

	/**
	 * Filters the attachment meta data. wp_prepare_attachment_for_js triggers a HeadObject to get filesize, usually uncached
	 * on media grid and sometimes on frontend with some things, increasing TTFB a lot.
	 *
	 * @param array $data Array of meta data for the given attachment.
	 *
	 * @return array
	 */
	function get_attachment_metadata( $data ) {
		if ( ! isset( $data['filesize'] ) ) {
			$data['filesize'] = '';
		}

		return $data;
	}

	/**
	 * Add the DNS address for the S3 Bucket to list for DNS prefetch.
	 *
	 * @param $hints
	 * @param $relation_type
	 *
	 * @return array
	 */
	function wp_filter_resource_hints( $hints, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = $this->get_s3_url();
		}

		return $hints;
	}

	/**
	 * Setup the filters for wp_privacy_exports_dir to use a temp folder location.
	 */
	function before_export_personal_data() {
		add_filter( 'wp_privacy_exports_dir', [ $this, 'set_wp_privacy_exports_dir' ] );
	}

	/**
	 * Remove the filters for wp_privacy_exports_dir as we only want it added in some cases.
	 */
	function after_export_personal_data() {
		remove_filter( 'wp_privacy_exports_dir', [ $this, 'set_wp_privacy_exports_dir' ] );
	}

	/**
	 * Override the wp_privacy_exports_dir location
	 *
	 * We don't want to use the default uploads folder location, as with Infinite Uploads this is
	 * going to the a iu:// custom URL handler, which is going to fail with the use of ZipArchive.
	 * Instgead we set to to sys_get_temp_dir and move the fail in the wp_privacy_personal_data_export_file_created
	 * hook.
	 *
	 * @param string $dir
	 *
	 * @return string
	 */
	function set_wp_privacy_exports_dir( $dir ) {
		if ( strpos( $dir, 'iu://' ) !== 0 ) {
			return $dir;
		}
		$dir = sys_get_temp_dir() . '/wp_privacy_exports_dir/';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir );
			file_put_contents( $dir . 'index.html', '' );
		}

		return $dir;
	}

	/**
	 * Move the tmp personal data file to the true uploads location
	 *
	 * Once a personal data file has been written, move it from the overriden "temp"
	 * location to the S3 location where it should have been stored all along, and where
	 * the "natural" Core URL is going to be pointing to.
	 */
	function move_temp_personal_data_to_s3( $archive_pathname ) {
		if ( strpos( $archive_pathname, sys_get_temp_dir() ) !== 0 ) {
			return;
		}
		$upload_dir  = wp_upload_dir();
		$exports_dir = trailingslashit( $upload_dir['basedir'] ) . 'wp-personal-data-exports/';
		$destination = $exports_dir . pathinfo( $archive_pathname, PATHINFO_FILENAME ) . '.' . pathinfo( $archive_pathname, PATHINFO_EXTENSION );
		copy( $archive_pathname, $destination );
		unlink( $archive_pathname );
	}

	/**
	 * Handle compatibility for various third party plugins
	 */
	function plugin_compatibility() {
		//WPCF7 form file uploads
		if ( ! defined( 'WPCF7_UPLOADS_TMP_DIR' ) ) {
			define( 'WPCF7_UPLOADS_TMP_DIR', WP_CONTENT_DIR . '/wpcf7_uploads' );
		}

		//WP Migrate DB
		add_filter( 'wpmdb_upload_info', array( $this, 'wpmdb_upload_info' ) );

		//Handle WooCommerce CSV imports
		add_filter( 'woocommerce_product_csv_importer_check_import_file_path', '__return_false' );
	}

	/**
	 * If using the "Export" or "Backup" features in WP Migrate DB Pro we will need to write files to the local filesystem.
	 * Defines a custom folder to write to.
	 */
	function wpmdb_upload_info() {
		return array(
			'path' => WP_CONTENT_DIR . '/wp-migrate-db', // note missing end trailing slash
			'url'  => WP_CONTENT_URL . '/wp-migrate-db' // note missing end trailing slash
		);
	}
}
