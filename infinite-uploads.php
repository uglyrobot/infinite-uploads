<?php
/*
Plugin Name: Infinite Uploads
Description: Store uploads in the cloud with unlimited storage
Author: UglyRobot
Version: 0.1-alpha-3
Author URI: https://uglyrobot.com
Text Domain: iup

Inspired by and borrowed heavily from S3 Uploads plugin from Human Made https://github.com/humanmade/S3-Uploads.
*/

define( 'INFINITE_UPLOADS_VERSION', '0.1-alpha-2' );

add_filter( 'infinite_uploads_s3_client_params', function ( $params ) {
	$params['endpoint']                = 'https://s3.us-west-000.backblazeb2.com';
	$params['use_path_style_endpoint'] = true;
	//$params['debug'] = [
	//	'logfn'        => 'error_log',
	//	'stream_size'  => 0,
	//];
	return $params;
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/inc/class-infinite-uploads-wp-cli-command.php';
}

add_action( 'plugins_loaded', 'infinite_uploads_init' );

function infinite_uploads_init() {
	// Ensure the AWS SDK can be loaded.
	if ( ! class_exists( '\\Aws\\S3\\S3Client' ) ) {
		// Require AWS Autoloader file.
		require_once dirname( __FILE__ ) . '/vendor/autoload.php';
	}

	if ( ! infinite_uploads_check_requirements() ) {
		return;
	}

	infinite_uploads_install();

	$instance = Infinite_Uploads::get_instance();
	$instance->setup();
}

function infinite_uploads_install() {
	global $wpdb;

	// Install the needed DB table if not already.
	$installed = get_site_option( 'iup_installed' );
	if ( INFINITE_UPLOADS_VERSION != $installed ) {
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->base_prefix}infinite_uploads_files (
	            `file` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
	            `size` BIGINT UNSIGNED NOT NULL DEFAULT '0',
	            `modified` INT UNSIGNED NOT NULL,
	            `synced` BOOLEAN NOT NULL DEFAULT '0',
	            PRIMARY KEY (`file`(255)),
	            INDEX (`synced`)
	        ) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $sql );

		if ( is_multisite() ) {
			update_site_option( 'iup_installed', INFINITE_UPLOADS_VERSION );
		} else {
			update_option( 'iup_installed', INFINITE_UPLOADS_VERSION, true );
		}
	}
}

/**
 * Check whether the environment meets the plugin's requirements, like the minimum PHP version.
 *
 * @return bool True if the requirements are met, else false.
 */
function infinite_uploads_check_requirements() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, '5.5.0', '<' ) ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			add_action( 'admin_notices', 'infinite_uploads_outdated_php_version_notice' );
		}

		return false;
	}

	if ( version_compare( $wp_version, '5.3.0', '<' ) ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			add_action( 'admin_notices', 'infinite_uploads_outdated_wp_version_notice' );
		}

		return false;
	}

	return true;
}

/**
 * Print an admin notice when the PHP version is not high enough.
 *
 * This has to be a named function for compatibility with PHP 5.2.
 */
function infinite_uploads_outdated_php_version_notice() {
	printf(
		'<div class="error"><p>The Infinite Uploads plugin requires PHP version 5.5.0 or higher. Your server is running PHP version %s.</p></div>',
		PHP_VERSION
	);
}

/**
 * Print an admin notice when the WP version is not high enough.
 *
 * This has to be a named function for compatibility with PHP 5.2.
 */
function infinite_uploads_outdated_wp_version_notice() {
	global $wp_version;

	printf(
		'<div class="error"><p>The Infinite Uploads plugin requires WordPress version 5.3 or higher. Your server is running WordPress version %s.</p></div>',
		$wp_version
	);
}

/**
 * Check if URL rewriting is enabled.
 *
 * @return bool
 */
function infinite_uploads_enabled() {
	return get_site_option( 'iup_enabled' );
}

/**
 * Autoload callback.
 *
 * @param $class_name Name of the class to load.
 */
function infinite_uploads_autoload( $class_name ) {
	/*
	 * Load plugin classes:
	 * - Class name: Infinite_Uploads_Image_Editor_Imagick.
	 * - File name: class-infinite-uploads-image-editor-imagick.php.
	 */
	$class_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	$class_path = dirname( __FILE__ ) . '/inc/' . $class_file;

	if ( file_exists( $class_path ) ) {
		require $class_path;

		return;
	}
}

spl_autoload_register( 'infinite_uploads_autoload' );
