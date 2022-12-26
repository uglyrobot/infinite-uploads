<?php


use UglyRobot\Infinite_Uploads\Aws\S3\S3ClientInterface;
use UglyRobot\Infinite_Uploads\Aws\CacheInterface;
use UglyRobot\Infinite_Uploads\Aws\LruArrayCache;
use UglyRobot\Infinite_Uploads\Aws\Result;
use UglyRobot\Infinite_Uploads\Aws\S3\Exception\S3Exception;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7\Stream;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7\CachingStream;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
use UglyRobot\Infinite_Uploads\Aws;

/**
 * Amazon S3 stream wrapper to use "iu://<bucket>/<key>" files with PHP
 * streams, supporting "r", "w", "a", "x".
 *
 * # Opening "r" (read only) streams:
 *
 * Read only streams are truly streaming by default and will not allow you to
 * seek. This is because data read from the stream is not kept in memory or on
 * the local filesystem. You can force a "r" stream to be seekable by setting
 * the "seekable" stream context option true. This will allow true streaming of
 * data from Amazon S3, but will maintain a buffer of previously read bytes in
 * a 'php://temp' stream to allow seeking to previously read bytes from the
 * stream.
 *
 * You may pass any GetObject parameters as 's3' stream context options. These
 * options will affect how the data is downloaded from Amazon S3.
 *
 * # Opening "w" and "x" (write only) streams:
 *
 * Because Amazon S3 requires a Content-Length header, write only streams will
 * maintain a 'php://temp' stream to buffer data written to the stream until
 * the stream is flushed (usually by closing the stream with fclose).
 *
 * You may pass any PutObject parameters as 's3' stream context options. These
 * options will affect how the data is uploaded to Amazon S3.
 *
 * When opening an "x" stream, the file must exist on Amazon S3 for the stream
 * to open successfully.
 *
 * # Opening "a" (write only append) streams:
 *
 * Similar to "w" streams, opening append streams requires that the data be
 * buffered in a "php://temp" stream. Append streams will attempt to download
 * the contents of an object in Amazon S3, seek to the end of the object, then
 * allow you to append to the contents of the object. The data will then be
 * uploaded using a PutObject operation when the stream is flushed (usually
 * with fclose).
 *
 * You may pass any GetObject and/or PutObject parameters as 's3' stream
 * context options. These options will affect how the data is downloaded and
 * uploaded from Amazon S3.
 *
 * Stream context options:
 *
 * - "seekable": Set to true to create a seekable "r" (read only) stream by
 *   using a php://temp stream buffer
 * - For "unlink" only: Any option that can be passed to the DeleteObject
 *   operation
 */
class Infinite_Uploads_Stream_Wrapper {
	/** @var resource|null Stream context (this is set by PHP) */
	public $context;

	/** @var StreamInterface Underlying stream resource */
	private $body;

	/** @var int Size of the body that is opened */
	private $size;

	/** @var array Hash of opened stream parameters */
	private $params = [];

	/** @var string Mode in which the stream was opened */
	private $mode;

	/** @var \Iterator Iterator used with opendir() related calls */
	private $objectIterator;

	/** @var string The bucket that was opened when opendir() was called */
	private $openedBucket;

	/** @var string The prefix of the bucket that was opened with opendir() */
	private $openedBucketPrefix;

	/** @var string Opened bucket path */
	private $openedPath;

	/** @var CacheInterface Cache for object and dir lookups */
	private $cache;

	/** @var string The opened protocol (e.g., "s3") */
	private $protocol = 'iu';

	/** @var cache for last file, so it doesn't need to be fetched again for immediate edits */
	private $last_file = [];

	/**
	 * Register the 'iu://' stream wrapper
	 *
	 * @param S3ClientInterface $client   Client to use with the stream wrapper
	 * @param string            $protocol Protocol to register as.
	 * @param CacheInterface    $cache    Default cache for the protocol.
	 */
	public static function register(
		S3ClientInterface $client,
		$protocol = 'iu',
		CacheInterface $cache = null
	) {
		if ( in_array( $protocol, stream_get_wrappers() ) ) {
			stream_wrapper_unregister( $protocol );
		}

		// Set the client passed in as the default stream context client
		stream_wrapper_register( $protocol, get_called_class(), STREAM_IS_URL );
		$default                        = stream_context_get_options( stream_context_get_default() );
		$default[ $protocol ]['client'] = $client;

		if ( $cache ) {
			$default[ $protocol ]['cache'] = $cache;
		} elseif ( ! isset( $default[ $protocol ]['cache'] ) ) {
			// Set a default cache adapter.
			$default[ $protocol ]['cache'] = new LruArrayCache();
		}

		stream_context_set_default( $default );
	}

	public function stream_close() {
		$this->body = $this->cache = null;
	}

	public function stream_open( $path, $mode, $options, &$opened_path ) {
		$this->initProtocol( $path );
		$this->params = $this->getBucketKey( $path );
		$this->mode   = rtrim( $mode, 'bt' );

		if ( $errors = $this->validate( $path, $this->mode ) ) {
			return $this->triggerError( $errors );
		}

		return $this->boolCall( function () use ( $path ) {
			switch ( $this->mode ) {
				case 'r':
					return $this->openReadStream( $path );
				case 'a':
					return $this->openAppendStream( $path );
				default:
					return $this->openWriteStream( $path );
			}
		} );
	}

