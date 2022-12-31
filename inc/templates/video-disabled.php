<div class="card">
	<div class="card-header">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php esc_html_e( 'Video Cloud', 'infinite-uploads' ); ?></h5>
			<?php require_once( dirname( __FILE__ ) . '/video-status-icon.php' ); ?>
		</div>
	</div>
	<div class="card-body cloud p-md-3">
		<div class="row align-items-center justify-content-center mb-3">
			<div class="col-lg col-xs-12">
				<div class="row justify-content-center mb-3 mt-2 mt-lg-2">
					<div class="col text-center">
						<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/video-player.jpg', dirname( __FILE__ ) ) ); ?>" alt="Video player example" height="180" width="320"/>
						<p class="lead"><?php esc_html_e( "Easily upload videos of any size directly to the cloud and have them automatically transcoded into multiple resolutions for optimal playback on any device. Plus, our customizable unbranded embedded video player allows you to stream your videos from our global CDN, ensuring smooth and seamless playback for your audience. With Infinite Uploads, you'll have everything you need to host and share your videos with the world. All right inside the WordPress dashboard!", 'infinite-uploads' ); ?></p>
						<p class="lead font-weight-bold"><?php esc_html_e( 'Included FREE with your Infinite Uploads storage plan!', 'infinite-uploads' ); ?></p>
					</div>
				</div>
				<div class="row justify-content-center">
					<div class="col text-center">
						<button class="btn text-nowrap btn-primary btn-lg" id="iup-enable-video-button"><span class="dashicons dashicons-video-alt3"></span> <?php esc_html_e( 'Enable Video Cloud', 'infinite-uploads' ); ?></button>
						<div class="spinner-grow text-muted d-none mx-auto" id="iup-enable-video-spinner" role="status"><span class="sr-only"><?php esc_html_e( 'Enabling...', 'iup' ); ?></span></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
