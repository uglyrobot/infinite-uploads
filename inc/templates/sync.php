<div class="card">
	<div class="card-header h5"><?php _e( 'Ready to Sync', 'iup' ); ?></div>
	<div class="card-body cloud p-5">
		<div class="row align-items-center justify-content-center mb-5">
			<div class="col">
				<p class="lead mb-0"><?php _e( "Total Bytes / Files", 'iup' ); ?></p>
				<span class="h1"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></span>

				<div class="container">
					<?php foreach ( $types as $type ) { ?>
						<div class="row mt-2">
							<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type['color']; ?>">&nbsp;</span></div>
							<div class="col-3 lead"><?php echo $type['label']; ?></div>
							<div class="col-3"><strong><?php echo size_format( $type['size'], 2 ); ?> / <?php echo number_format_i18n( $type['files'] ); ?></strong></div>
						</div>
					<?php } ?>
					<div class="row mt-2">
						<div class="col-7 text-muted text-center"><small><?php printf( __( 'Scanned %s ago', 'iup' ), human_time_diff( $stats['files_finished'] ) ); ?> &dash; <a href="#" class="badge badge-primary" data-toggle="modal" data-target="#scan-modal"><span data-toggle="tooltip"
						                                                                                                                                                                                                                                                   title="<?php esc_attr_e( 'Run a new scan to detect and sync recently uploaded files.', 'iup' ); ?>"><?php _e( 'Refresh', 'iup' ); ?></span></a></small>
						</div>
					</div>
				</div>
			</div>
			<div class="col-1 text-center">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/arrow.svg', dirname( __FILE__ ) ) ); ?>" alt="Right sync arrow" height="31" width="56"/>
			</div>
			<div class="col">
				<div class="row justify-content-center mb-3">
					<div class="col text-center">
						<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-blue.svg', dirname( __FILE__ ) ) ); ?>" alt="Push to Cloud" height="76" width="76"/>
						<p class="lead"><?php printf( __( 'You have %s of premium storage available!', 'iup' ), '10 GB' ); ?></p>
						<p class="lead"><?php _e( 'Move your media library to the Infinite Uploads cloud.', 'iup' ); ?></p>
					</div>
				</div>
				<div class="row justify-content-center">
					<div class="col-4 text-center">
						<button class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#upload-modal"><span class="dashicons dashicons-cloud"></span> <?php _e( 'Sync Now', 'iup' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col-2 text-center">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-3.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
			</div>
		</div>
	</div>
</div>