	/**
	 * Parse the protocol out of the given path.
	 *
	 * @param $path
	 */
	private function initProtocol( $path ) {
		$parts          = explode( '://', $path, 2 );
		$this->protocol = $parts[0] ?: 'iu';
	}

	private function getBucketKey( $path ) {
		// Remove the protocol
		$parts = explode( '://', $path );
		// Get the bucket, key
		$parts = explode( '/', $parts[1], 2 );

		return [
			'Bucket' => $parts[0],
			'Key'    => isset( $parts[1] ) ? $parts[1] : null,
		];
	}

	/**
	 * Validates the provided stream arguments for fopen and returns an array
	 * of errors.
	 */
	private function validate( $path, $mode ) {
		$errors = [];

		if ( ! $this->getOption( 'Key' ) ) {
			$errors[] = 'Cannot open a bucket. You must specify a path in the '
			            . 'form of iu://bucket/key';
		}

		if ( ! in_array( $mode, [ 'r', 'w', 'a', 'x' ] ) ) {
			$errors[] = "Mode not supported: {$mode}. "
			            . "Use one 'r', 'w', 'a', or 'x'.";
		}

		// When using mode "x" validate if the file exists before attempting
		// to read
		if ( $mode == 'x' ) {
			$this->debug( 'doesObjectExist', $this->getOption( 'Key' ) );
		}
		if ( $mode == 'x' &&
		     $this->getClient()->doesObjectExist(
			     $this->getOption( 'Bucket' ),
			     $this->getOption( 'Key' ),
			     $this->getOptions( true )
		     )
		) {
			$errors[] = "{$path} already exists on Infinite Uploads";
		}

		return $errors;
	}

	/**
	 * Get a specific stream context option
	 *
	 * @param string $name Name of the option to retrieve
	 *
	 * @return mixed|null
	 */
	private function getOption( $name ) {
		$options = $this->getOptions();

		return isset( $options[ $name ] ) ? $options[ $name ] : null;
	}

	/**
	 * Get the stream context options available to the current stream
	 *
	 * @param bool $removeContextData Set to true to remove contextual kvp's
	 *                                like 'client' from the result.
	 *
	 * @return array
	 */
	private function getOptions( $removeContextData = false ) {
		// Context is not set when doing things like stat
		if ( $this->context === null ) {
			$options = [];
		} else {
			$options = stream_context_get_options( $this->context );
			$options = isset( $options[ $this->protocol ] )
				? $options[ $this->protocol ]
				: [];
		}

		$default = stream_context_get_options( stream_context_get_default() );
		$default = isset( $default[ $this->protocol ] )
			? $default[ $this->protocol ]
			: [];
		$result  = $this->params + $options + $default;

		if ( $removeContextData ) {
			unset( $result['client'], $result['seekable'], $result['cache'] );
		}

		return $result;
	}

	/**
	 * Writes info to debug log if feature is defined.
	 *
	 * @param string $action What cloud API call is being performed.
	 * @param string $key    S3 path or prefix (key).
	 */
	private function debug( $action, $key ) {
		if ( defined( 'INFINITE_UPLOADS_SW_DEBUG' ) && INFINITE_UPLOADS_SW_DEBUG ) {
			$instance = $this->getOption( 'iup_instance' );
			$instance->stream_api_call_count['total'] ++;
			if ( isset( $instance->stream_api_call_count['commands'][ $action ] ) ) {
				$instance->stream_api_call_count['commands'][ $action ] ++;
			} else {
				$instance->stream_api_call_count['commands'][ $action ] = 1;
			}

			$error   = new Error;
			$trace   = $error->getTraceAsString();
			$pattern = '/' . preg_quote( WP_PLUGIN_DIR, '/' ) . '\/(?:(?!infinite\-uploads)([^\/]+))\//';
			preg_match( $pattern, $trace, $matches );
			if ( isset( $matches[1] ) ) {
				if ( isset( $instance->stream_plugin_api_call_count[ $matches[1] ] ) ) {
					$instance->stream_plugin_api_call_count[ $matches[1] ]['total'] ++;
					if ( isset( $instance->stream_plugin_api_call_count[ $matches[1] ]['commands'][ $action ] ) ) {
						$instance->stream_plugin_api_call_count[ $matches[1] ]['commands'][ $action ] ++;
					} else {
						$instance->stream_plugin_api_call_count[ $matches[1] ]['commands'][ $action ] = 1;
					}
				} else {
					$instance->stream_plugin_api_call_count[ $matches[1] ] = [ 'total' => 1, 'commands' => [ $action => 1 ] ];
				}
			}

			$log = "[INFINITE_UPLOADS Stream Debug] $action $key";
			if ( defined( 'INFINITE_UPLOADS_SW_DEBUG_BACKTRACE' ) && INFINITE_UPLOADS_SW_DEBUG_BACKTRACE ) {
				// Remove first item from backtrace as it's this function which is redundant.
				$trace = preg_replace( "/^#0\s+[^\n]*\n/", '', $trace, 1 );

				// Renumber backtrace items.
				$trace = preg_replace_callback( '/^#(\d+)(.*)$/', function ( $matches ) {
					return "#" . max( absint( $matches[1] ) - 1, 1 ) . $matches[2] . PHP_EOL;
				}, $trace );

				$log .= PHP_EOL . $trace;
			}
			error_log( $log );
		}
	}

