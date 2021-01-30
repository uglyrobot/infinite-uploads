<div class="modal fade" id="scan-remote-modal" tabindex="-1" role="dialog" aria-labelledby="scan-remote-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="scan-modal-label"><?php esc_html_e( 'Scanning Cloud', 'infinite-uploads' ); ?></h5>
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
							<h4><?php esc_html_e( 'Comparing to Cloud', 'infinite-uploads' ); ?></h4>
							<p class="lead"><?php esc_html_e( "Checking for files already existing in the cloud. Please leave this tab open while we complete your scan.", 'infinite-uploads' ); ?></p>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col text-center text-muted">
							<span
								class="h5" <?php echo ! $stats['cloud_files'] ? 'id="iup-scan-remote-progress"' : ''; ?>><?php printf( __( '<span id="iup-scan-remote-storage">%s</span> / <span id="iup-scan-remote-files">%s</span> Files Synced', 'infinite-uploads' ), $stats['cloud_size'], $stats['cloud_files'] ); ?></span>
						</div>
					</div>
					<?php if ( ! infinite_uploads_enabled() ) { ?>
						<div class="row justify-content-center mb-4">
							<div class="col text-center">
								<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-3.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
							</div>
						</div>
					<?php } //end not enabled ?>
				</div>
			</div>
		</div>
	</div>
</div>
