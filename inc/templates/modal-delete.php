<div class="modal fade" id="delete-modal" tabindex="-1" role="dialog" aria-labelledby="delete-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="delete-modal-label"><?php esc_html_e( 'Free Up Local Storage', 'infinite-uploads' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-4 mt-3">
						<div class="col text-center">
							<h4><?php esc_html_e( 'Delete Local Files', 'infinite-uploads' ); ?></h4>
							<p class="lead"><?php esc_html_e( "This will delete the duplicate copies of your files stored in your local media library. This saves space and improves server performance but will require downloading these files back to the uploads directory before disconnecting to prevent broken media on your site.", 'infinite-uploads' ); ?></p>
							<p><?php esc_html_e( 'If your host provides access to WP CLI, you can also execute the command:', 'infinite-uploads' ); ?> <code>wp infinite-uploads delete</code></p>
						</div>
					</div>
					<div class="row justify-content-center mb-5">
						<div class="col text-center text-muted">
							<div id="iup-delete-local-spinner" class="spinner-border spinner-border-sm" role="status" style="display: none;">
								<span class="sr-only">Deleting...</span>
							</div>
							<span class="h5"><?php printf( __( '<span id="iup-delete-size">%s</span> / <span id="iup-delete-files">%s</span> Deletable Files', 'infinite-uploads' ), $stats['deletable_size'], $stats['deletable_files'] ); ?></span>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col text-center">
							<button class="btn text-nowrap btn-info btn-lg" id="iup-delete-local-button"><?php esc_html_e( 'Start Delete', 'infinite-uploads' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
