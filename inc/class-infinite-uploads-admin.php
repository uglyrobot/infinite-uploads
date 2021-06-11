<?php

use UglyRobot\Infinite_Uploads\Aws\S3\Transfer;
use UglyRobot\Infinite_Uploads\Aws\Middleware;
use UglyRobot\Infinite_Uploads\Aws\ResultInterface;
use UglyRobot\Infinite_Uploads\Aws\CommandPool;
use UglyRobot\Infinite_Uploads\Aws\Exception\AwsException;
use UglyRobot\Infinite_Uploads\Aws\Exception\S3Exception;
use UglyRobot\Infinite_Uploads\Aws\S3\MultipartUploader;
use UglyRobot\Infinite_Uploads\Aws\Exception\MultipartUploadException;

class Infinite_Uploads_Admin {

	private static $instance;
	public $ajax_timelimit = 20;
	private $iup_instance;
	private $api;
	private $auth_error;

	public function __construct() {
		$this->iup_instance = Infinite_Uploads::get_instance();
		$this->api          = Infinite_Uploads_Api_Handler::get_instance();

		//single site
		add_action( 'admin_menu', [ &$this, 'admin_menu' ] );
		add_action( 'load-media_page_infinite_uploads', [ &$this, 'intercept_auth' ] );
		add_filter( 'plugin_action_links_infinite-uploads/infinite-uploads.php', [ &$this, 'plugins_list_links' ] );

		//multisite
		add_action( 'network_admin_menu', [ &$this, 'admin_menu' ] );
		add_action( 'load-settings_page_infinite_uploads', [ &$this, 'intercept_auth' ] );
		add_filter( 'network_admin_plugin_action_links_infinite-uploads/infinite-uploads.php', [ &$this, 'plugins_list_links' ] );

		add_action( 'admin_init', [ &$this, 'privacy_policy' ] );
		add_action( 'deactivate_plugin', [ &$this, 'block_bulk_deactivate' ] );

		if ( is_main_site() ) {
			add_action( 'wp_ajax_infinite-uploads-filelist', [ &$this, 'ajax_filelist' ] );
			add_action( 'wp_ajax_infinite-uploads-remote-filelist', [ &$this, 'ajax_remote_filelist' ] );
			add_action( 'wp_ajax_infinite-uploads-sync', [ &$this, 'ajax_sync' ] );
			add_action( 'wp_ajax_infinite-uploads-sync-errors', [ &$this, 'ajax_sync_errors' ] );
			add_action( 'wp_ajax_infinite-uploads-reset-errors', [ &$this, 'ajax_reset_errors' ] );
			add_action( 'wp_ajax_infinite-uploads-delete', [ &$this, 'ajax_delete' ] );
			add_action( 'wp_ajax_infinite-uploads-download', [ &$this, 'ajax_download' ] );
			add_action( 'wp_ajax_infinite-uploads-toggle', [ &$this, 'ajax_toggle' ] );
			add_action( 'wp_ajax_infinite-uploads-status', [ &$this, 'ajax_status' ] );
		}
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

	/**
	 * Adds a privacy policy statement.
	 */
	function privacy_policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>'
		           . sprintf(
			           esc_html__( 'When you upload files on this site, your files are transferred to and stored in the Infinite Uploads cloud. When you visit pages on this site media files may be downloaded from the Infinite Uploads cloud CDN which stores web log information including IP, User Agent, referrer, Location, and ISP info of site visitors for 7 days. The Infinite Uploads privacy policy is %1$s here %2$s.', 'infinite-uploads' ),
			           '<a href="https://infiniteuploads.com/privacy/?utm_source=iup_plugin&utm_medium=privacy_policy&utm_campaign=iup_plugin" target="_blank">', '</a>'
		           ) . '</p>';
		wp_add_privacy_policy_content( esc_html__( 'Infinite Uploads', 'infinite-uploads' ), wp_kses_post( wpautop( $content, false ) ) );
	}

	/**
	 * Logs a debugging line.
	 */
	function sync_debug_log( $message ) {
		if ( defined( 'INFINITE_UPLOADS_API_DEBUG' ) && INFINITE_UPLOADS_API_DEBUG ) {
			$log = '[INFINITE_UPLOADS Sync Debug] %s %s';

			$msg = sprintf(
				$log,
				INFINITE_UPLOADS_VERSION,
				$message
			);
			error_log( $msg );
		}
	}

