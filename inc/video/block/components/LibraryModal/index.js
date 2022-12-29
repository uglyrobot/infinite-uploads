import {__, _x} from '@wordpress/i18n';
import {Button, Modal} from '@wordpress/components';
import {useState, useEffect} from '@wordpress/element';
import Library from '../../../admin/components/Library';
import {InfiniteUploadsIcon} from '../Images';
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
					icon={InfiniteUploadsIcon(false)}
					style={{width: '98%'}}
					title={__('Cloud Video Library', 'infinite-uploads')}
					className="iup-block-library-model"
				>
					<p>
						{__(
							'Select a video from your library to insert into the editor.',
							'infinite-uploads'
						)}
					</p>

					<Library selectVideo={selectVideo}/>
				</Modal>
			)}
		</>
	);
}
