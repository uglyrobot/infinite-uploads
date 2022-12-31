import {useEffect, useState} from '@wordpress/element';
import {__, _x, _n, _nx} from '@wordpress/i18n';
import Container from 'react-bootstrap/Container';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import Card from "react-bootstrap/Card";
import Spinner from 'react-bootstrap/Spinner';
import Form from 'react-bootstrap/Form';
import PlayerCheckbox from "./PlayerCheckbox";
import ResCheckbox from "./ResCheckbox";
import ColorPick from "./ColorPick";
import Tabs from "react-bootstrap/Tabs";
import Tab from "react-bootstrap/Tab";
import Button from "react-bootstrap/Button";

export default function Settings() {
	const [loading, setLoading] = useState(false);
	const [settings, setSettings] = useState(IUP_VIDEO.settings);

	function updateSettings() {
		setLoading(true);
		const formData = new FormData();
		formData.append('settings', JSON.stringify(settings));
		formData.append('nonce', IUP_VIDEO.nonce);

		const options = {
			method: 'POST',
			headers: {
				Accept: 'application/json',
			},
			body: formData,
		};

		fetch(`${ajaxurl}?action=infinite-uploads-video-settings`, options)
			.then((response) => response.json())
			.then((data) => {
				if (data.success) {
					setSettings(data);
				} else {
					console.error(data.data);
				}
				setLoading(false);
			})
			.catch((error) => {
				console.log('Error:', error);
				setLoading(false);
			});
	}

	if (!settings) {
		return (
			<h2>{__('Video library not yet connected.', 'infinite-uploads')}</h2>
		)
	}

	return (
		<Container fluid>
			<Row className="justify-content-between align-items-center">
				<Col>
					<h1 className="text-muted mb-3">
						<img
							src={IUP_VIDEO.assetBase + '/img/iu-logo-gray.svg'}
							alt="Infinite Uploads Logo"
							height="32"
							width="32"
							className="me-2"
						/>
						{__('Infinite Uploads Video Settings', 'infinite-uploads')}
					</h1>
				</Col>
				<Col>
					<Button variant="primary" className="float-end" href={IUP_VIDEO.libraryUrl}>
						{__('Video Library', 'infinite-uploads')}
					</Button>
				</Col>
			</Row>

			<Card>
				<Card.Body>
					<Tabs
						defaultActiveKey="player"
						id="video-settings-tabs"
						className="mb-3"
					>
						<Tab eventKey="player" title={__('Player', 'infinite-uploads')} className="mt-4">
							<Row className="justify-content-center mb-5" xs={1} md={2}>
								<Col>
									<h5>{__('Main Player Color', 'infinite-uploads')}</h5>
									<p className="lead">{__('Select the primary color that will be displayed for the controls in the video player.', 'infinite-uploads')}</p>
								</Col>
								<Col>
									<ColorPick {...{settings, setSettings}} />
								</Col>
							</Row>
							<Row className="justify-content-center mb-5" xs={1} md={2}>
								<Col>
									<h5>{__('Player Language', 'infinite-uploads')}</h5>
									<p className="lead">{__('Select the default language that will be displayed in the video player.', 'infinite-uploads')}</p>
								</Col>
								<Col>
									<Form.Select size="lg" value={settings.UILanguage} onChange={(e) => setSettings({...settings, UILanguage: e.target.value})}>
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
										<option value="ua" label="Ukrainian"></option>
										<option value="vn" label="Vietnamese"></option>
									</Form.Select>
								</Col>
							</Row>
							<Row className="justify-content-center mb-5" xs={1} md={2}>
								<Col>
									<h5>{__('Player Controls', 'infinite-uploads')}</h5>
									<p className="lead">{__('Select the UI controls that will be displayed on the player.', 'infinite-uploads')}</p>
								</Col>
								<Col className="d-flex flex-wrap justify-content-between">
									<Row>
										<PlayerCheckbox control="play" icon="controls-play" label={__('Play / Pause', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="play-large" icon="video-alt3" label={__('Center Play Button', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="volume" icon="controls-volumeon" label={__('Volume', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="mute" icon="controls-volumeoff" label={__('Mute', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="pip" icon="external" label={__('Picture-in-Picture', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="settings" icon="admin-generic" label={__('Settings', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="captions" icon="format-status" label={__('Captions', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="current-time" icon="clock" label={__('Current Time', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="duration" icon="editor-video" label={__('Duration', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="rewind" icon="controls-skipback" label={__('10s Backward', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="fast-forward" icon="controls-skipforward" label={__('10s Forward', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="progress" icon="leftright" label={__('Progress Bar', 'infinite-uploads')} {...{settings, setSettings}} />
										<PlayerCheckbox control="fullscreen" icon="fullscreen-alt" label={__('Full Screen', 'infinite-uploads')} {...{settings, setSettings}} />
									</Row>
								</Col>
							</Row>
						</Tab>
						<Tab eventKey="encoding" title={__('Encoding', 'infinite-uploads')} className="mt-4">
							<Row className="justify-content-center mb-5" xs={1} md={2}>
								<Col>
									<h5>{__('Enabled Resolutions', 'infinite-uploads')}</h5>
									<p className="lead">{__('Select the enabled resolutions that will be encoded on upload. More resolutions provide a more efficient streaming service to users, but require more storage space. Resolutions larger than the original video will be skipped.', 'infinite-uploads')}</p>
								</Col>
								<Col>
									<ResCheckbox {...{settings, setSettings}} px={240} bitrate={600}/>
									<ResCheckbox {...{settings, setSettings}} px={360} bitrate={800}/>
									<ResCheckbox {...{settings, setSettings}} px={480} bitrate={1400}/>
									<ResCheckbox {...{settings, setSettings}} px={720} bitrate={2800}/>
									<ResCheckbox {...{settings, setSettings}} px={1080} bitrate={5000}/>
									<ResCheckbox {...{settings, setSettings}} px={1440} bitrate={8000}/>
									<ResCheckbox {...{settings, setSettings}} px={2160} bitrate={25000}/>
								</Col>
							</Row>
						</Tab>
					</Tabs>

					<Row className="justify-content-center mb-3">
						<Col className="text-center">
							<Button variant="info" className="text-nowrap text-white px-4" onClick={updateSettings} disabled={loading}>{__('Save Settings', 'infinite-uploads')}</Button>
						</Col>
					</Row>
				</Card.Body>
			</Card>

		</Container>
	);
}
