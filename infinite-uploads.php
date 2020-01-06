<?php
/*
Plugin Name: Infinite Uploads
Description: Store uploads in S3
Author: UglyRobot
Version: 0.1
Author URI: https://uglyrobot.com
*/

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

	if ( ! defined( 'INFINITE_UPLOADS_BUCKET' ) ) {
		return;
	}

	if ( ( ! defined( 'INFINITE_UPLOADS_KEY' ) || ! defined( 'INFINITE_UPLOADS_SECRET' ) ) && ! defined( 'INFINITE_UPLOADS_USE_INSTANCE_PROFILE' ) ) {
		return;
	}

	if ( ! infinite_uploads_enabled() ) {
		return;
	}

	if ( ! defined( 'INFINITE_UPLOADS_REGION' ) ) {
		wp_die( 'INFINITE_UPLOADS_REGION constant is required. Please define it in your wp-config.php' );
	}

	$instance = Infinite_Uploads::get_instance();
	$instance->setup();

	// Include newer version of getID3, as the one bundled with WordPress Core is too old that it
	// breaks with iu:// file paths. This is less than ideal for performance, but there's no
	// reliable WordPress hooks we can use to load this only when we need. Most infuriating is
	// WordPress does class_exists( 'getID3', false ) so we can't use an autoloader to override
	// the version being loaded.
	if ( ! class_exists( 'getID3' ) ) {
		require_once dirname( __FILE__ ) . '/lib/getid3/getid3.php';
	}

	// Add filters to "wrap" the wp_privacy_personal_data_export_file function call as we need to
	// switch out the personal_data directory to a local temp folder, and then upload after it's
	// complete, as Core tries to write directly to the ZipArchive which won't work with the
	// S3 streamWrapper.
	add_action( 'wp_privacy_personal_data_export_file', 'infinite_uploads_before_export_personal_data', 9 );
	add_action( 'wp_privacy_personal_data_export_file', 'infinite_uploads_after_export_personal_data', 11 );
	add_action( 'wp_privacy_personal_data_export_file_created', 'infinite_uploads_move_temp_personal_data_to_cloud', 1000 );
}

/**
 * Check whether the environment meets the plugin's requirements, like the minimum PHP version.
 *
 * @return bool True if the requirements are met, else false.
 */
function infinite_uploads_check_requirements() {
	if ( version_compare( '5.5.0', PHP_VERSION, '>' ) ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			add_action( 'admin_notices', 'infinite_uploads_outdated_php_version_notice' );
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
	printf( '<div class="error"><p>The Infinite Uploads plugin requires PHP version 5.5.0 or higher. Your server is running PHP version %s.</p></div>',
		PHP_VERSION
	);
}
/**
 * Check if URL rewriting is enabled.
 *
 * Define INFINITE_UPLOADS_AUTOENABLE to false in your wp-config to disable, or use the
 * infinite_uploads_enabled option.
 *
 * @return bool
 */
function infinite_uploads_enabled() {
	// Make sure the plugin is enabled when autoenable is on
	$constant_autoenable_off = ( defined( 'INFINITE_UPLOADS_AUTOENABLE' ) && false === INFINITE_UPLOADS_AUTOENABLE );

	if ( $constant_autoenable_off && 'enabled' !== get_option( 'infinite_uploads_enabled' ) ) {                         // If the plugin is not enabled, skip
		return false;
	}

	return true;
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

/**
 * Setup the filters for wp_privacy_exports_dir to use a temp folder location.
 */
function infinite_uploads_before_export_personal_data() {
	add_filter( 'wp_privacy_exports_dir', 'infinite_uploads_set_wp_privacy_exports_dir' );
}

/**
 * Remove the filters for wp_privacy_exports_dir as we only want it added in some cases.
 */
function infinite_uploads_after_export_personal_data() {
	remove_filter( 'wp_privacy_exports_dir', 'infinite_uploads_set_wp_privacy_exports_dir' );
}

/**
 * Override the wp_privacy_exports_dir location
 *
 * We don't want to use the default uploads folder location, as with Infinite Uploads this is
 * going to the a iu:// custom URL handler, which is going to fail with the use of ZipArchive.
 * Instead we set to to sys_get_temp_dir and move the fail in the wp_privacy_personal_data_export_file_created
 * hook.
 *
 * @param string $dir
 * @return string
 */
function infinite_uploads_set_wp_privacy_exports_dir( $dir ) {
	if ( strpos( $dir, 'iu://' ) !== 0 ) {
		return $dir;
	}
	$dir = sys_get_temp_dir() . '/wp_privacy_exports_dir/';
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir );
		file_put_contents( $dir . 'index.html', '' );
	}
	return $dir;
}

/**
 * Move the tmp personal data file to the true uploads location
 *
 * Once a personal data file has been written, move it from the overriden "temp"
 * location to the cloud location where it should have been stored all along, and where
 * the "natural" Core URL is going to be pointing to.
 */
function infinite_uploads_move_temp_personal_data_to_cloud( $archive_pathname ) {
	if ( strpos( $archive_pathname, sys_get_temp_dir() ) !== 0 ) {
		return;
	}
	$upload_dir  = wp_upload_dir();
	$exports_dir = trailingslashit( $upload_dir['basedir'] ) . 'wp-personal-data-exports/';
	$destination = $exports_dir . pathinfo( $archive_pathname, PATHINFO_FILENAME ) . '.' . pathinfo( $archive_pathname, PATHINFO_EXTENSION );
	copy( $archive_pathname, $destination );
	unlink( $archive_pathname );
}
