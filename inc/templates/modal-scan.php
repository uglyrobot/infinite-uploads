<div class="modal fade" id="scan-modal" tabindex="-1" role="dialog" aria-labelledby="scan-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="scan-modal-label"><?php _e( 'Scanning Files', 'infinite-uploads' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-4 mt-3">
						<div class="col text-center">
							<div class="mb-4 mx-auto" style="width: 76px; height: 76px;">
								<?php include( dirname( dirname( __FILE__ ) ) . '/assets/img/spinner-svg.html' ); ?>
							</div>
							<h4><?php _e( 'Scanning Local Filesystem', 'infinite-uploads' ); ?></h4>
							<p class="lead"><?php _e( "This usually only takes a minute or two but can take longer for very large media libraries with a lot of files. Please leave this tab open while we complete your scan.", 'infinite-uploads' ); ?></p>
						</div>
					</div>
					<div class="row justify-content-center mb-5">
						<div class="col text-center text-muted">
							<span class="h3"><?php _e( 'Found <span id="iup-scan-storage">0 MB</span> / <span id="iup-scan-files">0</span> Files...', 'infinite-uploads' ); ?></span>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col-2 text-center">
							<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-1.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
