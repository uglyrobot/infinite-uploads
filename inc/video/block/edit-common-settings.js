/**
 * WordPress dependencies
 */
import {__, _x} from '@wordpress/i18n';
import {ToggleControl, SelectControl} from '@wordpress/components';
import {useMemo, useCallback, Platform} from '@wordpress/element';

const VideoSettings = ({setAttributes, attributes}) => {
	const {autoplay, loop, muted, preload} = attributes;

	const autoPlayHelpText = __(
		'Autoplay may cause usability issues for some users.'
	);
	const getAutoplayHelp = Platform.select({
		web: useCallback((checked) => {
			return checked ? autoPlayHelpText : null;
		}, []),
		native: autoPlayHelpText,
	});

	const toggleFactory = useMemo(() => {
		const toggleAttribute = (attribute) => {
			return (newValue) => {
				setAttributes({[attribute]: newValue});
			};
		};

		return {
			autoplay: toggleAttribute('autoplay'),
			loop: toggleAttribute('loop'),
			muted: toggleAttribute('muted'),
			preload: toggleAttribute('preload'),
		};
	}, []);

	return (
		<>
			<ToggleControl
				label={__('Autoplay')}
				onChange={toggleFactory.autoplay}
				checked={autoplay}
				help={getAutoplayHelp}
			/>
			<ToggleControl
				label={__('Loop')}
				onChange={toggleFactory.loop}
				checked={loop}
			/>
			<ToggleControl
				label={__('Muted')}
				onChange={toggleFactory.muted}
				checked={muted}
			/>
			<ToggleControl
				label={__('Preload')}
				onChange={toggleFactory.preload}
				checked={preload}
			/>
		</>
	);
};

export default VideoSettings;
