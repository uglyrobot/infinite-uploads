<div class="row mt-2">
	<div class="col-12 col-lg-6 mb-4">
		<?php
		if ( infinite_uploads_enabled() ) {
			require_once( dirname( __FILE__ ) . '/cloud-overview.php' );
		} else {
			require_once( dirname( __FILE__ ) . '/sync.php' );
		}
		?>
	</div>
	<div class="col-12 col-lg-6 mb-4">
		<?php
		if ( $this->video->is_video_active() ) {
			$video_library_settings = $this->video->get_library_settings();
			require_once( dirname( __FILE__ ) . '/video-overview.php' );
		} else {
			require_once( dirname( __FILE__ ) . '/video-disabled.php' );
		}
		?>
	</div>
</div>