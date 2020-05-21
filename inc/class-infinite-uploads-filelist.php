<?php
/**
 * Lists files using a Breadth-First search algorithm to allow for time limits and resume across multiple requests.
 */


/**
 * Infinite_Uploads_Filelist
 */
class Infinite_Uploads_Filelist {

	public $is_done = false;
	public $paths_left = [];
	public $file_count = 0;
	public $file_list = [];
	protected $root_path;
	protected $timeout;
	protected $start_time;
	protected $excluded_files = [];
	protected $insert_rows = 500;

	/**
	 * Infinite_Uploads_Filelist constructor.
	 *
	 * @param string $root_path      The full path of the directory to iterate.
	 * @param float  $timeout        Timeout in seconds.
	 * @param array  $paths_left     Provide as returned if continuing the filelist after a timeout.
	 * @param array  $excluded_files File patterns to exclude from the built filelist.
	 */
	public function __construct( $root_path, $timeout = 25.0, $paths_left = [], $excluded_files = [] ) {
		$this->root_path      = rtrim( $root_path, '/' ); //expected no trailing slash.
		$this->timeout        = $timeout;
		$this->paths_left     = $paths_left;
		$this->excluded_files = $excluded_files;
	}

	/**
	 * Runs over the site's files.
	 */
	public function start() {
		global $wpdb;
		$this->start_time = microtime( true );
		$this->file_count = 0;
		$this->file_list  = [];

		// If just starting reset the local DB list storage
		if ( empty( $this->paths_left ) ) {
			//TRUNCATE is fastest, try it first
			$result = $wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}infinite_uploads_files" );
			//Sometimes hosts don't give the DB user TRUNCATE permissions, so DELETE all if we have to.
			if ( false === $result ) {
				$wpdb->query( "DELETE FROM {$wpdb->base_prefix}infinite_uploads_files WHERE 1" );
			}

			update_site_option( 'iup_files_scanned', [
				'files_started'    => time(),
				'files_finished'   => false,
				'compare_started'  => false,
				'compare_finished' => false,
			] );
		}

		$this->get_files();

		$this->flush_to_db();

		if ( empty( $this->paths_left ) ) {
			// So we are done. Say so.
			$this->is_done = true;

			$progress                   = get_site_option( 'iup_files_scanned' );
			$progress['files_finished'] = time();
			update_site_option( 'iup_files_scanned', $progress );
		}
	}

	/**
	 * Runs a breadth-first iteration on all files and gathers the relevant info for each one.
	 */
	protected function get_files() {

		$paths = ( empty( $this->paths_left ) ) ? array( $this->root_path ) : $this->paths_left;

		while ( ! empty( $paths ) ) {
			$path = array_pop( $paths );

			// Skip ".." items.
			if ( preg_match( '/\.\.([\/\\\\]|$)/', $path ) ) {
				continue;
			}

			if ( 0 !== strpos( $path, $this->root_path ) ) {
				// Build the absolute path in case it's not the first iteration.
				$path = rtrim( $this->root_path, '/' ) . $path;
			}

			if ( $this->is_excluded( $path ) ) {
				continue;
			}

			$contents = defined( 'GLOB_BRACE' )
				? glob( trailingslashit( $path ) . '{,.}[!.,!..]*', GLOB_BRACE )
				: glob( trailingslashit( $path ) . '[!.,!..]*' );

			foreach ( $contents as $item ) {
				$file = array();

				if ( is_link( $item ) || $this->is_excluded( $item ) ) {
					continue;
				} elseif ( is_file( $item ) ) {
					$file = ( is_readable( $item ) ) ? $this->get_file_info( $item ) : null;

					$file['name'] = $this->relative_path( $item );

					$this->add_file( $file );
				} elseif ( is_dir( $item ) ) {
					if ( ! in_array( $item, $paths, true ) ) {
						$paths[] = $this->relative_path( $item );
					}
				}
			}
			$this->paths_left = $paths;

			// If we have exceed the imposed time limit, lets pause the iteration here.
			if ( $this->has_exceeded_timelimit() ) {
				break;
			}
		}

		$this->is_done = false;
	}

	/**
	 * Checks path against excluded pattern.
	 *
	 * @return bool
	 * @todo Make this work.
	 *
	 */
	protected function is_excluded( $path ) {
		return false;
	}

	/**
	 * Checks file health and returns as many info as it can.
	 *
	 * @param string $item The file to be investigated.
	 *
	 * @return mixed File info or false for failure.
	 */
	protected function get_file_info( $item ) {
		$file          = array();
		$file['mtime'] = filemtime( $item );
		//$file['md5']   = md5_file( $item );
		$file['size'] = filesize( $item );

		if ( empty( $file['mtime'] ) && empty( $file['size'] ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Returns rel path of file/dir, relative to site root.
	 *
	 * @param string $item File's absolute path.
	 *
	 * @return string
	 */
	protected function relative_path( $item ) {
		// Retrieve the relative to the site root path of the file.
		$pos = strpos( $item, $this->root_path );
		if ( 0 === $pos ) {
			return substr_replace( $item, '', $pos, strlen( $this->root_path ) );
		}

		return $item;
	}

	/**
	 * Add file details to internal storage and the db.
	 */
	protected function add_file( $file ) {
		$this->file_list[] = $file;
		$this->file_count ++;

		if ( count( $this->file_list ) >= $this->insert_rows ) {
			$this->flush_to_db();
		}
	}

	/**
	 * Write the queued file list to DB storage.
	 */
	protected function flush_to_db() {
		global $wpdb;

		if ( count( $this->file_list ) ) {
			$values = array();
			foreach ( $this->file_list as $file ) {
				$values[] = $wpdb->prepare( "(%s,%d,%d)", $file['name'], $file['size'], $file['mtime'] );
			}

			$query = "INSERT INTO {$wpdb->base_prefix}infinite_uploads_files (file, size, modified) VALUES ";
			$query .= implode( ",\n", $values );
			$query .= " ON DUPLICATE KEY UPDATE size = VALUES(size), modified = VALUES(modified)";
			if ( $wpdb->query( $query ) ) {
				$this->file_list = [];
			}
		}
	}

	/**
	 * Checks if current iteration has exceeded the given time limit.
	 *
	 * @return bool True if we have exceeded the time limit, false if we haven't.
	 */
	protected function has_exceeded_timelimit() {
		$current_time = microtime( true );
		$time_diff    = number_format( $current_time - $this->start_time, 2 );

		$has_exceeded_timelimit = ! empty( $this->timeout ) && ( $time_diff > $this->timeout );

		return $has_exceeded_timelimit;
	}
}