	public function ajax_status() {
		// check caps
		if ( ! current_user_can( $this->iup_instance->capability ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		wp_send_json_success( $this->iup_instance->get_sync_stats() );
	}

	public function ajax_sync_errors() {
		global $wpdb;

		// check caps
		if ( ! current_user_can( $this->iup_instance->capability ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$html       = '';
		$error_list = $wpdb->get_results( "SELECT file, size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 AND errors >= 3" );
		foreach ( $error_list as $error ) {
			$html .= sprintf( '<li class="list-group-item list-group-item-warning">%s - %s</li>', esc_html( $error->file ), size_format( $error->size, 2 ) ) . PHP_EOL;
		}
		wp_send_json_success( $html );
	}

	public function ajax_reset_errors() {
		global $wpdb;

		// check caps
		if ( ! current_user_can( $this->iup_instance->capability ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$result = $wpdb->query( "UPDATE `{$wpdb->base_prefix}infinite_uploads_files` SET errors = 0, transferred = 0 WHERE synced = 0 AND errors >= 3" );

		wp_send_json_success( $result );
	}


	public function ajax_filelist() {
		global $wpdb;

		// check caps
		if ( ! current_user_can( $this->iup_instance->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'iup_scan' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$this->sync_debug_log( "Ajax time limit: " . $this->ajax_timelimit );

		$path = $this->iup_instance->get_original_upload_dir_root();
		$path = $path['basedir'];

		$remaining_dirs = [];
		//validate path is within uploads dir to prevent path traversal
		if ( isset( $_POST['remaining_dirs'] ) && is_array( $_POST['remaining_dirs'] ) ) {
			foreach ( $_POST['remaining_dirs'] as $dir ) {
				$realpath = realpath( $path . $dir );
				if ( 0 === strpos( $realpath, $path ) ) { //check that parsed path begins with upload dir
					$remaining_dirs[] = $dir;
				}
			}
		} elseif ( ! empty( $this->iup_instance->bucket ) ) {
			//If we are starting a new filesync and are logged into cloud storage abort any unfinished multipart uploads
			$to_abort = $wpdb->get_results( "SELECT file, transfer_status as upload_id FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE transfer_status IS NOT NULL" );
			if ( $to_abort ) {
				$s3       = $this->iup_instance->s3();
				$prefix   = $this->iup_instance->get_s3_prefix();
				$bucket   = $this->iup_instance->get_s3_bucket();
				$commands = [];
				foreach ( $to_abort as $file ) {
					$key = $prefix . $file->file;
					// Abort the multipart upload.
					$commands[] = $s3->getCommand( 'abortMultipartUpload', [
						'Bucket'   => $bucket,
						'Key'      => $key,
						'UploadId' => $file->upload_id,
					] );
					$this->sync_debug_log( "Aborting multipart upload for {$file->file} UploadId {$file->upload_id}" );
				}
				// Create a command pool
				$pool = new CommandPool( $s3, $commands );

				// Begin asynchronous execution of the commands
				$promise = $pool->promise();
			}
		}

		$filelist = new Infinite_Uploads_Filelist( $path, $this->ajax_timelimit, $remaining_dirs );
		$filelist->start();
		$this_file_count = count( $filelist->file_list );
		$remaining_dirs  = $filelist->paths_left;
		$is_done         = $filelist->is_done;
		$nonce           = wp_create_nonce( 'iup_scan' );

		$data  = compact( 'this_file_count', 'is_done', 'remaining_dirs', 'nonce' );
		$stats = $this->iup_instance->get_sync_stats();
		if ( $stats ) {
			$data = array_merge( $data, $stats );
		}

		// Force the abortMultipartUpload pool to complete synchronously just in case it hasn't finished
		if ( isset( $promise ) ) {
			$promise->wait();
		}

		wp_send_json_success( $data );
	}

	public function ajax_remote_filelist() {
		global $wpdb;

		// check caps
		if ( ! current_user_can( $this->iup_instance->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'iup_scan' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$this->sync_debug_log( "Ajax time limit: " . $this->ajax_timelimit );

		$s3     = $this->iup_instance->s3();
		$prefix = $this->iup_instance->get_s3_prefix();

		$args = [
			'Bucket' => $this->iup_instance->get_s3_bucket(),
			'Prefix' => trailingslashit( $prefix ),
		];

		if ( ! empty( $_POST['next_token'] ) ) {
			$args['ContinuationToken'] = sanitize_text_field( $_POST['next_token'] );
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
						$local_key = str_replace( $prefix, '', $object['Key'] );
						$file      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}infinite_uploads_files WHERE file = %s", $local_key ) );
						if ( $file && ! $file->synced && $file->size == $object['Size'] ) {
							$this->sync_debug_log( "Already synced file found: $local_key " . size_format( $file->size, 2 ) );
							$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'synced' => 1, 'transferred' => $file->size ], [ 'file' => $local_key ] );
						}
						if ( ! $file ) {
							$this->sync_debug_log( "Cloud only file found: $local_key " . size_format( $object['Size'], 2 ) );
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
						$values[] = $wpdb->prepare( "(%s,%d,%d,%s,%d,1,1)", $file['name'], $file['size'], $file['mtime'], $file['type'], $file['size'] );
					}

					$query = "INSERT INTO {$wpdb->base_prefix}infinite_uploads_files (file, size, modified, type, transferred, synced, deleted) VALUES ";
					$query .= implode( ",\n", $values );
					$query .= " ON DUPLICATE KEY UPDATE size = VALUES(size), modified = VALUES(modified), type = VALUES(type), transferred = VALUES(transferred), synced = 1, deleted = 1, errors = 0";
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


			$nonce = wp_create_nonce( 'iup_scan' );
			$data  = compact( 'file_count', 'req_count', 'is_done', 'next_token', 'timer', 'nonce' );
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

		if ( ! current_user_can( $this->iup_instance->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'iup_sync' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$progress = get_site_option( 'iup_files_scanned' );
		if ( ! $progress['sync_started'] ) {
			$progress['sync_started'] = time();
			update_site_option( 'iup_files_scanned', $progress );
		}

		//this loop has a parallel status check, so we make the timeout 2/3 of max execution time.
		$this->ajax_timelimit = max( 20, floor( ini_get( 'max_execution_time' ) * .6666 ) );
		$this->sync_debug_log( "Ajax time limit: " . $this->ajax_timelimit );
		$uploaded = 0;
		$errors   = [];
		$break    = false;
		$is_done  = false;
		$path     = $this->iup_instance->get_original_upload_dir_root();
		$s3       = $this->iup_instance->s3();
		while ( ! $break ) {
			$to_sync = $wpdb->get_results( $wpdb->prepare( "SELECT file, size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 AND errors < 3 AND transfer_status IS NULL ORDER BY errors ASC, file ASC LIMIT %d", INFINITE_UPLOADS_SYNC_PER_LOOP ) );
			if ( $to_sync ) {
				//build full paths
				$to_sync_full = [];
				$to_sync_size = 0;
				$to_sync_sql  = [];
				foreach ( $to_sync as $file ) {
					$to_sync_size += $file->size;
					if ( count( $to_sync_full ) && $to_sync_size > INFINITE_UPLOADS_SYNC_MAX_BYTES ) { //upload at minimum one file even if it's huuuge
						break;
					}
					$to_sync_full[] = $path['basedir'] . $file->file;
					$to_sync_sql[]  = esc_sql( $file->file );
				}
				//preset the error count in case request times out. Successful sync will clear error count.
				$wpdb->query( "UPDATE `{$wpdb->base_prefix}infinite_uploads_files` SET errors = ( errors + 1 ) WHERE file IN ('" . implode( "','", $to_sync_sql ) . "')" );

				$this->sync_debug_log( "Transfer manager batch size " . size_format( $to_sync_size, 2 ) . ", " . count( $to_sync_full ) . " files." );
				$concurrency = count( $to_sync_full ) > 1 ? INFINITE_UPLOADS_SYNC_CONCURRENCY : INFINITE_UPLOADS_SYNC_MULTIPART_CONCURRENCY;
				$obj         = new ArrayObject( $to_sync_full );
				$from        = $obj->getIterator();

				$transfer_args = [
					'concurrency' => $concurrency,
					'base_dir'    => $path['basedir'],
					'before'      => function ( UglyRobot\Infinite_Uploads\Aws\Command $command ) use ( $wpdb, &$uploaded, &$errors, &$part_sizes ) {
						//add middleware to modify object headers
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
						}

						if ( in_array( $command->getName(), [ 'PutObject' ], true ) ) {
							$this->sync_debug_log( "Uploading key {$command['Key']}" );
						}

						//add middleware to intercept result of each file upload
						if ( in_array( $command->getName(), [ 'PutObject', 'CompleteMultipartUpload' ], true ) ) {
							$command->getHandlerList()->appendSign(
								Middleware::mapResult( function ( ResultInterface $result ) use ( $wpdb, &$uploaded, $command ) {
									$this->sync_debug_log( "Finished uploading file: " . $command['Key'] );
									$uploaded ++;
									$file = $this->iup_instance->get_file_from_result( $result );
									$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}infinite_uploads_files` SET transferred = size, synced = 1, errors = 0, transfer_status = null WHERE file = %s", $file ) );

									return $result;
								} )
							);
						}

						//add middleware to intercept result and record the uploadId for resuming later
						if ( in_array( $command->getName(), [ 'CreateMultipartUpload' ], true ) ) {
							$this->sync_debug_log( "Starting multipart upload for key {$command['Key']}" );
							$command->getHandlerList()->appendSign(
								Middleware::mapResult( function ( ResultInterface $result ) use ( $wpdb ) {
									$file = $this->iup_instance->get_file_from_result( $result );
									$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'synced' => 0, 'transfer_status' => $result['UploadId'] ], [ 'file' => $file ], [ '%d', '%s' ] );

									return $result;
								} )
							);
						}

						//add middleware to check if we should bail before each new upload part
						if ( in_array( $command->getName(), [ 'UploadPart' ], true ) ) {
							$this->sync_debug_log( "Uploading key {$command['Key']} part {$command['PartNumber']}" );
							$command->getHandlerList()->appendSign(
								Middleware::mapResult( function ( ResultInterface $result ) use ( $command ) {
									global $wpdb;
									$this->sync_debug_log( "Finished Uploading key {$command['Key']} part {$command['PartNumber']}" );

									$file = $this->iup_instance->get_file_from_result( $result );
									$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}infinite_uploads_files` SET transferred = ( transferred + %d ), synced = 0, errors = 0 WHERE file = %s", $command['ContentLength'], $file ) );

									return $result;
								} )
							);
						}
					},
				];
				try {
					$manager = new Transfer( $s3, $from, 's3://' . $this->iup_instance->bucket . '/', $transfer_args );
					$manager->transfer();
				} catch ( Exception $e ) {
					$this->sync_debug_log( "Transfer sync exception: " . $e->__toString() );
					if ( method_exists( $e, 'getRequest' ) ) {
						$file        = str_replace( trailingslashit( $this->iup_instance->bucket ), '', $e->getRequest()->getRequestTarget() );
						$error_count = $wpdb->get_var( $wpdb->prepare( "SELECT errors FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE file = %s", $file ) );
						if ( $error_count >= 3 ) {
							$errors[] = sprintf( esc_html__( 'Error uploading %s. Retries exceeded.', 'infinite-uploads' ), $file );
						} else {
							$errors[] = sprintf( esc_html__( 'Error uploading %s. Queued for retry.', 'infinite-uploads' ), $file );
						}
					} else { //I don't know which error case trigger this but it's common
						$errors[] = esc_html__( 'Error uploading file. Queued for retry.', 'infinite-uploads' );
					}
				}

			} else { // we are done with transfer manager, continue any unfinished multipart uploads one by one

				$to_sync = $wpdb->get_row( "SELECT file, size, errors, transfer_status as upload_id FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 AND errors < 3 AND transfer_status IS NOT NULL ORDER BY errors ASC, file ASC LIMIT 1" );
				if ( $to_sync ) {
					$this->sync_debug_log( "Continuing multipart upload: " . $to_sync->file );

					//preset the error count in case request times out. Successful sync will clear error count.
					$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}infinite_uploads_files` SET errors = ( errors + 1 ) WHERE file = %s", $to_sync->file ) );
					$to_sync->errors ++; //increment error result so it's accurate

					$key = $this->iup_instance->get_s3_prefix() . $to_sync->file;
					try {
						$upload_state = $this->iup_instance->get_multipart_upload_state( $key, $to_sync->upload_id );
						$progress     = round( ( ( count( $upload_state->getUploadedParts() ) * $upload_state->getPartSize() ) / $to_sync->size ) * 100 );
						$this->sync_debug_log( sprintf( 'Uploaded %s%% of file (%d, %s parts)', $progress, count( $upload_state->getUploadedParts() ), size_format( $upload_state->getPartSize() ) ) );
						$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'transferred' => ( count( $upload_state->getUploadedParts() ) * $upload_state->getPartSize() ) ], [ 'file' => $to_sync->file ], [ '%d' ] );

						$parts_started = [];
						$source        = $path['basedir'] . $to_sync->file;
						$uploader      = new MultipartUploader( $s3, $source, [
							'concurrency'   => INFINITE_UPLOADS_SYNC_MULTIPART_CONCURRENCY,
							'state'         => $upload_state,
							'before_upload' => function ( UglyRobot\Infinite_Uploads\Aws\Command $command ) use ( &$parts_started, $uploaded, $errors ) {

								$this->sync_debug_log( "Uploading key {$command['Key']} part {$command['PartNumber']}" );

								$command->getHandlerList()->appendSign(
									Middleware::mapResult( function ( ResultInterface $result ) use ( $command, &$parts_started, $uploaded, $errors ) {
										global $wpdb;
										$this->sync_debug_log( "Finished Uploading key {$command['Key']} part {$command['PartNumber']}" );

										$file = $this->iup_instance->get_file_from_result( $result );
										$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}infinite_uploads_files` SET transferred = ( transferred + %d ), synced = 0, errors = 0 WHERE file = %s", $command['ContentLength'], $file ) );

										return $result;
									} )
								);
							},
						] );

						//Recover from errors
						do {
							try {
								$result = $uploader->upload();
							} catch ( MultipartUploadException $e ) {
								$uploader = new MultipartUploader( $s3, $source, [
									'state'         => $e->getState(),
									'before_upload' => function ( UglyRobot\Infinite_Uploads\Aws\Command $command ) use ( $wpdb ) {
										$this->sync_debug_log( "Uploading key {$command['Key']} part {$command['PartNumber']}" );
										$command->getHandlerList()->appendSign(
											Middleware::mapResult( function ( ResultInterface $result ) use ( $wpdb, $command ) {
												global $wpdb;
												$this->sync_debug_log( "Finished Uploading key {$command['Key']} part {$command['PartNumber']}" );

												$file = $this->iup_instance->get_file_from_result( $result );
												$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}infinite_uploads_files` SET transferred = ( transferred + %d ), synced = 0, errors = 0 WHERE file = %s", $command['ContentLength'], $file ) );

												return $result;
											} )
										);
									},
								] );
							}
						} while ( ! isset( $result ) );

						//Abort a multipart upload if failed a second time
						try {
							$result = $uploader->upload();
							$this->sync_debug_log( "Finished multipart file upload: " . $to_sync->file );
							$uploaded ++;
							$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'transferred' => $to_sync->size, 'synced' => 1, 'errors' => 0, 'transfer_status' => null ], [ 'file' => $to_sync->file ], [ '%d', '%d', '%d', null ] );
						} catch ( MultipartUploadException $e ) {
							$params = $e->getState()->getId();
							$result = $s3->abortMultipartUpload( $params );
							//restart the multipart
							$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", [ 'transferred' => 0, 'synced' => 0, 'transfer_status' => null ], [ 'file' => $to_sync->file ], [ '%d', null ] );
							$this->sync_debug_log( "Get multipart retry UploadState exception: " . $e->__toString() );
							if ( ( $to_sync->errors ) >= 3 ) {
								$errors[] = sprintf( esc_html__( 'Error uploading %s. Retries exceeded.', 'infinite-uploads' ), $to_sync->file );
							} else {
								$errors[] = sprintf( esc_html__( 'Error uploading %s. Queued for retry.', 'infinite-uploads' ), $to_sync->file );
							}
						}

					} catch ( Exception $e ) {
						$this->sync_debug_log( "Get multipart UploadState exception: " . $e->__toString() );
						if ( ( $to_sync->errors ) >= 3 ) {
							$errors[] = sprintf( esc_html__( 'Error uploading %s. Retries exceeded.', 'infinite-uploads' ), $to_sync->file );
						} else {
							$errors[] = sprintf( esc_html__( 'Error uploading %s. Queued for retry.', 'infinite-uploads' ), $to_sync->file );
						}
					}

				} else {
					$is_done = true;
				}
			}

			if ( $is_done || timer_stop() >= $this->ajax_timelimit ) {
				$break            = true;
				$permanent_errors = false;

				if ( $is_done ) {
					$permanent_errors          = (int) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 AND errors >= 3" );
					$progress                  = get_site_option( 'iup_files_scanned' );
					$progress['sync_finished'] = time();
					update_site_option( 'iup_files_scanned', $progress );
				}

				$nonce = wp_create_nonce( 'iup_sync' );
				wp_send_json_success( array_merge( compact( 'uploaded', 'is_done', 'errors', 'permanent_errors', 'nonce' ), $this->iup_instance->get_sync_stats() ) );
			}
		}
	}

	public function ajax_delete() {
		global $wpdb;

		if ( ! current_user_can( $this->iup_instance->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'iup_delete' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$deleted = 0;
		$errors  = [];
		$path    = $this->iup_instance->get_original_upload_dir_root();
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

		if ( ! current_user_can( $this->iup_instance->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'iup_download' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$progress = get_site_option( 'iup_files_scanned' );
		if ( empty( $progress['download_started'] ) ) {
			$progress['download_started'] = time();
			update_site_option( 'iup_files_scanned', $progress );
		}

		$downloaded = 0;
		$errors     = [];
		$break      = false;
		$path       = $this->iup_instance->get_original_upload_dir_root();
		$s3         = $this->iup_instance->s3();
		while ( ! $break ) {
			$to_sync = $wpdb->get_results( $wpdb->prepare( "SELECT file, size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 1 AND errors < 3 ORDER BY errors ASC, file ASC LIMIT %d", INFINITE_UPLOADS_SYNC_PER_LOOP ) );
			//build full paths
			$to_sync_full = [];
			$to_sync_size = 0;
			$to_sync_sql  = [];
			foreach ( $to_sync as $file ) {
				$to_sync_size += $file->size;
				if ( count( $to_sync_full ) && $to_sync_size > INFINITE_UPLOADS_SYNC_MAX_BYTES ) { //upload at minimum one file even if it's huuuge
					break;
				}
				$to_sync_full[] = 's3://' . untrailingslashit( $this->iup_instance->bucket ) . $file->file;
				$to_sync_sql[]  = esc_sql( $file->file );
			}
			//preset the error count in case request times out. Successful sync will clear error count.
			$wpdb->query( "UPDATE `{$wpdb->base_prefix}infinite_uploads_files` SET errors = ( errors + 1 ) WHERE file IN ('" . implode( "','", $to_sync_sql ) . "')" );

			$obj  = new ArrayObject( $to_sync_full );
			$from = $obj->getIterator();

			$transfer_args = [
				'concurrency' => INFINITE_UPLOADS_SYNC_CONCURRENCY,
				'base_dir'    => 's3://' . $this->iup_instance->bucket,
				'before'      => function ( UglyRobot\Infinite_Uploads\Aws\Command $command ) use ( $wpdb, &$downloaded ) {//add middleware to intercept result of each file upload
					if ( in_array( $command->getName(), [ 'GetObject' ], true ) ) {
						$command->getHandlerList()->appendSign(
							Middleware::mapResult( function ( ResultInterface $result ) use ( $wpdb, &$downloaded ) {
								$downloaded ++;
								$file = $this->iup_instance->get_file_from_result( $result );
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
					if ( $error_count >= 3 ) {
						$errors[] = sprintf( esc_html__( 'Error downloading %s. Retries exceeded.', 'infinite-uploads' ), $file );
					} else {
						$errors[] = sprintf( esc_html__( 'Error downloading %s. Queued for retry.', 'infinite-uploads' ), $file );
					}
				} else {
					$errors[] = esc_html__( 'Error downloading file. Queued for retry.', 'infinite-uploads' );
				}
			}

			$is_done = ! (bool) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1 AND deleted = 1 AND errors < 3" );
			if ( $is_done || timer_stop() >= $this->ajax_timelimit ) {
				$break = true;

				if ( $is_done ) {
					$progress                      = get_site_option( 'iup_files_scanned' );
					$progress['download_finished'] = time();
					update_site_option( 'iup_files_scanned', $progress );

					$this->api->disconnect();
				}

				$nonce = wp_create_nonce( 'iup_download' );
				wp_send_json_success( array_merge( compact( 'downloaded', 'is_done', 'errors', 'nonce' ), $this->iup_instance->get_sync_stats() ) );
			}
		}
	}

	/**
	 * Enable or disable url rewriting
	 */
	public function ajax_toggle() {
		if ( ! current_user_can( $this->iup_instance->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'iup_toggle' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$enabled = (bool) $_REQUEST['enabled'];
		$this->iup_instance->toggle_cloud( $enabled );

		wp_send_json_success();
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
	 * Adds settings links to plugin row.
	 */
	function plugins_list_links( $actions ) {
		// Build and escape the URL.
		$url = esc_url( $this->settings_url() );

		// Create the link.
		$custom_links = [];
		if ( $this->api->has_token() ) {
			$custom_links['settings'] = "<a href='$url'>" . esc_html__( 'Settings', 'infinite-uploads' ) . '</a>';
		} else {
			$custom_links['connect'] = "<a href='$url' style='color: #EE7C1E;'>" . esc_html__( 'Connect', 'infinite-uploads' ) . '</a>';
		}
		$custom_links['support'] = '<a href="' . esc_url( $this->api_url( '/support/?utm_source=iup_plugin&utm_medium=plugin&utm_campaign=iup_plugin&utm_term=support&utm_content=meta' ) ) . '">' . esc_html__( 'Support', 'infinite-uploads' ) . '</a>';

		// Replace deactivate link if they haven't disconnected yet.
		if ( array_key_exists( 'deactivate', $actions ) ) {
			if ( $this->api->has_token() && $this->api->get_site_data() ) {
				$actions['deactivate'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					$url . "&deactivate-notice=1",
					/* translators: %s: Plugin name. */
					esc_attr( sprintf( _x( 'Deactivate %s', 'plugin' ), __( 'Infinite Uploads', 'infinite-uploads' ) ) ),
					__( 'Deactivate' )
				);
			}
		}

		// Adds the links to the beginning of the array.
		return array_merge( $custom_links, $actions );
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
			$base = network_admin_url( 'settings.php?page=infinite_uploads' );
		} else {
			$base = admin_url( 'upload.php?page=infinite_uploads' );
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Get a url to the public Infinite Uploads site.
	 *
	 * @param string $path Optional path on the site.
	 *
	 * @return Infinite_Uploads_Api_Handler|string
	 */
	function api_url( $path = '' ) {
		$url = trailingslashit( $this->api->server_root );

		if ( $path && is_string( $path ) ) {
			$url .= ltrim( $path, '/' );
		}

		return $url;
	}

	/**
	 * Registers a new settings page under Settings.
	 */
	function admin_menu() {
		if ( is_multisite() ) {
			$page = add_submenu_page(
				'settings.php',
				__( 'Infinite Uploads', 'infinite-uploads' ),
				__( 'Infinite Uploads', 'infinite-uploads' ),
				$this->iup_instance->capability,
				'infinite_uploads',
				[
					$this,
					'settings_page',
				]
			);
		} else {
			$page = add_media_page(
				__( 'Infinite Uploads', 'infinite-uploads' ),
				__( 'Infinite Uploads', 'infinite-uploads' ),
				$this->iup_instance->capability,
				'infinite_uploads',
				[
					$this,
					'settings_page',
				]
			);
		}

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
	 * Disable the bulk Deactivate button from Plugins list.
	 */
	function block_bulk_deactivate( $plugin ) {
		if ( ( ( isset( $_POST['action'] ) && 'deactivate-selected' === $_POST['action'] ) || ( isset( $_POST['action2'] ) && 'deactivate-selected' === $_POST['action2'] ) ) && 'infinite-uploads/infinite-uploads.php' === $plugin ) {
			if ( $this->api->has_token() && $this->api->get_site_data() ) {
				wp_redirect( $this->settings_url( [ 'deactivate-notice' => 1 ] ) );
				exit;
			}
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
		if ( ! current_user_can( $this->iup_instance->capability ) ) {
			wp_die( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		if ( ! empty( $_GET['temp_token'] ) ) {
			$result = $this->api->authorize( $_GET['temp_token'] );
			if ( ! $result ) {
				$this->auth_error = $this->api->api_error;
			} else {
				wp_safe_redirect( $this->settings_url() );
			}
		}

		if ( isset( $_GET['clear'] ) ) {
			delete_site_option( 'iup_files_scanned' );
			wp_safe_redirect( $this->settings_url() );
		}

		if ( isset( $_GET['refresh'] ) ) {
			$this->api->get_site_data( true );
			wp_safe_redirect( $this->settings_url() );
		}

		if ( isset( $_GET['reinstall'] ) ) {
			infinite_uploads_install();
			wp_safe_redirect( $this->settings_url() );
		}
	}

	/**
	 * Settings page display callback.
	 */
	function settings_page() {
		global $wpdb;

		$region_labels = [
			'US' => esc_html__( 'United States', 'infinite-uploads' ),
			'EU' => esc_html__( 'Europe', 'infinite-uploads' ),
		];

		$stats    = $this->iup_instance->get_sync_stats();
		$api_data = $this->api->get_site_data();
		?>
		<div id="container" class="wrap iup-background">

			<h1>
				<img src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-words.svg', __FILE__ ) ); ?>" alt="Infinite Uploads Logo" height="75" width="300"/>
			</h1>

			<?php if ( $this->auth_error ) { ?>
				<div class="alert alert-danger mt-1 alert-dismissible fade show" role="alert">
					<?php echo esc_html( $this->auth_error ); ?>
					<button type="button" class="close" data-dismiss="alert" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
			<?php } ?>

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

			<?php if ( isset( $_GET['deactivate-notice'] ) && $this->api->has_token() && $api_data ) { ?>
				<div class="alert alert-warning mt-1" role="alert">
					<div class="row align-items-center">
						<div class="col-md col-12 mb-md-0 mb-2">
							<?php _e( "There is uploaded media from your site that may only exist in the Infinite Uploads cloud. <strong>You MUST download your media files before deactivating this plugin to prevent data loss!</strong>", 'infinite-uploads' ); ?>
						</div>
						<div class="col-sm-4 col-lg-3 text-md-right">
							<button class="btn text-nowrap btn-info" data-toggle="modal" data-target="#scan-remote-modal" data-next="download"><?php esc_html_e( 'Download & Disconnect', 'infinite-uploads' ); ?></button>
						</div>
					</div>
				</div>
			<?php } ?>

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
					if ( isset( $api_data->site ) && $api_data->site->upload_writeable ) {
						require_once( dirname( __FILE__ ) . '/templates/modal-upload.php' );
						require_once( dirname( __FILE__ ) . '/templates/modal-enable.php' );
					}
				}

				require_once( dirname( __FILE__ ) . '/templates/settings.php' );

				require_once( dirname( __FILE__ ) . '/templates/modal-remote-scan.php' );
				require_once( dirname( __FILE__ ) . '/templates/modal-delete.php' );
				require_once( dirname( __FILE__ ) . '/templates/modal-download.php' );

			} else {
				if ( ! empty( $stats['files_finished'] ) && $stats['files_finished'] >= ( time() - DAY_IN_SECONDS ) ) {
					$to_sync = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE deleted = 0" );
					require_once( dirname( __FILE__ ) . '/templates/connect.php' );
				} else {
					//Make sure table is installed so we can show an error if not.
					if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->base_prefix}infinite_uploads_files'" ) ) {
						infinite_uploads_install();
						if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->base_prefix}infinite_uploads_files'" ) ) {
							require_once( dirname( __FILE__ ) . '/templates/install-error.php' );
						} else {
							require_once( dirname( __FILE__ ) . '/templates/welcome.php' );
						}
					} else {
						require_once( dirname( __FILE__ ) . '/templates/welcome.php' );
					}
				}
				require_once( dirname( __FILE__ ) . '/templates/modal-scan.php' );
			}
			?>
		</div>
		<?php
		require_once( dirname( __FILE__ ) . '/templates/footer.php' );
	}
}
