<?php

use Aws\S3\Transfer;

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

		// Attempt to copy the local Canola test file to the generated path on S3.
		WP_CLI::print_value( 'Attempting to upload file ' . $s3_path );

		$copy = copy(
			dirname( dirname( __FILE__ ) ) . '/verify.txt',
			$s3_path
		);

		// Check that the copy worked.
		if ( ! $copy ) {
			WP_CLI::error( 'Failed to copy / write to S3 - check your policy?' );

			return;
		}

		WP_CLI::print_value( 'File uploaded to S3 successfully.' );

		// Delete the file off S3.
		WP_CLI::print_value( 'Attempting to delete file. ' . $s3_path );
		$delete = unlink( $s3_path );

		// Check that the delete worked.
		if ( ! $delete ) {
			WP_CLI::error( 'Failed to delete ' . $s3_path );

			return;
		}

		WP_CLI::print_value( 'File deleted from S3 successfully.' );

		WP_CLI::success( 'Looks like your configuration is correct.' );
	}

	/**
	 * Create an AWS IAM user for Infinite Uploads to user
	 *
	 * @subcommand create-iam-user
	 * @synopsis --admin-key=<key> --admin-secret=<secret> [--username=<username>] [--format=<format>]
	 */
	public function create_iam_user( $args, $args_assoc ) {

		$args_assoc = wp_parse_args( $args_assoc, array(
			'format' => 'table',
		) );
		if ( empty( $args_assoc['username'] ) ) {
			$username = 'infinite-uploads-' . sanitize_title( home_url() );
		} else {
			$username = $args_assoc['username'];
		}

		try {
			$iam = Aws\Common\Aws::factory( array( 'key' => $args_assoc['admin-key'], 'secret' => $args_assoc['admin-secret'] ) )->get( 'iam' );

			$iam->createUser( array(
				'UserName' => $username,
			));

			$credentials = $iam->createAccessKey( array(
				'UserName' => $username,
			));

			$credentials = $credentials['AccessKey'];

			$iam->putUserPolicy( array(
				'UserName'       => $username,
				'PolicyName'     => $username . '-policy',
				'PolicyDocument' => $this->get_iam_policy(),
			));

		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI\Utils\format_items( $args_assoc['format'], array( (object) $credentials ), array( 'AccessKeyId', 'SecretAccessKey' ) );

	}

	private function get_iam_policy() {

		$bucket = strtok( INFINITE_UPLOADS_BUCKET, '/' );

		$path = null;

		if ( strpos( INFINITE_UPLOADS_BUCKET, '/' ) ) {
			$path = str_replace( strtok( INFINITE_UPLOADS_BUCKET, '/' ) . '/', '', INFINITE_UPLOADS_BUCKET );
		}

		return '{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "Stmt1392016154000",
      "Effect": "Allow",
      "Action": [
        "b2:AbortMultipartUpload",
        "b2:DeleteObject",
        "b2:GetBucketAcl",
        "b2:GetBucketLocation",
        "b2:GetBucketPolicy",
        "b2:GetObject",
        "b2:GetObjectAcl",
        "b2:ListBucket",
        "b2:ListBucketMultipartUploads",
        "b2:ListMultipartUploadParts",
        "b2:PutObject",
        "b2:PutObjectAcl"
      ],
      "Resource": [
        "arn:aws:b2:::' . INFINITE_UPLOADS_BUCKET . '/*"
      ]
    },
    {
      "Sid": "AllowRootAndHomeListingOfBucket",
      "Action": ["b2:ListBucket"],
      "Effect": "Allow",
      "Resource": ["arn:aws:b2:::' . $bucket . '"],
      "Condition":{"StringLike":{"b2:prefix":["' . ( $path ? $path . '/' : '' ) . '*"]}}
    }
  ]
}';
	}

	/**
	 * Create AWS IAM Policy that Infinite Uploads requires
	 *
	 * It's typically not a good idea to use access keys that have full access to your S3 account,
	 * as if the keys are compromised through the WordPress site somehow, you don't
	 * want to give full control via those keys.
	 *
	 * @subcommand generate-iam-policy
	 */
	public function generate_iam_policy() {

		WP_Cli::print_value( $this->get_iam_policy() );

	}

	/**
	 * List files in the S3 bukcet
	 *
	 * @synopsis [<path>]
	 */
	public function ls( $args ) {

		$s3 = Infinite_Uploads::get_instance()->b2();

		$prefix = '';

		if ( strpos( INFINITE_UPLOADS_BUCKET, '/' ) ) {
			$prefix = trailingslashit( str_replace( strtok( INFINITE_UPLOADS_BUCKET, '/' ) . '/', '', INFINITE_UPLOADS_BUCKET ) );
		}

		if ( isset( $args[0] ) ) {
			$prefix .= trailingslashit( ltrim( $args[0], '/' ) );
		}

		try {
			$objects = $s3->getIterator('ListObjects', array(
				'Bucket' => strtok( INFINITE_UPLOADS_BUCKET, '/' ),
				'Prefix' => $prefix,
			));
			foreach ( $objects as $object ) {
				WP_CLI::line( str_replace( $prefix, '', $object['Key'] ) );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

	}

	/**
	 * Copy files to / from the uploads directory. Use iu://bucket/location for S3
	 *
	 * @synopsis <from> <to>
	 */
	public function cp( $args ) {

		$from = $args[0];
		$to = $args[1];

		if ( is_dir( $from ) ) {
			$this->recurse_copy( $from, $to );
		} else {
			copy( $from, $to );
		}

		WP_CLI::success( sprintf( 'Completed copy from %s to %s', $from, $to ) );
	}

	/**
	 * Upload a directory to S3
	 *
	 * @subcommand upload-directory
	 * @synopsis <from> [<to>] [--concurrency=<concurrency>] [--verbose]
	 */
	public function upload_directory( $args, $args_assoc ) {

		$from = $args[0];
		$to = '';
		if ( isset( $args[1] ) ) {
			$to = $args[1];
		}

		$s3 = Infinite_Uploads::get_instance()->b2();
		$args_assoc = wp_parse_args( $args_assoc, [ 'concurrency' => 5, 'verbose' => false ] );

		$transfer_args = [
			'concurrency' => $args_assoc['concurrency'],
			'debug'       => (bool) $args_assoc['verbose'],
			'before'      => function ( AWS\Command $command ) {
				if ( in_array( $command->getName(), [ 'PutObject', 'CreateMultipartUpload' ], true ) ) {
					$acl = defined( 'INFINITE_UPLOADS_OBJECT_ACL' ) ? INFINITE_UPLOADS_OBJECT_ACL : 'public-read';
					$command['ACL'] = $acl;
				}
			},
		];
		try {
			$manager = new Transfer( $s3, $from, 'iu://' . INFINITE_UPLOADS_BUCKET . '/' . $to, $transfer_args );
			$manager->transfer();
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Delete files from S3
	 *
	 * @synopsis <path> [--regex=<regex>]
	 */
	public function rm( $args, $args_assoc ) {

		$s3 = Infinite_Uploads::get_instance()->b2();

		$prefix = '';
		$regex = isset( $args_assoc['regex'] ) ? $args_assoc['regex'] : '';

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
					function() {
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
	 * Ensable the auto-rewriting of media links to S3
	 */
	public function enable( $args, $assoc_args ) {
		update_option( 'infinite_uploads_enabled', 'enabled' );

		WP_CLI::success( 'Media URL rewriting enabled.' );
	}

	/**
	 * Disable the auto-rewriting of media links to S3
	 */
	public function disable( $args, $assoc_args ) {
		delete_option( 'infinite_uploads_enabled' );

		WP_CLI::success( 'Media URL rewriting disabled.' );
	}

	private function recurse_copy( $src, $dst ) {
		$dir = opendir( $src );
		@mkdir( $dst );
		while ( false !== ( $file = readdir( $dir ) ) ) {
			if ( ( '.' !== $file ) && ( '..' !== $file ) ) {
				if ( is_dir( $src . '/' . $file ) ) {
					$this->recurse_copy( $src . '/' . $file,$dst . '/' . $file );
				} else {
					WP_CLI::line( sprintf( 'Copying from %s to %s', $src . '/' . $file, $dst . '/' . $file ) );
					copy( $src . '/' . $file,$dst . '/' . $file );
				}
			}
		}
		closedir( $dir );
	}

	/**
	 * Verify that the required constants for the S3 connections are set.
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
}

WP_CLI::add_command( 'infinite-uploads', 'Infinite_Uploads_WP_CLI_Command' );
