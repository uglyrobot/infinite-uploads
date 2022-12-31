<span class="m-0 p-0 text-muted iup-enabled-status">
	<?php esc_html_e( 'Status', 'infinite-uploads' ); ?>
	<?php if ( $this->video->is_video_active() && ! $this->video->is_video_enabled() ) { ?>
		<span class="dashicons dashicons-video-alt3 text-warning" data-toggle="tooltip" title="<?php esc_attr_e( 'There is a problem with your Infinite Uploads account', 'infinite-uploads' ); ?>"></span>
	<?php } elseif ( $this->video->is_video_active() ) { ?>
		<span class="dashicons dashicons-video-alt3 " data-toggle="tooltip" title="<?php esc_attr_e( 'Enabled', 'infinite-uploads' ); ?>"></span>
	<?php } elseif ( $this->api->has_token() ) { ?>
		<span class="dashicons dashicons-video-alt3 text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Disabled', 'infinite-uploads' ); ?>"></span>
	<?php } else { ?>
		<span class="dashicons dashicons-video-alt3 text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Disabled - waiting to connect', 'infinite-uploads' ); ?>"></span>
	<?php } ?>
</span>
