<div class="card">
	<div class="card-body cloud p-md-5">
		<div class="row justify-content-center mb-5 mt-3">
			<div class="col text-center">
				<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-blue.svg', dirname( __FILE__ ) ) ); ?>" alt="Push to Cloud" height="76" width="76"/>
				<h4><?php esc_html_e( 'Infinite Uploads Setup', 'infinite-uploads' ); ?></h4>
				<p class="lead"><?php esc_html_e( "Welcome to Infinite Uploads, scalable cloud storage and delivery for your uploads made easy! Get started with a scan of your existing Media Library. Then our smart recommendations will help you chose the best plan, create or connect your account, and voilà – you're ready to push to the cloud.", 'infinite-uploads' ); ?></p>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col text-center">
				<button class="btn text-nowrap btn-primary btn-lg" data-toggle="modal" data-target="#scan-modal"><?php esc_html_e( 'Run Scan', 'infinite-uploads' ); ?></button>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col text-center">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-0.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
			</div>
		</div>
	</div>
</div>
