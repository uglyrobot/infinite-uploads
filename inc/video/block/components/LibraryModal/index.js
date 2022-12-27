import {__, _x} from '@wordpress/i18n';
import {Button, Modal} from '@wordpress/components';
import {useState, useEffect} from '@wordpress/element';
import Library from '../../../settings/index'
import './styles.scss';

export default function LibraryModal({selectVideo, ...props}) {
	const [isOpen, setOpen] = useState(false);
	const openModal = () => setOpen(true);
	const closeModal = () => setOpen(false);

	return (
		<>
			<Button variant="primary" onClick={openModal}>
				{__('Select from Library', 'infinite-uploads')}
			</Button>
			{isOpen && (
				<Modal
					{...props}
					isDismissible={true}
					onRequestClose={closeModal}
					style={{width: '90%'}}
					title={__('Video Library', 'infinite-uploads')}
				>
					<p>{__('Select a video from your library to insert into the editor.', 'infinite-uploads')}</p>

					<Library selectVideo={selectVideo}/>

				</Modal>
			)}
		</>
	);
}
