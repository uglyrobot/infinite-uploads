<?php

use Aws\S3\Transfer;
use Aws\Exception\AwsException;
use Aws\Exception\S3Exception;
use Aws\Middleware;
use Aws\ResultInterface;

class Infinite_Uploads_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * Verifies the API keys entered will work for writing and deleting from S3.
	 *
	 * @subcommand verify
	 */
	public function verify_api_keys() {
		// Verify first that we have the necessary access keys to connect to S3.
		if ( ! $this->verify_s3_access_constants() ) {
			return;
		}

		// Get S3 Upload instance.
		$instance = Infinite_Uploads::get_instance();

		// Create a path in the base directory, with a random file name to avoid potentially overwriting existing data.
		$upload_dir = wp_upload_dir();
		$s3_path    = $upload_dir['basedir'] . '/' . mt_rand() . '.txt';

		// Attempt to copy the local Canola test file to the generated path on Infinite Uploads cloud.
		WP_CLI::print_value( 'Attempting to upload file ' . $s3_path );

		$copy = copy(
			dirname( dirname( __FILE__ ) ) . '/readme.md',
			$s3_path
		);

		// Check that the copy worked.
		if ( ! $copy ) {
			WP_CLI::error( 'Failed to copy / write to Infinite Uploads cloud - check your policy?' );

			return;
		}

		WP_CLI::print_value( 'File uploaded to Infinite Uploads cloud successfully.' );

		// Delete the file off Infinite Uploads cloud.
		WP_CLI::print_value( 'Attempting to delete file. ' . $s3_path );
		$delete = unlink( $s3_path );

		// Check that the delete worked.
		if ( ! $delete ) {
			WP_CLI::error( 'Failed to delete ' . $s3_path );

			return;
		}

		WP_CLI::print_value( 'File deleted from Infinite Uploads cloud successfully.' );

		WP_CLI::success( 'Looks like your configuration is correct.' );
	}

	/**
	 * Verify that the required constants for the Infinite Uploads cloud connections are set.
	 *
	 * @return bool true if all constants are set, else false.
	 */
	private function verify_s3_access_constants() {
		$required_constants = [
			'INFINITE_UPLOADS_BUCKET',
		];

		// Credentials do not need to be set when using AWS Instance Profiles.
		if ( ! defined( 'INFINITE_UPLOADS_USE_INSTANCE_PROFILE' ) || ! INFINITE_UPLOADS_USE_INSTANCE_PROFILE ) {
			array_push( $required_constants, 'INFINITE_UPLOADS_KEY', 'INFINITE_UPLOADS_SECRET' );
		}

		$all_set = true;
		foreach ( $required_constants as $constant ) {
			if ( ! defined( $constant ) ) {
				WP_CLI::error( sprintf( 'The required constant %s is not defined.', $constant ), false );
				$all_set = false;
			}
		}

		return $all_set;
	}

	/**
	 * List files in the Infinite Uploads cloud
	 *
	 * @synopsis [<path>]
	 */
	public function ls( $args ) {

		$s3 = Infinite_Uploads::get_instance()->s3();

		$prefix = '';

		if ( strpos( INFINITE_UPLOADS_BUCKET, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( INFINITE_UPLOADS_BUCKET, '/' ) . '/', '', INFINITE_UPLOADS_BUCKET ) );
		}

		if ( isset( $args[0] ) ) {
			$prefix .= trailingslashit( ltrim( $args[0], '/' ) );
		}

		try {
			$objects = $s3->getIterator( 'ListObjects', array(
				'Bucket' => strtok( INFINITE_UPLOADS_BUCKET, '/' ),
				'Prefix' => $prefix,
			) );
			foreach ( $objects as $object ) {
				WP_CLI::line( str_replace( $prefix, '', $object['Key'] ) . ' ' . size_format( $object['Size'] ) . ' ' . $object['LastModified']->__toString() );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

	}

	/**
	 * Copy files to / from the uploads directory. Use s3://bucket/location for Infinite Uploads cloud
	 *
	 * @synopsis <from> <to>
	 */
	public function cp( $args ) {

		$from = $args[0];
		$to   = $args[1];

		if ( is_dir( $from ) ) {
			$this->recurse_copy( $from, $to );
		} else {
			copy( $from, $to );
		}

		WP_CLI::success( sprintf( 'Completed copy from %s to %s', $from, $to ) );
	}

	private function recurse_copy( $src, $dst ) {
		$dir = opendir( $src );
		@mkdir( $dst );
		while ( false !== ( $file = readdir( $dir ) ) ) {
			if ( ( '.' !== $file ) && ( '..' !== $file ) ) {
				if ( is_dir( $src . '/' . $file ) ) {
					$this->recurse_copy( $src . '/' . $file, $dst . '/' . $file );
				} else {
					WP_CLI::line( sprintf( 'Copying from %s to %s', $src . '/' . $file, $dst . '/' . $file ) );
					copy( $src . '/' . $file, $dst . '/' . $file );
				}
			}
		}
		closedir( $dir );
	}

	/**
	 * Upload a directory to Infinite Uploads cloud
	 *
	 * @subcommand upload-directory
	 * @synopsis <from> [<to>] [--concurrency=<concurrency>] [--verbose]
	 */
	public function upload_directory( $args, $args_assoc ) {

		$from = $args[0];
		$to   = '';
		if ( isset( $args[1] ) ) {
			$to = $args[1];
		}

		$s3         = Infinite_Uploads::get_instance()->s3();
		$args_assoc = wp_parse_args( $args_assoc, [ 'concurrency' => 5, 'verbose' => false ] );

		$transfer_args = [
			'concurrency' => $args_assoc['concurrency'],
			'debug'       => (bool) $args_assoc['verbose'],
			'before'      => function ( AWS\Command $command ) {
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
			},
		];
		try {
			$manager = new Transfer( $s3, $from, 's3://' . INFINITE_UPLOADS_BUCKET . '/' . $to, $transfer_args );
			$manager->transfer();
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Sync the uploads directory to Infinite Uploads cloud storage.
	 *
	 * @subcommand sync
	 * @synopsis [--concurrency=<concurrency>] [--noscan] [--verbose]
	 */
	public function sync( $args, $args_assoc ) {
		global $wpdb;
		$instance   = Infinite_Uploads::get_instance();
		$s3         = $instance->s3();
		$args_assoc = wp_parse_args( $args_assoc, [ 'concurrency' => 5, 'noscan' => false, 'verbose' => false ] );

		$path = $instance->get_original_upload_dir();

		if ( ! $args_assoc['noscan'] ) {

			WP_CLI::line( __( 'Scanning local filesystem...', 'iup' ) );
			$filelist = new Infinite_Uploads_Filelist( $path['basedir'], 9999, [] );
			$filelist->start();

			$stats = $instance->get_sync_stats();
			WP_CLI::line( sprintf( __( '%s files (%s) found in uploads.', 'iup' ), $stats['total_files'], $stats['total_size'] ) );

			WP_CLI::line( __( 'Comparing to the cloud...', 'iup' ) );
			$prefix = '';

			if ( strpos( INFINITE_UPLOADS_BUCKET, '/' ) ) {
				$prefix = trailingslashit( str_replace( strtok( INFINITE_UPLOADS_BUCKET, '/' ) . '/', '', INFINITE_UPLOADS_BUCKET ) );
			}

			$args = array(
				'Bucket' => strtok( INFINITE_UPLOADS_BUCKET, '/' ),
				'Prefix' => $prefix,
			);

			//set flag
			$progress                    = get_site_option( 'iup_files_scanned' );
			$progress['compare_started'] = time();
			update_site_option( 'iup_files_scanned', $progress );

			try {
				$results = $s3->getPaginator( 'ListObjectsV2', $args );
				foreach ( $results as $result ) {
					foreach ( $result['Contents'] as $object ) {
						$local_key = str_replace( untrailingslashit( $prefix ), '', $object['Key'] );
						$file      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}infinite_uploads_files WHERE file = %s AND synced = 0", $local_key ) );
						if ( $file && $file->size == $object['Size'] ) {
							$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", array( 'synced' => 1 ), array( 'file' => $local_key ) );
						}
					}
				}

				//set flag
				$progress                     = get_site_option( 'iup_files_scanned' );
				$progress['compare_finished'] = time();
				update_site_option( 'iup_files_scanned', $progress );

				$stats = $instance->get_sync_stats();
				WP_CLI::line( sprintf( __( '%s files (%s) remaining to be synced.', 'iup' ), $stats['remaining_files'], $stats['remaining_size'] ) );

			} catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		}

		//begin transfer
		$synced       = $wpdb->get_var( "SELECT count(*) AS files FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 1" );
		$unsynced     = $wpdb->get_var( "SELECT count(*) AS files FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0" );
		$progress_bar = null;
		if ( ! $args_assoc['verbose'] ) {
			$progress_bar = \WP_CLI\Utils\make_progress_bar( __( 'Copying to the cloud...', 'iup' ), $synced + $unsynced );
			for ( $i = 0; $i < $synced; $i ++ ) {
				$progress_bar->tick();
			}
		}

		$progress = get_site_option( 'iup_files_scanned' );
		if ( ! $progress['sync_started'] ) {
			$progress['sync_started'] = time();
			update_site_option( 'iup_files_scanned', $progress );
		}

		$uploaded = 0;
		$break    = false;
		while ( ! $break ) {
			$to_sync = $wpdb->get_col( "SELECT file FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 LIMIT 1000" );
			//build full paths
			$to_sync_full = [];
			foreach ( $to_sync as $key => $file ) {
				$to_sync_full[] = $path['basedir'] . $file;
			}

			$obj  = new ArrayObject( $to_sync_full );
			$from = $obj->getIterator();

			$transfer_args = [
				'concurrency' => $args_assoc['concurrency'],
				'base_dir'    => $path['basedir'],
				'before'      => function ( AWS\Command $command ) use ( $args_assoc, $progress_bar, $wpdb, $unsynced, &$uploaded ) {
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
					//add middleware to intercept result of each file upload
					if ( in_array( $command->getName(), [ 'PutObject', 'CompleteMultipartUpload' ], true ) ) {
						$command->getHandlerList()->appendSign(
							Middleware::mapResult( function ( ResultInterface $result ) use ( $args_assoc, $progress_bar, $command, $wpdb, $unsynced, &$uploaded ) {
								$uploaded ++;
								$file = strstr( substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], INFINITE_UPLOADS_BUCKET ) + strlen( INFINITE_UPLOADS_BUCKET ) ) ), '?', true ) ?: substr( $result['@metadata']["effectiveUri"], ( strrpos( $result['@metadata']["effectiveUri"], INFINITE_UPLOADS_BUCKET ) + strlen( INFINITE_UPLOADS_BUCKET ) ) );
								$wpdb->update( "{$wpdb->base_prefix}infinite_uploads_files", array( 'synced' => 1 ), array( 'file' => $file ) );

								if ( $args_assoc['verbose'] ) {
									WP_CLI::success( sprintf( __( '%s - Synced %s of %s files.', 'iup' ), $file, number_format_i18n( $uploaded ), number_format_i18n( $unsynced ) ) );
								} else {
									$progress_bar->tick();
								}

								return $result;
							} )
						);
					}
				},
			];
			try {
				$manager = new Transfer( $s3, $from, 's3://' . INFINITE_UPLOADS_BUCKET . '/', $transfer_args );
				$manager->transfer();
			} catch ( Exception $e ) {
				$file = str_replace( trailingslashit( INFINITE_UPLOADS_BUCKET ), '', $e->getRequest()->getRequestTarget() );
				WP_CLI::warning( sprintf( __( '%s error uploading %s. Queued for retry.', 'iup' ), $e->getAwsErrorCode(), $file ) );
			}

			$is_done = ! (bool) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0" );
			if ( $is_done ) {
				$break                     = true;
				$progress                  = get_site_option( 'iup_files_scanned' );
				$progress['sync_finished'] = time();
				update_site_option( 'iup_files_scanned', $progress );
				if ( ! $args_assoc['verbose'] ) {
					$progress_bar->finish();
				}
				WP_CLI::success( __( 'Sync complete!', 'iup' ) );
			}

		}
	}

	/**
	 * Delete files from Infinite Uploads cloud
	 *
	 * @synopsis <path> [--regex=<regex>]
	 */
	public function rm( $args, $args_assoc ) {

		$s3 = Infinite_Uploads::get_instance()->s3();

		$prefix = '';
		$regex  = isset( $args_assoc['regex'] ) ? $args_assoc['regex'] : '';

		if ( strpos( INFINITE_UPLOADS_BUCKET, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( INFINITE_UPLOADS_BUCKET, '/' ) . '/', '', INFINITE_UPLOADS_BUCKET ) );
		}

		if ( isset( $args[0] ) ) {
			$prefix .= ltrim( $args[0], '/' );

			if ( strpos( $args[0], '.' ) === false ) {
				$prefix = trailingslashit( $prefix );
			}
		}

		try {
			$objects = $s3->deleteMatchingObjects(
				strtok( INFINITE_UPLOADS_BUCKET, '/' ),
				$prefix,
				$regex,
				array(
					'before_delete',
					function () {
						WP_CLI::line( sprintf( 'Deleting file' ) );
					},
				)
			);

		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::success( sprintf( 'Successfully deleted %s', $prefix ) );
	}

	/**
	 * Enable the auto-rewriting of media links to Infinite Uploads cloud
	 */
	public function enable( $args, $assoc_args ) {
		if ( is_multisite() ) {
			update_site_option( 'iup_enabled', true );
		} else {
			update_option( 'iup_enabled', true, true );
		}

		WP_CLI::success( 'Media URL rewriting enabled.' );
	}

	/**
	 * Disable the auto-rewriting of media links to Infinite Uploads cloud
	 */
	public function disable( $args, $assoc_args ) {
		if ( is_multisite() ) {
			update_site_option( 'iup_enabled', false );
		} else {
			update_option( 'iup_enabled', false, true );
		}

		WP_CLI::success( 'Media URL rewriting disabled.' );
	}
}

WP_CLI::add_command( 'infinite-uploads', 'Infinite_Uploads_WP_CLI_Command' );
