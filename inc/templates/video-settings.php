<div class="card">
	<div class="card-header">
		<div class="d-flex align-items-center">
			<h5 class="h5 m-0 mr-auto p-0"><span class="dashicons dashicons-format-video"></span> <?php esc_html_e( 'Stream Video', 'infinite-uploads' ); ?></h5>
			<nav id="stream-nav-tab">
				<div class="nav nav-tabs" role="tablist">
					<button class="nav-link" id="nav-stats-tab" data-toggle="tab" data-target="#nav-stats" type="button" role="tab" aria-controls="nav-stats" aria-selected="true">Stats</button>
					<button class="nav-link active" id="nav-player-tab" data-toggle="tab" data-target="#nav-player" type="button" role="tab" aria-controls="nav-player" aria-selected="false">Player</button>
					<button class="nav-link" id="nav-encoding-tab" data-toggle="tab" data-target="#nav-encoding" type="button" role="tab" aria-controls="nav-encoding" aria-selected="false">Encoding</button>
					<button class="nav-link" id="nav-security-tab" data-toggle="tab" data-target="#nav-security" type="button" role="tab" aria-controls="nav-security" aria-selected="false">Security</button>
				</div>
			</nav>
		</div>
	</div>
	<div class="card-body p-md-5 tab-content">

		<div class="tab-pane fade show" id="nav-stats" role="tabpanel" aria-labelledby="nav-stats-tab">
			<div class="row justify-content-center mb-5">
				<div class="col-md-6 col-sm-12">
					<h5><?php esc_html_e( 'Some stats here', 'infinite-uploads' ); ?></h5>
					<p class="lead"><?php esc_html_e( 'stats charts go here.', 'infinite-uploads' ); ?></p>
				</div>
				<div class="col-md-6 col-sm-12">
					<div class="row">
						<div class="col"><?php esc_html_e( 'Color', 'infinite-uploads' ); ?></div>
					</div>
					<div class="row">
						<div class="col badge badge-pill badge-light text-left p-3">
							<p class="h5 ml-2 mb-0">
							</p></div>
					</div>
				</div>
			</div>
		</div>

		<div class="tab-pane fade show active" id="nav-player" role="tabpanel" aria-labelledby="nav-player-tab">
			<div class="row justify-content-center mb-5">
				<div class="col-md-6 col-sm-12">
					<h5><?php esc_html_e( 'Main Player Color', 'infinite-uploads' ); ?></h5>
					<p class="lead"><?php esc_html_e( 'Select the primary color that will be displayed for the controls in the video player.', 'infinite-uploads' ); ?></p>
				</div>
				<div class="col-md-6 col-sm-12">
					<div class="row">
						<div class="col"><?php esc_html_e( 'Color', 'infinite-uploads' ); ?></div>
					</div>
					<div class="row">
						<div class="col badge badge-pill badge-light text-left p-3">
							<p class="h5 ml-2 mb-0">
								<input class="form-control form-control-lg color-field" type="text" value="#6fa8dc" aria-label="<?php esc_attr_e( 'Color', 'infinite-uploads' ); ?>">
							</p></div>
					</div>
				</div>
			</div>
			<div class="row justify-content-center mb-5">
				<div class="col-md-6 col-sm-12">
					<h5><?php esc_html_e( 'Player Language', 'infinite-uploads' ); ?></h5>
					<p class="lead"><?php esc_html_e( 'Select the default language that will be displayed in the video player.', 'infinite-uploads' ); ?></p>
				</div>
				<div class="col-md-6 col-sm-12">
					<div class="row">
						<div class="col badge badge-pill badge-light text-left p-3">
							<p class="h5 ml-2 mb-0">
								<select class="custom-select custom-select-lg">
									<option value="en" label="English"></option>
									<option value="ar" label="Arabic"></option>
									<option value="bu" label="Bulgarian"></option>
									<option value="cn" label="Chinese"></option>
									<option value="cz" label="Czech"></option>
									<option value="dk" label="Danish"></option>
									<option value="nl" label="Dutch"></option>
									<option value="fi" label="Finnish"></option>
									<option value="fr" label="French"></option>
									<option value="de" label="German"></option>
									<option value="gr" label="Greek"></option>
									<option value="hu" label="Hungarian"></option>
									<option value="id" label="Indonesian"></option>
									<option value="it" label="Italian"></option>
									<option value="jp" label="Japanese"></option>
									<option value="kr" label="Korean"></option>
									<option value="no" label="Norwegian"></option>
									<option value="pl" label="Polish"></option>
									<option value="pt" label="Portuguese"></option>
									<option value="ro" label="Romanian"></option>
									<option value="rs" label="Serbian"></option>
									<option value="sk" label="Slovakian"></option>
									<option value="si" label="Slovenian"></option>
									<option value="es" label="Spanish"></option>
									<option value="se" label="Swedish"></option>
									<option value="ru" label="Russian"></option>
									<option value="th" label="Thai"></option>
									<option value="tr" label="Turkish"></option>
									<option value="ua" label="Ukranian"></option>
									<option value="vn" label="Vietnamese"></option>
								</select>
							</p>
						</div>
					</div>
				</div>
			</div>
			<!--<div class="row justify-content-center mb-5">
					<div class="col-md-6 col-sm-12">
						<h5><?php /*esc_html_e( 'Font Family', 'infinite-uploads' ); */ ?></h5>
						<p class="lead"><?php /*esc_html_e( 'The font family that will be used within the player.', 'infinite-uploads' ); */ ?></p>
					</div>
					<div class="col-md-6 col-sm-12">
						<div class="row">
							<div class="col badge badge-pill badge-light text-left p-3">
								<p class="h5 ml-2 mb-0">
									<select class="custom-select custom-select-lg">
										<option value="arial" label="Arial"></option>
										<option value="inter" label="Inter"></option>
										<option value="lato" label="Lato"></option>
										<option value="oswald" label="Oswald"></option>
										<option value="raleway" label="Raleway"></option>
										<option value="roboto" label="Roboto"></option>
										<option selected="" value="rubik" label="Rubik"></option>
										<option value="ubuntu" label="Ubuntu"></option>
									</select>
								</p>
							</div>
						</div>
					</div>
				</div>-->

			<div class="row justify-content-center mb-5">
				<div class="col-md-6 col-sm-12">
					<h5><?php esc_html_e( 'Player Controls', 'infinite-uploads' ); ?></h5>
					<p class="lead"><?php esc_html_e( 'Select the UI controls that will be displayed on the player.', 'infinite-uploads' ); ?></p>
				</div>
				<div class="col-md-6 col-sm-12 d-flex flex-wrap justify-content-between">
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-play" checked>
						<label class="custom-control-label" for="player-controls-play"><span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Play / Pause', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-play-large" checked>
						<label class="custom-control-label" for="player-controls-play-large"><span class="dashicons dashicons-video-alt3"></span> <?php esc_html_e( 'Center Play Button', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-volume" checked>
						<label class="custom-control-label" for="player-controls-volume"><span class="dashicons dashicons-controls-volumeon"></span> <?php esc_html_e( 'Volume', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-mute" checked>
						<label class="custom-control-label" for="player-controls-mute"><span class="dashicons dashicons-controls-volumeoff"></span> <?php esc_html_e( 'Mute', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-pip" checked>
						<label class="custom-control-label" for="player-controls-pip"><span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Picture-in-Picture', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-settings" checked>
						<label class="custom-control-label" for="player-controls-settings"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-captions" checked>
						<label class="custom-control-label" for="player-controls-captions"><span class="dashicons dashicons-format-status"></span> <?php esc_html_e( 'Captions', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-current-time" checked>
						<label class="custom-control-label" for="player-controls-current-time"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Current Time', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-duration" checked>
						<label class="custom-control-label" for="player-controls-duration"><span class="dashicons dashicons-editor-video"></span> <?php esc_html_e( 'Duration', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-rewind" checked>
						<label class="custom-control-label" for="player-controls-rewind"><span class="dashicons dashicons-controls-skipback"></span> <?php esc_html_e( '10s Backward', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-fast-forward" checked>
						<label class="custom-control-label" for="player-controls-fast-forward"><span class="dashicons dashicons-controls-skipforward"></span> <?php esc_html_e( '10s Forward', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-progress" checked>
						<label class="custom-control-label" for="player-controls-progress"><span class="dashicons dashicons-leftright"></span> <?php esc_html_e( 'Progress Bar', 'infinite-uploads' ); ?></label>
					</div>
					<div class="custom-control custom-switch m-2">
						<input class="custom-control-input" type="checkbox" role="switch" id="player-controls-fullscreen" checked>
						<label class="custom-control-label" for="player-controls-fullscreen"><span class="dashicons dashicons-fullscreen-alt"></span> <?php esc_html_e( 'Full Screen', 'infinite-uploads' ); ?></label>
					</div>

				</div>
			</div>

			<div class="row justify-content-center mb-3">
				<div class="col-xl-2 col-lg-3 col-md-4 text-center">
					<button class="btn text-nowrap btn-info btn-lg btn-block" name="iup_settings_submit" value="1" type="submit">Save</button>
				</div>
			</div>
		</div>

		<div class="tab-pane fade show" id="nav-encoding" role="tabpanel" aria-labelledby="nav-encoding-tab">
			<div class="row justify-content-center mb-5">
				<div class="col-md-6 col-sm-12">
					<h5><?php esc_html_e( 'Encoding stuff here', 'infinite-uploads' ); ?></h5>
					<p class="lead"><?php esc_html_e( 'Resolutions, Watermark', 'infinite-uploads' ); ?></p>
				</div>
				<div class="col-md-6 col-sm-12">
					<div class="row">
						<div class="col badge badge-pill badge-light text-left p-3">
							<p class="h5 ml-2 mb-0">
							</p></div>
					</div>
				</div>
			</div>
		</div>

		<div class="tab-pane fade show" id="nav-security" role="tabpanel" aria-labelledby="nav-security-tab">
			<div class="row justify-content-center mb-5">
				<div class="col-md-6 col-sm-12">
					<h5><?php esc_html_e( 'Security settings', 'infinite-uploads' ); ?></h5>
					<p class="lead"><?php esc_html_e( 'stats charts go here.', 'infinite-uploads' ); ?></p>
				</div>
				<div class="col-md-6 col-sm-12">
					<div class="row">
						<div class="col"><?php esc_html_e( 'Color', 'infinite-uploads' ); ?></div>
					</div>
					<div class="row">
						<div class="col badge badge-pill badge-light text-left p-3">
							<p class="h5 ml-2 mb-0">
							</p></div>
					</div>
				</div>
			</div>
		</div>

	</div>
</div>
