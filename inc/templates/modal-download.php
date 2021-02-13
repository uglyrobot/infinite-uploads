<div class="modal fade" id="download-modal" tabindex="-1" role="dialog" aria-labelledby="download-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="download-modal-label"><?php esc_html_e( 'Download & Disconnect', 'infinite-uploads' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-4 mt-3">
						<div class="col text-center">
							<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/download-from-cloud.svg', dirname( __FILE__ ) ) ); ?>" alt="Download from Cloud" height="76" width="76"/>
							<h4><?php esc_html_e( 'Downloading Files', 'infinite-uploads' ); ?></h4>
							<p class="lead"><?php esc_html_e( "This process can take many hours for very large media libraries with a lot of files. Please leave this tab open while the download is being processed. If you close the tab the download will be interrupted and you will have to continue where you left off later.", 'infinite-uploads' ); ?></p>
							<p><?php esc_html_e( 'If your host provides access to WP CLI, that is the fastest and most efficient way to sync your files. Simply execute the command:', 'infinite-uploads' ); ?> <code>wp infinite-uploads download</code></p>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col text-center">
							<div id="iup-download-errors" class="alert alert-warning text-left" role="alert">
								<ul class="mb-0 mb-lc-0"></ul>
							</div>
							<div class="progress download">
								<div id="iup-download-progress-bar" class="progress-bar progress-bar-animated progress-bar-striped" role="progressbar" style="width: <?php echo $stats['pcnt_downloaded']; ?>%;" aria-valuenow="<?php echo $stats['pcnt_downloaded']; ?>" aria-valuemin="0"
								     aria-valuemax="100"><?php echo $stats['pcnt_downloaded']; ?>%
								</div>
							</div>
							<div class="col text-center text-muted">
								<div class=" spinner-border spinner-border-sm" role="status">
									<span class="sr-only">Downloading...</span>
								</div>
								<span class="h6" id="iup-download-progress"><?php printf( __( '<span id="iup-download-size">%s</span> / <span id="iup-download-files">%s</span> File(s) Remaining', 'infinite-uploads' ), $stats['deleted_size'], $stats['deleted_files'] ); ?></span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
