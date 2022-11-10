<div class="card">
	<div class="card-header">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php esc_html_e( 'Ready to Sync', 'infinite-uploads' ); ?></h5>
			<?php require_once( dirname( __FILE__ ) . '/status-icon.php' ); ?>
		</div>
	</div>
	<div class="card-body cloud p-md-5">
		<div class="row align-items-center justify-content-center mb-5">
			<div class="col-lg col-xs-12">
				<p class="lead mb-0"><?php esc_html_e( "Total Local Bytes / Files", 'infinite-uploads' ); ?></p>
				<span class="h2 text-nowrap"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></span>

				<div class="container p-0 ml-md-3">
					<?php foreach ( $this->iup_instance->get_filetypes( false ) as $type ) { ?>
						<div class="row mt-2">
							<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type->color; ?>">&nbsp;</span></div>
							<div class="col-4 lead text-nowrap"><?php echo $type->label; ?></div>
							<div class="col-5 text-nowrap"><strong><?php echo size_format( $type->size, 2 ); ?> / <?php echo number_format_i18n( $type->files ); ?></strong></div>
						</div>
					<?php } ?>
					<div class="row mt-2">
						<div class="col text-muted"><small><?php printf( esc_html__( 'Scanned %s ago', 'infinite-uploads' ), human_time_diff( $stats['files_finished'] ) ); ?> &dash; <a href="#" class="badge badge-primary" data-toggle="modal" data-target="#scan-modal"><span
										data-toggle="tooltip"
										title="<?php esc_attr_e( 'Run a new scan to detect and sync recently uploaded files.', 'infinite-uploads' ); ?>"><?php esc_html_e( 'Refresh', 'infinite-uploads' ); ?></span></a></small>
						</div>
					</div>
				</div>
			</div>
			<div class="col-lg-2 text-center d-none d-lg-block">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/arrow.svg', dirname( __FILE__ ) ) ); ?>" alt="Right sync arrow" height="31" width="56"/>
			</div>
			<div class="col-lg col-xs-12">
				<?php if ( isset( $api_data->site ) && ! $api_data->site->upload_writeable ) { ?>
					<div class="row justify-content-center mb-3 mt-4 mt-lg-0">
						<div class="col text-center">
							<p class="lead text-warning"><?php esc_html_e( 'Please fix the billing issue with your account to sync your files to the cloud and enable Infinite Uploads.', 'infinite-uploads' ); ?></p>
						</div>
					</div>
					<div class="row justify-content-center">
						<div class="col text-center">
							<a class="btn text-nowrap btn-info btn-lg" href="<?php echo esc_url( $this->api_url( '/account/billing/' ) ); ?>" role="button"><?php esc_html_e( 'Account Management', 'infinite-uploads' ); ?></a>
						</div>
					</div>
				<?php } else { ?>
					<div class="row justify-content-center mb-3 mt-4 mt-lg-0">
						<div class="col text-center">
							<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-blue.svg', dirname( __FILE__ ) ) ); ?>" alt="Push to Cloud" height="76" width="76"/>
							<p class="lead"><?php printf( esc_html__( 'You have %s of premium storage available!', 'infinite-uploads' ), $this->size_format_zero( ( $api_data->plan->storage_limit * GB_IN_BYTES ) - $api_data->stats->cloud->storage, 2 ) ); ?></p>
							<p class="lead"><?php esc_html_e( 'Move your media library to the Infinite Uploads cloud.', 'infinite-uploads' ); ?></p>
						</div>
					</div>
					<div class="row justify-content-center">
						<div class="col text-center">
							<?php if ( ! empty( $stats['sync_finished'] ) ) { //if sync is finished show enable button ?>
								<button class="btn text-nowrap btn-primary btn-lg" data-toggle="modal" data-target="#enable-modal"><span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Sync Now', 'infinite-uploads' ); ?></button>
							<?php } elseif ( ! empty( $stats['compare_finished'] ) ) { ?>
								<button class="btn text-nowrap btn-primary btn-l" data-toggle="modal" data-target="#upload-modal"><span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Sync Now', 'infinite-uploads' ); ?></button>
							<?php } else { ?>
								<button class="btn text-nowrap btn-primary btn-lg" id="iup-sync-button" data-toggle="modal" data-target="#scan-remote-modal" data-next="upload"><span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Sync Now', 'infinite-uploads' ); ?></button>
							<?php } ?>
						</div>
					</div>
				<?php } ?>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col text-center">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-3.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
			</div>
		</div>
	</div>
</div>