	/**
	 * Gets the client from the stream context
	 *
	 * @return S3ClientInterface
	 * @throws \RuntimeException if no client has been configured
	 */
	private function getClient() {
		if ( ! $client = $this->getOption( 'client' ) ) {
			throw new \RuntimeException( 'No client in stream context' );
		}

		return $client;
	}

	/**
	 * Trigger one or more errors
	 *
	 * @param string|array $errors Errors to trigger
	 * @param mixed        $flags  If set to STREAM_URL_STAT_QUIET, then no
	 *                             error or exception occurs
	 *
	 * @return bool Returns false
	 * @throws \RuntimeException if throw_errors is true
	 */
	private function triggerError( $errors, $flags = null ) {
		// This is triggered with things like file_exists()
		if ( $flags & STREAM_URL_STAT_QUIET ) {
			return $flags & STREAM_URL_STAT_LINK
				// This is triggered for things like is_link()
				? $this->formatUrlStat( false )
				: false;
		}

		// This is triggered when doing things like lstat() or stat()
		trigger_error( implode( "\n", (array) $errors ), E_USER_WARNING );

		return false;
	}

	/**
	 * Prepare a url_stat result array
	 *
	 * @param string|array $result Data to add
	 *
	 * @return array Returns the modified url_stat result
	 */
	private function formatUrlStat( $result = null ) {
		$stat = $this->getStatTemplate();
		switch ( gettype( $result ) ) {
			case 'NULL':
			case 'string':
				// Directory with 0777 access - see "man 2 stat".
				$stat['mode'] = $stat[2] = 0040777;
				break;
			case 'array':
				// Regular file with 0777 access - see "man 2 stat".
				$stat['mode'] = $stat[2] = 0100777;
				// Pluck the content-length if available.
				if ( isset( $result['ContentLength'] ) ) {
					$stat['size'] = $stat[7] = $result['ContentLength'];
				} elseif ( isset( $result['Size'] ) ) {
					$stat['size'] = $stat[7] = $result['Size'];
				}
				if ( isset( $result['LastModified'] ) ) {
					// ListObjects or HeadObject result
					$stat['mtime'] = $stat[9] = $stat['ctime'] = $stat[10]
						= strtotime( $result['LastModified'] );
				}
		}

		return $stat;
	}

	/**
	 * Gets a URL stat template with default values
	 *
	 * @return array
	 */
	private function getStatTemplate() {
		return [
			0         => 0,
			'dev'     => 0,
			1         => 0,
			'ino'     => 0,
			2         => 0,
			'mode'    => 0,
			3         => 0,
			'nlink'   => 0,
			4         => 0,
			'uid'     => 0,
			5         => 0,
			'gid'     => 0,
			6         => - 1,
			'rdev'    => - 1,
			7         => 0,
			'size'    => 0,
			8         => 0,
			'atime'   => 0,
			9         => 0,
			'mtime'   => 0,
			10        => 0,
			'ctime'   => 0,
			11        => - 1,
			'blksize' => - 1,
			12        => - 1,
			'blocks'  => - 1,
		];
	}

	/**
	 * Invokes a callable and triggers an error if an exception occurs while
	 * calling the function.
	 *
	 * @param callable $fn
	 * @param int      $flags
	 *
	 * @return bool
	 */
	private function boolCall( callable $fn, $flags = null ) {
		try {
			return $fn();
		} catch ( \Exception $e ) {
			return $this->triggerError( $e->getMessage(), $flags );
		}
	}

	private function openReadStream( $path ) {
		//check cache
		if ( null !== ( $object = $this->cacheObjectGet( $path ) ) ) {
			$this->body = Psr7\Utils::streamFor( $object ); //make it a stream
			$this->size = $this->body->getSize();
		} else {
			$this->debug( 'GetObject (stream)', $this->getOption( 'Key' ) );
			$client                     = $this->getClient();
			$command                    = $client->getCommand( 'GetObject', $this->getOptions( true ) );
			$command['@http']['stream'] = true;
			$result                     = $client->execute( $command );
			$this->size                 = $result['ContentLength'];
			$this->body                 = $result['Body'];
			//$this->cacheObjectSet( $path, $this->body ); //need to figure out how to wait for this to finish downloading
		}

		// Wrap the body in a caching entity body if seeking is allowed
		if ( $this->getOption( 'seekable' ) && ! $this->body->isSeekable() ) {
			$this->body = new CachingStream( $this->body );
		}

		return true;
	}

