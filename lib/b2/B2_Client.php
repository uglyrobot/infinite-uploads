<?php
/*
 * Based on and adapted from https://github.com/bcarrella/backblaze-b2 licensed GPLv3. Rebuilt as a standalone class that requires only WordPress core as a dependency.
 */
namespace InfiniteUploads\B2;

use WP_Error;
use Exception;

class B2_Client {
	public $version = 2;
	/**
	 * If you setup CNAME records to point to b2 servers (for white-label service)
	 * assign this property with the equivalent URLs
	 * ['f0001.backblazeb2.com' => 'alias01.mydomain.com']
	 *
	 * @var array
	 */
	public $domainAliases = [];
	/**
	 * Lower limit for using large files upload support. More information:
	 * https://www.backblaze.com/b2/docs/large_files.html. Default: 3 GB
	 * Files larger than this value will be uploaded in multiple parts.
	 *
	 * @var int
	 */
	public $largeFileLimit = 3000000000;
	protected $keyId;
	protected $applicationKey;
	protected $accountId;
	protected $authToken;
	protected $apiUrl = '';
	protected $downloadUrl;
	protected $recommendedPartSize;

	/**
	 * Client constructor. Accepts the account ID, application key and an optional array of options.
	 *
	 * @param string $keyId
	 * @param string $applicationKey
	 * @param array  $options
	 *
	 * @throws Exception
	 */
	public function __construct( $keyId, $applicationKey, $options = [] ) {

		$this->keyId          = $keyId;
		$this->applicationKey = $applicationKey;

		if ( empty( $this->keyId ) or empty( $this->applicationKey ) ) {
			throw new \Exception( 'Please provide "keyId" and "applicationKey"' );
		}

		//init auth
		$this->getAuth();
	}

	/**
	 * Get the cached api auth details.
	 *
	 * @param bool $refresh Whether to force refresh the url.
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function getAuth( $refresh = false ) {
		if ( $refresh || false === ( $auth = get_site_transient( 'iu-b2-auth' ) ) ) {
			$auth = $this->authorizeAccount( $this->keyId, $this->applicationKey );
			set_site_transient( 'iu-b2-auth', $auth, DAY_IN_SECONDS );
		}

		$versionPath = '/b2api/v' . $this->version;

		$this->accountId           = $auth->accountId;
		$this->authToken           = $auth->authorizationToken;
		$this->apiUrl              = $auth->apiUrl . $versionPath;
		$this->downloadUrl         = $auth->downloadUrl;
		$this->recommendedPartSize = $auth->recommendedPartSize;

		return $auth;
	}

	/**
	 * Authorize the B2 account in order to get an auth token and API/download URLs.
	 *
	 * @param string $keyId
	 * @param string $applicationKey
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function authorizeAccount( $keyId, $applicationKey ) {
		$baseApiUrl  = 'https://api.backblazeb2.com';
		$versionPath = '/b2api/v' . $this->version;

		return $this->request( 'GET', $baseApiUrl . $versionPath . '/b2_authorize_account', [
			'headers' => [ 'Authorization' => 'Basic ' . base64_encode( $keyId . ':' . $applicationKey ) ],
		] );
	}

	/**
	 * HTTP API request handler
	 *
	 * @param string $method
	 * @param string $uri
	 * @param array  $options
	 * @param bool   $returnJson
	 * @param bool   $wantsGetContents
	 *
	 * @return mixed|string
	 * @throws Exception
	 */
	protected function request( $method, $uri = null, $options = [], $returnJson = true, $wantsGetContents = true ) {

		$args = wp_parse_args( $options, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'method'  => $method,
			'timeout' => 60,
		) );

		if ( empty( $args['headers']['Authorization'] ) && ! empty( $this->authToken ) ) {
			$args['headers']['Authorization'] = $this->authToken;
		}

		if ( isset( $args['json'] ) ) {
			$args['body'] = json_encode( $args['json'] );
			unset( $args['json'] );
		}

		//use default api base only if special url not provided
		$endpoint = $uri;
		if ( substr( $uri, 0, 8 ) !== 'https://' ) {
			$endpoint = $this->apiUrl . $uri;
		}

