<div class="card">
	<div class="card-header h5">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php esc_html_e( 'Stream Video Settings', 'infinite-uploads' ); ?></h5>
		</div>
	</div>
	<div class="card-body p-md-5">
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
						<p class="h5 ml-2 mb-0"><input type="text" value="#6fa8dc"/></p></div>
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
							<select>
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
		<div class="row justify-content-center mb-5">
			<div class="col-md-6 col-sm-12">
				<h5><?php esc_html_e( 'Font Family', 'infinite-uploads' ); ?></h5>
				<p class="lead"><?php esc_html_e( 'The font family that will be used within the player.', 'infinite-uploads' ); ?></p>
			</div>
			<div class="col-md-6 col-sm-12">
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><select>
								<option value="arial" label="Arial"></option>
								<option value="inter" label="Inter"></option>
								<option value="lato" label="Lato"></option>
								<option value="oswald" label="Oswald"></option>
								<option value="raleway" label="Raleway"></option>
								<option value="roboto" label="Roboto"></option>
								<option selected="" value="rubik" label="Rubik"></option>
								<option value="ubuntu" label="Ubuntu"></option>
							</select></p>
					</div>
				</div>
			</div>
		</div>

		<div class="row justify-content-center mb-5">
			<div class="col-md-6 col-sm-12">
				<h5><?php esc_html_e( 'Player Controls', 'infinite-uploads' ); ?></h5>
				<p class="lead"><?php esc_html_e( 'Select the UI controls that will be displayed on the player.', 'infinite-uploads' ); ?></p>
			</div>
			<div class="col-md-6 col-sm-12">
				<div class="row">
					<div class="twelve columns">
						<div class="six columns">
							<div class="controls-box" data-control="play">
								<div data-control="play" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Play / Pause <small><b class="fa fa-play"></b></small></div>
							</div>
							<div class="controls-box" data-control="play-large">
								<div data-control="play-large" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Big Play Button <small><b class="fa fa-play-circle"></b></small></div>
							</div>
							<div class="controls-box" data-control="volume">
								<div data-control="volume" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Volume <small><b class="fa fa-volume-up"></b></small></div>
							</div>
							<div class="controls-box" data-control="mute">
								<div data-control="mute" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Mute <small><b class="fa fa-volume-mute"></b></small></div>
							</div>
							<div class="controls-box" data-control="pip">
								<div data-control="pip" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Picture-in-Picture <small><b class="fa fa-external-link"></b></small></div>
							</div>
							<div class="controls-box" data-control="settings">
								<div data-control="settings" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Settings <small><b class="fa fa-cog"></b></small></div>
							</div>
							<div class="controls-box" data-control="captions">
								<div data-control="captions" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Captions <small><b class="fa fa-closed-captioning"></b></small></div>
							</div>

						</div>
						<div class="six columns">
							<div class="controls-box" data-control="current-time">
								<div data-control="current-time" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Current Time <small><b class="fa fa-tilde"></b></small></div>
							</div>
							<div class="controls-box" data-control="duration">
								<div data-control="duration" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Duration <small><b class="fa fa-tilde"></b></small></div>
							</div>
							<div class="controls-box" data-control="rewind">
								<div data-control="rewind" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> 10s Backward <small><b class="fa fa-backward"></b></small></div>
							</div>
							<div class="controls-box" data-control="fast-forward">
								<div data-control="fast-forward" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> 10s Forward <small><b class="fa fa-forward"></b></small></div>
							</div>
							<div class="controls-box" data-control="progress">
								<div data-control="progress" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Progress <small><b class="fa fa-rectangle-wide"></b></small></div>
							</div>
							<div class="controls-box" data-control="fullscreen">
								<div data-control="fullscreen" class="button button-dark-empty noselect button-pricing-zone-select button-video-control toggle toggled"><i class="fa fa-check-circle"></i> Full Screen <small><b class="fa fa-expand-alt"></b></small></div>
							</div>

						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
