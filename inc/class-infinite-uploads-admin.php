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
	private $auth_error;

	public function __construct() {
		$this->iup_instance = Infinite_Uploads::get_instance();
		$this->api          = Infinite_Uploads_Api_Handler::get_instance();

		add_action( 'admin_menu', [ &$this, 'admin_menu' ] );
		add_action( 'load-settings_page_infinite_uploads', [ &$this, 'intercept_auth' ] );

		add_action( 'wp_ajax_infinite-uploads-filelist', [ &$this, 'ajax_filelist' ] );
		add_action( 'wp_ajax_infinite-uploads-remote-filelist', [ &$this, 'ajax_remote_filelist' ] );
		add_action( 'wp_ajax_infinite-uploads-sync', [ &$this, 'ajax_sync' ] );
		add_action( 'wp_ajax_infinite-uploads-delete', [ &$this, 'ajax_delete' ] );
		add_action( 'wp_ajax_infinite-uploads-download', [ &$this, 'ajax_download' ] );
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

		if ( strpos( $this->iup_instance->bucket, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( $this->iup_instance->bucket, '/' ) . '/', '', $this->iup_instance->bucket ) );
		}

		$args = [
			'Bucket' => strtok( $this->iup_instance->bucket, '/' ),
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
					$query .= " ON DUPLICATE KEY UPDATE size = VALUES(size), modified = VALUES(modified), type = VALUES(type), synced = 1, deleted = 1, errors = 0";
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
			$to_sync = $wpdb->get_col( "SELECT file FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 AND errors < 3 LIMIT 10" );
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
									$file = '/' . urldecode( strstr( substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], $this->iup_instance->bucket ) + strlen( $this->iup_instance->bucket ) ) ), '?', true ) ?: substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], $this->iup_instance->bucket ) + strlen( $this->iup_instance->bucket ) ) ) );
									$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'synced' => 1, 'errors' => 0 ], [ 'file' => $file ] );

									return $result;
								} )
							);
						}
					}
				},
			];
			try {
				$manager = new Transfer( $s3, $from, 's3://' . $this->iup_instance->bucket . '/', $transfer_args );
				$manager->transfer();
			} catch ( Exception $e ) {
				if ( method_exists( $e, 'getRequest' ) ) {
					$file        = str_replace( trailingslashit( $this->iup_instance->bucket ), '', $e->getRequest()->getRequestTarget() );
					$error_count = $wpdb->get_var( $wpdb->prepare( "SELECT errors FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE file = %s", $file ) );
					$error_count ++;
					if ( $error_count >= 3 ) {
						$errors[] = sprintf( __( 'Error uploading %s. Retries exceeded.', 'infinite-uploads' ), $file );
					} else {
						$errors[] = sprintf( __( 'Error uploading %s. Queued for retry.', 'infinite-uploads' ), $file );
					}
					$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'errors' => $error_count ], [ 'file' => $file ] );
				} else {
					$errors[] = __( 'Error uploading file. Queued for retry.', 'infinite-uploads' );
				}
			}

			$is_done = ! (bool) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 AND errors < 3" );
			if ( $is_done || timer_stop() >= $this->ajax_timelimit ) {
				$break            = true;
				$permanent_errors = false;

				if ( $is_done ) {
					$permanent_errors          = (int) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 AND errors >= 3" );
					$progress                  = get_site_option( 'iup_files_scanned' );
					$progress['sync_finished'] = time();
					update_site_option( 'iup_files_scanned', $progress );
				}

				wp_send_json_success( array_merge( compact( 'uploaded', 'is_done', 'errors', 'permanent_errors' ), $this->iup_instance->get_sync_stats() ) );
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
			$to_sync = $wpdb->get_col( "SELECT file FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 1 AND errors < 3 LIMIT 10" );
			//build full paths
			$to_sync_full = [];
			foreach ( $to_sync as $key => $file ) {
				$to_sync_full[] = 's3://' . untrailingslashit( $this->iup_instance->bucket ) . $file;
			}

			$obj  = new ArrayObject( $to_sync_full );
			$from = $obj->getIterator();

			$transfer_args = [
				'concurrency' => 5,
				'base_dir'    => 's3://' . $this->iup_instance->bucket,
				'before'      => function ( AWS\Command $command ) use ( $wpdb, &$downloaded ) {//add middleware to intercept result of each file upload
					if ( in_array( $command->getName(), [ 'GetObject' ], true ) ) {
						$command->getHandlerList()->appendSign(
							Middleware::mapResult( function ( ResultInterface $result ) use ( $wpdb, &$downloaded ) {
								$downloaded ++;
								$file = '/' . urldecode( strstr( substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], $this->iup_instance->bucket ) + strlen( $this->iup_instance->bucket ) ) ), '?', true ) ?: substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], $this->iup_instance->bucket ) + strlen( $this->iup_instance->bucket ) ) ) );
								$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'deleted' => 0, 'errors' => 0 ], [ 'file' => $file ] );

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
					$file        = str_replace( untrailingslashit( $path['basedir'] ), '', str_replace( trailingslashit( $this->iup_instance->bucket ), '', $e->getRequest()->getRequestTarget() ) );
					$error_count = $wpdb->get_var( $wpdb->prepare( "SELECT errors FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE file = %s", $file ) );
					$error_count ++;
					if ( $error_count >= 3 ) {
						$errors[] = sprintf( __( 'Error downloading %s. Retries exceeded.', 'infinite-uploads' ), $file );
					} else {
						$errors[] = sprintf( __( 'Error downloading %s. Queued for retry.', 'infinite-uploads' ), $file );
					}
					$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'errors' => $error_count ], [ 'file' => $file ] );
				} else {
					$errors[] = __( 'Error downloading file. Queued for retry.', 'infinite-uploads' );
				}
			}

			$is_done = ! (bool) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 1 AND errors < 3" );
			if ( $is_done || timer_stop() >= $this->ajax_timelimit ) {
				$break = true;

				if ( $is_done ) {
					$progress                      = get_site_option( 'iup_files_scanned' );
					$progress['download_finished'] = time();
					//update_site_option( 'iup_files_scanned', $progress );

					//logout and disable
					$this->api->set_token( '' ); //logout
					if ( is_multisite() ) {
						update_site_option( 'iup_enabled', false );
					} else {
						update_option( 'iup_enabled', false, true );
					}
					delete_site_option( 'iup_files_scanned' );
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
	 * Get the settings url with optional url args.
	 *
	 * @param array $args Optional. Same as for add_query_arg()
	 *
	 * @return string
	 */
	function settings_url( $args = [] ) {
		if ( is_multisite() ) {
			$base = network_admin_url( 'settings.php?page=infinite_uploads' );
		} else {
			$base = admin_url( 'options-general.php?page=infinite_uploads' );
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Identical to WP core size_format() function except it returns "0 GB" instead of false on failure.
	 *
	 * @param int|string $bytes    Number of bytes. Note max integer size for integers.
	 * @param int        $decimals Optional. Precision of number of decimal places. Default 0.
	 *
	 * @return string Number string on success.
	 */
	function size_format_zero( $bytes, $decimals = 0 ) {
		if ( $bytes > 0 ) {
			return size_format( $bytes, $decimals );
		} else {
			return '0 GB';
		}
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

		$types = $this->iup_instance->get_filetypes( true );
		wp_localize_script( 'iup-js', 'local_types', $types );

		$api_data = $this->api->get_site_data();
		if ( $this->api->has_token() && $api_data ) {
			$cloud_types = $this->iup_instance->get_filetypes( true, $api_data->stats->site->types );
			wp_localize_script( 'iup-js', 'cloud_types', $cloud_types );
		}
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
	 * Checks for temp_token in url and processes auth if present.
	 */
	function intercept_auth() {
		if ( ! empty( $_GET['temp_token'] ) ) {
			$result = $this->api->authorize( $_GET['temp_token'] );
			if ( ! $result ) {
				$this->auth_error = $this->api->api_error;
			} else {
				wp_safe_redirect( $this->settings_url() );
			}
		}
	}

	/**
	 * Settings page display callback.
	 */
	function settings_page() {
		global $wpdb;

		$region_labels = [
			'US' => __( 'United States', 'infinite-uploads' ),
			'EU' => __( 'Europe', 'infinite-uploads' ),
		];

		if ( $this->auth_error ) {
			?>
			<div class="alert alert-warning" role="alert"><p><?php echo esc_html( $this->auth_error ); ?></p></div><?php
		}

		if ( isset( $_GET['clear'] ) ) {
			delete_site_option( 'iup_files_scanned' );
		}

		$stats       = $this->iup_instance->get_sync_stats();
		$api_data    = $this->api->get_site_data();
		//var_dump($api_data);
		//var_dump($stats);
		?>
		<div id="iup-error" class="alert alert-warning" role="alert" style="display: none;"></div>
		<div id="container" class="wrap iup-background">

			<h1>
				<img src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-words.svg', __FILE__ ) ); ?>" alt="Infinite Uploads Logo" height="75" width="300"/>
			</h1>

			<?php
			if ( $this->api->has_token() && $api_data ) {
				if ( ! $api_data->stats->site->files ) {
					$synced           = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1" );
					$cloud_size       = $synced->size;
					$cloud_files      = $synced->files;
					$cloud_total_size = $api_data->stats->cloud->storage + $synced->size;
				} else {
					$cloud_size       = $api_data->stats->site->storage;
					$cloud_files      = $api_data->stats->site->files;
					$cloud_total_size = $api_data->stats->cloud->storage;
				}
				if ( infinite_uploads_enabled() ) {
					require_once( dirname( __FILE__ ) . '/templates/cloud-overview.php' );
				} else {
					require_once( dirname( __FILE__ ) . '/templates/sync.php' );
					require_once( dirname( __FILE__ ) . '/templates/modal-scan.php' );
					require_once( dirname( __FILE__ ) . '/templates/modal-remote-scan.php' );
					require_once( dirname( __FILE__ ) . '/templates/modal-upload.php' );
					require_once( dirname( __FILE__ ) . '/templates/modal-enable.php' );
				}

				require_once( dirname( __FILE__ ) . '/templates/settings.php' );

				require_once( dirname( __FILE__ ) . '/templates/modal-delete.php' );
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
		</div>
		<?php
	}
}
