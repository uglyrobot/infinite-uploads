<?php

class Infinite_Uploads {

	private static $instance;
	public $original_upload_dir;
	public $original_file;
	private $bucket;
	private $bucket_url;
	private $key;
	private $secret;

	public function __construct( $bucket, $key, $secret, $bucket_url = null, $region = null ) {

		$this->bucket     = $bucket;
		$this->key        = $key;
		$this->secret     = $secret;
		$this->bucket_url = $bucket_url;
		$this->region     = $region;
	}

	/**
	 *
	 * @return Infinite_Uploads
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_Uploads(
				INFINITE_UPLOADS_BUCKET,
				defined( 'INFINITE_UPLOADS_KEY' ) ? INFINITE_UPLOADS_KEY : null,
				defined( 'INFINITE_UPLOADS_SECRET' ) ? INFINITE_UPLOADS_SECRET : null,
				defined( 'INFINITE_UPLOADS_BUCKET_URL' ) ? INFINITE_UPLOADS_BUCKET_URL : null,
				INFINITE_UPLOADS_REGION
			);
		}

		return self::$instance;
	}

	/**
	 * Setup the hooks, urls filtering etc for Infinite Uploads
	 */
	public function setup() {

		Infinite_Uploads_Admin::get_instance();

		// don't register all this until we've enabled rewriting.
		if ( ! infinite_uploads_enabled() ) {
			return;
		}

		$this->register_stream_wrapper();

		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
		add_filter( 'wp_image_editors', [ $this, 'filter_editors' ], 9 );
		add_action( 'delete_attachment', [ $this, 'delete_attachment_files' ] );
		add_filter( 'wp_read_image_metadata', [ $this, 'wp_filter_read_image_metadata' ], 10, 2 );
		add_filter( 'wp_resource_hints', [ $this, 'wp_filter_resource_hints' ], 10, 2 );
		remove_filter( 'admin_notices', 'wpthumb_errors' );

		add_action( 'wp_handle_sideload_prefilter', [ $this, 'filter_sideload_move_temp_file_to_s3' ] );

		add_filter( 'pre_wp_unique_filename_file_list', [ $this, 'get_files_for_unique_filename_file_list' ], 10, 3 );

		// Add filters to "wrap" the wp_privacy_personal_data_export_file function call as we need to
		// switch out the personal_data directory to a local temp folder, and then upload after it's
		// complete, as Core tries to write directly to the ZipArchive which won't work with the
		// IU streamWrapper.
		add_action( 'wp_privacy_personal_data_export_file', [ $this, 'before_export_personal_data', 9 ] );
		add_action( 'wp_privacy_personal_data_export_file', [ $this, 'after_export_personal_data', 11 ] );
		add_action( 'wp_privacy_personal_data_export_file_created', [ $this, 'move_temp_personal_data_to_s3', 1000 ] );

		if ( ! defined( 'INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) {
			new Infinite_Uploads_Rewriter( INFINITE_UPLOADS_BUCKET_URL );
		}
	}

	/**
	 * Register the stream wrapper for s3
	 */
	public function register_stream_wrapper() {
		if ( defined( 'INFINITE_UPLOADS_USE_LOCAL' ) && INFINITE_UPLOADS_USE_LOCAL ) {
			stream_wrapper_register( 'iu', 'Infinite_Uploads_Local_Stream_Wrapper', STREAM_IS_URL );
		} else {
			Infinite_Uploads_Stream_Wrapper::register( $this->s3() );
			$objectAcl = defined( 'INFINITE_UPLOADS_OBJECT_ACL' ) ? INFINITE_UPLOADS_OBJECT_ACL : 'public-read';
			stream_context_set_option( stream_context_get_default(), 'iu', 'ACL', $objectAcl );
		}

		stream_context_set_option( stream_context_get_default(), 'iu', 'seekable', true );
	}

	/*
	 *
	 */

	/**
	 * @return Aws\S3\S3Client
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

		$params   = apply_filters( 'infinite_uploads_s3_client_params', $params );
		$this->s3 = Aws\S3\S3Client::factory( $params );

		return $this->s3;
	}

	/**
	 * Tear down the hooks, url filtering etc for Infinite Uploads
	 */
	public function tear_down() {

		stream_wrapper_unregister( 'iu' );
		remove_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
		remove_filter( 'wp_image_editors', [ $this, 'filter_editors' ], 9 );
		remove_filter( 'wp_handle_sideload_prefilter', [ $this, 'filter_sideload_move_temp_file_to_s3' ] );
	}

	public function get_original_upload_dir() {
		if ( empty( $this->original_upload_dir ) ) {
			return wp_upload_dir();
		}

		return $this->original_upload_dir;
	}

	public function get_sync_stats() {
		global $wpdb;

		$total   = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE 1" );
		$local   = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE deleted = 0" );
		$synced  = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1" );
		$deleted = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 1" );

		$progress = (array) get_site_option( 'iup_files_scanned' );

		/*$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		foreach ( $progress as $key => $timestamp ) {
			$progress[ $key ] = $timestamp ? date_i18n( $date_format, $progress[ $key ] ) : $timestamp;
		}*/

		return array_merge( $progress, [
			'is_data'         => (bool) $total->files,
			'total_files'     => number_format_i18n( (int) $total->files ),
			'total_size'      => size_format( (int) $total->size, 2 ),
			'local_files'     => number_format_i18n( (int) $local->files ),
			'local_size'      => size_format( (int) $local->size, 2 ),
			'cloud_files'     => number_format_i18n( (int) $synced->files ),
			'cloud_size'      => size_format( (int) $synced->size, 2 ),
			'deleted_files'   => number_format_i18n( (int) $deleted->files ),
			'deleted_size'    => size_format( (int) $deleted->size, 2 ),
			'remaining_files' => number_format_i18n( max( $total->files - $synced->files, 0 ) ),
			'remaining_size'  => size_format( max( $total->size - $synced->size, 0 ), 2 ),
			'pcnt_complete'   => ( $local->files ? round( ( $synced->files / $total->files ) * 100, 2 ) : 0 ),
		] );
	}

	public function get_local_filetypes( $is_chart = false ) {
		global $wpdb;

		$types  = $wpdb->get_results( "SELECT type, count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE deleted = 0 GROUP BY type ORDER BY size DESC" );
		$labels = [
			'image'    => [ 'color' => '#26A9E0', 'label' => __( 'Images', 'iup' ) ],
			'audio'    => [ 'color' => '#00A167', 'label' => __( 'Audio', 'iup' ) ],
			'video'    => [ 'color' => '#C035E2', 'label' => __( 'Video', 'iup' ) ],
			'document' => [ 'color' => '#EE7C1E', 'label' => __( 'Documents', 'iup' ) ],
			'archive'  => [ 'color' => '#EC008C', 'label' => __( 'Archives', 'iup' ) ],
			'code'     => [ 'color' => '#EFED27', 'label' => __( 'Code', 'iup' ) ],
			'other'    => [ 'color' => '#F1F1F1', 'label' => __( 'Other', 'iup' ) ],
		];

		$data = [];
		foreach ( $types as $type ) {
			if ( isset( $labels[ $type->type ] ) ) {
				$data[ $type->type ] = [
					'color' => $labels[ $type->type ]['color'],
					'label' => $labels[ $type->type ]['label'],
					'size'  => $type->size,
					'files' => $type->files,
				];
			}
		}

		$chart = [];
		if ( $is_chart ) {
			foreach ( $data as $item ) {
				$chart['datasets'][0]['data'][]            = $item['size'];
				$chart['datasets'][0]['backgroundColor'][] = $item['color'];
				$chart['labels'][]                         = $item['label'] . ": " . sprintf( _n( '%s file totalling %s', '%s files totalling %s', $item['files'], 'iup' ), number_format_i18n( $item['files'] ), size_format( $item['size'], 1 ) );
			}

			$total_size     = array_sum( wp_list_pluck( $data, 'size' ) );
			$total_files    = array_sum( wp_list_pluck( $data, 'files' ) );
			$chart['total'] = sprintf( _n( '%s / %s File', '%s / %s Files', $total_files, 'iup' ), size_format( $total_size, 2 ), number_format_i18n( $total_files ) );

			return $chart;
		}

		return $data;
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

			return 'other';
		}
	}

