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

	public function __construct() {
		$this->iup_instance = Infinite_Uploads::get_instance();

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

		$stats = $this->iup_instance->get_sync_stats();
		$types = $this->iup_instance->get_local_filetypes();
		echo '<div id="container" class="wrap iup-background">';
		?>

		<h1>
			<img src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-words.svg', __FILE__ ) ); ?>" alt="Infinite Uploads Logo" height="75" width="300"/>
			<span class="badge badge-secondary float-right"><?php _e( 'Disabled', 'iup' ); ?></span>
			<span class="badge badge-success float-right mr-2"><?php _e( 'Enabled', 'iup' ); ?></span>
		</h1>

		<div class="card">
			<div class="card-body cloud p-5">
				<div class="row justify-content-center mb-5 mt-3">
					<div class="col text-center">
						<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-blue.svg', __FILE__ ) ); ?>" alt="Push to Cloud" height="76" width="76"/>
						<h4><?php _e( 'Infinite Uploads Setup', 'iup' ); ?></h4>
						<p class="lead"><?php _e( "Welcome to Infinite Uploads, scalable cloud storage and delivery for your uploads made easy! Get started with a scan of your existing Media Library for smart recommendations choosing the best plan for your site, create or connect your account, and voilà – you're ready to push to the cloud.", 'iup' ); ?></p>
					</div>
				</div>
				<div class="row justify-content-center mb-5">
					<div class="col-2 text-center">
						<button class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#scan-modal"><?php _e( 'Run Scan', 'iup' ); ?></button>
					</div>
				</div>
				<div class="row justify-content-center mb-1">
					<div class="col-2 text-center">
						<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-0.svg', __FILE__ ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header h5"><?php _e( 'Local File Overview', 'iup' ); ?></div>
			<div class="card-body cloud p-5">
				<div class="row align-items-center justify-content-center">
					<div class="col">
						<p class="lead mb-0"><?php _e( "Total Bytes / Files", 'iup' ); ?></p>
						<span class="h1"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></span>

						<div class="container">
							<?php foreach ( $types as $type ) { ?>
								<div class="row mt-2">
									<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type['color']; ?>">&nbsp;</span></div>
									<div class="col-3 lead"><?php echo $type['label']; ?></div>
									<div class="col-3 text-justify"><strong><?php echo size_format( $type['size'], 2 ); ?> / <?php echo number_format_i18n( $type['files'] ); ?></strong></div>
								</div>
							<?php } ?>
						</div>
					</div>
					<div class="col">
						<canvas id="iup-local-pie"></canvas>
					</div>
				</div>
				<div class="row justify-content-center mb-3">
					<div class="col text-center">
						<h4><?php _e( 'Ready to Connect!', 'iup' ); ?></h4>
						<p class="lead"><?php _e( 'Get smart plan recommendations, create or connect to existing account, and sync to the cloud.', 'iup' ); ?></p>
					</div>
				</div>
				<div class="row justify-content-center mb-5">
					<div class="col-2 text-center">
						<a class="btn btn-primary btn-lg btn-block" id="" href="https://infiniteuploads.com/?register=<?php echo admin_url( 'options-general.php?page=infinite_uploads' ); ?>" role="button"><span class="dashicons dashicons-cloud"></span> <?php _e( 'Connect', 'iup' ); ?></a>
					</div>
				</div>
				<div class="row justify-content-center mb-1">
					<div class="col-2 text-center">
						<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-2.svg', __FILE__ ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header h5"><?php _e( 'Ready to Sync', 'iup' ); ?></div>
			<div class="card-body cloud p-5">
				<div class="row align-items-center justify-content-center mb-5">
					<div class="col">
						<p class="lead mb-0"><?php _e( "Total Bytes / Files", 'iup' ); ?></p>
						<span class="h1"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></span>

						<div class="container">
							<?php foreach ( $types as $type ) { ?>
								<div class="row mt-2">
									<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type['color']; ?>">&nbsp;</span></div>
									<div class="col-3 lead"><?php echo $type['label']; ?></div>
									<div class="col-3"><strong><?php echo size_format( $type['size'], 2 ); ?> / <?php echo number_format_i18n( $type['files'] ); ?></strong></div>
								</div>
							<?php } ?>
						</div>
					</div>
					<div class="col-1 text-center">
						<img src="<?php echo esc_url( plugins_url( '/assets/img/arrow.svg', __FILE__ ) ); ?>" alt="Right sync arrow" height="31" width="56"/>
					</div>
					<div class="col">
						<div class="row justify-content-center mb-3">
							<div class="col text-center">
								<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-blue.svg', __FILE__ ) ); ?>" alt="Push to Cloud" height="76" width="76"/>
								<p class="lead"><?php printf( __( 'You have %s of premium storage available!', 'iup' ), '10 GB' ); ?></p>
								<p class="lead"><?php _e( 'Move your media library to the Infinite Uploads cloud.', 'iup' ); ?></p>
							</div>
						</div>
						<div class="row justify-content-center">
							<div class="col-4 text-center">
								<button class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#upload-modal"><span class="dashicons dashicons-cloud"></span> <?php _e( 'Sync Now', 'iup' ); ?></button>
							</div>
						</div>
					</div>
				</div>
				<div class="row justify-content-center mb-1">
					<div class="col-2 text-center">
						<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-3.svg', __FILE__ ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header h5"><?php _e( 'Cloud Storage Overview', 'iup' ); ?></div>
			<div class="card-body cloud p-5">
				<div class="row align-items-center justify-content-center mb-5">
					<div class="col">
						<p class="lead mb-0"><?php _e( "This Site's Cloud Bytes / Files", 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'iup' ); ?>"></span></p>
						<span class="h1"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></span>

						<div class="container">
							<?php foreach ( $types as $type ) { ?>
								<div class="row mt-2">
									<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type['color']; ?>">&nbsp;</span></div>
									<div class="col-3 lead"><?php echo $type['label']; ?></div>
									<div class="col-3"><strong><?php echo size_format( $type['size'], 2 ); ?> / <?php echo number_format_i18n( $type['files'] ); ?></strong></div>
								</div>
							<?php } ?>
						</div>
					</div>
					<div class="col text-center">
						<p class="h5"><?php printf( __( '%s / %s %s Plan', 'iup' ), '1.2 GB', '10 GB', 'Starter' ); ?></p>
						<canvas id="iup-cloud-pie"></canvas>
					</div>
				</div>
				<div class="row justify-content-center mb-1">
					<div class="col-4 text-center">
						<p><?php _e( 'Visit the Infinite Uploads site to view, manage, or change your plan.', 'iup' ); ?></p>
						<a class="btn btn-info btn-lg" id="" href="https://infiniteuploads.com/?register=<?php echo admin_url( 'options-general.php?page=infinite_uploads' ); ?>" role="button"><?php _e( 'Account Management', 'iup' ); ?></a>
					</div>
				</div>
			</div>
		</div>


		<div class="card">
			<div class="card-header h5"><?php _e( 'Account & Settings', 'iup' ); ?></div>
			<div class="card-body p-5">
				<div class="row justify-content-center mb-5">
					<div class="col">
						<h5><?php _e( 'Infinite Uploads Plan', 'iup' ); ?></h5>
						<p class="lead"><?php _e( 'Your current Infinite Uploads plan and storage.', 'iup' ); ?></p>
					</div>
					<div class="col">
						<div class="row">
							<div class="col"><?php _e( 'Used / Available', 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'iup' ); ?>"></span></div>
							<div class="col text-right"><?php _e( 'Need more?', 'iup' ); ?> <a href="#" class="text-warning"><?php _e( 'Switch to a new plan.', 'iup' ); ?></a></div>
						</div>
						<div class="row">
							<div class="col badge badge-pill badge-light text-left p-3">
								<p class="h5 ml-2 mb-0"><?php printf( __( '%s / %s %s', 'iup' ), '1.2 GB', '10 GB', 'Starter' ); ?></p></div>
						</div>
					</div>
				</div>
				<div class="row justify-content-center mb-5">
					<div class="col">
						<h5><?php _e( 'CDN Bandwidth', 'iup' ); ?></h5>
						<p class="lead"><?php _e( 'Infinite Uploads includes allotted bandwidth for CDN delivery of your files.', 'iup' ); ?></p>
					</div>
					<div class="col">
						<div class="row">
							<div class="col"><?php _e( 'Used / Available', 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'iup' ); ?>"></span></div>
						</div>
						<div class="row">
							<div class="col badge badge-pill badge-light text-left p-3">
								<p class="h5 ml-2 mb-0"><?php printf( __( '%s / %s %s', 'iup' ), '2.2 GB', '10 GB', 'Starter' ); ?></p></div>
						</div>
					</div>
				</div>
				<div class="row justify-content-center mb-5">
					<div class="col">
						<h5><?php _e( 'CDN URL', 'iup' ); ?></h5>
						<p class="lead"><?php _e( 'Your uploads are served from this CDN url via 45 edge locations around the world.', 'iup' ); ?></p>
					</div>
					<div class="col">
						<div class="row">
							<div class="col"><?php _e( 'Current CDN URL', 'iup' ); ?></div>
							<div class="col text-right"><?php _e( 'Use your own domain!', 'iup' ); ?> <a href="#" class="text-warning"><?php _e( 'Upgrade to a business plan.', 'iup' ); ?></a></div>
						</div>
						<div class="row">
							<div class="col badge badge-pill badge-light text-left p-3">
								<p class="h5 ml-2 mb-0"><?php echo esc_html( '67865.infiniteuploads.com' ); ?></p></div>
						</div>
					</div>
				</div>
				<div class="row justify-content-center mb-5">
					<div class="col">
						<h5><?php _e( 'Storage Region', 'iup' ); ?></h5>
						<p class="lead"><?php _e( 'The location of our servers storing your uploads.', 'iup' ); ?></p>
					</div>
					<div class="col">
						<div class="row">
							<div class="col"><?php _e( 'Region', 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Region can only be selected when first connecting your site.', 'iup' ); ?>"></span></div>
						</div>
						<div class="row">
							<div class="col badge badge-pill badge-light text-left p-3">
								<p class="h5 ml-2 mb-0"><?php _e( 'United States', 'iup' ); ?></p></div>
						</div>
					</div>
				</div>
				<div class="row justify-content-center">
					<div class="col">
						<h5><?php _e( 'Import & Disconnect', 'iup' ); ?></h5>
						<p class="lead"><?php _e( 'Download your media files and disconnect from our cloud. To cancel or manage your storage plan please visit infiniteuploads.com.', 'iup' ); ?></p>
					</div>
					<div class="col">
						<div class="row text-center mb-3">
							<div class="col"><?php _e( 'We will download your files back to the uploads directory before disconnecting to prevent broken media on your site.', 'iup' ); ?></div>
						</div>
						<div class="row justify-content-center">
							<div class="col-4 text-center">
								<button class="btn btn-info btn-lg btn-block" data-toggle="modal" data-target="#download-modal"><?php _e( 'Disconnect', 'iup' ); ?></button>
								<p><?php printf( __( '%s / %s files to Download', 'iup' ), '1.21 GB', '1,213' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>


		<!-- Scan Modal -->
		<div class="modal fade" id="scan-modal" tabindex="-1" role="dialog" aria-labelledby="scan-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="scan-modal-label"><?php _e( 'Scanning Files', 'iup' ); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="container-fluid">
							<div class="row justify-content-center mb-4 mt-3">
								<div class="col text-center">
									<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/scan.svg', __FILE__ ) ); ?>" alt="Push to Cloud" height="76" width="76"/>
									<h4><?php _e( 'Scanning Local Filesystem', 'iup' ); ?></h4>
									<p class="lead"><?php _e( "This usually only takes a minute or two but can take longer for very large media libraries with a lot of files. Please leave this tab open while we complete your scan.", 'iup' ); ?></p>
								</div>
							</div>
							<div class="row justify-content-center mb-5">
								<div class="col text-center text-muted">
									<div class="spinner-border mr-1" role="status"><span class="sr-only"><?php _e( 'Loading...', 'iup' ); ?></span></div>
									<span class="h3"><?php printf( __( 'Found %s / %s Files...', 'iup' ), '1.2 GB', '1,234' ); ?></span>

								</div>
							</div>
							<div class="row justify-content-center mb-3">
								<div class="col-2 text-center">
									<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-1.svg', __FILE__ ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Upload Modal -->
		<div class="modal fade" id="upload-modal" tabindex="-1" role="dialog" aria-labelledby="upload-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="upload-modal-label"><?php _e( 'Upload to Cloud', 'iup' ); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="container-fluid">
							<div class="row justify-content-center mb-5 mt-3">
								<div class="col text-center">
									<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/push-to-cloud.svg', __FILE__ ) ); ?>" alt="Push to Cloud" height="76" width="76"/>
									<h4><?php _e( 'Sync in Progress', 'iup' ); ?></h4>
									<p class="lead"><?php _e( "This process can take many hours for very large media libraries with a lot of files. Please leave this tab open while the sync is being processed. If you close the tab the sync will be interrupted and you will have to continue where you left off later.", 'iup' ); ?></p>
									<p><?php _e( 'If your host provides access to WP CLI, that is the fastest and most efficient way to sync your files. Simply execute the command:', 'iup' ); ?> <code>wp infinite-uploads sync</code></p>
								</div>
							</div>
							<div class="row justify-content-center mb-5">
								<div class="col text-center">
									<div class="progress">
										<div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">25%</div>
									</div>
								</div>
							</div>
							<div class="row justify-content-center mb-3">
								<div class="col-2 text-center">
									<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-4.svg', __FILE__ ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Download Modal -->
		<div class="modal fade" id="download-modal" tabindex="-1" role="dialog" aria-labelledby="download-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="download-modal-label"><?php _e( 'Download & Disconnect', 'iup' ); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="container-fluid">
							<div class="row justify-content-center mb-5 mt-3">
								<div class="col text-center">
									<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/download-from-cloud.svg', __FILE__ ) ); ?>" alt="Download from Cloud" height="76" width="76"/>
									<h4><?php _e( 'Downloading Files', 'iup' ); ?></h4>
									<p class="lead"><?php _e( "This usually only takes a minute or two but can take longer for very large media libraries with a lot of files. Please leave this tab open while we complete your scan.", 'iup' ); ?></p>
								</div>
							</div>
							<div class="row justify-content-center mb-5">
								<div class="col text-center">
									<div class="progress download">
										<div class="progress-bar" role="progressbar" style="width: 66%;" aria-valuenow="66" aria-valuemin="0" aria-valuemax="100">66%</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>


		<div class="jumbotron">
			<h2 class="display-4"><?php _e( 'OLD SYNC DESIGN', 'iup' ); ?></h2>
			<p class="lead"><?php _e( 'Copy all your existing uploads the the cloud.', 'iup' ); ?></p>
			<hr class="my-4">
			<p><?php _e( 'Before we can begin serving all your files from the Infinite Uploads global CDN, we need to copy your uploads directory to our cloud storage. Please be patient as this can take quite a while depending on the size of your uploads directory and server speed.', 'iup' ); ?></p>
			<p><?php _e( 'If your host provides access to WP CLI, that is the fastest and most efficient way to sync your files. Simply execute the command:', 'iup' ); ?> <code>wp infinite-uploads sync</code></p>
			<?php
			$instance = Infinite_Uploads::get_instance();
			$stats    = $instance->get_sync_stats();
			?>
			<div class="alert alert-danger fade show overflow-auto" role="alert" id="iup-error" style="display: none;max-height: 100px">
			</div>
			<div class="container-fluid">
				<div class="row mt-4 ml-1" id="iup-progress-gauges" <?php echo $stats['is_data'] ? '' : 'style="display: none;"'; ?>>
					<ul class="list-group list-group-horizontal">
						<li class="list-group-item list-group-item-primary"><h3 class="m-0"><span class="iup-progress-total-size"><?php echo esc_html( $stats['local_size'] ); ?></span><small class="text-muted"> <?php _e( 'Local', 'iup' ); ?></small></h3></li>
						<li class="list-group-item list-group-item-primary"><h3 class="m-0"><span class="iup-progress-total-files"><?php echo esc_html( $stats['local_files'] ); ?></span><small class="text-muted"> <?php _e( 'Local Files', 'iup' ); ?></small></h3></li>
					</ul>
					<ul class="list-group list-group-horizontal iup-progress-gauges-cloud ml-4" <?php echo $stats['compare_started'] ? '' : 'style="display: none;"'; ?>>
						<li class="list-group-item list-group-item-success"><h3 class="m-0"><span class="iup-progress-pcnt"><?php echo esc_html( $stats['pcnt_complete'] ); ?></span>%<small class="text-muted"> <?php _e( 'Synced', 'iup' ); ?></small></h3></li>
					</ul>
					<ul class="list-group list-group-horizontal iup-progress-gauges-cloud ml-4" <?php echo $stats['compare_started'] ? '' : 'style="display: none;"'; ?>>
						<li class="list-group-item list-group-item-info"><h3 class="m-0"><span class="iup-progress-size"><?php echo esc_html( $stats['remaining_size'] ); ?></span><small class="text-muted"> <?php _e( 'Remaining', 'iup' ); ?></small></h3></li>
						<li class="list-group-item list-group-item-info"><h3 class="m-0"><span class="iup-progress-files"><?php echo esc_html( $stats['remaining_files'] ); ?></span><small class="text-muted"> <?php _e( 'Remaining Files', 'iup' ); ?></small></h3></li>
					</ul>
				</div>

				<div class="progress mt-4" id="iup-sync-progress-bar" style="height: 30px;<?php echo $stats['compare_started'] ? '' : 'display: none;'; ?>">
					<div class="progress-bar bg-info iup-cloud" role="progressbar" style="width: <?php echo esc_attr( $stats['pcnt_complete'] ); ?>%" aria-valuenow="<?php echo esc_attr( $stats['pcnt_complete'] ); ?>" aria-valuemin="0" aria-valuemax="100">
						<div>
							<span class="iup-progress-pcnt"><?php echo esc_html( $stats['pcnt_complete'] ); ?></span>%
						</div>
					</div>
					<div class="progress-bar bg-warning iup-local" role="progressbar" style="width: <?php echo 100 - $stats['pcnt_complete']; ?>%" aria-valuenow="<?php echo 100 - $stats['pcnt_complete']; ?>" aria-valuemin="0" aria-valuemax="100">
						<div <?php echo $stats['compare_started'] ? '' : 'style="display: none;"'; ?>><span class="iup-progress-size"><?php echo esc_html( $stats['remaining_size'] ); ?></span> (<span
								class="iup-progress-files"><?php echo esc_html( $stats['remaining_files'] ); ?></span> <?php _e( 'files', 'iup' ); ?>)
						</div>
					</div>
				</div>

				<div class="row mt-4 iup-scan-progress" style="display:none;">
					<ul class="col-md">
						<li class="iup-local">
							<div class="spinner-border float-left mr-3 text-hide" role="status"><span class="sr-only"><?php _e( 'Loading...', 'iup' ); ?></span></div>
							<h3 class="text-muted"><?php _e( 'Scanning local filesystem', 'iup' ); ?></h3></li>
						<li class="iup-cloud">
							<div class="spinner-border float-left mr-3 text-hide" role="status"><span class="sr-only"><?php _e( 'Loading...', 'iup' ); ?></span></div>
							<h3 class="text-muted"><?php _e( 'Comparing to the cloud', 'iup' ); ?></h3></li>
						<li class="iup-sync">
							<div class="spinner-border float-left mr-3 text-hide" role="status"><span class="sr-only"><?php _e( 'Loading...', 'iup' ); ?></span></div>
							<h3 class="text-muted"><?php _e( 'Copying to the cloud', 'iup' ); ?></h3></li>
					</ul>
				</div>
				<p class="iup-scan-progress text-muted" style="display:none;"><?php _e( 'Please leave this tab open while the sync is being processed. If you close the tab the sync will be interrupted and you will have to continue where you left off later.', 'iup' ); ?></p>

				<div class="row mt-4">
					<button type="button" class="btn btn-primary" id="iup-sync"><?php _e( 'Sync to Cloud', 'iup' ); ?></button>
					<button type="button" class="btn btn-primary" id="iup-continue-sync" style="display: n1one;"><?php _e( 'Continue Sync', 'iup' ); ?></button>
				</div>
			</div>
		</div>

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

		<?php
		echo '</div>';
	}
}