		var_dump( 'API Call:', $endpoint, $args );

		$result = wp_remote_request( $endpoint, $args );
		if ( ! is_wp_error( $result ) ) {
			$status = wp_remote_retrieve_response_code( $result );
			$body   = wp_remote_retrieve_body( $result );

			var_dump( 'API Response:', $status, $body );

			if ( 200 != $status ) {
				$error = json_decode( $body );
			}

			if ( 200 == $status ) {
				if ( $returnJson ) {
					return json_decode( $body );
				}

				return $body;
			} elseif ( 401 == $status && 'expired_auth_token' == $error->code ) {
				//if auth key is timed out, reissue and try this request again
				$this->getAuth( true );
				$this->request( $method, $uri, $options, $returnJson, $wantsGetContents );
			} else {
				throw new Exception( $error->code . ': ' . $error->message );
			}
		} else {
			throw new Exception( $result->get_error_message() . ': ' . $result->get_error_code() );
		}


		return $result;
	}

	/**
	 * Create a bucket with the given name and type.
	 *
	 * @param array $options
	 *
	 * @return object
	 * @throws Exception
	 */
	public function createBucket( $options ) {
		if ( ! in_array( $options['BucketType'], [ 'allPublic', 'allPrivate' ] ) ) {
			throw new Exception(
				sprintf( 'Bucket type must be %s or %s', 'allPublic', 'allPrivate' )
			);
		}

		$lastVersionOnly = [
			[
				'daysFromHidingToDeleting'  => 1,
				'daysFromUploadingToHiding' => null,
				'fileNamePrefix'            => '',
			],
		];

		return $this->request( 'POST', '/b2_create_bucket', [
			'json' => [
				'accountId'      => $this->accountId,
				'bucketName'     => $options['BucketName'],
				'bucketType'     => $options['BucketType'],
				'lifecycleRules' => ( ( isset( $options['KeepLastVersionOnly'] ) && $options['KeepLastVersionOnly'] ) ? $lastVersionOnly : null ),
			],
		] );
	}

	/**
	 * Updates the type attribute of a bucket by the given ID.
	 *
	 * @param array $options
	 *
	 * @return object
	 * @throws Exception
	 */
	public function updateBucket( $options ) {
		if ( ! in_array( $options['BucketType'], [ 'allPublic', 'allPrivate' ] ) ) {
			throw new Exception(
				sprintf( 'Bucket type must be %s or %s', 'allPublic', 'allPrivate' )
			);
		}

		if ( ! isset( $options['BucketId'] ) && isset( $options['BucketName'] ) ) {
			$options['BucketId'] = $this->getBucketIdFromName( $options['BucketName'] );
		}

		return $this->request( 'POST', '/b2_update_bucket', [
			'json' => [
				'accountId'  => $this->accountId,
				'bucketId'   => $options['BucketId'],
				'bucketType' => $options['BucketType'],
			],
		] );
	}

	/**
	 * Maps the provided bucket name to the appropriate bucket ID.
	 *
	 * @param $name
	 *
	 * @return string|null
	 */
	public function getBucketIdFromName( $name ) {
		$bucket = $this->getBucketFromName( $name );

		if ( isset( $bucket->bucketId ) ) {
			return $bucket->bucketId;
		}

		return null;
	}

	/**
	 * @param $name
	 *
	 * @return object|null
	 * @throws Exception
	 */
	public function getBucketFromName( $name ) {

		$buckets = $this->listBuckets( false, [ 'bucketName' => $name ] );

		foreach ( $buckets as $bucket ) {
			if ( $bucket->bucketName === $name ) {
				return $bucket;
			}
		}

		return null;
	}

	/**
	 * Returns a list of bucket objects representing the buckets on the account.
	 *
	 * @param bool  $refresh Refresh the cache or not, default FALSE
	 * @param array $options List of options for b2_list_bucket request, bucketId, bucketName, bucketTypes
	 *
	 * @return object
	 * @throws Exception
	 */
	public function listBuckets( $refresh = false, $options = [] ) {
		$cacheKey = 'iu-buckets';
		$cacheKey .= ( count( $options ) ? '_' . implode( '_', $options ) : '' );

		$bucketsObj = [];
		if ( $refresh || false === ( $buckets = wp_cache_get( $cacheKey ) ) ) {
			$req            = [
				'json' => [
					'accountId' => $this->accountId,    // required
				],
			];
			$allowedOptions = [ 'bucketId', 'bucketName', 'bucketTypes' ];
			foreach ( $options as $index => $option ) {
				if ( in_array( $index, $allowedOptions ) ) {
					$req['json'][ $index ] = $option;
				}
			}
			$buckets = $this->request( 'POST', '/b2_list_buckets', $req )->buckets;
			wp_cache_set( $cacheKey, $buckets, HOUR_IN_SECONDS );
		}

		return $buckets;
	}

	/**
	 * Deletes the bucket identified by its ID.
	 *
	 * @param array $options
	 *
	 * @return bool
	 *
	 * @todo Clear cache key
	 */
	public function deleteBucket( $options ) {
		if ( ! isset( $options['BucketId'] ) && isset( $options['BucketName'] ) ) {
			$options['BucketId'] = $this->getBucketIdFromName( $options['BucketName'] );
		}

		$this->request( 'POST', '/b2_delete_bucket', [
			'json' => [
				'accountId' => $this->accountId,
				'bucketId'  => $options['BucketId'],
			],
		] );

		return true;
	}

	/**
	 * Uploads a file to a bucket and returns a File object.
	 *
	 * @param array $options
	 *
	 * @return object
	 */
	public function upload( $options ) {
		// Clean the path if it starts with /.
		if ( substr( $options['FileName'], 0, 1 ) === '/' ) {
			$options['FileName'] = ltrim( $options['FileName'], '/' );
		}

		if ( ! isset( $options['BucketId'] ) && isset( $options['BucketName'] ) ) {
			$options['BucketId'] = $this->getBucketIdFromName( $options['BucketName'] );
		}

		if ( ! isset( $options['FileLastModified'] ) ) {
			$options['FileLastModified'] = round( microtime( true ) * 1000 );
		}

		if ( ! isset( $options['FileContentType'] ) ) {
			$options['FileContentType'] = 'b2/x-auto';
		}

		list( $options['hash'], $options['size'] ) = $this->getFileHashAndSize( $options['Body'] );

		if ( $options['size'] <= $this->largeFileLimit && $options['size'] <= $this->recommendedPartSize ) {
			return $this->uploadStandardFile( $options );
		} else {
			return $this->uploadLargeFile( $options );
		}
	}

	/**
	 * Calculate hash and size of file/stream. If $offset and $partSize is given return
	 * hash and size of this chunk
	 *
	 * @param      $content
	 * @param int  $offset
	 * @param null $partSize
	 *
	 * @return array
	 */
	protected function getFileHashAndSize( $data, $offset = 0, $partSize = null ) {
		if ( ! $partSize ) {
			if ( is_resource( $data ) ) {
				// We need to calculate the file's hash incrementally from the stream.
				$context = hash_init( 'sha1' );
				hash_update_stream( $context, $data );
				$hash = hash_final( $context );
				// Similarly, we have to use fstat to get the size of the stream.
				$size = fstat( $data )['size'];
				// Rewind the stream before passing it to the HTTP client.
				rewind( $data );
			} else {
				// We've been given a simple string body, it's super simple to calculate the hash and size.
				$hash = sha1( $data );
				$size = mb_strlen( $data, '8bit' );
			}
		} else {
			$dataPart = $this->getPartOfFile( $data, $offset, $partSize );
			$hash     = sha1( $dataPart );
			$size     = mb_strlen( $dataPart, '8bit' );
		}

		return array( $hash, $size );
	}

	/**
	 * Return selected part of file
	 *
	 * @param $data
	 * @param $offset
	 * @param $partSize
	 *
	 * @return bool|string
	 */
	protected function getPartOfFile( $data, $offset, $partSize ) {
		// Get size and hash of one data chunk
		if ( is_resource( $data ) ) {
			// Get data chunk
			fseek( $data, $offset );
			$dataPart = fread( $data, $partSize );
			// Rewind the stream before passing it to the HTTP client.
			rewind( $data );
		} else {
			$dataPart = substr( $data, $offset, $partSize );
		}

		return $dataPart;
	}

	/**
	 * Upload single file (smaller than 3 GB)
	 *
	 * @param array $options
	 *
	 * @return object
	 * @throws Exception
	 */
	protected function uploadStandardFile( $options = array() ) {
		// Retrieve the URL that we should be uploading to.
		$response = $this->getUploadUrl( $options['BucketId'] );

		$uploadEndpoint  = $response->uploadUrl;
		$uploadAuthToken = $response->authorizationToken;

		$response = $this->request( 'POST', $uploadEndpoint, [
			'headers' => [
				'Authorization'                      => $uploadAuthToken,
				'Content-Type'                       => $options['FileContentType'],
				'Content-Length'                     => $options['size'],
				'X-Bz-File-Name'                     => $options['FileName'],
				'X-Bz-Content-Sha1'                  => $options['hash'],
				'X-Bz-Info-src_last_modified_millis' => $options['FileLastModified'],
			],
			'body'    => $options['Body'],
		] );

		//TODO if specific error codes bad_auth_token, expired_auth_token, service_unavailable returned force refresh uploadUrl and try again.

		return $response;
	}

	/**
	 * Get the cached upload auth for single files. As per docs should be cached and valid for up to 24hrs unless a specific error is returned during upload.
	 *
	 * @param string $bucketId
	 * @param bool   $refresh Whether to force refresh the url.
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function getUploadUrl( $bucketId, $refresh = false ) {
		$key = 'iu-b2-upload' . $bucketId;
		if ( $refresh || false === ( $auth = get_site_transient( $key ) ) ) {
			$auth = $this->request( 'POST', '/b2_get_upload_url', [
				'json' => [
					'bucketId' => $bucketId,
				],
			] );
			set_site_transient( $key, $auth, DAY_IN_SECONDS );
		}

		return $auth;
	}

	/**
	 * Upload large file. Large files will be uploaded in chunks of recommendedPartSize bytes (usually 100MB each)
	 *
	 * @param array $options
	 *
	 * @return object $file
	 *
	 * @throws Exception
	 * @todo Fetch multiple upload part urls and upload in parallel.
	 */
	protected function uploadLargeFile( $options ) {
		// Prepare for uploading the parts of a large file.
		$response = $this->request( 'POST', '/b2_start_large_file', [
			'json' => [
				'bucketId'    => $options['BucketId'],
				'fileName'    => $options['FileName'],
				'contentType' => $options['FileContentType'],
				/**
				 * 'fileInfo' => [
				 * 'src_last_modified_millis' => $options['FileLastModified'],
				 * 'large_file_sha1' => $options['hash']
				 * ]
				 **/
			],
		] );
		$fileId   = $response->fileId;

		$partsCount = ceil( $options['size'] / $this->recommendedPartSize );

		$hashParts = [];
		for ( $i = 1; $i <= $partsCount; $i ++ ) {
			$bytesSent = ( $i - 1 ) * $this->recommendedPartSize;
			$bytesLeft = $options['size'] - $bytesSent;
			$partSize  = ( $bytesLeft > $this->recommendedPartSize ) ? $this->recommendedPartSize : $bytesLeft;

			// Retrieve the URL that we should be uploading to.
			$response = $this->request( 'POST', '/b2_get_upload_part_url', [
				'json' => [
					'fileId' => $fileId,
				],
			] );

			$uploadEndpoint  = $response->uploadUrl;
			$uploadAuthToken = $response->authorizationToken;

			list( $hash, $size ) = $this->getFileHashAndSize( $options['Body'], $bytesSent, $partSize );
			$hashParts[] = $hash;

			$response = $this->request( 'POST', $uploadEndpoint, [
				'headers' => [
					'Authorization'     => $uploadAuthToken,
					'X-Bz-Part-Number'  => $i,
					'Content-Length'    => $size,
					'X-Bz-Content-Sha1' => $hash,
				],
				'body'    => $this->getPartOfFile( $options['Body'], $bytesSent, $partSize ),
			] );
		}

		// Finish upload of large file
		return $this->request( 'POST', '/b2_finish_large_file', [
			'json' => [
				'fileId'        => $fileId,
				'partSha1Array' => $hashParts,
			],
		] );
	}

	/**
	 * @param      $file
	 * @param bool $appendToken
	 * @param int  $tokenTimeout
	 *
	 * @return string
	 */
	public function getDownloadUrlForFile( $file, $appendToken = false, $tokenTimeout = 60 ) {
		return $this->getDownloadUrl( $file->bucketId, $file->fileName, $appendToken, $tokenTimeout );
	}

	/**
	 * @param string $bucketId
	 * @param string $filePath
	 * @param bool   $appendToken
	 * @param int    $tokenTimeout
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getDownloadUrl( $bucketId, $filePath, $appendToken = false, $tokenTimeout = 60 ) {
		$bucket = $this->getBucketFromId( $bucketId );

		$baseUrl = strtr( $this->downloadUrl, $this->domainAliases );
		$path    = $baseUrl . '/file/' . $bucket->getName() . '/' . $filePath;

		if ( $appendToken ) {
			$path .= '?Authorization='
			         . $this->getDownloadAuthorization( $bucket->bucketId, dirname( $filePath ) . '/', $tokenTimeout );
		}

		return $path;
	}

	/**
	 * @param $bucketId
	 *
	 * @return object|null
	 * @throws Exception
	 */
	public function getBucketFromId( $bucketId ) {
		$buckets = $this->listBuckets( false, [ 'bucketId' => $bucketId ] );

		foreach ( $buckets as $bucket ) {
			if ( $bucket->bucketId === $bucketId ) {
				return $bucket;
			}
		}

		return null;
	}

	/**
	 * @param     $bucket
	 * @param     $path
	 * @param int $validDuration
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getDownloadAuthorization( $bucketId, $path, $validDuration = 60 ) {

		$response = $this->request( 'POST', '/b2_get_download_authorization', [
			'json' => [
				'bucketId'               => $bucketId,
				'fileNamePrefix'         => $path,
				'validDurationInSeconds' => $validDuration,
			],
		] );

		return $response->authorizationToken;
	}

	/**
	 * Download a file from a B2 bucket.
	 *
	 * @param array $options
	 *
	 * @return bool|mixed|string
	 * @throws Exception
	 */
	public function download( $options ) {
		$requestUrl     = null;
		$requestOptions = [ 'timeout' => 300 ];

		if ( isset( $options['FileId'] ) ) {
			$requestUrl = $this->downloadUrl . '/b2api/v' . $this->version . '/b2_download_file_by_id';
			$requestUrl = add_query_arg( [ 'fileId' => $options['FileId'] ], $requestUrl );
		} else {
			if ( ! isset( $options['BucketName'] ) && isset( $options['BucketId'] ) ) {
				$options['BucketName'] = $this->getBucketNameFromId( $options['BucketId'] );
			}

			$requestUrl = sprintf( '%s/file/%s/%s', $this->downloadUrl, $options['BucketName'], $options['FileName'] );
		}

		if ( isset( $options['stream'] ) ) {
			$requestOptions['stream'] = $options['stream'];
			if ( isset( $options['SaveAs'] ) ) {
				$requestOptions['filename'] = $options['SaveAs'];
			}

			$response = $this->request( 'GET', $requestUrl, $requestOptions, false, false );
		} else {
			$response = $this->request( 'GET', $requestUrl, $requestOptions, false );
		}

		return isset( $options['SaveAs'] ) ? true : $response;
	}

	/**
	 * Maps the provided bucket ID to the appropriate bucket name.
	 *
	 * @param $id
	 *
	 * @return string|null
	 * @throws Exception
	 */
	public function getBucketNameFromId( $id ) {
		$bucket = $this->getBucketFromId( $id );

		if ( isset( $bucket->bucketName ) ) {
			return $bucket->bucketName;
		}

		return null;
	}

	public function accelRedirectData( $options ) {
		$parsed = parse_url( $this->downloadUrl );

		return [
			'host'  => $parsed['host'],
			'query' => sprintf( "fileId=%s", $options['FileId'] ),
		];
	}

	/**
	 * Test whether a file exists in B2 for the given bucket.
	 *
	 * @param array $options FileId or BucketName and FileName
	 *
	 * @return boolean
	 *
	 * @throws Exception
	 */
	public function fileExists( $options ) {
		try {
			$file = $this->getFile( $options );
		} catch ( exception $e ) {
			return false;
		}

		return $file;
	}

	/**
	 * Returns a single File object representing a file stored on B2.
	 *
	 * @param array $options
	 *
	 * @return object
	 * @throws Exception If no file id was provided and BucketName + FileName does not resolve to a file, a Exception is thrown.
	 */
	public function getFile( $options ) {
		if ( ! isset( $options['FileId'] ) && isset( $options['BucketName'] ) && isset( $options['FileName'] ) ) {
			$options['FileId'] = $this->getFileIdFromBucketAndFileName( $options['BucketName'], $options['FileName'] );

			if ( ! $options['FileId'] ) {
				throw new Exception();
			}
		}

		return $this->request( 'POST', '/b2_get_file_info', [
			'json' => [
				'fileId' => $options['FileId'],
			],
		] );
	}

	protected function getFileIdFromBucketAndFileName( $bucketName, $fileName ) {
		$files = $this->listFiles( [
			'BucketName' => $bucketName,
			'FileName'   => $fileName,
		] );

		foreach ( $files as $file ) {
			if ( $file->fileName === $fileName ) {
				return $file->fileId;
			}
		}

		return null;
	}

	/**
	 * Retrieve a collection of File objects representing the files stored inside a bucket.
	 *
	 * @param array $options
	 *
	 * @return array
	 * @throws Exception
	 */
	public function listFiles( $options ) {
		// if FileName is set, we only attempt to retrieve information about that single file.
		$fileName = ! empty( $options['FileName'] ) ? $options['FileName'] : null;

		$nextFileName = null;
		$maxFileCount = 1000;
		$files        = [];

		if ( ! isset( $options['BucketId'] ) && isset( $options['BucketName'] ) ) {
			$options['BucketId'] = $this->getBucketIdFromName( $options['BucketName'] );
		}

		if ( $fileName ) {
			$nextFileName = $fileName;
			$maxFileCount = 1;
		}

		// B2 returns, at most, 1000 files per "page". Loop through the pages and compile an array of File objects.
		while ( true ) {
			$response = $this->request( 'POST', '/b2_list_file_names', [
				'json' => [
					'bucketId'      => $options['BucketId'],
					'startFileName' => $nextFileName,
					'maxFileCount'  => $maxFileCount,
				],
			] );

			foreach ( $response->files as $file ) {
				// if we have a file name set, only retrieve information if the file name matches
				if ( ! $fileName || ( $fileName === $file['fileName'] ) ) {
					$files[] = $file;
				}
			}

			if ( $fileName || $response->nextFileName === null ) {
				// We've got all the files - break out of loop.
				break;
			}

			$nextFileName = $response->nextFileName;
		}

		return $files;
	}

	/**
	 * Deletes the file identified by ID from Backblaze B2.
	 *
	 * @param array $options
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function deleteFile( $options ) {
		if ( ! isset( $options['FileName'] ) ) {
			$file = $this->getFile( $options );

			$options['FileName'] = $file->fileName;
		}

		if ( ! isset( $options['FileId'] ) && isset( $options['BucketName'] ) && isset( $options['FileName'] ) ) {
			$file = $this->getFile( $options );

			$options['FileId'] = $file->fileId;
		}

		$this->request( 'POST', '/b2_delete_file_version', [
			'json' => [
				'fileName' => $options['FileName'],
				'fileId'   => $options['FileId'],
			],
		] );

		return true;
	}

	/**
	 * @param Key $key
	 *
	 * @throws Exception
	 */
	public function createKey( $key ) {
		throw new Exception( __FUNCTION__ . ' has not been implemented yet' );
	}
}