	/**
	 * Get cached put/get object
	 *
	 * @param string $key Cache key
	 *
	 * @return mixed|null
	 */
	private function cacheObjectGet( $key ) {
		$instance = $this->getOption( 'iup_instance' );
		if ( isset( $instance->stream_file_cache[ $key ] ) ) {
			$this->debug_cache( 'Object HIT', $key );

			return $instance->stream_file_cache[ $key ];
		}

		$this->debug_cache( 'Object MISS', $key );

		return null;
	}

	/**
	 * Writes info to debug log if feature is defined.
	 *
	 * @param string $action Hit, Miss, Set, Delete.
	 * @param string $key    S3 path or prefix (key).
	 */
	private function debug_cache( $action, $key ) {
		if ( defined( 'INFINITE_UPLOADS_SW_DEBUG_CACHE' ) && INFINITE_UPLOADS_SW_DEBUG_CACHE ) {
			$log = "[INFINITE_UPLOADS Stream Cache] $action $key";
			error_log( $log );
		}
	}

	private function openAppendStream( $path ) {
		try {
			//check cache
			if ( null !== ( $object = $this->cacheObjectGet( $path ) ) ) {
				$this->body = Psr7\Utils::streamFor( $object ); //make it a stream
			} else {
				// Get the body of the object and seek to the end of the stream
				$this->debug( 'GetObject (append stream)', $this->getOption( 'Key' ) );
				$client     = $this->getClient();
				$this->body = $client->getObject( $this->getOptions( true ) )['Body'];
				$this->cacheObjectSet( $path, Psr7\Utils::copyToString( $this->body ) ); //this is untested
			}
			$this->body->seek( 0, SEEK_END );

			return true;
		} catch ( S3Exception $e ) {
			// The object does not exist, so use a simple write stream
			return $this->openWriteStream( $path );
		}
	}

	/**
	 * Cache last put/get object till the end of the request. This prevents multiple GetObject requests for the same object.
	 *
	 * @param string $key  Cache key
	 * @param string $body Convert any streams to string before caching
	 */
	private function cacheObjectSet( $key, $body ) {
		//don't cache files that are too big in memory
		if ( strlen( $body ) > INFINITE_UPLOADS_STREAM_CACHE_MAX_BYTES ) {
			unset( $body );

			return;
		}

		$instance                            = $this->getOption( 'iup_instance' );
		$instance->stream_file_cache         = []; //only keep most recent file for now
		$instance->stream_file_cache[ $key ] = $body;
		$this->debug_cache( 'Object SET', $key );
	}

	private function openWriteStream( $path ) {
		$this->body = new Stream( fopen( 'php://temp', 'r+' ) );

		return true;
	}

	public function stream_eof() {
		return $this->body->eof();
	}

	public function stream_flush() {
		if ( $this->mode == 'r' ) {
			return false;
		}

		if ( $this->body->isSeekable() ) {
			$this->body->seek( 0 );
		}
		$params         = $this->getOptions( true );
		$params['Body'] = $this->body;

		// Attempt to guess the ContentType of the upload based on the
		// file extension of the key. Added by Joe Hoyle
		if ( ! isset( $params['ContentType'] ) &&
		     ( $type = Psr7\MimeType::fromFilename( $params['Key'] ) )
		) {
			$params['ContentType'] = $type;
		}

		/// Expires:
		if ( defined( 'INFINITE_UPLOADS_HTTP_EXPIRES' ) ) {
			$params['Expires'] = INFINITE_UPLOADS_HTTP_EXPIRES;
		}
		// Cache-Control:
		if ( defined( 'INFINITE_UPLOADS_HTTP_CACHE_CONTROL' ) ) {
			if ( is_numeric( INFINITE_UPLOADS_HTTP_CACHE_CONTROL ) ) {
				$params['CacheControl'] = 'max-age=' . INFINITE_UPLOADS_HTTP_CACHE_CONTROL;
			} else {
				$params['CacheControl'] = INFINITE_UPLOADS_HTTP_CACHE_CONTROL;
			}
		}

		/**
		 * Filter the parameters passed to object storage via AWS PHP SDK
		 * Theses are the parameters passed to S3Client::putObject()
		 * See; https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#putobject
		 *
		 * @param  {array} $params S3Client::putObject parameters.
		 *
		 * @return {array} $params S3Client::putObject parameters.
		 * @since  1.0
		 * @hook   infinite_uploads_putObject_params
		 *
		 */
		$params = apply_filters( 'infinite_uploads_putObject_params', $params );

		return $this->boolCall( function () use ( $params ) {
			$this->debug( 'PutObject', $params['Key'] );
			$file = Psr7\Utils::copyToString( $params['Body'] );
			$bool = (bool) $this->getClient()->putObject( $params );

			//Cache the stat for this file so we don't have to do another HeadObject in the same request
			$cache_key = "iu://{$params['Bucket']}/{$params['Key']}";
			if ( $bool ) {
				$this->getCacheStorage()->set( $cache_key, $this->formatUrlStat( [ 'ContentLength' => $params['Body']->getSize(), 'LastModified' => time() ] ) );
				$this->debug_cache( 'SET', $cache_key );
				$this->cacheObjectSet( $cache_key, $file );
			}
			unset( $file );

			/**
			 * Action when a new object has been uploaded to cloud storage.
			 *
			 * @param {array} $params S3Client::putObject parameters used to upload the object.
			 * @param {boolean} $result Whether the putObject request succeeded or failed.
			 *
			 * @since 1.0
			 * @hook  infinite_uploads_putObject
			 *
			 */
			do_action( 'infinite_uploads_putObject', $params, $bool );

			return $bool;
		} );
	}

