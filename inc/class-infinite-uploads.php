<?php
use Aws\S3\Transfer;

class Infinite_uploads {

	private static $instance;
	private $bucket;
	private $bucket_url;
	private $key;
	private $secret;
	public $ajax_timelimit = 20;

	public $original_upload_dir;
	public $original_file;

	/**
	 *
	 * @return Infinite_uploads
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_uploads(
				INFINITE_UPLOADS_BUCKET,
				defined( 'INFINITE_UPLOADS_KEY' ) ? INFINITE_UPLOADS_KEY : null,
				defined( 'INFINITE_UPLOADS_SECRET' ) ? INFINITE_UPLOADS_SECRET : null,
				defined( 'INFINITE_UPLOADS_BUCKET_URL' ) ? INFINITE_UPLOADS_BUCKET_URL : null,
				INFINITE_UPLOADS_REGION
			);
		}

		return self::$instance;
	}

	public function __construct( $bucket, $key, $secret, $bucket_url = null, $region = null ) {

		$this->bucket     = $bucket;
		$this->key        = $key;
		$this->secret     = $secret;
		$this->bucket_url = $bucket_url;
		$this->region     = $region;
	}

	/**
	 * Setup the hooks, urls filtering etc for Infinite Uploads
	 */
	public function setup() {
		global $wpdb;
		$this->register_stream_wrapper();

		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		add_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		add_action( 'delete_attachment', array( $this, 'delete_attachment_files' ) );
		add_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10, 2 );
		add_filter( 'wp_resource_hints', array( $this, 'wp_filter_resource_hints' ), 10, 2 );
		remove_filter( 'admin_notices', 'wpthumb_errors' );

