<div class="modal fade" id="enable-modal" tabindex="-1" role="dialog" aria-labelledby="enable-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="enable-modal-label"><?php _e( 'Enable Infinite Uploads', 'infinite-uploads' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-5 mt-3">
						<div class="col text-center">
							<h4><?php _e( 'Enable the Infinite Uploads Cloud', 'infinite-uploads' ); ?></h4>
							<p class="lead"><?php _e( 'Your media library has finished syncing to the Infinite Uploads cloud. Enable now to serve all media from the cloud and global CDN. All new media uploaded will skip the local filesystem and be synced directly to the Infinite Uploads cloud.', 'infinite-uploads' ); ?></p>
							<?php $error_count = $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE synced = 0 AND errors >= 3" ); ?>
							<div id="iup-enable-errors" class="alert alert-warning text-left"
							     role="alert" <?php echo ( $error_count ) ? '' : 'style="display:none;"'; ?>><?php printf( __( 'Note <span>%s</span> files errored while syncing to the cloud. If they are referenced in posts or pages they could show as a 404 after enabling.', 'infinite-uploads' ), number_format_i18n( $error_count ) ); ?></div>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col-4 text-center">
							<button class="btn btn-info btn-lg btn-block" id="iup-enable-button"><span class="dashicons dashicons-cloud-saved"></span><?php _e( 'Enable', 'infinite-uploads' ); ?></button>
							<div class="spinner-border text-muted text-hide" id="iup-enable-spinner" role="status"><span class="sr-only"><?php _e( 'Enabling...', 'iup' ); ?></span></div>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col-2 text-center">
							<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-5.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
