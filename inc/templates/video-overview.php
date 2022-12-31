<div class="card">
	<div class="card-header">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php esc_html_e( 'Video Cloud Overview', 'infinite-uploads' ); ?></h5>
			<?php require_once( dirname( __FILE__ ) . '/video-status-icon.php' ); ?>
		</div>
	</div>
	<div class="card-body cloud p-md-4">
		<div class="row align-items-center justify-content-between mb-1">
			<div class="col">
				<h6>
					<?php esc_html_e( 'Site stats:', 'infinite-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'infinite-uploads' ); ?>"></span>
				</h6>
			</div>
		</div>
		<div class="row align-items-center justify-content-between mb-5">
			<div class="col-lg col-xs-12 mx-sm-0">
				<p class="lead mb-0"><?php esc_html_e( "Video Count", 'infinite-uploads' ); ?></p>
				<span class="h2 text-nowrap"><?php echo number_format_i18n( $video_library_settings->VideoCount ?? 0 ); ?></span>
			</div>
			<div class="col-lg col-xs-12 mx-sm-0">
				<p class="lead mb-0"><?php esc_html_e( "Video Storage", 'infinite-uploads' ); ?></p>
				<span class="h2 text-nowrap"><?php echo $this->size_format_zero( $video_library_settings->StorageUsage ?? 0, 2 ); ?></span>
			</div>
			<div class="col-lg col-xs-12 mx-sm-0">
				<p class="lead mb-0"><?php esc_html_e( "Video Bandwidth", 'infinite-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'This calendar month.', 'infinite-uploads' ); ?>"></span></p>
				<span class="h2 text-nowrap"><?php echo $this->size_format_zero( $video_library_settings->TrafficUsage ?? 0, 2 ); ?></span>
			</div>
		</div>
		<div class="row mb-4">
			<div class="col text-center">
				<p class="lead"><?php esc_html_e( "Upload, transcode, and embed videos of any size via our Gutenberg Block, shortcode, or video library. Included FREE with your Infinite Uploads storage & CDN plan.", 'infinite-uploads' ); ?></p>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col text-center">
				<p><?php esc_html_e( 'View and manage your cloud video library.', 'infinite-uploads' ); ?></p>
				<a class="btn text-nowrap btn-primary btn-lg" href="<?php echo esc_url( $this->video->library_url() ); ?>" role="button"><span class="dashicons dashicons-embed-video"></span> <?php esc_html_e( 'Video Library', 'infinite-uploads' ); ?></a>
			</div>
			<div class="col text-center">
				<p><?php esc_html_e( 'Manage cloud video library settings.', 'infinite-uploads' ); ?></p>
				<a class="btn text-nowrap btn-info btn-lg" href="<?php echo esc_url( $this->video->settings_url() ); ?>" role="button"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'infinite-uploads' ); ?></a>
			</div>
		</div>
	</div>
</div>