	/**
	 * @return LruArrayCache
	 */
	private function getCacheStorage() {
		if ( ! $this->cache ) {
			$this->cache = $this->getOption( 'cache' ) ?: new LruArrayCache();
		}

		return $this->cache;
	}

	public function stream_read( $count ) {
		return $this->body->read( $count );
	}

	public function stream_seek( $offset, $whence = SEEK_SET ) {
		return ! $this->body->isSeekable()
			? false
			: $this->boolCall( function () use ( $offset, $whence ) {
				$this->body->seek( $offset, $whence );

				return true;
			} );
	}

	public function stream_metadata( $path, $option, $value ) {
		return false;
	}

	public function stream_tell() {
		return $this->boolCall( function () {
			return $this->body->tell();
		} );
	}

	public function stream_write( $data ) {
		return $this->body->write( $data );
	}

	public function stream_stat() {
		$stat    = $this->getStatTemplate();
		$stat[7] = $stat['size'] = $this->getSize();
		$stat[2] = $stat['mode'] = $this->mode;

		return $stat;
	}

	/**
	 * Returns the size of the opened object body.
	 *
	 * @return int|null
	 */
	private function getSize() {
		$size = $this->body->getSize();

		return $size !== null ? $size : $this->size;
	}

	/**
	 * Provides information for is_dir, is_file, filesize, etc. Works on
	 * buckets, keys, and prefixes.
	 *
	 * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
	 */
	public function url_stat( $path, $flags ) {
		$this->initProtocol( $path );

		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		/**
		 * If the file is actually just a path to a directory
		 * then return it as always existing. This is to work
		 * around wp_upload_dir doing file_exists checks on
		 * the uploads directory on every page load.
		 *
		 * Added by Joe Hoyle
		 */
		if ( ! $extension ) {
			return [
				0         => 0,
				'dev'     => 0,
				1         => 0,
				'ino'     => 0,
				2         => 16895,
				'mode'    => 16895,
				3         => 0,
				'nlink'   => 0,
				4         => 0,
				'uid'     => 0,
				5         => 0,
				'gid'     => 0,
				6         => - 1,
				'rdev'    => - 1,
				7         => 0,
				'size'    => 0,
				8         => 0,
				'atime'   => 0,
				9         => 0,
				'mtime'   => 0,
				10        => 0,
				'ctime'   => 0,
				11        => - 1,
				'blksize' => - 1,
				12        => - 1,
				'blocks'  => - 1,
			];
		}

		// Some paths come through as IU:// for some reason.
		$split = explode( '://', $path );
		$path  = strtolower( $split[0] ) . '://' . $split[1];

		// Check if this path is in the url_stat cache
		if ( null !== ( $value = $this->getCacheStorage()->get( $path ) ) ) {
			$this->debug_cache( 'HIT', $path );

			return $value;
		}

		$this->debug_cache( 'MISS', $path );
		$stat = $this->createStat( $path, $flags );

		//cache missing objects as well
		$this->getCacheStorage()->set( $path, $stat );
		$this->debug_cache( 'SET', $path );

		return $stat;
	}

	private function createStat( $path, $flags ) {
		$this->initProtocol( $path );
		$parts = $this->withPath( $path );

		if ( ! $parts['Key'] ) {
			return $this->statDirectory( $parts, $path, $flags );
		}

		return $this->boolCall( function () use ( $parts, $path ) {
			try {
				$this->debug( 'HeadObject', $parts['Key'] );
				$result = $this->getClient()->headObject( $parts );
				if ( substr( $parts['Key'], - 1, 1 ) == '/' &&
				     $result['ContentLength'] == 0
				) {
					// Return as if it is a bucket to account for console
					// bucket objects (e.g., zero-byte object "foo/")
					return $this->formatUrlStat( $path );
				} else {
					// Attempt to stat and cache regular object
					return $this->formatUrlStat( $result->toArray() );
				}
			} catch ( S3Exception $e ) {
				//only check if it's a prefix if no file extension for performance reasons.
				$extension = pathinfo( $path, PATHINFO_EXTENSION );
				if ( ! $extension ) {
					// Maybe this isn't an actual key, but a prefix. Do a prefix
					// listing of objects to determine.
					$prefix = rtrim( $parts['Key'], '/' ) . '/';
					$this->debug( 'ListObjects', $prefix );
					$result = $this->getClient()->listObjects( [
						'Bucket'  => $parts['Bucket'],
						'Prefix'  => $prefix,
						'MaxKeys' => 1,
					] );
					if ( ! $result['Contents'] && ! $result['CommonPrefixes'] ) {
						throw new \Exception( "File or directory not found: $path" );
					}
				} else {
					throw new \Exception( "File or directory not found: $path" );
				}

				return $this->formatUrlStat( $path );
			}
		}, $flags );
	}

