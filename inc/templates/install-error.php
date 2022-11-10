<div class="card">
	<div class="card-body cloud p-md-5">
		<div class="row justify-content-center mb-5 mt-3">
			<div class="col text-center">
				<h4 class="text-warning"><?php esc_html_e( 'Installation Error', 'infinite-uploads' ); ?></h4>
				<p class="lead"><?php esc_html_e( "We are so sorry, there appears to have been a problem installing the needed tables for the Infinite Uploads plugin. We want to get this working for you so please contact us and we will help you out!", 'infinite-uploads' ); ?></p>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col text-center">
				<a class="btn text-nowrap btn-info btn-lg" href="<?php echo esc_url( $this->api_url( '/support/?utm_source=iup_plugin&utm_medium=plugin&utm_campaign=iup_plugin&utm_content=error&utm_term=support' ) ); ?>" role="button"><?php esc_html_e( 'Contact Support', 'infinite-uploads' ); ?></a>
			</div>
		</div>
	</div>
</div>
