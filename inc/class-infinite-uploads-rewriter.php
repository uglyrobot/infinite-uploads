<?php

/**
 * Infinite_Uploads_Rewriter
 *
 * @since 1.0
 */

class Infinite_Uploads_Rewriter {
	var $uploads_path = null;    // uploads PATH
	var $replacements = [];    // urls to be searched for and replaced with CDN URL
	var $cdn_url = null;    // CDN URL

	/**
	 * constructor
	 *
	 * @param string $uploads_url  Original upload url
	 * @param array  $replacements Urls to filter
	 * @param string $cdn_url      Destination CDN url
	 *
	 * @since 1.0
	 */
	function __construct( $uploads_url, $replacements, $cdn_url ) {

		$this->uploads_path = trailingslashit( parse_url( $uploads_url, PHP_URL_PATH ) );

		$this->replacements = array_unique(
			array_map( [ $this, 'protocolize_url' ],
				apply_filters( 'infinite_uploads_replacement_urls', $replacements )
			)
		);

		$this->cdn_url = $this->protocolize_url( $cdn_url );

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

		return trailingslashit( $url );
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

		// start regex
		$regex_rule = '#((?:https?:)?(?:';

		//add all the domains to replace
		$regex_rule .= implode( '|',
			array_map( [ $this, 'relative_url' ],
				array_map( 'quotemeta', $this->replacements )
			)
		);

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
		/**
		 * Filters the find/replace url rewriter that replaces matches in HTML output with CDN url.
		 *
		 * @param  {string}  $cdn_url  The base url to replace the match with, like `https://xxxxx.infiniteuploads.cloud/`.
		 * @param  {string}  $match  The base url of the match found in HTML, like `https://mysite.com/wp-content/uploads/`.
		 *
		 * @return {string} The base url to replace the match with.
		 * @since  1.0
		 * @hook   infinite_uploads_rewrite_url
		 *
		 */
		return apply_filters( 'infinite_uploads_rewrite_url', $this->cdn_url, $matches[0] );
	}
}
