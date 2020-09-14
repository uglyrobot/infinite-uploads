<?php

use Aws\S3\Transfer;
use Aws\Middleware;
use Aws\ResultInterface;
use Aws\Exception\AwsException;
use Aws\Exception\S3Exception;

class Infinite_Uploads_Admin {

	private static $instance;
	public $ajax_timelimit = 20;
	private $iup_instance;
	private $api;

	public function __construct() {
		$this->iup_instance = Infinite_Uploads::get_instance();
		$this->api          = Infinite_Uploads_Api::get_instance();

		add_action( 'admin_menu', [ &$this, 'admin_menu' ] );

		add_action( 'wp_ajax_infinite-uploads-filelist', [ &$this, 'ajax_filelist' ] );
		add_action( 'wp_ajax_infinite-uploads-remote-filelist', [ &$this, 'ajax_remote_filelist' ] );
		add_action( 'wp_ajax_infinite-uploads-sync', [ &$this, 'ajax_sync' ] );
		add_action( 'wp_ajax_infinite-uploads-delete', [ &$this, 'ajax_delete' ] );
		add_action( 'wp_ajax_infinite-uploads-toggle', [ &$this, 'ajax_toggle' ] );
	}

	/**
	 *
	 * @return Infinite_Uploads_Admin
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_Uploads_Admin();
		}

		return self::$instance;
	}

	public function ajax_filelist() {

		// check caps
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_send_json_error();
		}

		$path = $this->iup_instance->get_original_upload_dir();
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
		$stats = $this->iup_instance->get_sync_stats();
		if ( $stats ) {
			$data = array_merge( $data, $stats );
		}

		wp_send_json_success( $data );
	}

	public function ajax_remote_filelist() {
		global $wpdb;

		// check caps
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_send_json_error();
		}

		$s3 = $this->iup_instance->s3();

		$prefix = '';

		if ( strpos( INFINITE_UPLOADS_BUCKET, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( INFINITE_UPLOADS_BUCKET, '/' ) . '/', '', INFINITE_UPLOADS_BUCKET ) );
		}

		$args = [
			'Bucket' => strtok( INFINITE_UPLOADS_BUCKET, '/' ),
			'Prefix' => $prefix,
		];

		if ( ! empty( $_POST['next_token'] ) ) {
			$args['ContinuationToken'] = $_POST['next_token'];
		} else {
			$progress                    = get_site_option( 'iup_files_scanned' );
			$progress['compare_started'] = time();
			update_site_option( 'iup_files_scanned', $progress );
		}

		try {
			$results    = $s3->getPaginator( 'ListObjectsV2', $args );
			$req_count  = $file_count = 0;
			$is_done    = false;
			$next_token = null;
			foreach ( $results as $result ) {
				$req_count ++;
				$is_done          = ! $result['IsTruncated'];
				$next_token       = isset( $result['NextContinuationToken'] ) ? $result['NextContinuationToken'] : null;
				$cloud_only_files = [];
				if ( $result['Contents'] ) {
					foreach ( $result['Contents'] as $object ) {
						$file_count ++;
						$local_key = str_replace( untrailingslashit( $prefix ), '', $object['Key'] );
						$file      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}infinite_uploads_files WHERE file = %s", $local_key ) );
						if ( $file && ! $file->synced && $file->size == $object['Size'] ) {
							$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'synced' => 1 ], [ 'file' => $local_key ] );
						}
						if ( ! $file ) {
							$cloud_only_files[] = [
								'name'  => $local_key,
								'size'  => $object['Size'],
								'mtime' => strtotime( $object['LastModified']->__toString() ),
								'type'  => $this->iup_instance->get_file_type( $local_key ),
							];
						}
					}
				}

				//flush new files to db
				if ( count( $cloud_only_files ) ) {
					$values = [];
					foreach ( $cloud_only_files as $file ) {
						$values[] = $wpdb->prepare( "(%s,%d,%d,%s,1,1)", $file['name'], $file['size'], $file['mtime'], $file['type'] );
					}

					$query = "INSERT INTO {$wpdb->base_prefix}infinite_uploads_files (file, size, modified, type, synced, deleted) VALUES ";
					$query .= implode( ",\n", $values );
					$query .= " ON DUPLICATE KEY UPDATE size = VALUES(size), modified = VALUES(modified), type = VALUES(type), synced = 1, deleted = 1";
					$wpdb->query( $query );
				}

				if ( ( $timer = timer_stop() ) >= $this->ajax_timelimit ) {
					break;
				}
			}

			if ( $is_done ) {
				$progress                     = get_site_option( 'iup_files_scanned' );
				$progress['compare_finished'] = time();
				update_site_option( 'iup_files_scanned', $progress );
			}

			$data  = compact( 'file_count', 'req_count', 'is_done', 'next_token', 'timer' );
			$stats = $this->iup_instance->get_sync_stats();
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

		$progress = get_site_option( 'iup_files_scanned' );
		if ( ! $progress['sync_started'] ) {
			$progress['sync_started'] = time();
			update_site_option( 'iup_files_scanned', $progress );
		}

		$uploaded = 0;
		$errors   = [];
		$break    = false;
		$path     = $this->iup_instance->get_original_upload_dir();
		$s3       = $this->iup_instance->s3();
		while ( ! $break ) {
			$to_sync = $wpdb->get_col( "SELECT file FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 LIMIT 10" );
			//build full paths
			$to_sync_full = [];
			foreach ( $to_sync as $key => $file ) {
				$to_sync_full[] = $path['basedir'] . $file;
			}

			$obj  = new ArrayObject( $to_sync_full );
			$from = $obj->getIterator();

			$transfer_args = [
				'concurrency' => 5,
				'base_dir'    => $path['basedir'],
				'before'      => function ( AWS\Command $command ) use ( $wpdb, &$uploaded ) {
					if ( in_array( $command->getName(), [ 'PutObject', 'CreateMultipartUpload' ], true ) ) {
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
						//add middleware to intercept result of each file upload
						if ( in_array( $command->getName(), [ 'PutObject', 'CompleteMultipartUpload' ], true ) ) {
							$command->getHandlerList()->appendSign(
								Middleware::mapResult( function ( ResultInterface $result ) use ( $wpdb, &$uploaded ) {
									$uploaded ++;
									$file = urldecode( strstr( substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], INFINITE_UPLOADS_BUCKET ) + strlen( INFINITE_UPLOADS_BUCKET ) ) ), '?', true ) ?: substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], INFINITE_UPLOADS_BUCKET ) + strlen( INFINITE_UPLOADS_BUCKET ) ) ) );
									$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'synced' => 1 ], [ 'file' => $file ] );

									return $result;
								} )
							);
						}
					}
				},
			];
			try {
				$manager = new Transfer( $s3, $from, 's3://' . INFINITE_UPLOADS_BUCKET . '/', $transfer_args );
				$manager->transfer();
			} catch ( Exception $e ) {
				if ( method_exists( $e, 'getRequest' ) ) {
					$file     = str_replace( trailingslashit( INFINITE_UPLOADS_BUCKET ), '', $e->getRequest()->getRequestTarget() );
					$errors[] = sprintf( __( 'Error uploading %s. Queued for retry.', 'iup' ), $file );
				} else {
					$errors[] = __( 'Error uploading file. Queued for retry.', 'iup' );
				}
			}

			$is_done = ! (bool) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0" );
			if ( $is_done || timer_stop() >= $this->ajax_timelimit ) {
				$break = true;

				if ( $is_done ) {
					$progress                  = get_site_option( 'iup_files_scanned' );
					$progress['sync_finished'] = time();
					update_site_option( 'iup_files_scanned', $progress );
				}

				wp_send_json_success( array_merge( compact( 'uploaded', 'is_done', 'errors' ), $this->iup_instance->get_sync_stats() ) );
			}
		}
	}

	public function ajax_delete() {
		global $wpdb;

		$deleted = 0;
		$errors  = [];
		$path    = $this->iup_instance->get_original_upload_dir();
		$break   = false;
		while ( ! $break ) {
			$to_delete = $wpdb->get_col( "SELECT file FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 0 LIMIT 500" );
			foreach ( $to_delete as $file ) {
				@unlink( $path['basedir'] . $file );
				$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'deleted' => 1 ], [ 'file' => $file ] );
				$deleted ++;
			}

			$is_done = ! (bool) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 0" );
			if ( $is_done || timer_stop() >= $this->ajax_timelimit ) {
				$break = true;
				wp_send_json_success( array_merge( compact( 'deleted', 'is_done', 'errors' ), $this->iup_instance->get_sync_stats() ) );
			}
		}
	}

	public function ajax_download() {
		global $wpdb;

		$progress = get_site_option( 'iup_files_scanned' );
		if ( empty( $progress['download_started'] ) ) {
			$progress['download_started'] = time();
			update_site_option( 'iup_files_scanned', $progress );
		}

		$downloaded = 0;
		$errors     = [];
		$break      = false;
		$path       = $this->iup_instance->get_original_upload_dir();
		$s3         = $this->iup_instance->s3();
		while ( ! $break ) {
			$to_sync = $wpdb->get_col( "SELECT file FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 1 LIMIT 10" );
			//build full paths
			$to_sync_full = [];
			foreach ( $to_sync as $key => $file ) {
				$to_sync_full[] = 's3://' . INFINITE_UPLOADS_BUCKET . $file;
			}

			$obj  = new ArrayObject( $to_sync_full );
			$from = $obj->getIterator();

			$transfer_args = [
				'concurrency' => 5,
				'base_dir'    => 's3://' . INFINITE_UPLOADS_BUCKET,
				'before'      => function ( AWS\Command $command ) use ( $wpdb, &$downloaded ) {//add middleware to intercept result of each file upload
					if ( in_array( $command->getName(), [ 'GetObject' ], true ) ) {
						$command->getHandlerList()->appendSign(
							Middleware::mapResult( function ( ResultInterface $result ) use ( $wpdb, &$downloaded ) {
								$downloaded ++;
								$file = urldecode( strstr( substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], INFINITE_UPLOADS_BUCKET ) + strlen( INFINITE_UPLOADS_BUCKET ) ) ), '?', true ) ?: substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], INFINITE_UPLOADS_BUCKET ) + strlen( INFINITE_UPLOADS_BUCKET ) ) ) );
								$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'deleted' => 0 ], [ 'file' => $file ] );

								return $result;
							} )
						);
					}
				},
			];
			try {
				$manager = new Transfer( $s3, $from, $path['basedir'], $transfer_args );
				$manager->transfer();
			} catch ( Exception $e ) {
				if ( method_exists( $e, 'getRequest' ) ) {
					$file     = str_replace( trailingslashit( INFINITE_UPLOADS_BUCKET ), '', $e->getRequest()->getRequestTarget() );
					$errors[] = sprintf( __( 'Error downloading %s. Queued for retry.', 'iup' ), $file );
				} else {
					$errors[] = __( 'Error downloading file. Queued for retry.', 'iup' );
				}
			}

			$is_done = ! (bool) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 1" );
			if ( $is_done || timer_stop() >= $this->ajax_timelimit ) {
				$break = true;

				if ( $is_done ) {
					$progress                      = get_site_option( 'iup_files_scanned' );
					$progress['download_finished'] = time();
					update_site_option( 'iup_files_scanned', $progress );
				}

				wp_send_json_success( array_merge( compact( 'downloaded', 'is_done', 'errors' ), $this->iup_instance->get_sync_stats() ) );
			}
		}
	}

	public function ajax_toggle() {
		$enabled = (bool) $_REQUEST['enabled'];
		if ( is_multisite() ) {
			update_site_option( 'iup_enabled', $enabled );
		} else {
			update_option( 'iup_enabled', $enabled, true );
		}

		wp_send_json_success();
	}

	/**
	 * Registers a new settings page under Settings.
	 */
	function admin_menu() {
		$page = add_options_page(
			__( 'Infinite Uploads', 'infinite-uploads' ),
			__( 'Infinite Uploads', 'infinite-uploads' ),
			'manage_options',
			'infinite_uploads',
			[
				$this,
				'settings_page',
			]
		);

		add_action( 'admin_print_scripts-' . $page, [ &$this, 'admin_scripts' ] );
		add_action( 'admin_print_styles-' . $page, [ &$this, 'admin_styles' ] );
	}
	/**/
	/**
	 *
	 */
	function admin_scripts() {
		wp_enqueue_script( 'iup-bootstrap', plugins_url( 'assets/bootstrap/js/bootstrap.bundle.min.js', __FILE__ ), [ 'jquery' ], INFINITE_UPLOADS_VERSION );
		wp_enqueue_script( 'iup-chartjs', plugins_url( 'assets/js/Chart.min.js', __FILE__ ), [], INFINITE_UPLOADS_VERSION );
		wp_enqueue_script( 'iup-js', plugins_url( 'assets/js/infinite-uploads.js', __FILE__ ), [], INFINITE_UPLOADS_VERSION );

		$types = $this->iup_instance->get_local_filetypes( true );
		wp_localize_script( 'iup-js', 'local_types', $types );
	}

