<div class="card">
	<div class="card-header">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php _e( 'Local File Overview', 'infinite-uploads' ); ?></h5>
			<?php require_once( dirname( __FILE__ ) . '/status-icon.php' ); ?>
		</div>
	</div>
	<div class="card-body cloud p-5">
		<div class="row align-items-center justify-content-center">
			<div class="col">
				<p class="lead mb-0"><?php _e( "Total Bytes / Files", 'infinite-uploads' ); ?></p>
				<span class="h1"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></span>

				<div class="container">
					<?php foreach ( $this->iup_instance->get_filetypes( false ) as $type ) { ?>
						<div class="row mt-2">
							<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type->color; ?>">&nbsp;</span></div>
							<div class="col-3 lead"><?php echo $type->label; ?></div>
							<div class="col-4"><strong><?php echo size_format( $type->size, 2 ); ?> / <?php echo number_format_i18n( $type->files ); ?></strong></div>
						</div>
					<?php } ?>
					<div class="row mt-2">
						<div class="col-7 text-muted text-center"><small><?php printf( __( 'Scanned %s ago', 'infinite-uploads' ), human_time_diff( $stats['files_finished'] ) ); ?> &dash; <a href="#" class="badge badge-primary" data-toggle="modal" data-target="#scan-modal"><span
										data-toggle="tooltip"
										title="<?php esc_attr_e( 'Run a new scan to detect and sync recently uploaded files.', 'infinite-uploads' ); ?>"><?php _e( 'Refresh', 'infinite-uploads' ); ?></span></a></small>
						</div>
					</div>
				</div>
			</div>
			<div class="col">
				<canvas id="iup-local-pie"></canvas>
			</div>
		</div>
		<div class="row justify-content-center mb-3">
			<div class="col text-center">
				<h4><?php _e( 'Ready to Connect!', 'infinite-uploads' ); ?></h4>
				<p class="lead"><?php _e( 'Get smart plan recommendations, create or connect to existing account, and sync to the cloud.', 'infinite-uploads' ); ?></p>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col-2 text-center">
				<form method="post" action="<?php echo $this->api_url( '/smart-pricing/' ); ?>">
					<input type="hidden" name="action" value="iup_connect">
					<input type="hidden" name="site_id" value="<?php echo esc_attr( $this->api->get_site_id() ); ?>">
					<input type="hidden" name="domain" value="<?php echo esc_url( $this->api->network_site_url() ); ?>">
					<input type="hidden" name="redirect_url" value="<?php echo esc_url( $this->settings_url() ); ?>">
					<input type="hidden" name="bytes" value="<?php echo esc_attr( $to_sync->size ); ?>">
					<input type="hidden" name="files" value="<?php echo esc_attr( $to_sync->files ); ?>">
					<button class="btn btn-primary btn-lg btn-block" type="submit"><span class="dashicons dashicons-cloud"></span> <?php _e( 'Connect', 'infinite-uploads' ); ?></button>

				</form>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col-2 text-center">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-2.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
			</div>
		</div>
	</div>
</div>
