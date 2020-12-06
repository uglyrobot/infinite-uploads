<?php

/**
 * Infinite_Uploads_Rewriter
 *
 * @since 1.0
 */

class Infinite_Uploads_Rewriter {
	var $uploads_url = null;    // origin URL
	var $uploads_path = null;    // origin PATH
	var $default_cdn_url = null;    // other origin URL
	var $cdn_url = null;    // CDN URL

	/**
	 * constructor
	 *
	 * @param string $uploads_url     Original upload url
	 * @param string $cdn_url         Destination CDN url
	 * @param string $default_cdn_url Optional second url to filter
	 *
	 * @since 1.0
	 */
	function __construct( $uploads_url, $cdn_url, $default_cdn_url = null ) {
		$this->uploads_url  = trailingslashit( $uploads_url );
		$this->uploads_path = trailingslashit( parse_url( $uploads_url, PHP_URL_PATH ) );
		if ( $default_cdn_url && $default_cdn_url != $cdn_url ) {
			$this->default_cdn_url = $this->protocolize_url( trailingslashit( $default_cdn_url ) );
		}

		$this->cdn_url = $this->protocolize_url( trailingslashit( $cdn_url ) );

		add_action( 'template_redirect', [ &$this, 'handle_rewrite_hook' ] );

		// Make sure we replace urls in REST API responses
		add_filter( 'the_content', [ &$this, 'rewrite_the_content' ], 100 );
	}

	/**
	 * Add https protocol to url when needed
	 *
	 * @since   1.0
	 */
	public function protocolize_url( $url ) {
		if ( strpos( $url, ':' ) === false && ! in_array( $url[0], [ '/', '#', '?' ], true ) &&
		     ! preg_match( '/^[a-z0-9-]+?\.php/i', $url ) ) {
			$url = 'https://' . $url;
		}

		return $url;
	}

	/**
	 * run rewrite hook
	 *
	 * @since   1.0
	 */
	public function handle_rewrite_hook() {
		ob_start( [ &$this, 'rewrite' ] );
	}


	/**
	 * rewrite html content
	 *
	 * @since   1.0
	 */
	public function rewrite_the_content( $html ) {
		return $this->rewrite( $html );
	}

	/**
	 * rewrite url
	 *
	 * @param string $html current raw HTML doc
	 *
	 * @return  string  updated HTML doc with CDN links
	 * @since 1.0
	 *
	 */
	public function rewrite( $html ) {

		// Check for full url
		$regex_rule = '#((?:https?:)?(?:' . $this->relative_url( quotemeta( $this->uploads_url ) );

		//if too replacement is not default, we may have default cdn already hardcoded in html so replace that too
		if ( $this->default_cdn_url ) {
			$regex_rule .= '|' . $this->relative_url( quotemeta( $this->default_cdn_url ) );
		}

		// check for relative paths
		$regex_rule .= ')|(?<=[(\"\'=\s])' . quotemeta( $this->uploads_path ) . ')#';

		// call the cdn rewriter callback
		$cdn_html = preg_replace_callback( $regex_rule, [ $this, 'rewrite_url' ], $html );

		return $cdn_html;
	}

	/**
	 * Get relative url
	 *
	 * @param string $url a full url
	 *
	 * @return  string  protocol relative url
	 * @since   1.0
	 *
	 */
	protected function relative_url( $url ) {
		return substr( $url, strpos( $url, '//' ) );
	}

	/**
	 * rewrite url
	 *
	 * @param string $matches the matches from regex
	 *
	 * @return  string  updated url if not excluded
	 * @since   1.0
	 *
	 */
	protected function rewrite_url( $matches ) {
		return apply_filters( 'infinite_uploads_rewrite_url', $this->cdn_url, $matches[0] );
	}
}
