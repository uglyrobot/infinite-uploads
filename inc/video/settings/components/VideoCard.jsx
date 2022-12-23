import {__, _x, _n, _nx} from '@wordpress/i18n';
import Card from 'react-bootstrap/Card';
import {useState} from '@wordpress/element';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import {VideoLength, VideoViews, VideoSize} from './VideoAttributes';
import VideoModal from "./VideoModal";

function VideoCard({video, videos, setVideos}) {
	const [src, setSrc] = useState(IUP_VIDEO.cdnUrl + '/' + video.guid + '/' + video.thumbnailFileName);

	return (

		<VideoModal {...{video, setVideos}}>
			<Card className="m-0 shadow-sm">
				<div className="ratio ratio-16x9 overflow-hidden bg-black">
					<div>
						<Card.Img variant="top" src={src} className="w-auto h-100 mx-auto d-block"
						          onMouseOver={() => setSrc(IUP_VIDEO.cdnUrl + '/' + video.guid + '/preview.webp')}
						          onMouseOut={() => setSrc(IUP_VIDEO.cdnUrl + '/' + video.guid + '/thumbnail.jpg')}
						          alt={__('Video thumbnail', 'infinite-uploads')}/>
					</div>
				</div>
				<Card.Body className={"p-2"}>
					<Card.Title className="h6 card-title text-truncate">{video.title}</Card.Title>
					<small className="row justify-content-between text-muted text-center">
						<Col>
							<VideoLength video={video}/>
						</Col>
						<Col>
							<VideoViews video={video}/>
						</Col>
						<Col>
							<VideoSize video={video}/>
						</Col>
					</small>
				</Card.Body>
			</Card>
		</VideoModal>
  )
}

export default VideoCard;
