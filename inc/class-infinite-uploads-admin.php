<?php

class Infinite_Uploads_admin {

	private static $instance;

	public function __construct() {
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
	}

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

					//progress indication
					$('.iup-scan-progress').show();
					$('.iup-scan-progress .spinner-border').addClass('text-hide');
					$('.iup-scan-progress .iup-local .spinner-border').removeClass('text-hide');
					$('.iup-scan-progress h3').addClass('text-muted');
					$('.iup-scan-progress .iup-local h3').removeClass('text-muted');

					var data = {"remaining_dirs": remaining_dirs};
					$.post(ajaxurl + '?action=infinite-uploads-filelist', data, function (json) {
						if (json.success) {
							if (json.data.is_data) {
								$('#iup-progress-gauges').show();
							}
							$('.iup-progress-pcnt').text(json.data.pcnt_complete);
							$('.iup-progress-size').text(json.data.remaining_size);
							$('.iup-progress-files').text(json.data.remaining_files);
							$('.iup-progress-total-size').text(json.data.total_size);
							$('.iup-progress-total-files').text(json.data.total_files);
							$('#iup-sync-progress-bar .iup-cloud').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete);
							$('#iup-sync-progress-bar .iup-local').css('width', 100 - json.data.pcnt_complete + "%").attr('aria-valuenow', 100 - json.data.pcnt_complete);
							if (!json.data.is_done) {
								buildFilelist(json.data.remaining_dirs);
							} else {
								fetchRemoteFilelist('');
							}

						} else {
							$('#iup-error-msg').text(json.data.substr(0, 200));
							$('#iup-error').show();

							$('.iup-scan-progress').hide();
							$('#iup-sync').show();
						}
					}, 'json').fail(function () {
						$('#iup-error-msg').text("Unknown Error");
						$('#iup-error').show();

						$('.iup-scan-progress').hide();
						$('#iup-sync').show();
					});
				};

				var fetchRemoteFilelist = function (next_token) {

					//progress indication
					$('.iup-scan-progress').show();
					$('.iup-scan-progress .spinner-border').addClass('text-hide');
					$('.iup-scan-progress .iup-cloud .spinner-border').removeClass('text-hide');
					$('.iup-scan-progress h3').addClass('text-muted');
					$('.iup-scan-progress .iup-cloud h3').removeClass('text-muted');

					var data = {"next_token": next_token};
					$.post(ajaxurl + '?action=infinite-uploads-remote-filelist', data, function (json) {
						if (json.success) {
							$('.iup-progress-gauges-cloud, .iup-sync-progress-bar .iup-local div').show();
							$('.iup-progress-pcnt').text(json.data.pcnt_complete);
							$('.iup-progress-size').text(json.data.remaining_size);
							$('.iup-progress-files').text(json.data.remaining_files);
							$('#iup-sync-progress-bar').show();
							$('#iup-sync-progress-bar .iup-cloud').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete);
							$('#iup-sync-progress-bar .iup-local').css('width', 100 - json.data.pcnt_complete + "%").attr('aria-valuenow', 100 - json.data.pcnt_complete);
							if (!json.data.is_done) {
								fetchRemoteFilelist(json.data.next_token);
							} else {
								syncFilelist();
							}

						} else {
							$('#iup-error-msg').text(json.data.substr(0, 200));
							$('#iup-error').show();

							$('.iup-scan-progress').hide();
							$('#iup-sync').show();
						}
					}, 'json')
						.fail(function () {
							$('#iup-error-msg').text("Unknown Error");
							$('#iup-error').show();

							$('.iup-scan-progress').hide();
							$('#iup-sync').show();
						});
				};

				var syncFilelist = function () {

					//progress indication
					$('.iup-scan-progress').show();
					$('.iup-scan-progress .spinner-border').addClass('text-hide');
					$('.iup-scan-progress .iup-sync .spinner-border').removeClass('text-hide');
					$('.iup-scan-progress h3').addClass('text-muted');
					$('.iup-scan-progress .iup-sync h3').removeClass('text-muted');
					$('#iup-sync-progress-bar .progress-bar').addClass('progress-bar-animated progress-bar-striped');

					$.post(ajaxurl + '?action=infinite-uploads-sync', {}, function (json) {
						if (json.success) {
							$('.iup-progress-pcnt').text(json.data.pcnt_complete);
							$('.iup-progress-size').text(json.data.remaining_size);
							$('.iup-progress-files').text(json.data.remaining_files);
							$('#iup-sync-progress-bar .iup-cloud').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete);
							$('#iup-sync-progress-bar .iup-local').css('width', 100 - json.data.pcnt_complete + "%").attr('aria-valuenow', 100 - json.data.pcnt_complete);
							if (!json.data.is_done) {
								syncFilelist();
							} else {
								$('#iup-continue-sync').show();
								$('.iup-scan-progress').hide();
								$('#iup-sync-progress-bar .progress-bar').removeClass('progress-bar-animated progress-bar-striped');
							}

						} else {
							$('#iup-error-msg').text(json.data.substr(0, 200));
							$('#iup-error').show();

							$('#iup-continue-sync').show();
							$('.iup-scan-progress').hide();
							$('#iup-sync-progress-bar .progress-bar').removeClass('progress-bar-animated progress-bar-striped');
						}
					}, 'json')
						.fail(function () {
							$('#iup-error-msg').text("Unknown Error");
							$('#iup-error').show();

							$('#iup-continue-sync').show();
							$('.iup-scan-progress').hide();
							$('#iup-sync-progress-bar .progress-bar').removeClass('progress-bar-animated progress-bar-striped');
						});
				};

				//Syncing
				$('#iup-sync').on('click', function () {
					$('#iup-sync, #iup-continue-sync, #iup-error').hide();

					buildFilelist([]);
				});
				//Resync in case of error
				$('#iup-continue-sync').on('click', function () {
					$('#iup-sync, #iup-continue-sync, #iup-error').hide();

					syncFilelist();
				});
			});
		</script>
		<h2 class="display-5">
			<img src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo.svg', __FILE__ ) ); ?>" alt="Infinite Uploads Logo" height="30" width="30"/>
			Infinite Uploads
		</h2>

		<div class="jumbotron">
			<h2 class="display-4">1. Connect</h2>
			<p class="lead">Create your free Infinite Uploads cloud account and connect this site.</p>
			<hr class="my-4">
			<p>Infinite Uploads is free to get started, and includes 2GB of free cloud storage with unlimited CDN bandwidth.</p>
			<a class="btn btn-primary btn-lg" href="https://infiniteuploads.com/?register=<?php echo admin_url( 'options-general.php?page=infinite_uploads' ); ?>" role="button">Create Account or Login</a>
		</div>

		<div class="jumbotron">
			<h2 class="display-4">2. Sync</h2>
			<p class="lead">Copy all your existing uploads the the cloud.</p>
			<hr class="my-4">
			<p>Before we can begin serving all your files from the Infinite Uploads global CDN, we need to copy your uploads directory to our cloud storage. Please be patient as this can take quite a while depending on the size of your uploads directory and server speed.</p>
			<p>If your host provides access to WP CLI, that is the fastest and most efficient way to sync your files. Simply execute the command: <code>wp infinite-uploads sync</code></p>
			<?php
			$instance = Infinite_uploads::get_instance();
			$stats    = $instance->get_sync_stats();
			?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert" id="iup-error" style="display: none;">
				<strong>Error!</strong> <span id="iup-error-msg"></span>
				<button type="button" class="close" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="container-fluid">
				<div class="row mt-4 ml-1" id="iup-progress-gauges" <?php echo $stats['is_data'] ? '' : 'style="display: none;"'; ?>>
					<ul class="list-group list-group-horizontal">
						<li class="list-group-item list-group-item-primary"><h3 class="m-0"><span class="iup-progress-total-size"><?php echo esc_html( $stats['total_size'] ); ?></span><small class="text-muted"> Local</small></h3></li>
						<li class="list-group-item list-group-item-primary"><h3 class="m-0"><span class="iup-progress-total-files"><?php echo esc_html( $stats['total_files'] ); ?></span><small class="text-muted"> Local Files</small></h3></li>
					</ul>
					<ul class="list-group list-group-horizontal iup-progress-gauges-cloud ml-4" <?php echo $stats['compare_started'] ? '' : 'style="display: none;"'; ?>>
						<li class="list-group-item list-group-item-success"><h3 class="m-0"><span class="iup-progress-pcnt"><?php echo esc_html( $stats['pcnt_complete'] ); ?></span>%<small class="text-muted"> Synced</small></h3></li>
					</ul>
					<ul class="list-group list-group-horizontal iup-progress-gauges-cloud ml-4" <?php echo $stats['compare_started'] ? '' : 'style="display: none;"'; ?>>
						<li class="list-group-item list-group-item-info"><h3 class="m-0"><span class="iup-progress-size"><?php echo esc_html( $stats['remaining_size'] ); ?></span><small class="text-muted"> Remaining</small></h3></li>
						<li class="list-group-item list-group-item-info"><h3 class="m-0"><span class="iup-progress-files"><?php echo esc_html( $stats['remaining_files'] ); ?></span><small class="text-muted"> Remaining Files</small></h3></li>
					</ul>
				</div>

				<div class="progress mt-4" id="iup-sync-progress-bar" style="height: 30px;<?php echo $stats['compare_started'] ? '' : 'display: none;'; ?>">
					<div class="progress-bar bg-info iup-cloud" role="progressbar" style="width: <?php echo esc_attr( $stats['pcnt_complete'] ); ?>%" aria-valuenow="<?php echo esc_attr( $stats['pcnt_complete'] ); ?>" aria-valuemin="0" aria-valuemax="100">
						<div>
							<span class="iup-progress-pcnt"><?php echo esc_html( $stats['pcnt_complete'] ); ?></span>%
						</div>
					</div>
					<div class="progress-bar bg-warning iup-local" role="progressbar" style="width: <?php echo 100 - $stats['pcnt_complete']; ?>%" aria-valuenow="<?php echo 100 - $stats['pcnt_complete']; ?>" aria-valuemin="0" aria-valuemax="100">
						<div <?php echo $stats['compare_started'] ? '' : 'style="display: none;"'; ?>><span class="iup-progress-size"><?php echo esc_html( $stats['remaining_size'] ); ?></span> (<span class="iup-progress-files"><?php echo esc_html( $stats['remaining_files'] ); ?></span> files)</div>
					</div>
				</div>

				<div class="row mt-4 iup-scan-progress" style="display:none;">
					<ul class="col-md">
						<li class="iup-local">
							<div class="spinner-border float-left mr-3 text-hide" role="status"><span class="sr-only">Loading...</span></div>
							<h3 class="text-muted">Scanning local filesystem</h3></li>
						<li class="iup-cloud">
							<div class="spinner-border float-left mr-3 text-hide" role="status"><span class="sr-only">Loading...</span></div>
							<h3 class="text-muted">Comparing to the cloud</h3></li>
						<li class="iup-sync">
							<div class="spinner-border float-left mr-3 text-hide" role="status"><span class="sr-only">Loading...</span></div>
							<h3 class="text-muted">Copying to the cloud</h3></li>
					</ul>
				</div>
				<p class="iup-scan-progress text-muted" style="display:none;">Please leave this tab open while the sync is being processed. If you close the tab the sync will be interrupted and you will have to continue where you left off later.</p>

				<div class="row mt-4">
					<button type="button" class="btn btn-primary" id="iup-sync">
						Sync to Cloud
					</button>
					<button type="button" class="btn btn-primary" id="iup-continue-sync" style="display: none;">
						Continue Sync
					</button>
				</div>
			</div>
		</div>

		<div class="jumbotron">
			<h2 class="display-4">3. Enable</h2>
			<p class="lead">Enable syncing and serving new uploads from the the Infinite Uploads cloud and global CDN.</p>
			<hr class="my-4">
			<!-- Button trigger modal -->
			<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal" disabled>
				Enable Infinite Uploads
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
