<div class="card">
	<div class="card-header h5"><?php _e( 'Local File Overview', 'iup' ); ?></div>
	<div class="card-body cloud p-5">
		<div class="row align-items-center justify-content-center">
			<div class="col">
				<p class="lead mb-0"><?php _e( "Total Bytes / Files", 'iup' ); ?></p>
				<span class="h1"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></span>

				<div class="container">
					<?php foreach ( $types as $type ) { ?>
						<div class="row mt-2">
							<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type['color']; ?>">&nbsp;</span></div>
							<div class="col-3 lead"><?php echo $type['label']; ?></div>
							<div class="col-3 text-justify"><strong><?php echo size_format( $type['size'], 2 ); ?> / <?php echo number_format_i18n( $type['files'] ); ?></strong></div>
						</div>
					<?php } ?>
					<div class="row mt-2">
						<div class="col-7 text-muted text-center"><small><?php printf( __( 'Scanned %s ago', 'iup' ), human_time_diff( $stats['files_finished'] ) ); ?> &dash; <a href="#" class="badge badge-primary" data-toggle="modal" data-target="#scan-modal"><span data-toggle="tooltip"
						                                                                                                                                                                                                                                                   title="<?php esc_attr_e( 'Run a new scan to detect and sync recently uploaded files.', 'iup' ); ?>"><?php _e( 'Refresh', 'iup' ); ?></span></a></small>
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
				<h4><?php _e( 'Ready to Connect!', 'iup' ); ?></h4>
				<p class="lead"><?php _e( 'Get smart plan recommendations, create or connect to existing account, and sync to the cloud.', 'iup' ); ?></p>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col-2 text-center">
				<a class="btn btn-primary btn-lg btn-block" id="" href="https://infiniteuploads.com/?register=<?php echo admin_url( 'options-general.php?page=infinite_uploads' ); ?>" role="button"><span class="dashicons dashicons-cloud"></span> <?php _e( 'Connect', 'iup' ); ?></a>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col-2 text-center">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-2.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
			</div>
		</div>
	</div>
</div>
