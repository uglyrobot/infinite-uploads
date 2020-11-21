<div class="card">
	<div class="card-header h5"><?php _e( 'Account & Settings', 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Includes usage data for all connected sites', 'iup' ); ?>"></span></div>
	<div class="card-body p-5">
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'Cloud Enabled', 'iup' ); ?></h5>
				<p class="lead"><?php _e( 'Your current Infinite Uploads plan and storage.', 'iup' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Used / Available', 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'iup' ); ?>"></span></div>
					<div class="custom-control custom-switch form-control-lg btn-info">
						<input type="checkbox" class="custom-control-input" id="customSwitch1">
						<label class="custom-control-label" for="customSwitch1"></label>
					</div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'Infinite Uploads Plan', 'iup' ); ?></h5>
				<p class="lead"><?php _e( 'Your current Infinite Uploads plan and storage.', 'iup' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Used / Available', 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'iup' ); ?>"></span></div>
					<div class="col text-right"><?php _e( 'Need more?', 'iup' ); ?> <a href="#" class="text-warning"><?php _e( 'Switch to a new plan.', 'iup' ); ?></a></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php printf( __( '%s / %s', 'iup' ), $this->size_format_zero( $cloud_total_size, 2 ), esc_html( $api_data->plan->label ) ); ?></p></div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'CDN Bandwidth', 'iup' ); ?></h5>
				<p class="lead"><?php _e( 'Infinite Uploads includes allotted bandwidth for CDN delivery of your files.', 'iup' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Used / Available', 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'iup' ); ?>"></span></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php printf( __( '%s / %s', 'iup' ), $this->size_format_zero( $api_data->stats->cloud->bandwidth, 2 ), $this->size_format_zero( $api_data->plan->bandwidth_limit ) ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'CDN URL', 'iup' ); ?></h5>
				<p class="lead"><?php _e( 'Your uploads are served from this CDN url via 45 edge locations around the world.', 'iup' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Current CDN URL', 'iup' ); ?></div>
					<div class="col text-right"><?php _e( 'Use your own domain!', 'iup' ); ?> <a href="#" class="text-warning"><?php _e( 'Upgrade to a business plan.', 'iup' ); ?></a></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php echo esc_html( $api_data->site->cdn_url ); ?></p></div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'Storage Region', 'iup' ); ?></h5>
				<p class="lead"><?php _e( 'The location of our servers storing your uploads.', 'iup' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Region', 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Region can only be selected when first connecting your site.', 'iup' ); ?>"></span></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php echo esc_html( $region_labels[ $api_data->site->region ] ); ?></p></div>
				</div>
			</div>
		</div>
		<?php if ( (bool) $stats['deletable_files'] ) { ?>
			<div class="row justify-content-center iup-settings-row-delete">
				<div class="col">
					<h5><?php _e( 'Free Up Local Storage', 'iup' ); ?></h5>
					<p class="lead"><?php _e( 'There are unused local copies of files already synced to the cloud. You can optionally delete these to free up local storage space.', 'iup' ); ?></p>
				</div>
				<div class="col mt-4">
					<div class="row text-center mb-3">
						<div class="col"><?php _e( 'This saves space and improves server performance.', 'iup' ); ?></div>
					</div>
					<div class="row justify-content-center">
						<div class="col-4 text-center">
							<button class="btn btn-info btn-lg btn-block" data-toggle="modal" data-target="#delete-modal"><?php _e( 'Delete', 'iup' ); ?></button>
							<p><strong><?php printf( __( '%s / %s deletable files', 'iup' ), $stats['deletable_size'], $stats['deletable_files'] ); ?></strong></p>
						</div>
					</div>
				</div>
			</div>
		<?php } ?>
		<div class="row justify-content-center">
			<div class="col">
				<h5><?php _e( 'Import & Disconnect', 'iup' ); ?></h5>
				<p class="lead"><?php _e( 'Download your media files and disconnect from our cloud. To cancel or manage your storage plan please visit infiniteuploads.com.', 'iup' ); ?></p>
			</div>
			<div class="col mt-4">
				<div class="row text-center mb-3">
					<div class="col"><?php _e( 'We will download your files back to the uploads directory before disconnecting to prevent broken media on your site.', 'iup' ); ?></div>
				</div>
				<div class="row justify-content-center">
					<div class="col-4 text-center">
						<button class="btn btn-info btn-lg btn-block" data-toggle="modal" data-target="#download-modal"><?php _e( 'Disconnect', 'iup' ); ?></button>
						<!--<p><?php printf( __( '%s / %s files to Download', 'iup' ), '1.21 GB', '1,213' ); ?></p>-->
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
