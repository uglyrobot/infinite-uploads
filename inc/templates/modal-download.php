<div class="modal fade" id="download-modal" tabindex="-1" role="dialog" aria-labelledby="download-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="download-modal-label"><?php _e( 'Download & Disconnect', 'iup' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-5 mt-3">
						<div class="col text-center">
							<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/download-from-cloud.svg', dirname( __FILE__ ) ) ); ?>" alt="Download from Cloud" height="76" width="76"/>
							<h4><?php _e( 'Downloading Files', 'iup' ); ?></h4>
							<p class="lead"><?php _e( "This usually only takes a minute or two but can take longer for very large media libraries with a lot of files. Please leave this tab open while we complete your scan.", 'iup' ); ?></p>
						</div>
					</div>
					<div class="row justify-content-center mb-5">
						<div class="col text-center">
							<div class="progress download">
								<div class="progress-bar" role="progressbar" style="width: 66%;" aria-valuenow="66" aria-valuemin="0" aria-valuemax="100">66%</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
