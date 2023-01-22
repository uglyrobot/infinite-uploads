import {__, _x, _n, _nx} from '@wordpress/i18n';
import Form from 'react-bootstrap/Form';
import Col from 'react-bootstrap/Col';

export default function PlayerCheckbox({control, icon, label, settings, setSettings}) {

	return (
		<Col>
			<Form.Check
				type="checkbox"
				id={control}
				inline
				label={
					<span className="text-nowrap"><span className={"dashicons dashicons-" + icon}></span> {label}</span>
				}
				checked={settings.Controls.includes(control)}
				onChange={e => setSettings(settings => {
					if (e.target.checked) {
						return {...settings, Controls: [...settings.Controls, control]};
					} else {
						return {...settings, Controls: settings.Controls.filter(ctrl => ctrl !== control)};
					}
				})}
			/>
		</Col>
	);
}