	/**
	 * Get the bucket and key from the passed path (e.g. iu://bucket/key)
	 *
	 * @param string $path Path passed to the stream wrapper
	 *
	 * @return array Hash of 'Bucket', 'Key', and custom params from the context
	 */
	private function withPath( $path ) {
		$params = $this->getOptions( true );

		return $this->getBucketKey( $path ) + $params;
	}

	private function statDirectory( $parts, $path, $flags ) {
		// Stat "directories": buckets, or "iu://"
		if ( ! $parts['Bucket'] ) {
			$this->debug( 'doesBucketExist', $parts['Bucket'] );
		}
		if ( ! $parts['Bucket'] ||
		     $this->getClient()->doesBucketExist( $parts['Bucket'] )
		) {

			return $this->formatUrlStat( $path );
		}

		return $this->triggerError( "File or directory not found: $path", $flags );
	}

	/**
	 * Support for mkdir().
	 *
	 * @param string $path    Directory which should be created.
	 * @param int    $mode    Permissions. 700-range permissions map to
	 *                        ACL_PUBLIC. 600-range permissions map to
	 *                        ACL_AUTH_READ. All other permissions map to
	 *                        ACL_PRIVATE. Expects octal form.
	 * @param int    $options A bitwise mask of values, such as
	 *                        STREAM_MKDIR_RECURSIVE.
	 *
	 * @return bool
	 * @link http://www.php.net/manual/en/streamwrapper.mkdir.php
	 */
	public function mkdir( $path, $mode, $options ) {
		$this->initProtocol( $path );
		$params = $this->withPath( $path );
		$this->clearCacheKey( $path );
		if ( ! $params['Bucket'] ) {
			return false;
		}

		if ( ! isset( $params['ACL'] ) ) {
			$params['ACL'] = $this->determineAcl( $mode );
		}

		return empty( $params['Key'] )
			? $this->createBucket( $path, $params )
			: $this->createSubfolder( $path, $params );
	}

	/**
	 * Clears a specific stat cache value from the stat cache and LRU cache.
	 *
	 * @param string $key S3 path (iu://bucket/key).
	 */
	private function clearCacheKey( $key ) {
		clearstatcache( true, $key );
		$this->getCacheStorage()->remove( $key );
		$this->debug_cache( 'DELETE', $key );
	}

	/**
	 * Determine the most appropriate ACL based on a file mode.
	 *
	 * @param int $mode File mode
	 *
	 * @return string
	 */
	private function determineAcl( $mode ) {
		switch ( substr( decoct( $mode ), 0, 1 ) ) {
			case '7':
				return 'public-read';
			case '6':
				return 'authenticated-read';
			default:
				return 'private';
		}
	}

	/**
	 * Creates a bucket for the given parameters.
	 *
	 * @param string $path   Stream wrapper path
	 * @param array  $params A result of StreamWrapper::withPath()
	 *
	 * @return bool Returns true on success or false on failure
	 */
	private function createBucket( $path, array $params ) {
		$this->debug( 'doesBucketExist', $params['Bucket'] );
		if ( $this->getClient()->doesBucketExist( $params['Bucket'] ) ) {
			return $this->triggerError( "Bucket already exists: {$path}" );
		}

		return $this->boolCall( function () use ( $params, $path ) {
			$this->debug( 'CreateBucket', $params['Bucket'] );
			$this->getClient()->createBucket( $params );
			$this->clearCacheKey( $path );

			return true;
		} );
	}

	/**
	 * Creates a pseudo-folder by creating an empty "/" suffixed key
	 *
	 * @param string $path   Stream wrapper path
	 * @param array  $params A result of StreamWrapper::withPath()
	 *
	 * @return bool
	 */
	private function createSubfolder( $path, array $params ) {
		// Ensure the path ends in "/" and the body is empty.
		$params['Key']  = rtrim( $params['Key'], '/' ) . '/';
		$params['Body'] = '';

		// Fail if this pseudo directory key already exists
		$this->debug( 'doesObjectExist', $params['Key'] );
		if ( $this->getClient()->doesObjectExist(
			$params['Bucket'],
			$params['Key'] )
		) {
			return $this->triggerError( "Subfolder already exists: {$path}" );
		}

		return $this->boolCall( function () use ( $params, $path ) {
			$this->debug( 'PutObject', $params['Key'] );
			$bool = (bool) $this->getClient()->putObject( $params );

			//Cache the stat for this file so we don't have to do another HeadObject in the same request
			$cache_key = "iu://{$params['Bucket']}/{$params['Key']}";
			if ( $bool ) {
				$this->getCacheStorage()->set( $cache_key, $this->formatUrlStat( [ 'ContentLength' => $params['Body']->getSize(), 'LastModified' => time() ] ) );
				$this->debug_cache( 'SET', $cache_key );
				//purposely don't cache this 0-length fake file
			}

			return true;
		} );
	}

