<div class="card">
	<div class="card-header h5"><?php _e( 'Cloud Storage Overview', 'iup' ); ?></div>
	<div class="card-body cloud p-5">
		<div class="row align-items-center justify-content-center mb-5">
			<div class="col">
				<p class="lead mb-0"><?php _e( "This Site's Cloud Bytes / Files", 'iup' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'iup' ); ?>"></span></p>
				<span class="h1"><?php echo $stats['local_size']; ?><small class="text-muted"> / <?php echo $stats['local_files']; ?></small></span>

				<div class="container">
					<?php foreach ( $types as $type ) { ?>
						<div class="row mt-2">
							<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo $type['color']; ?>">&nbsp;</span></div>
							<div class="col-3 lead"><?php echo $type['label']; ?></div>
							<div class="col-3"><strong><?php echo size_format( $type['size'], 2 ); ?> / <?php echo number_format_i18n( $type['files'] ); ?></strong></div>
						</div>
					<?php } ?>
				</div>
			</div>
			<div class="col text-center">
				<p class="h5"><?php printf( __( '%s / %s %s Plan', 'iup' ), '1.2 GB', '10 GB', 'Starter' ); ?></p>
				<canvas id="iup-cloud-pie"></canvas>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col-4 text-center">
				<p><?php _e( 'Visit the Infinite Uploads site to view, manage, or change your plan.', 'iup' ); ?></p>
				<a class="btn btn-info btn-lg" id="" href="https://infiniteuploads.com/?register=<?php echo admin_url( 'options-general.php?page=infinite_uploads' ); ?>" role="button"><?php _e( 'Account Management', 'iup' ); ?></a>
			</div>
		</div>
	</div>
</div>
