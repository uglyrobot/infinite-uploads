import {__, _x, _n, _nx} from '@wordpress/i18n';

export function VideoSize({video}) {
	const sizeOf = function (bytes) {
		if (bytes === 0) {
			return "0 B";
		}
		var e = Math.floor(Math.log(bytes) / Math.log(1024));
		return (bytes / Math.pow(1024, e)).toFixed(1) + ' ' + ' KMGTP'.charAt(e) + 'B';
	}

	return (
		<span className="d-inline-flex text-nowrap" title={__('Storage Size', 'infinite-uploads')}><span className="dashicons dashicons-media-video me-1"></span>{sizeOf(video.storageSize)}</span>
	)
}

export function VideoLength({video}) {
	//function to turn seconds into a human readable time
	const secondsToTime = function (seconds) {
		var h = Math.floor(seconds / 3600);
		var m = Math.floor(seconds % 3600 / 60);
		var s = Math.floor(seconds % 3600 % 60);
		return (h > 0 ? h + ":" : "") + (m > 0 ? (h > 0 && m < 10 ? "0" : "") + m + ":" : "0:") + (s < 10 ? "0" : "") + s;
	}

	return (
		<span className="d-inline-flex text-nowrap" title={__('Video Length', 'infinite-uploads')}><span className="dashicons dashicons-clock me-1"></span>{secondsToTime(video.length)}</span>
	)
}


export function VideoViews({video}) {
	return (
		<span className="d-inline-flex text-nowrap" title={__('View Count', 'infinite-uploads')}><span className="dashicons dashicons-welcome-view-site me-1"></span>{video.views}</span>
	)
}


export function VideoDate({video}) {
	//change datetime to human-readable in localstring
	const dateTime = function (date) {
		return new Date(date).toLocaleString();
	}

	return (
		<small className="d-inline-flex text-nowrap" title={__('Upload Date', 'infinite-uploads')}><span className="dashicons dashicons-calendar me-1"></span>{dateTime(video.dateUploaded)}</small>
	)
}
