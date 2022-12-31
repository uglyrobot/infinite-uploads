import {__, _x, _n, _nx} from '@wordpress/i18n';
import {useState, useEffect, useRef} from '@wordpress/element';
import Container from 'react-bootstrap/Container';
import Modal from 'react-bootstrap/Modal';
import Button from 'react-bootstrap/Button';
import Uppy from '@uppy/core';
import Tus from '@uppy/tus';
import {DragDrop, StatusBar, useUppy} from '@uppy/react';
import UppyCreateVid from '../../block/edit-uppy-plugin';
import '@uppy/core/dist/style.css';
import '@uppy/drag-drop/dist/style.css';
import '@uppy/status-bar/dist/style.css';

export default function UploadModal({getVideos}) {
	const [show, setShow] = useState(false);
	const uploadAuth = useRef(null);
	const uploaded = useRef({});

	const handleShow = () => {
		setShow(true);
	};
	const handleClose = () => {
		setShow(false);
	};

	const uppy = useUppy(() => {
		return new Uppy({
			debug: true,
			restrictions: {
				maxNumberOfFiles: null,
				allowedFileTypes: ['video/*'],
			},
			autoProceed: true,
			allowMultipleUploadBatches: true,
			onBeforeUpload: (files) => {
				//TODO trigger error if video_id is null
			},
		})
			.use(Tus, {
				endpoint: 'https://video.bunnycdn.com/tusupload',
				retryDelays: [0, 1000, 3000, 5000, 10000],
				onBeforeRequest: (req, file) => {
					//console.log('Video Auth:', uploadAuth.current[file.id]);
					if (!uploadAuth.current[file.id]) {
						throw new Error('Error fetching auth.');
						return false;
					}

					req.setHeader(
						'AuthorizationSignature',
						uploadAuth.current[file.id].AuthorizationSignature
					);
					req.setHeader(
						'AuthorizationExpire',
						uploadAuth.current[file.id].AuthorizationExpire
					);
					req.setHeader('VideoId', uploadAuth.current[file.id].VideoId);
					req.setHeader('LibraryId', IUP_VIDEO.libraryId);
				},
			})
			.use(UppyCreateVid, {uploadAuth}); //our custom plugin
	});

	uppy.on('error', (error) => {
		console.error(error.stack);
	});
	uppy.on('upload-error', (file, error, response) => {
		console.log('error with file:', file.id);
		console.log('error message:', error);
	});
	uppy.on('upload-success', (file, response) => {
		if (!uploaded.current[file.id]) { //make sure it only triggers once
			getVideos();
			uploaded.current = {...uploaded.current, [file.id]: true};
		}
	});

	return (
		<>
			<Button
				variant="primary"
				className="text-nowrap text-white ms-4"
				onClick={handleShow}
			>
				<span className="dashicons dashicons-video-alt3"></span>
				{__('Upload Videos', 'infinite-uploads')}
			</Button>

			<Modal
				show={show}
				onHide={handleClose}
				size="lg"
				aria-labelledby="contained-modal-title-vcenter"
				centered
			>
				<Modal.Header closeButton>
					<Modal.Title id="contained-modal-title-vcenter">
						{__('Upload Videos', 'infinite-uploads')}
					</Modal.Title>
				</Modal.Header>
				<Modal.Body>
					<Container fluid className="p-3">

						<div className="uppy-wrapper">
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
											'Drop videos here or %{browse}.',
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
							<StatusBar
								// assuming `props.uppy` contains an Uppy instance:
								uppy={uppy}
								hideUploadButton={true}
								hideAfterFinish={true}
								showProgressDetails
							/>
						</div>
					</Container>
				</Modal.Body>
			</Modal>
		</>
	);
}
