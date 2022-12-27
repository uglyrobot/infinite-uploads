import {__, _x, _n, _nx} from '@wordpress/i18n';
import Card from 'react-bootstrap/Card';
import {useState} from '@wordpress/element';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import {VideoLength, VideoViews, VideoSize} from './VideoAttributes';
import VideoModal from "./VideoModal";
import ProgressBar from 'react-bootstrap/ProgressBar';
import DeleteModal from "./DeleteModal";

function VideoCard({video, videos, setVideos, selectVideo}) {

	const getThumbnail = (file) => {
		return IUP_VIDEO.cdnUrl + '/' + video.guid + '/' + file;
	}

	const [src, setSrc] = useState(getThumbnail(video.thumbnailFileName));

	const statusLabels = {
		0: __('Awaiting Upload', 'infinite-uploads'),
		1: __('Uploaded', 'infinite-uploads'),
		2: __('Processing', 'infinite-uploads'),
		3: __('Transcoding', 'infinite-uploads'),
		4: __('Finished', 'infinite-uploads'),
		5: __('Error', 'infinite-uploads'),
		6: __('Upload Failed', 'infinite-uploads'),
	}
	const status = statusLabels[video.status];

	if ([0, 1, 5, 6].includes(video.status)) {
		return (
			<span className="m-3 w-100 p-0">
				<Card className="m-0 shadow-sm">
					<div className="ratio ratio-16x9 overflow-hidden bg-black text-white rounded-top">
						<div>
							<div className="d-flex justify-content-center align-items-center h-100 text-secondary font-weight-bold">
							{status}
							</div>
						</div>
					</div>
					<Card.Body className={"p-2"}>
						<Card.Title className="h6 card-title text-truncate">{video.title}</Card.Title>
						<Row className="justify-content-end text-muted align-items-center">
							<Col className="justify-content-end d-flex">
								<DeleteModal video={video} setVideos={setVideos}/>
							</Col>
						</Row>
					</Card.Body>
				</Card>
			</span>
		)
	} else if ([2].includes(video.status)) { //processing
		return (
			<span className="m-3 w-100 p-0">
				<Card className="m-0 shadow-sm">
				<div className="ratio ratio-16x9 overflow-hidden bg-black text-white rounded-top">
					<div>
						<div className="d-flex justify-content-center align-items-center h-100 text-secondary font-weight-bold">
							{status}
						</div>
					</div>
				</div>
				<Card.Body className={"p-2"}>
					<Card.Title className="h6 card-title text-truncate">{video.title}</Card.Title>
					<ProgressBar animated now={video.encodeProgress} label={`${video.encodeProgress}%`} className="w-100"/>
				</Card.Body>
			</Card>
			</span>
		)
	} else {
		return (
			<VideoModal {...{video, setVideos, selectVideo}}>
				<Card className="m-0 shadow-sm">
					<div className="ratio ratio-16x9 overflow-hidden bg-black rounded-top">
						<div className="iup-video-thumb" style={{backgroundImage: `url("${src}")`}}
						     onMouseOver={() => setSrc(getThumbnail('preview.webp'))}
						     onMouseOut={() => setSrc(getThumbnail(video.thumbnailFileName))}
						>
						</div>
					</div>
					<Card.Body className={"p-2"}>
						<Card.Title className="h6 card-title text-truncate">{video.title}</Card.Title>
						{video.status === 3 ? (
							<small className="row justify-content-between text-muted align-items-center">
								<Col className="col-auto">
									{__('Transcoding', 'infinite-uploads')}:
								</Col>
								<Col>
									<ProgressBar animated now={video.encodeProgress} label={`${video.encodeProgress}%`} className="w-100"/>
								</Col>
							</small>
						) : (
							<small className="row justify-content-between text-muted align-items-center">
								<Col>
									<VideoLength video={video}/>
								</Col>
								<Col></Col>
								<Col>
									<VideoSize video={video}/>
								</Col>
							</small>
						)}
					</Card.Body>
				</Card>
			</VideoModal>
		)
	}
}

export default VideoCard;
