<?php

class Infinite_Uploads_admin {

	private static $instance;

	/**
	 *
	 * @return Infinite_Uploads_admin
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Infinite_Uploads_admin();
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
		$page = add_options_page(
			__( 'Infinite Uploads', 'infinite-uploads' ),
			__( 'Infinite Uploads', 'infinite-uploads' ),
			'manage_options',
			'infinite_uploads',
			array(
				$this,
				'settings_page',
			)
		);

		add_action( 'admin_print_scripts-' . $page, array( &$this, 'bootstrap_script' ) );
		add_action( 'admin_print_styles-' . $page, array( &$this, 'bootstrap_style' ) );
	}

	/**
	 *
	 */
	function bootstrap_script() {
		wp_enqueue_script( 'iup-bootstrap', plugins_url( 'assets/bootstrap/js/bootstrap.bundle.min.js', __FILE__ ), array( 'jquery' ), INFINITE_UPLOADS_VERSION );
	}

	/**
	 *
	 */
	function bootstrap_style() {

		wp_enqueue_style( 'iup-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), false, INFINITE_UPLOADS_VERSION );

		//hide all admin notices from another source on these pages
		//remove_all_actions( 'admin_notices' );
		//remove_all_actions( 'network_admin_notices' );
		//remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Settings page display callback.
	 */
	function settings_page() {
		echo '<div id="container" class="wrap">';
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {

				var buildFilelist = function (remaining_dirs) {
					var data = {"remaining_dirs": remaining_dirs};
					$.post(ajaxurl + '?action=infinite-uploads-filelist', data, function (json) {
						console.log(json.data);
						if (json.success) {
							$('.iup-progress-pcnt').text(json.data.pcnt_complete);
							$('.iup-progress-size').text(json.data.remaining_size);
							$('.iup-progress-files').text(json.data.remaining_files);
							$('#iup-sync-progress-bar .iup-cloud').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete);
							$('#iup-sync-progress-bar .iup-local').css('width', 100 - json.data.pcnt_complete + "%").attr('aria-valuenow', 100 - json.data.pcnt_complete);
							if (!json.data.is_done) {
								buildFilelist(json.data.remaining_dirs);
							} else {
								$('#iup-scan-progress .iup-cloud').show();
								$('#iup-scan-progress .iup-local').hide();
								fetchRemoteFilelist('');
							}

						} else {
							$('#iup-error span').text(json.data);
							$('#iup-error').show();

							$('#iup-scan-progress .iup-local, #iup-scan-progress .iup-cloud, #iup-scan-progress').hide();
							$('#iup-scan').show();
						}
					}, 'json');
				};

				var fetchRemoteFilelist = function (next_token) {
					var data = {"next_token": next_token};
					$.post(ajaxurl + '?action=infinite-uploads-prep-sync', data, function (json) {
						console.log(json.data);
						if (json.success) {
							$('.iup-progress-pcnt').text(json.data.pcnt_complete);
							$('.iup-progress-size').text(json.data.remaining_size);
							$('.iup-progress-files').text(json.data.remaining_files);
							$('#iup-sync-progress-bar .iup-cloud').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete);
							$('#iup-sync-progress-bar .iup-local').css('width', 100 - json.data.pcnt_complete + "%").attr('aria-valuenow', 100 - json.data.pcnt_complete);
							if (!json.data.is_done) {
								fetchRemoteFilelist(json.data.next_token);
							} else {
								$('#iup-scan-progress .iup-local, #iup-scan-progress .iup-cloud, #iup-scan-progress').hide();
								$('#iup-scan').show();
							}

						} else {
							$('#iup-error span').text(json.data);
							$('#iup-error').show();

							$('#iup-scan-progress .iup-local, #iup-scan-progress .iup-cloud, #iup-scan-progress').hide();
							$('#iup-scan').show();
						}
					}, 'json');
				};

				var syncFilelist = function () {
					$.post(ajaxurl + '?action=infinite-uploads-sync', {}, function (json) {
						console.log(json.data);
						if (json.success) {
							$('.iup-progress-pcnt').text(json.data.pcnt_complete);
							$('.iup-progress-size').text(json.data.remaining_size);
							$('.iup-progress-files').text(json.data.remaining_files);
							$('#iup-sync-progress-bar .iup-cloud').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete);
							$('#iup-sync-progress-bar .iup-local').css('width', 100 - json.data.pcnt_complete + "%").attr('aria-valuenow', 100 - json.data.pcnt_complete);
							if (!json.data.is_done) {
								syncFilelist();
							} else {
								$('#iup-sync').show();
								$('#iup-sync-progress').hide();
								$('#iup-sync-progress-bar .progress-bar').removeClass('progress-bar-animated progress-bar-striped');
							}

						} else {
							$('#iup-error span').text(json.data);
							$('#iup-error').show();

							$('#iup-sync').show();
							$('#iup-sync-progress').hide();
							$('#iup-sync-progress-bar .progress-bar').removeClass('progress-bar-animated progress-bar-striped');
						}
					}, 'json');
				};

				//Scan
				$('#iup-scan').on('click', function () {
					$('#iup-scan').hide();
					$('#iup-scan-progress .iup-cloud').hide();
					$('#iup-scan-progress .iup-local, #iup-scan-progress').show();

					buildFilelist([]);
				});

				//Syncing
				$('#iup-sync').on('click', function () {
					$('#iup-scan, #iup-sync').hide();
					$('#iup-sync-progress').show();
					$('#iup-sync-progress-bar .progress-bar').addClass('progress-bar-animated progress-bar-striped');


					syncFilelist();
				});
			});
		</script>
		<h2 class="display-5"><span class="dashicons dashicons-cloud"></span> Infinite Uploads</h2>

		<div class="jumbotron">
			<h2 class="display-4">1. Connect</h2>
			<p class="lead">Create your free Infinite Uploads cloud account and connect this site.</p>
			<hr class="my-4">
			<p>Infinite Uploads is free to get started, and includes 2GB of free cloud storage with unlimited CDN bandwidth.</p>
			<a class="btn btn-primary btn-lg" href="#" role="button">Create Account or Login</a>
		</div>

		<div class="jumbotron">
			<h2 class="display-4">2. Sync</h2>
			<p class="lead">Copy all your existing uploads the the cloud.</p>
			<hr class="my-4">
			<p>Before we can begin serving all your files from the Infinite Uploads global CDN, we need to copy your uploads directory to our cloud storage.</p>

			<?php
			$instance = Infinite_uploads::get_instance();
			$stats    = $instance->get_sync_stats();
			?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert" id="iup-error" style="display: none;">
				<strong>Error!</strong> <span></span>
				<button type="button" class="close" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<ul class="list-group list-group-horizontal id=" iup-sync-progress
			">
			<li class="list-group-item list-group-item-success"><h3 class="m-0"><span class="iup-progress-pcnt"><?php echo esc_html( $stats['pcnt_complete'] ); ?></span>%<small class="text-muted"> Synced</small></h3></li>
			<li class="list-group-item list-group-item-primary"><h3 class="m-0"><span class="iup-progress-size"><?php echo esc_html( $stats['remaining_size'] ); ?></span><small class="text-muted"> Remaining</small></h3></li>
			<li class="list-group-item list-group-item-info display-5"><h3 class="m-0"><span class="iup-progress-files"><?php echo esc_html( $stats['remaining_files'] ); ?></span><small class="text-muted"> Files Remaining</small></h3></li>
			</ul>
			<div class="progress mt-4" id="iup-sync-progress-bar" style="height: 30px;">
				<div class="progress-bar bg-info iup-cloud" role="progressbar" style="width: <?php echo esc_attr( $stats['pcnt_complete'] ); ?>%" aria-valuenow="<?php echo esc_attr( $stats['pcnt_complete'] ); ?>" aria-valuemin="0" aria-valuemax="100">
					<div><span class="iup-progress-pcnt"><?php echo esc_html( $stats['pcnt_complete'] ); ?></span>%</div>
				</div>
				<div class="progress-bar bg-warning iup-local" role="progressbar" style="width: <?php echo 100 - $stats['pcnt_complete']; ?>%" aria-valuenow="<?php echo 100 - $stats['pcnt_complete']; ?>" aria-valuemin="0" aria-valuemax="100">
					<div><span class="iup-progress-size"><?php echo esc_html( $stats['remaining_size'] ); ?></span> (<span class="iup-progress-files"><?php echo esc_html( $stats['remaining_files'] ); ?></span> files)</div>
				</div>
			</div>
			<div class="row mt-4" id="iup-scan-progress" style="display:none;">
				<div class="spinner-grow float-left mr-1" role="status">
					<span class="sr-only">Loading...</span>
				</div>
				<h3 class="display-5 float-left iup-local">Scanning local filesystem...</h3>
				<h3 class="display-5 float-left iup-cloud">Comparing to the cloud...</h3>
			</div>
			<div class="row mt-4">
				<button type="button" class="btn btn-primary" id="iup-scan">
					Scan Uploads
				</button>
				<button type="button" class="btn btn-primary ml-2" id="iup-sync">
					Sync to Cloud
				</button>
			</div>
		</div>

		<div class="jumbotron">
			<h2 class="display-4">3. Enable</h2>
			<p class="lead">Enable syncing and serving new uploads from the the Infinite Uploads cloud and global CDN.</p>
			<hr class="my-4">
			<p>Before we can begin serving all your files from the Infinite Uploads global CDN, we need to copy your uploads directory to our cloud storage.</p>
			<!-- Button trigger modal -->
			<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal">
				Copy to Cloud
			</button>
		</div>


		<div class="container">
			<h2>Settings</h2>
			<form>
				<div class="form-group custom-control custom-switch">
					<input type="checkbox" class="custom-control-input" id="customSwitch1">
					<label class="custom-control-label" for="customSwitch1">Keep a local copy of new uploads</label>
					<small class="form-text text-muted">The default is to move all new uploads to the cloud to free up local storage and make your site stateless. If you are just trying out Infinite Uploads or using it more for backup purposes you may want to enable this setting.</small>
				</div>
				<div class="form-group custom-control custom-switch">
					<input type="checkbox" class="custom-control-input" id="customSwitch2">
					<label class="custom-control-label" for="customSwitch2">Keep a local copy of new uploads</label>
					<small class="form-text text-muted">The default is to move all new uploads to the cloud to free up storage and make your site stateless. If you are just trying out Infinite Uploads or using it more for backup purposes you may want to enable this.</small>
				</div>
				<button type="submit" class="btn btn-primary">Save</button>
			</form>
		</div>


		<!-- Modal -->
		<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						...
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
						<button type="button" class="btn btn-primary">Save changes</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		echo '</div>';
	}
}
