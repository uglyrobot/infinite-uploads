import {__, _x, _n, _nx} from '@wordpress/i18n';
import {useState, useEffect} from '@wordpress/element';
import Modal from 'react-bootstrap/Modal';
import Button from 'react-bootstrap/Button';

export default function DeleteModal({video, setVideos}) {
	const [show, setShow] = useState(false);

	const handleClose = () => setShow(false);
	const handleShow = () => setShow(true);

	function deleteVideo() {
		const formData = new FormData();
		formData.append('video_id', video.guid);
		formData.append('nonce', IUP_VIDEO.nonce);

		const options = {
			method: 'POST',
			headers: {
				Accept: 'application/json',
			},
			body: formData,
		};

		fetch(`${ajaxurl}?action=infinite-uploads-video-delete`, options)
			.then((response) => response.json())
			.then((data) => {
				console.log(data);
				if (data.success) {
					setVideos((videos) =>
						videos.filter((v) => v.guid !== video.guid)
					);
					handleClose();
				} else {
					console.error(data.data);
				}
			})
			.catch((error) => {
				console.log('Error:', error);
			});
	}

	return (
		<>
			<Button
				variant="outline-danger"
				size="sm"
				onClick={handleShow}
				className="rounded-4"
			>
				{__('Delete Video', 'infinite-uploads')}
			</Button>

			<Modal show={show} onHide={handleClose}>
				<Modal.Header closeButton>
					<Modal.Title>
						{__('Delete Video:', 'infinite-uploads')}{' '}
						{video.title}
					</Modal.Title>
				</Modal.Header>
				<Modal.Body>
					{__(
						'Are you sure you would like to delete this video?',
						'infinite-uploads'
					)}
				</Modal.Body>
				<Modal.Footer>
					<Button variant="secondary" onClick={handleClose}>
						{__('Cancel', 'infinite-uploads')}
					</Button>
					<Button variant="danger" onClick={deleteVideo}>
						{__('Delete', 'infinite-uploads')}
					</Button>
				</Modal.Footer>
			</Modal>
		</>
	);
}
