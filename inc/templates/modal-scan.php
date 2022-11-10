<div class="modal fade" id="scan-modal" tabindex="-1" role="dialog" aria-labelledby="scan-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="scan-modal-label"><?php esc_html_e( 'Scanning Files', 'infinite-uploads' ); ?></h5>
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
							<h4><?php esc_html_e( 'Scanning Local Filesystem', 'infinite-uploads' ); ?></h4>
							<p class="lead"><?php esc_html_e( "This usually only takes a minute or two but can take longer for very large media libraries with a lot of files. Please leave this tab open while we complete your scan.", 'infinite-uploads' ); ?></p>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col text-center text-muted">
							<span class="h5" id="iup-scan-progress">
								<?php
								printf(
								// translators: %1$s is the opening a tag for storage
								// translators: %2$s is the closing a tag for storage
								// translators: %3$s is the opening a tag for files
								// translators: %4$s is the closing a tag for files
									esc_html__( 'Found %1$s0 MB%2$s / %3$s0%4$s Files...', 'infinite-uploads' ),
									'<span id="iup-scan-storage">', '</span>', '<span id="iup-scan-files">', '</span>' );
								?>
							</span>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col text-center">
							<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-1.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
