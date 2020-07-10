<?php

use Aws\S3\Transfer;
use Aws\Middleware;
use Aws\ResultInterface;
use Aws\Exception\AwsException;
use Aws\Exception\S3Exception;

class Infinite_Uploads_Admin {

	private static $instance;
	private $iup_instance;
	public $ajax_timelimit = 20;

	public function __construct() {
		$this->iup_instance = Infinite_Uploads::get_instance();

		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );

		add_action( 'wp_ajax_infinite-uploads-filelist', array( &$this, 'ajax_filelist' ) );
		add_action( 'wp_ajax_infinite-uploads-remote-filelist', array( &$this, 'ajax_remote_filelist' ) );
		add_action( 'wp_ajax_infinite-uploads-sync', array( &$this, 'ajax_sync' ) );
		add_action( 'wp_ajax_infinite-uploads-delete', array( &$this, 'ajax_delete' ) );
		add_action( 'wp_ajax_infinite-uploads-toggle', array( &$this, 'ajax_toggle' ) );
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

		$args = array(
			'Bucket' => strtok( INFINITE_UPLOADS_BUCKET, '/' ),
			'Prefix' => $prefix,
		);

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
							$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", array( 'synced' => 1 ), array( 'file' => $local_key ) );
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
					$values = array();
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
									$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", array( 'synced' => 1 ), array( 'file' => $file ) );

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
				$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", array( 'deleted' => 1 ), array( 'file' => $file ) );
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
								$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", array( 'deleted' => 0 ), array( 'file' => $file ) );

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
			array(
				$this,
				'settings_page',
			)
		);

		add_action( 'admin_print_scripts-' . $page, array( &$this, 'admin_scripts' ) );
		add_action( 'admin_print_styles-' . $page, array( &$this, 'admin_styles' ) );
	}

	/**
	 *
	 */
	function admin_scripts() {
		wp_enqueue_script( 'iup-bootstrap', plugins_url( 'assets/bootstrap/js/bootstrap.bundle.min.js', __FILE__ ), array( 'jquery' ), INFINITE_UPLOADS_VERSION );
		wp_enqueue_script( 'iup-chartjs', plugins_url( 'assets/js/Chart.min.js', __FILE__ ), array(), INFINITE_UPLOADS_VERSION );
		wp_enqueue_script( 'iup-js', plugins_url( 'assets/js/infinite-uploads.js', __FILE__ ), array(), INFINITE_UPLOADS_VERSION );

		$types = $this->iup_instance->get_local_filetypes( true );
		wp_localize_script( 'iup-js', 'local_types', $types );
	}

	/**
	 *
	 */
	function admin_styles() {

		wp_enqueue_style( 'iup-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), false, INFINITE_UPLOADS_VERSION );

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
		echo '<div id="container" class="wrap">';
		?>
		<style type="text/css">
			.card {
				margin-top: inherit;
				padding: inherit;
				min-width: inherit;
				max-width: inherit;
				box-shadow: inherit;
				background: inherit;
				box-sizing: inherit;
			}
		</style>
		<h2 class="display-5">
			<img src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo.svg', __FILE__ ) ); ?>" alt="Infinite Uploads Logo" height="30" width="30"/><?php _e( 'Infinite Uploads', 'iup' ); ?></h2>

		<div class="jumbotron">
			<div class="card-header"><?php _e( 'Local File Overview', 'iup' ); ?></div>
			<p class="lead"><?php _e( 'Create your free Infinite Uploads cloud account and connect this site.', 'iup' ); ?></p>
			<hr class="my-4">
			<p><?php _e( 'Infinite Uploads is free to get started, and includes 2GB of free cloud storage with unlimited CDN bandwidth.', 'iup' ); ?></p>
			<a class="btn btn-primary btn-lg" href="https://infiniteuploads.com/?register=<?php echo admin_url( 'options-general.php?page=infinite_uploads' ); ?>" role="button"><?php _e( 'Create Account or Login', 'iup' ); ?></a>
		</div>

		<div class="card">
			<div class="card-header"><?php _e( 'Local File Overview', 'iup' ); ?></div>
			<div class="card-body">
				<div class="row align-items-center justify-content-center">
					<div class="col">
						<h5>Total Bytes/Files</h5>
						<h1 class="display-1"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></h1>

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
					<div class="col">
						<canvas id="iup-local-pie"></canvas>
					</div>
				</div>
				<div class="row justify-content-center mb-2">
					<div class="col text-center">
						<h4><?php _e( 'Ready to Connect!', 'iup' ); ?></h4>
						<p class="lead"><?php _e( 'Get smart plan recommendations, create or connect to existing account, and sync to the cloud.', 'iup' ); ?></p>
					</div>
				</div>
				<div class="row justify-content-center mb-2">
					<div class="col-2 text-center">
						<button type="button" class="btn btn-primary btn-lg btn-block" id=""><span class="dashicons dashicons-cloud"></span> <?php _e( 'Connect', 'iup' ); ?></button>
					</div>
				</div>
				<div class="row justify-content-center">
					<div class="col-2 text-center">
						<div class="progress">
							<div class="progress-bar" role="progressbar" style="width: 50%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="jumbotron">
			<h2 class="display-4"><?php _e( '1. Connect', 'iup' ); ?></h2>
			<p class="lead"><?php _e( 'Create your free Infinite Uploads cloud account and connect this site.', 'iup' ); ?></p>
			<hr class="my-4">
			<p><?php _e( 'Infinite Uploads is free to get started, and includes 2GB of free cloud storage with unlimited CDN bandwidth.', 'iup' ); ?></p>
			<a class="btn btn-primary btn-lg" href="https://infiniteuploads.com/?register=<?php echo admin_url( 'options-general.php?page=infinite_uploads' ); ?>" role="button"><?php _e( 'Create Account or Login', 'iup' ); ?></a>
		</div>

		<div class="jumbotron">
			<h2 class="display-4"><?php _e( '2. Sync', 'iup' ); ?></h2>
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

		<div class="container">
			<h2><?php _e( 'Settings', 'iup' ); ?></h2>
			<form>
				<div class="form-group custom-control custom-switch">
					<input type="checkbox" class="custom-control-input" id="customSwitch1">
					<label class="custom-control-label" for="customSwitch1"><?php _e( 'Keep a local copy of new uploads', 'iup' ); ?></label>
					<small
						class="form-text text-muted"><?php _e( 'The default is to move all new uploads to the cloud to free up local storage and make your site stateless. If you are just trying out Infinite Uploads or using it more for backup purposes you may want to enable this setting.', 'iup' ); ?></small>
				</div>
				<div class="form-group custom-control custom-switch">
					<input type="checkbox" class="custom-control-input" id="customSwitch2">
					<label class="custom-control-label" for="customSwitch2"><?php _e( 'Keep a local copy of new uploads', 'iup' ); ?></label>
					<small class="form-text text-muted"><?php _e( 'The default is to move all new uploads to the cloud to free up storage and make your site stateless. If you are just trying out Infinite Uploads or using it more for backup purposes you may want to enable this.', 'iup' ); ?></small>
				</div>
				<button type="submit" class="btn btn-primary"><?php _e( 'Save', 'iup' ); ?></button>
			</form>
		</div>


		<!-- Modal -->
		<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?php _e( 'Modal title', 'iup' ); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						...
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php _e( 'Close', 'iup' ); ?></button>
						<button type="button" class="btn btn-primary"><?php _e( 'Save changes', 'iup' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
		echo '</div>';
	}
}
