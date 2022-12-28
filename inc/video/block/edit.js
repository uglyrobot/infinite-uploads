/**
 * WordPress components that create the necessary UI elements for the block
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-components/
 *
 * See https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/video/
 */
/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import {useSelect} from '@wordpress/data';
import {PanelBody, Placeholder, Spinner, Button} from '@wordpress/components';
import {useBlockProps, InspectorControls} from '@wordpress/block-editor';
import {media} from '@wordpress/icons';
import {__, sprintf} from '@wordpress/i18n';
import {useRef, useEffect, useState} from '@wordpress/element';
import Uppy from '@uppy/core';
import Tus from '@uppy/tus';
import {DragDrop, StatusBar, useUppy} from '@uppy/react';
import UppyCreateVid from './edit-uppy-plugin';
import VideoCommonSettings from './edit-common-settings';
import LibraryModal from './components/LibraryModal';
import '../../assets/css/admin.css';

//pulled from wp_localize_script later

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @param {Object}   props               Properties passed to the function.
 * @param {Object}   props.attributes    Available block attributes.
 * @param {Function} props.setAttributes Function that updates individual attributes.
 *
 * @return {WPElement} Element to render.
 */
export default function Edit({clientId, attributes, setAttributes}) {
	const blockProps = useBlockProps();
	const isSelected = useSelect((select) =>
		select('core/block-editor').isBlockSelected(clientId, true)
	);

	/* Possible video statuses
	Created = 0
	Uploaded = 1
	Processing = 2
	Transcoding = 3
	Finished = 4
	Error = 5
	UploadFailed = 6
	*/
	const [video, setVideo] = useState(null);
	const [isUploading, setUploading] = useState(false);
	const [showOverlay, setShowOverlay] = useState(true);

	//reenable the click overlay whenever the block is unselected so we can click back on it
	useEffect(() => {
		if (!isSelected) {
			setShowOverlay(true);
		}
	}, [isSelected]);

	useEffect(() => {
		if (attributes.video_id) {
			getVideo();
		}
	}, []);

	//poll the video status every 5 seconds while status is 2-3
	useEffect(() => {
		if (video && (video.status === 2 || video.status === 3)) {
			const interval = setInterval(() => {
				getVideo();
			}, 5000);
			return () => clearInterval(interval);
		}
	}, [video]);

	const uppy = useUppy(() => {
		return new Uppy({
			debug: true,
			restrictions: {
				maxNumberOfFiles: 1,
				allowedFileTypes: ['video/*'],
			},
			autoProceed: true,
			allowMultipleUploadBatches: false,
			onBeforeUpload: (files) => {
				//TODO trigger error if video_id is null
			},
		})
			.use(Tus, {
				endpoint: 'https://video.bunnycdn.com/tusupload',
				retryDelays: [0, 1000, 3000, 5000, 10000],
				onBeforeRequest: (req) => {
					console.log('Video Auth:', blockProps.videoAuth);
					if (blockProps.videoAuth) {
						setAttributes({
							video_id: blockProps.videoAuth.VideoId,
						});
						attributes.video_id = blockProps.videoAuth.VideoId; //I don't know why this is needed
					} else {
						throw new Error('Error fetching auth.');
						return false;
					}
					console.log('VideoId attr:', attributes.video_id);

					req.setHeader(
						'AuthorizationSignature',
						blockProps.videoAuth.AuthorizationSignature
					);
					req.setHeader(
						'AuthorizationExpire',
						blockProps.videoAuth.AuthorizationExpire
					);
					req.setHeader('VideoId', blockProps.videoAuth.VideoId);
					req.setHeader('LibraryId', IUP_VIDEO.libraryId);
				},
			})
			.use(UppyCreateVid, {blockProps}); //our custom plugin
	});

	let uploadSuccess = useRef(false);
	uppy.on('upload', (data) => {
		// data object consists of `id` with upload ID and `fileIDs` array
		// with file IDs in current upload
		// data: { id, fileIDs }
		setUploading(true);
		uploadSuccess.current = false;
	});

	uppy.on('cancel-all', () => {
		setUploading(false);
	});
	uppy.on('error', (error) => {
		console.error(error.stack);
		setUploading(false);
	});
	uppy.on('upload-error', (file, error, response) => {
		console.log('error with file:', file.id);
		console.log('error message:', error);
		setUploading(false);
	});
	uppy.on('upload-success', (file, response) => {
		if (!uploadSuccess.current) {
			uploadSuccess.current = true;
			getVideo();
		}
		setUploading(false);
	});

	function getVideo() {
		if (!attributes.video_id) {
			return false;
		}
		const options = {
			method: 'GET',
			headers: {
				Accept: 'application/json',
				AccessKey: IUP_VIDEO.apiKey,
			},
		};

		fetch(
			`https://video.bunnycdn.com/library/${IUP_VIDEO.libraryId}/videos/${attributes.video_id}`,
			options
		)
			.then((response) => response.json())
			.then((data) => {
				console.log('Video:', data);
				setVideo(data);
			})
			.catch((error) => {
				console.error(error);
			});
	}

	const selectVideo = (video) => {
		setAttributes({video_id: video.guid});
		setVideo(video);
		setUploading(false);
	};

	if (
		!isUploading &&
		attributes.video_id &&
		video &&
		[1, 2, 3, 4].includes(video.status)
	) {
		if (video.status === 4) {
			return (
				<>
					<div {...blockProps}>
						<figure className="iup-video-embed-wrapper">
							<iframe
								src={`https://iframe.mediadelivery.net/embed/${IUP_VIDEO.libraryId}/${attributes.video_id}?autoplay=${attributes.autoplay}&preload=${attributes.preload}&loop=${attributes.loop}&muted=${attributes.muted}`}
								loading="lazy"
								className="iup-video-embed"
								sandbox="allow-scripts allow-same-origin allow-presentation"
								allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
								allowFullScreen={true}
							></iframe>
						</figure>
						{showOverlay && (
							<button
								className="iup-video-overlay"
								onClick={() => setShowOverlay(false)}
							/>
						)}
					</div>
					<InspectorControls>
						<PanelBody title={__('Settings')}>
							<VideoCommonSettings
								setAttributes={setAttributes}
								attributes={attributes}
							/>
						</PanelBody>
					</InspectorControls>
				</>
			);
		} else {
			let label = '';
			let style = {};
			if (video.status === 3) {
				label = sprintf(
					__('Video %d%% encoded...', 'infinite-uploads'),
					video.encodeProgress
				);
				style = {
					backgroundImage: `url("${IUP_VIDEO.cdnUrl}/${attributes.video_id}/${video.thumbnailFileName}")`,
				};
			} else if (video.status <= 1) {
				label = __('Awaiting Upload...', 'infinite-uploads');
			} else if (video.status > 4) {
				label = __('Video Error. Upload again.', 'infinite-uploads');
			} else {
				label = sprintf(
					__('Video %d%% processed...', 'infinite-uploads'),
					video.encodeProgress
				);
			}
			return (
				<>
					<div {...blockProps}>
						<div className="ratio-16-9-outer">
							<div className="ratio-16-9-inner" style={style}>
								<div className="ratio-16-9-content">
									<Spinner
										style={{
											height: '0.9em',
											width: '0.9em',
										}}
									/>{' '}
									{label}
								</div>
							</div>
						</div>
					</div>
					<InspectorControls>
						<PanelBody title={__('Settings')}>
							<VideoCommonSettings
								setAttributes={setAttributes}
								attributes={attributes}
							/>
						</PanelBody>
					</InspectorControls>
				</>
			);
		}
	} else {
		return (
			<div {...blockProps}>
				<Placeholder
					icon={media}
					instructions={__(
						'Upload a new video direct to the cloud or select a video from your cloud library.',
						'infinite-uploads'
					)}
					label={__('Infinite Uploads Video', 'infinite-uploads')}
				>
					<div className="placeholder-wrapper">
						<div className="uppy-wrapper">
							{!isUploading ? (
								<DragDrop
									width="100%"
									height="100%"
									// assuming `props.uppy` contains an Uppy instance:
									uppy={uppy}
									locale={{
										strings: {
											// Text to show on the droppable area.
											// `%{browse}` is replaced with a link that opens the system file selection dialog.
											dropHereOr: __(
												'Drop video file here or %{browse}.',
												'infinite-uploads'
											),
											// Used as the label for the link that opens the system file selection dialog.
											browse: __(
												'browse files',
												'infinite-uploads'
											),
										},
									}}
								/>
							) : (
								''
							)}
							<StatusBar
								// assuming `props.uppy` contains an Uppy instance:
								uppy={uppy}
								hideUploadButton={false}
								hideAfterFinish={true}
								showProgressDetails
							/>
						</div>
						{!isUploading && (
							<LibraryModal selectVideo={selectVideo}/>
						)}
					</div>
				</Placeholder>
			</div>
		);
	}
}
