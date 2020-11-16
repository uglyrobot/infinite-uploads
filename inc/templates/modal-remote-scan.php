<div class="modal fade" id="scan-remote-modal" tabindex="-1" role="dialog" aria-labelledby="scan-remote-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="scan-modal-label"><?php _e( 'Scanning Cloud', 'iup' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-4 mt-3">
						<div class="col text-center">
							<div class="mb-4 mx-auto" style="width: 76px; height: 76px;">
								<?php include( dirname( dirname( __FILE__ ) ) . '/assets/img/spinner-svg-2.html' ); ?>
							</div>
							<h4><?php _e( 'Comparing to Cloud', 'iup' ); ?></h4>
							<p class="lead"><?php _e( "Checking for files already uploaded to the cloud. Please leave this tab open while we complete your scan.", 'iup' ); ?></p>
						</div>
					</div>
					<div class="row justify-content-center mb-5">
						<div class="col text-center text-muted">
							<span class="h3"><?php printf( __( '<span id="iup-scan-remote-storage">%s</span> / <span id="iup-scan-remote-files">%s</span> Files Synced...', 'iup' ), $stats['cloud_size'], $stats['cloud_files'] ); ?></span>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col-2 text-center">
							<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-3.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