	/**
	 *
	 */
	function admin_styles() {

		wp_enqueue_style( 'iup-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), false, INFINITE_UPLOADS_VERSION );
		wp_enqueue_style( 'iup-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), [ 'iup-bootstrap' ], INFINITE_UPLOADS_VERSION );

		//hide all admin notices from another source on these pages
		//remove_all_actions( 'admin_notices' );
		//remove_all_actions( 'network_admin_notices' );
		//remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Settings page display callback.
	 */
	function settings_page() {
		global $wpdb;

		if ( isset( $_GET['temp_token'] ) ) {
			$result = $this->api->authorize( $_GET['temp_token'] );
		}

		if ( isset( $_GET['clear'] ) ) {
			delete_site_option( 'iup_files_scanned' );
		}

		$stats    = $this->iup_instance->get_sync_stats();
		$types    = $this->iup_instance->get_local_filetypes();
		$api_data = $this->api->get_site_data();

		//var_dump($stats);
		?>
		<div id="container" class="wrap iup-background">

			<h1>
				<img src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-words.svg', __FILE__ ) ); ?>" alt="Infinite Uploads Logo" height="75" width="300"/>
			</h1>

			<?php
			if ( $this->api->has_token() ) {
				if ( ! empty( $stats['sync_finished'] ) ) {
					require_once( dirname( __FILE__ ) . '/templates/cloud-overview.php' );
				} else {
					require_once( dirname( __FILE__ ) . '/templates/sync.php' );
					require_once( dirname( __FILE__ ) . '/templates/modal-upload.php' );
				}

				require_once( dirname( __FILE__ ) . '/templates/settings.php' );

				require_once( dirname( __FILE__ ) . '/templates/modal-download.php' );

			} else {
				if ( ! empty( $stats['files_finished'] ) && $stats['files_finished'] >= ( time() - DAY_IN_SECONDS ) ) {
					$to_sync = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE deleted = 0" );
					require_once( dirname( __FILE__ ) . '/templates/connect.php' );
				} else {
					require_once( dirname( __FILE__ ) . '/templates/welcome.php' );
				}
				require_once( dirname( __FILE__ ) . '/templates/modal-scan.php' );
			}
			?>

			<?php if ( ! infinite_uploads_enabled() ) { ?>
				<div class="jumbotron">
					<h2 class="display-4"><?php _e( '3. Enable', 'iup' ); ?></h2>
					<p class="lead"><?php _e( 'Enable syncing and serving new uploads from the the Infinite Uploads cloud and global CDN.', 'iup' ); ?></p>
					<hr class="my-4">
					<!-- Button trigger modal -->
					<button type="button" class="btn btn-primary" id="iup-enable">
						<?php _e( 'Enable Infinite Uploads', 'iup' ); ?>
					</button>
					<div class="spinner-border text-hide" id="iup-enable-spinner" role="status"><span class="sr-only"><?php _e( 'Enabling...', 'iup' ); ?></span></div>
				</div>
			<?php } ?>


		</div>
		<?php
	}
}