	public function rmdir( $path, $options ) {
		$this->initProtocol( $path );
		$this->clearCacheKey( $path );
		$params = $this->withPath( $path );
		$client = $this->getClient();

		if ( ! $params['Bucket'] ) {
			return $this->triggerError( 'You must specify a bucket' );
		}

		return $this->boolCall( function () use ( $params, $path, $client ) {
			if ( ! $params['Key'] ) {
				$this->debug( 'deleteBucket', $params['Bucket'] );
				$client->deleteBucket( [ 'Bucket' => $params['Bucket'] ] );

				return true;
			}

			return $this->deleteSubfolder( $path, $params );
		} );
	}

	/**
	 * Deletes a nested subfolder if it is empty.
	 *
	 * @param string $path   Path that is being deleted (e.g., 'iu://a/b/c')
	 * @param array  $params A result of StreamWrapper::withPath()
	 *
	 * @return bool
	 */
	private function deleteSubfolder( $path, $params ) {
		// Use a key that adds a trailing slash if needed.
		$prefix = rtrim( $params['Key'], '/' ) . '/';
		$this->debug( 'ListObjects', $prefix );

		$result = $this->getClient()->listObjects( [
			'Bucket'  => $params['Bucket'],
			'Prefix'  => $prefix,
			'MaxKeys' => 1,
		] );

		// Check if the bucket contains keys other than the placeholder
		if ( $contents = $result['Contents'] ) {
			return ( count( $contents ) > 1 || $contents[0]['Key'] != $prefix )
				? $this->triggerError( 'Subfolder is not empty' )
				: $this->unlink( rtrim( $path, '/' ) . '/' );
		}

		return $result['CommonPrefixes']
			? $this->triggerError( 'Subfolder contains nested folders' )
			: true;
	}

	public function unlink( $path ) {
		$this->initProtocol( $path );

		return $this->boolCall( function () use ( $path ) {
			$this->clearCacheKey( $path );
			$this->debug( 'DeleteObject', $path );

			$this->getClient()->deleteObject( $this->withPath( $path ) );
			$this->getCacheStorage()->set( $path, false );
			$this->debug_cache( 'SET', $path );
			$this->cacheObjectDelete( $path );

			return true;
		} );
	}

	/**
	 * Cache last put/get object
	 *
	 * @param $key
	 */
	private function cacheObjectDelete( $key ) {
		$instance = $this->getOption( 'iup_instance' );
		unset( $instance->stream_file_cache[ $key ] );
		$this->debug_cache( 'Object DELETE', $key );
	}

	/**
	 * Close the directory listing handles
	 *
	 * @return bool true on success
	 */
	public function dir_closedir() {
		$this->objectIterator = null;
		gc_collect_cycles();

		return true;
	}

	/**
	 * This method is called in response to rewinddir()
	 *
	 * @return boolean true on success
	 */
	public function dir_rewinddir() {
		$this->boolCall( function () {
			$this->objectIterator = null;
			$this->dir_opendir( $this->openedPath, null );

			return true;
		} );
	}

	/**
	 * Support for opendir().
	 *
	 * The opendir() method of the Amazon S3 stream wrapper supports a stream
	 * context option of "listFilter". listFilter must be a callable that
	 * accepts an associative array of object data and returns true if the
	 * object should be yielded when iterating the keys in a bucket.
	 *
	 * @param string $path    The path to the directory
	 *                        (e.g. "iu://dir[</prefix>]")
	 * @param string $options Unused option variable
	 *
	 * @return bool true on success
	 * @see http://www.php.net/manual/en/function.opendir.php
	 */
	public function dir_opendir( $path, $options ) {
		$this->initProtocol( $path );
		$this->openedPath = $path;
		$params           = $this->withPath( $path );
		$delimiter        = $this->getOption( 'delimiter' );
		/** @var callable $filterFn */
		$filterFn           = $this->getOption( 'listFilter' );
		$op                 = [ 'Bucket' => $params['Bucket'] ];
		$this->openedBucket = $params['Bucket'];

		if ( $delimiter === null ) {
			$delimiter = '/';
		}

		if ( $delimiter ) {
			$op['Delimiter'] = $delimiter;
		}

		if ( $params['Key'] ) {
			$params['Key'] = rtrim( $params['Key'], $delimiter ) . $delimiter;
			// Support paths ending in "*" to allow listing of arbitrary prefixes.
			if ( substr( $params['Key'], - 1, 1 ) === '*' ) {
				$params['Key'] = rtrim( $params['Key'], '*' );
				// Set the opened bucket prefix to be the directory. This is because $this->openedBucketPrefix
				// will be removed from the resulting keys, and we want to return all files in the directory
				// of the wildcard.
				$this->openedBucketPrefix = substr( $params['Key'], 0, ( strrpos( $params['Key'], '/' ) ?: 0 ) + 1 );
			} else {
				$params['Key']            = rtrim( $params['Key'], $delimiter ) . $delimiter;
				$this->openedBucketPrefix = $params['Key'];
			}
			$op['Prefix'] = $params['Key'];
		}

		$this->openedBucketPrefix = $params['Key'];

		$this->debug( 'ListObjects', $op['Prefix'] );
		// Filter our "/" keys added by the console as directories, and ensure
		// that if a filter function is provided that it passes the filter.
		$this->objectIterator = \UglyRobot\Infinite_Uploads\Aws\flatmap(
			$this->getClient()->getPaginator( 'ListObjects', $op ),
			function ( Result $result ) use ( $filterFn ) {
				$contentsAndPrefixes = $result->search( '[Contents[], CommonPrefixes[]][]' );

				// Filter out dir place holder keys and use the filter fn.
				return array_filter(
					$contentsAndPrefixes,
					function ( $key ) use ( $filterFn ) {
						return ( ! $filterFn || call_user_func( $filterFn, $key ) )
						       && ( ! isset( $key['Key'] ) || substr( $key['Key'], - 1, 1 ) !== '/' );
					}
				);
			}
		);

		return true;
	}

