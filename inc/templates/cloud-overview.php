<div class="card">
	<div class="card-header">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php esc_html_e( 'Cloud Storage Overview', 'infinite-uploads' ); ?></h5>
			<?php require_once( dirname( __FILE__ ) . '/status-icon.php' ); ?>
		</div>
	</div>
	<div class="card-body cloud p-md-5">
		<div class="row align-items-center justify-content-center mb-5">
			<div class="col-lg col-xs-12 mx-sm-0">
				<p class="lead mb-0"><?php esc_html_e( "This Site's Cloud Bytes / Files", 'infinite-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'infinite-uploads' ); ?>"></span></p>
				<span class="h2 text-nowrap"><?php echo $this->size_format_zero( $cloud_size, 2 ); ?><small class="text-muted"> / <?php echo number_format_i18n( $cloud_files ); ?></small></span>

				<div class="container p-0 ml-md-3">
					<?php foreach ( $this->iup_instance->get_filetypes( false, $api_data->stats->site->types ) as $type ) { ?>
						<div class="row mt-2">
							<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type->color; ?>">&nbsp;</span></div>
							<div class="col-4 lead text-nowrap"><?php echo $type->label; ?></div>
							<div class="col-5 text-nowrap"><strong><?php echo size_format( $type->size, 2 ); ?> / <?php echo number_format_i18n( $type->files ); ?></strong></div>
						</div>
					<?php } ?>
				</div>
			</div>
			<div class="col-lg col-xs-12 text-center mt-5 mt-lg-0 iup-pie-wrapper">
				<p class="h5"><?php printf( esc_html__( '%s / %s', 'infinite-uploads' ), $this->size_format_zero( $cloud_total_size, 2 ), esc_html( $api_data->plan->label ) ); ?></p>
				<canvas id="iup-cloud-pie"></canvas>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col text-center">
				<p><?php esc_html_e( 'Visit the Infinite Uploads site to view, manage, or change your plan.', 'infinite-uploads' ); ?></p>
				<a class="btn text-nowrap btn-info btn-lg" href="<?php echo esc_url( $this->api_url( '/account/' ) ); ?>" role="button"><?php esc_html_e( 'Account Management', 'infinite-uploads' ); ?></a>
			</div>
		</div>
	</div>
</div>
