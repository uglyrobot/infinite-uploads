<div class="card">
	<div class="card-header h5">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php _e( 'Account & Settings', 'infinite-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Includes usage data for all connected sites', 'infinite-uploads' ); ?>"></span></h5>
			<span class="m-0 p-0 text-muted iup-refresh-icon">
				<div class="spinner-grow spinner-grow-sm text-secondary text-hide" role="status">
				  <span class="sr-only">Refreshing...</span>
				</div>
				<span class="dashicons dashicons-update-alt" role="button" data-target="<?php echo esc_url( $this->settings_url( [ 'refresh' => 1 ] ) ); ?>" data-toggle="tooltip" title="<?php esc_attr_e( 'Refresh account data', 'infinite-uploads' ); ?>"></span>
				<small><?php printf( __( 'Updated %s ago', 'infinite-uploads' ), human_time_diff( $api_data->refreshed ) ); ?></small>
			</span>
		</div>
	</div>
	<div class="card-body p-5">
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'Infinite Uploads Plan', 'infinite-uploads' ); ?></h5>
				<p class="lead"><?php _e( 'Your current Infinite Uploads plan and storage.', 'infinite-uploads' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Used / Available', 'infinite-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'infinite-uploads' ); ?>"></span></div>
					<div class="col text-right"><?php _e( 'Need more?', 'infinite-uploads' ); ?> <a href="#" class="text-warning"><?php _e( 'Switch to a new plan.', 'infinite-uploads' ); ?></a></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php printf( __( '%s / %s', 'infinite-uploads' ), $this->size_format_zero( $cloud_total_size, 2 ), esc_html( $api_data->plan->label ) ); ?></p></div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'CDN Bandwidth', 'infinite-uploads' ); ?></h5>
				<p class="lead"><?php _e( 'Infinite Uploads includes allotted bandwidth for CDN delivery of your files.', 'infinite-uploads' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Used / Available', 'infinite-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'infinite-uploads' ); ?>"></span></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php printf( __( '%s / %s', 'infinite-uploads' ), $this->size_format_zero( $api_data->stats->cloud->bandwidth, 2 ), $this->size_format_zero( $api_data->plan->bandwidth_limit ) ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'CDN URL', 'infinite-uploads' ); ?></h5>
				<p class="lead"><?php _e( 'Your uploads are served from this CDN url via 45 edge locations around the world.', 'infinite-uploads' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Current CDN URL', 'infinite-uploads' ); ?></div>
					<div class="col text-right"><?php _e( 'Use your own domain!', 'infinite-uploads' ); ?> <a href="#" class="text-warning"><?php _e( 'Upgrade to a business plan.', 'infinite-uploads' ); ?></a></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php echo esc_html( $api_data->site->cdn_url ); ?></p></div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col">
				<h5><?php _e( 'Storage Region', 'infinite-uploads' ); ?></h5>
				<p class="lead"><?php _e( 'The location of our servers storing your uploads.', 'infinite-uploads' ); ?></p>
			</div>
			<div class="col">
				<div class="row">
					<div class="col"><?php _e( 'Region', 'infinite-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Region can only be selected when first connecting your site.', 'infinite-uploads' ); ?>"></span></div>
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
					<h5><?php _e( 'Free Up Local Storage', 'infinite-uploads' ); ?></h5>
					<p class="lead"><?php _e( 'There are unused local copies of files already synced to the cloud. You can optionally delete these to free up local storage space.', 'infinite-uploads' ); ?></p>
				</div>
				<div class="col mt-4">
					<div class="row text-center mb-3">
						<div class="col"><?php _e( 'This saves space and improves server performance.', 'infinite-uploads' ); ?></div>
					</div>
					<div class="row justify-content-center">
						<div class="col-4 text-center">
							<button class="btn btn-info btn-lg btn-block" data-toggle="modal" data-target="#delete-modal"><?php _e( 'Delete', 'infinite-uploads' ); ?></button>
							<p><strong><?php printf( __( '%s / %s deletable files', 'infinite-uploads' ), $stats['deletable_size'], $stats['deletable_files'] ); ?></strong></p>
						</div>
					</div>
				</div>
			</div>
		<?php } ?>
		<div class="row justify-content-center">
			<div class="col">
				<h5><?php _e( 'Import & Disconnect', 'infinite-uploads' ); ?></h5>
				<p class="lead"><?php _e( 'Download your media files and disconnect from our cloud. To cancel or manage your storage plan please visit infiniteuploads.com.', 'infinite-uploads' ); ?></p>
			</div>
			<div class="col mt-4">
				<div class="row text-center mb-3">
					<div class="col"><?php _e( 'We will download your files back to the uploads directory before disconnecting to prevent broken media on your site.', 'infinite-uploads' ); ?></div>
				</div>
				<div class="row justify-content-center">
					<div class="col-4 text-center">
						<button class="btn btn-info btn-lg btn-block" data-toggle="modal" data-target="#download-modal"><?php _e( 'Disconnect', 'infinite-uploads' ); ?></button>
						<!--<p><?php printf( __( '%s / %s files to Download', 'infinite-uploads' ), '1.21 GB', '1,213' ); ?></p>-->
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
