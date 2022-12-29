import {__, _x, _n, _nx} from '@wordpress/i18n';
import {useEffect, useState} from '@wordpress/element';
import Button from "react-bootstrap/Button";
import Row from "react-bootstrap/Row";
import Col from "react-bootstrap/Col";
import {ChromePicker} from "react-color";

export default function ColorPick({settings, setSettings}) {
	const [showPicker, setShowPicker] = useState(false);

	return (
		<Row className="justify-content-start">
			<Col xs={2}>
				<Button style={{backgroundColor: settings.PlayerKeyColor}} variant="secondary" className="rounded-pill px-4" onClick={() => {
					setShowPicker(!showPicker);
				}}>{settings.PlayerKeyColor}</Button>
			</Col>
			{showPicker && (
				<Col>
					<ChromePicker
						color={settings.PlayerKeyColor}
						onChangeComplete={(color) => {
							setSettings({...settings, PlayerKeyColor: color.hex});
						}}
						disableAlpha
					/>
				</Col>
			)}
		</Row>
	);
}