		add_action( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_s3' ) );

		Infinite_Uploads_admin::get_instance();

		add_action( 'wp_ajax_infinite-uploads-filelist', array( &$this, 'ajax_filelist' ) );
		add_action( 'wp_ajax_infinite-uploads-prep-sync', array( &$this, 'ajax_prep_sync' ) );
		add_action( 'wp_ajax_infinite-uploads-sync', array( &$this, 'ajax_sync' ) );

		// Install the needed DB table if not already.
		$installed = get_site_option( 'iup_installed' );
		if ( INFINITE_UPLOADS_VERSION != $installed ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$wpdb->base_prefix}infinite_uploads_files (
	            `file` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
	            `size` BIGINT UNSIGNED NOT NULL DEFAULT '0',
	            `modified` INT UNSIGNED NOT NULL,
	            `synced` BOOLEAN NOT NULL DEFAULT '0',
	            PRIMARY KEY (`file`(255)),
	            INDEX (`synced`)
	        ) {$charset_collate};";

			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			dbDelta( $sql );

			update_site_option( 'iup_installed', INFINITE_UPLOADS_VERSION );
		}
	}

	/**
	 * Tear down the hooks, url filtering etc for Infinite Uploads
	 */
	public function tear_down() {

		stream_wrapper_unregister( 'iu' );
		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		remove_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		remove_filter( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_s3' ) );
	}

	/*
	 *
	 */
	public function ajax_filelist() {

		// check caps
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_send_json_error();
		}

		$path = $this->get_original_upload_dir();
		$path = $path['basedir'];

		if ( isset( $_POST['remaining_dirs'] ) && is_array( $_POST['remaining_dirs'] ) ) {
			$remaining_dirs = $_POST['remaining_dirs'];
		} else {
			$remaining_dirs = [];
		}

		$filelist = new Infinite_Uploads_Filelist( $path, $this->ajax_timelimit, $remaining_dirs );
		$filelist->start();
		$this_file_count = count( $filelist->file_list );
		$remaining_dirs  = $filelist->paths_left;
		$is_done         = $filelist->is_done;

		$data  = compact( 'this_file_count', 'is_done', 'remaining_dirs' );
		$stats = $this->get_sync_stats();
		if ( $stats ) {
			$data = array_merge( $data, $stats );
		}

		wp_send_json_success( $data );
	}

	public function ajax_prep_sync() {
		global $wpdb;

		// check caps
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_send_json_error();
		}

		$s3 = $this->s3();

		$prefix = '';

		if ( strpos( INFINITE_UPLOADS_BUCKET, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( INFINITE_UPLOADS_BUCKET, '/' ) . '/', '', INFINITE_UPLOADS_BUCKET ) );
		}

		$args = array(
			'Bucket' => strtok( INFINITE_UPLOADS_BUCKET, '/' ),
			'Prefix' => $prefix,
		);

		if ( ! empty( $_POST['next_token'] ) ) {
			$args['ContinuationToken'] = $_POST['next_token'];
		}

		try {
			$results    = $s3->getPaginator( 'ListObjectsV2', $args );
			$req_count  = $file_count = 0;
			$is_done    = false;
			$next_token = null;
			foreach ( $results as $result ) {
				$req_count ++;
				$is_done    = ! $result['IsTruncated'];
				$next_token = isset( $result['NextContinuationToken'] ) ? $result['NextContinuationToken'] : null;
				foreach ( $result['Contents'] as $object ) {
					$file_count ++;
					$local_key = str_replace( untrailingslashit( $prefix ), '', $object['Key'] );
					$file      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}infinite_uploads_files WHERE file = %s AND synced = 0", $local_key ) );
					if ( $file && $file->size == $object['Size'] ) {
						$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", array( 'synced' => 1 ), array( 'file' => $local_key ) );
					}
				}

				if ( ( $timer = timer_stop() ) >= $this->ajax_timelimit ) {
					break;
				}
			}

			$data  = compact( 'file_count', 'req_count', 'is_done', 'next_token', 'timer' );
			$stats = $this->get_sync_stats();
			if ( $stats ) {
				$data = array_merge( $data, $stats );
			}

			wp_send_json_success( $data );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_sync() {
		global $wpdb;

		$uploaded = 0;
		$break    = false;
		$path     = $this->get_original_upload_dir();
		while ( ! $break ) {
			$to_sync = $wpdb->get_col( "SELECT file FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 LIMIT 10" );
			//build full paths
			$to_sync_full = [];
			foreach ( $to_sync as $key => $file ) {
				$to_sync_full[] = $path['basedir'] . $file;
			}

			$obj  = new ArrayObject( $to_sync_full );
			$from = $obj->getIterator();

			$s3            = $this->s3();
			$transfer_args = [
				'concurrency' => 5,
				'base_dir'    => $path['basedir'],
				'before'      => function ( AWS\Command $command ) {
					if ( in_array( $command->getName(), [ 'PutObject', 'CreateMultipartUpload' ], true ) ) {
						$acl            = defined( 'INFINITE_UPLOADS_OBJECT_ACL' ) ? INFINITE_UPLOADS_OBJECT_ACL : 'public-read';
						$command['ACL'] = $acl;
						/// Expires:
						if ( defined( 'INFINITE_UPLOADS_HTTP_EXPIRES' ) ) {
							$command['Expires'] = INFINITE_UPLOADS_HTTP_EXPIRES;
						}
						// Cache-Control:
						if ( defined( 'INFINITE_UPLOADS_HTTP_CACHE_CONTROL' ) ) {
							if ( is_numeric( INFINITE_UPLOADS_HTTP_CACHE_CONTROL ) ) {
								$command['CacheControl'] = 'max-age=' . INFINITE_UPLOADS_HTTP_CACHE_CONTROL;
							} else {
								$command['CacheControl'] = INFINITE_UPLOADS_HTTP_CACHE_CONTROL;
							}
						}
					}
				},
			];
			try {
				$manager = new Transfer( $s3, $from, 's3://' . INFINITE_UPLOADS_BUCKET . '/', $transfer_args );
				$manager->transfer();
				$uploaded += 10;
				foreach ( $to_sync as $file ) {
					$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", array( 'synced' => 1 ), array( 'file' => $file ) );
				}
			} catch ( Exception $e ) {
				wp_send_json_error( $e->getMessage() );
			}

			if ( timer_stop() >= $this->ajax_timelimit ) {
				$break   = true;
				$is_done = ! (bool) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0" );
				wp_send_json_success( array_merge( compact( 'uploaded', 'is_done' ), $this->get_sync_stats() ) );
			}
		}
	}

	public function get_sync_stats() {
		global $wpdb;

		$total  = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files`" );
		$synced = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1" );

		if ( ! $total || ! $total->files || ! $total->size ) {
			return false;
		}

		$progress = get_site_option( 'iup_files_scanned' );

		return array_merge( $progress, [
			'total_files'     => number_format_i18n( (int) $total->files ),
			'total_size'      => size_format( (int) $total->size ),
			'cloud_files'     => number_format_i18n( (int) $synced->files ),
			'cloud_size'      => size_format( (int) $synced->size ),
			'remaining_files' => number_format_i18n( $total->files - $synced->files ),
			'remaining_size'  => size_format( $total->size - $synced->size ),
			'pcnt_complete'   => round( ( $synced->files / $total->files ) * 100, 2 ),
		] );
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

	public function filter_upload_dir( $dirs ) {

		$this->original_upload_dir = $dirs;

		$dirs['path']    = str_replace( WP_CONTENT_DIR, 'iu://' . $this->bucket, $dirs['path'] );
		$dirs['basedir'] = str_replace( WP_CONTENT_DIR, 'iu://' . $this->bucket, $dirs['basedir'] );

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

	public function get_s3_url() {
		if ( $this->bucket_url ) {
			return $this->bucket_url;
		}

		$bucket = strtok( $this->bucket, '/' );
		$path   = substr( $this->bucket, strlen( $bucket ) );

		return apply_filters( 'infinite_uploads_bucket_url', 'https://' . $bucket . '.s3.amazonaws.com' . $path );
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

	public function get_original_upload_dir() {

		if ( empty( $this->original_upload_dir ) ) {
			wp_upload_dir();
		}

		return $this->original_upload_dir;
	}

	/**
	 * @return Aws\S3\S3Client
	 */
	public function s3() {

		if ( ! empty( $this->s3 ) ) {
			return $this->s3;
		}

		$params = array( 'version' => 'latest' );

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
	 * @return array|bool
	 */
	public function wp_filter_read_image_metadata( $meta, $file ) {
		remove_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10 );
		$temp_file = $this->copy_image_from_s3( $file );
		$meta      = wp_read_image_metadata( $temp_file );
		add_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10, 2 );
		unlink( $temp_file );
		return $meta;
	}

	/**
	 * Add the DNS address for the S3 Bucket to list for DNS prefetch.
	 *
	 * @param $hints
	 * @param $relation_type
	 * @return array
	 */
	function wp_filter_resource_hints( $hints, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = $this->get_s3_url();
		}

		return $hints;
	}

	/**
	 * Get a local copy of the file.
	 *
	 * @param  string $file
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
}