	/**
	 * Override the files used for wp_unique_filename() comparisons
	 *
	 * @param array|null $files
	 * @param string     $dir
	 *
	 * @return array
	 */
	public function get_files_for_unique_filename_file_list( ?array $files, string $dir, string $filename ): array {
		$name = pathinfo( $filename, PATHINFO_FILENAME );
		// The iu:// streamwrapper support listing by partial prefixes with wildcards.
		// For example, scandir( iu://bucket/2019/06/my-image* )
		return scandir( trailingslashit( $dir ) . $name . '*' );
	}

	public function filter_upload_dir( $dirs ) {

		$this->original_upload_dir = $dirs;

		$dirs['path']    = str_replace( $dirs['basedir'], 'iu://' . $this->bucket, $dirs['path'] );
		$dirs['basedir'] = str_replace( $dirs['basedir'], 'iu://' . $this->bucket, $dirs['basedir'] );

		if ( ! defined( 'INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) {

			if ( defined( 'INFINITE_UPLOADS_USE_LOCAL' ) && INFINITE_UPLOADS_USE_LOCAL ) {
				$dirs['url']     = str_replace( 'iu://' . $this->bucket, $dirs['baseurl'] . '/iu/' . $this->bucket, $dirs['path'] );
				$dirs['baseurl'] = str_replace( 'iu://' . $this->bucket, $dirs['baseurl'] . '/iu/' . $this->bucket, $dirs['basedir'] );

			} else {
				$dirs['url']     = str_replace( 'iu://' . $this->bucket, $this->get_s3_url(), $dirs['path'] );
				$dirs['baseurl'] = str_replace( 'iu://' . $this->bucket, $this->get_s3_url(), $dirs['basedir'] );
			}
		}

		return $dirs;
	}

	public function get_s3_url() {
		if ( $this->bucket_url ) {
			return $this->bucket_url;
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
	 * @param $post_id
	 */
	public function delete_attachment_files( $post_id ) {
		$meta = wp_get_attachment_metadata( $post_id );
		$file = get_attached_file( $post_id );

		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $sizeinfo ) {
				$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
				wp_delete_file( $intermediate_file );
			}
		}

		wp_delete_file( $file );
	}

	/**
	 * Get the S3 bucket name
	 *
	 * @return string
	 */
	public function get_s3_bucket() {
		return $bucket = strtok( $this->bucket, '/' );
	}

	public function get_s3_bucket_region() {
		return $this->region;
	}

	public function filter_editors( $editors ) {

		if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, 'Infinite_Uploads_Image_Editor_Imagick' );

		return $editors;
	}

	/**
	 * Copy the file from /tmp to an s3 dir so handle_sideload doesn't fail due to
	 * trying to do a rename() on the file cross streams. This is somewhat of a hack
	 * to work around the core issue https://core.trac.wordpress.org/ticket/29257
	 *
	 * @param array File array
	 *
	 * @return array
	 */
	public function filter_sideload_move_temp_file_to_s3( array $file ) {
		$upload_dir = wp_upload_dir();
		$new_path   = $upload_dir['basedir'] . '/tmp/' . basename( $file['tmp_name'] );

		copy( $file['tmp_name'], $new_path );
		unlink( $file['tmp_name'] );
		$file['tmp_name'] = $new_path;

		return $file;
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
}
