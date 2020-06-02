<?php

/**
 * Infinite_Uploads_Rewriter
 *
 * @since 1.0
 */

class Infinite_Uploads_Rewriter {
	var $uploads_url = null;    // origin URL
	var $uploads_path = null;    // origin PATH
	var $cdn_url = null;    // CDN URL

	var $dirs = null;    // included directories
	var $excludes = []; // excludes

	/**
	 * constructor
	 *
	 * @param string $uploads_url Source uploads url to replace
	 * @param string $cdn_url     Destination CDN url
	 *
	 * @since 1.0
	 */

	function __construct( $uploads_url, $cdn_url ) {

		$this->uploads_url  = parse_url( $uploads_url, PHP_URL_HOST );
		$this->uploads_path = trim( parse_url( $uploads_url, PHP_URL_PATH ), '/' );
		$this->cdn_url      = $cdn_url;
		//$this->excludes       = $excludes;

		add_action( 'template_redirect', [ &$this, 'handle_rewrite_hook' ] );

		// Make sure we replace urls in REST API responses
		add_filter( 'the_content', [ &$this, 'rewrite_the_content' ], 100 );
	}

	/**
	 * run rewrite hook
	 *
	 * @since   1.0
	 */

	public function handle_rewrite_hook() {
		ob_start( array( &$this, 'rewrite' ) );
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

		// get dir scope in regex format
		$blog_url = '(https?:)?' . $this->relative_url( quotemeta( trailingslashit( $this->uploads_url ) ) );

		// regex rule start
		$regex_rule = '#(?<=[(\"\'])';

		// check if relative paths
		$regex_rule .= '(?:(?:' . $blog_url . ')?(?:\.?\/)?' . quotemeta( $this->uploads_path ) . ')?';

		// regex rule end
		$regex_rule .= '/(?:([^\"\']+\.[^/\"\')]+))(?=[\"\')])#';

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
	 * @param string $asset current asset
	 *
	 * @return  string  updated url if not excluded
	 * @since   1.0
	 *
	 */
	protected function rewrite_url( &$asset ) {
		if ( $this->exclude_asset( $asset[0] ) ) {
			return $asset[0];
		}

		$blog_url = $this->relative_url( trailingslashit( $this->uploads_url ) . $this->uploads_path );

		$subst_urls = [
			'http:' . $blog_url,
			'https:' . $blog_url,
		];

		// is it a protocol independent URL?
		if ( strpos( $asset[0], '//' ) === 0 ) {
			return str_replace( $blog_url, $this->cdn_url, $asset[0] );
		}

		// is it a relative URL with ./?
		if ( strpos( $asset[0], './' ) === 0 ) {
			return str_replace( './' . $this->uploads_path, $this->cdn_url, $asset[0] );
		}

		// is it a relative URL with /?
		if ( strpos( $asset[0], '/' ) === 0 ) {
			return str_replace( '/' . $this->uploads_path, $this->cdn_url, $asset[0] );
		}

		// is it a relative URL with no root?
		if ( strpos( $asset[0], $this->uploads_path ) === 0 ) {
			return str_replace( $this->uploads_path, $this->cdn_url, $asset[0] );
		}

		// check if not a relative path
		if ( strstr( $asset[0], $blog_url ) ) {
			return str_replace( $subst_urls, $this->cdn_url, $asset[0] );
		}

		// relative URL
		return $this->cdn_url . $asset[0];
	}

	/**
	 * exclude assets that should not be rewritten
	 *
	 * @param string $asset current asset
	 *
	 * @return  boolean  true if need to be excluded
	 * @since   1.0
	 *
	 */
	protected function exclude_asset( &$asset ) {
		// excludes
		foreach ( $this->excludes as $exclude ) {
			if ( ! ! $exclude && stristr( $asset, $exclude ) != false ) {
				return true;
			}
		}

		return false;
	}
}
