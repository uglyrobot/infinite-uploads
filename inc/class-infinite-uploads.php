<?php

class Infinite_Uploads {

	private static $instance;
	private        $bucket;
	private        $bucket_url;
	private        $key;
	private        $secret;

	public $original_upload_dir;
	public $original_file;

	/**
	 *
	 * @return Infinite_Uploads
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_Uploads(
				'5822983365ad6ee76ef30117',
				'00082835de7e3170000000003',
				'K000D4CZksbPWRd5ne/SgYXuA5rGADA'
			);
		}

		return self::$instance;
	}

	public function __construct( $bucket, $key, $secret, $bucket_url = null ) {

		$this->bucket     = $bucket;
		$this->key        = $key;
		$this->secret     = $secret;
		$this->bucket_url = $bucket_url;
	}

	/**
	 * Setup the hooks, urls filtering etc for Infinite Uploads
	 */
	public function setup() {
		return;
		$this->register_stream_wrapper();

		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		add_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		add_action( 'delete_attachment', array( $this, 'delete_attachment_files' ) );
		add_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10, 2 );
		add_filter( 'wp_resource_hints', array( $this, 'wp_filter_resource_hints' ), 10, 2 );
		remove_filter( 'admin_notices', 'wpthumb_errors' );

		add_action( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_cloud' ) );
	}

	/**
	 * Tear down the hooks, url filtering etc for Infinite Uploads
	 */
	public function tear_down() {

		stream_wrapper_unregister( 'iu' );
		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		remove_filter( 'wp_image_editors', array( $this, 'filter_editors' ), 9 );
		remove_filter( 'wp_handle_sideload_prefilter', array( $this, 'filter_sideload_move_temp_file_to_cloud' ) );
	}

	/**
	 * Register the stream wrapper
	 */
	public function register_stream_wrapper() {
		if ( defined( 'INFINITE_UPLOADS_USE_LOCAL' ) && INFINITE_UPLOADS_USE_LOCAL ) {
			stream_wrapper_register( 'iu', 'Infinite_Uploads_Local_Stream_Wrapper', STREAM_IS_URL );
		} else {
			Infinite_Uploads_Stream_Wrapper::register( $this->b2() );
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
	 * Delete all attachment files from cloud when an attachment is deleted.
	 *
	 * WordPress Core's handling of deleting files for attachments via
	 * wp_delete_attachment_files is not compatible with remote streams, as
	 * it makes many assumptions about local file paths. The hooks also do
	 * not exist to be able to modify their behavior. As such, we just clean
	 * up the iu files when an attachment is removed, and leave WordPress to try
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
				unlink( $intermediate_file );
			}
		}

		unlink( $file );
	}

	public function get_s3_url() {
		if ( $this->bucket_url ) {
			return $this->bucket_url;
		}

		$bucket = strtok( $this->bucket, '/' );
		$path   = substr( $this->bucket, strlen( $bucket ) );

		return apply_filters( 'infinite_uploads_bucket_url', 'https://' . $bucket . '.b2.amazonaws.com' . $path );
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
	 * @return InfiniteUploads\B2\B2_Client
	 * @throws Exception
	 */
	public function b2() {

		if ( ! empty( $this->b2 ) ) {
			return $this->b2;
		}

		$this->b2 = new InfiniteUploads\B2\B2_Client( $this->key, $this->secret );

		return $this->b2;
	}

	public function filter_editors( $editors ) {

		if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, 'Infinite_Uploads_Image_Editor_Imagick' );

		return $editors;
	}

	/**
	 * Copy the file from /tmp to an b2 dir so handle_sideload doesn't fail due to
	 * trying to do a rename() on the file cross streams. This is somewhat of a hack
	 * to work around the core issue https://core.trac.wordpress.org/ticket/29257
	 *
	 * @param array File array
	 * @return array
	 */
	public function filter_sideload_move_temp_file_to_cloud( array $file ) {
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
		$temp_file = $this->copy_image_from_cloud( $file );
		$meta      = wp_read_image_metadata( $temp_file );
		add_filter( 'wp_read_image_metadata', array( $this, 'wp_filter_read_image_metadata' ), 10, 2 );
		unlink( $temp_file );
		return $meta;
	}

	/**
	 * Add the DNS address for the CDN to list for DNS prefetch.
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
	public function copy_image_from_cloud( $file ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}
		$temp_filename = wp_tempnam( $file );
		copy( $file, $temp_filename );
		return $temp_filename;
	}
}
