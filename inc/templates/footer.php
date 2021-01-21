<div id="iup-footer" class="container mt-5">
	<div class="row">
		<div class="col-sm text-center text-muted">
			<strong><?php _e( "The Cloud by Infinite Uploads", 'infinite-uploads' ); ?></strong>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-sm text-center text-muted">
			<a href="<?php echo esc_url( $this->api_url( '/support/' ) ); ?>" class="text-muted"><?php _e( "Support", 'infinite-uploads' ); ?></a> |
			<a href="<?php echo esc_url( $this->api_url( '/terms-of-service/' ) ); ?>" class="text-muted"><?php _e( "Terms of Service", 'infinite-uploads' ); ?></a> |
			<a href="<?php echo esc_url( $this->api_url( '/privacy/' ) ); ?>" class="text-muted"><?php _e( "Privacy Policy", 'infinite-uploads' ); ?></a>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-sm text-center text-muted">
			<a href="https://twitter.com/infiniteuploads" class="text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Twitter', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-twitter"></span></a>
			<a href="https://www.facebook.com/infiniteuploads/" class="text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Facebook', 'infinite-uploads' ); ?>"><span class="dashicons dashicons-facebook-alt"></span></a>
		</div>
	</div>
</div>
