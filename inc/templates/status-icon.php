<span class="m-0 p-0 text-muted iup-enabled-status">
	<?php esc_html_e( 'Status', 'infinite-uploads' ); ?>
	<?php if ( isset( $api_data->site ) && ! $api_data->site->upload_writeable ) { ?>
		<span class="dashicons dashicons-cloud text-warning" data-toggle="tooltip" title="<?php esc_attr_e( 'There is a problem with your Infinite Uploads account', 'infinite-uploads' ); ?>"></span>
	<?php } elseif ( infinite_uploads_enabled() ) { ?>
		<span class="dashicons dashicons-cloud-saved" data-toggle="tooltip" title="<?php esc_attr_e( 'Enabled - new uploads are moved to the cloud', 'infinite-uploads' ); ?>"></span>
	<?php } elseif ( $this->api->has_token() ) { ?>
		<span class="dashicons dashicons-cloud-upload" data-toggle="tooltip" title="<?php esc_attr_e( 'Disabled - waiting to sync media to the cloud', 'infinite-uploads' ); ?>"></span>
	<?php } else { ?>
		<span class="dashicons dashicons-cloud text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Disabled - waiting to connect', 'infinite-uploads' ); ?>"></span>
	<?php } ?>
</span>
