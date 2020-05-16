<?php

class Infinite_uploads_admin {

	private static $instance;

	/**
	 *
	 * @return Infinite_uploads_admin
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_uploads_admin();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
	}

	/**
	 * Registers a new settings page under Settings.
	 */
	function admin_menu() {
		add_options_page(
			__( 'Infinite Uploads', 'infinite-uploads' ),
			__( 'Infinite Uploads', 'infinite-uploads' ),
			'manage_options',
			'infinite_uploads',
			array(
				$this,
				'settings_page'
			)
		);
	}

	/**
	 * Settings page display callback.
	 */
	function settings_page() {
		echo __( 'This is the page content', 'infinite-uploads' );
	}
}
