<div class="modal fade" id="upload-modal" tabindex="-1" role="dialog" aria-labelledby="upload-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="upload-modal-label"><?php _e( 'Upload to Cloud', 'iup' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-5 mt-3">
						<div class="col text-center">
							<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/push-to-cloud.svg', dirname( __FILE__ ) ) ); ?>" alt="Push to Cloud" height="76" width="76"/>
							<h4><?php _e( 'Sync in Progress', 'iup' ); ?></h4>
							<p class="lead"><?php _e( "This process can take many hours for very large media libraries with a lot of files. Please leave this tab open while the sync is being processed. If you close the tab the sync will be interrupted and you will have to continue where you left off later.", 'iup' ); ?></p>
							<p><?php _e( 'If your host provides access to WP CLI, that is the fastest and most efficient way to sync your files. Simply execute the command:', 'iup' ); ?> <code>wp infinite-uploads sync</code></p>
						</div>
					</div>
					<div class="row justify-content-center mb-5">
						<div class="col text-center">
							<div class="progress">
								<div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">25%</div>
							</div>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col-2 text-center">
							<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-4.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
