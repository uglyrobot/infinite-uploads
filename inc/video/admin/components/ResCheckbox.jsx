import {__, _x, _n, _nx} from '@wordpress/i18n';
import Form from 'react-bootstrap/Form';
import Row from "react-bootstrap/Row";
import Col from "react-bootstrap/Col";
import {Badge} from "react-bootstrap";

export default function ResCheckbox({px, bitrate, settings, setSettings}) {
	const resolution = px + "p";
	//calculate width if 16:9 based on height
	const width = Math.round(px * 16 / 9);
	const label = "(" + width + "x" + px + ")";
	const bitrateLabel = bitrate ? bitrate + " kbps" : "";
	const hdLabel = px >= 2160 ? "4K" : px >= 1440 ? "2K" : px >= 1080 ? "Full HD" : px >= 720 ? "HD" : "";
	const disabled = px >= 1440;

	return (
		<Badge bg="light" text="dark" className="mb-2 rounded-pill px-3 w-100">
			<Row className="d-flex justify-content-between align-items-center">
				<Col className="">
					<Form.Check
						className="d-flex align-items-center"
						type="checkbox"
						id={resolution}
						label={
							<span className="text-nowrap"><strong>{resolution}</strong> {label} {hdLabel}</span>
						}
						checked={settings.EnabledResolutions.includes(resolution)}
						onChange={e => setSettings(settings => {
							if (e.target.checked) {
								return {...settings, EnabledResolutions: [...settings.EnabledResolutions, resolution]};
							} else {
								return {...settings, EnabledResolutions: settings.EnabledResolutions.filter(res => res !== resolution)};
							}
						})}
						disabled={disabled}
					/>
				</Col>
				<Col className="col-auto">
					{bitrateLabel}
				</Col>
			</Row>
		</Badge>
	);
}