	/**
	 * This method is called in response to readdir()
	 *
	 * @return string Should return a string representing the next filename, or
	 *                false if there is no next file.
	 * @link http://www.php.net/manual/en/function.readdir.php
	 */
	public function dir_readdir() {
		// Skip empty result keys
		if ( ! $this->objectIterator->valid() ) {
			return false;
		}

		// First we need to create a cache key. This key is the full path to
		// then object in s3: protocol://bucket/key.
		// Next we need to create a result value. The result value is the
		// current value of the iterator without the opened bucket prefix to
		// emulate how readdir() works on directories.
		// The cache key and result value will depend on if this is a prefix
		// or a key.
		$cur = $this->objectIterator->current();
		if ( isset( $cur['Prefix'] ) ) {
			// Include "directories". Be sure to strip a trailing "/"
			// on prefixes.
			$result = rtrim( $cur['Prefix'], '/' );
			$key    = $this->formatKey( $result );
			$stat   = $this->formatUrlStat( $key );
		} else {
			$result = $cur['Key'];
			$key    = $this->formatKey( $cur['Key'] );
			$stat   = $this->formatUrlStat( $cur );
		}

		// Cache the object data for quick url_stat lookups used with
		// RecursiveDirectoryIterator.
		$this->getCacheStorage()->set( $key, $stat );
		$this->debug_cache( 'SET', $key );
		$this->objectIterator->next();

		// Remove the prefix from the result to emulate other stream wrappers.
		return $this->openedBucketPrefix
			? substr( $result, strlen( $this->openedBucketPrefix ) )
			: $result;
	}

	private function formatKey( $key ) {
		$protocol = explode( '://', $this->openedPath )[0];

		return "{$protocol}://{$this->openedBucket}/{$key}";
	}

	/**
	 * Called in response to rename() to rename a file or directory. Currently
	 * only supports renaming objects.
	 *
	 * @param string $path_from the path to the file to rename
	 * @param string $path_to   the new path to the file
	 *
	 * @return bool true if file was successfully renamed
	 * @link http://www.php.net/manual/en/function.rename.php
	 */
	public function rename( $path_from, $path_to ) {
		// PHP will not allow rename across wrapper types, so we can safely
		// assume $path_from and $path_to have the same protocol
		$this->initProtocol( $path_from );
		$partsFrom = $this->withPath( $path_from );
		$partsTo   = $this->withPath( $path_to );
		//$this->clearCacheKey( $path_from );
		//$this->clearCacheKey( $path_to );

		if ( ! $partsFrom['Key'] || ! $partsTo['Key'] ) {
			return $this->triggerError( 'The Infinite Uploads stream wrapper only '
			                            . 'supports copying objects' );
		}

		return $this->boolCall( function () use ( $partsFrom, $partsTo ) {
			$options = $this->getOptions( true );
			// Copy the object and allow overriding default parameters if
			// desired, but by default copy metadata
			$this->debug( 'CopyObject', $partsFrom['Key'] . ' to ' . $partsTo['Key'] );
			$this->getClient()->copy(
				$partsFrom['Bucket'],
				$partsFrom['Key'],
				$partsTo['Bucket'],
				$partsTo['Key'],
				isset( $options['acl'] ) ? $options['acl'] : 'private',
				$options
			);

			//Copy the stat cache for this file so we don't have to do another HeadObject in the same request
			$from_key  = "iu://{$partsFrom['Bucket']}/{$partsFrom['Key']}";
			$from_stat = $this->getCacheStorage()->get( $from_key );
			if ( ! is_null( $from_stat ) ) {
				$to_key = "iu://{$partsTo['Bucket']}/{$partsTo['Key']}";
				$this->getCacheStorage()->set( $to_key, $from_stat );
				$this->debug_cache( 'SET', $to_key );
			}

			// Delete the original object
			$this->debug( 'DeleteObject', $partsFrom['Key'] );
			$this->getClient()->deleteObject( [
				                                  'Bucket' => $partsFrom['Bucket'],
				                                  'Key'    => $partsFrom['Key'],
			                                  ] + $options );
			//cache source file as deleted so file_exists will not trigger a HeadObject later
			$this->getCacheStorage()->set( $from_key, false );
			$this->debug_cache( 'SET', $from_key );
			$this->cacheObjectDelete( $from_key );

			return true;
		} );
	}

	public function stream_cast( $cast_as ) {
		return false;
	}
}
