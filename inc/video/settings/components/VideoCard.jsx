import {__, _x, _n, _nx} from '@wordpress/i18n';
import Card from 'react-bootstrap/Card';
import {useState} from '@wordpress/element';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';

function VideoCard({video}) {
  const [src, setSrc] = useState(IUP_VIDEO.cdnUrl + '/' + video.guid + '/thumbnail.jpg');

  const sizeOf = function (bytes) {
    if (bytes === 0) {
      return "0 B";
    }
    var e = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, e)).toFixed(1) + ' ' + ' KMGTP'.charAt(e) + 'B';
  }

  //function to turn seconds into a human readable time
  const secondsToTime = function (seconds) {
    var h = Math.floor(seconds / 3600);
    var m = Math.floor(seconds % 3600 / 60);
    var s = Math.floor(seconds % 3600 % 60);
    return (h > 0 ? h + ":" : "") + (m > 0 ? (h > 0 && m < 10 ? "0" : "") + m + ":" : "0:") + (s < 10 ? "0" : "") + s;
  }

  //change datetime to human readable in localstring
  const dateTime = function (date) {
    return new Date(date).toLocaleString();
  }

  return (
    <a className="col m-3 w-100 p-0 text-decoration-none" role="button">
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
          <Row className="text-muted text-center">
            <Col>
              <small>
                {dateTime(video.dateUploaded)}</small>
            </Col>
          </Row>
          <small className="row justify-content-between text-muted text-center">
            <div className="col" title={__('Video Length', 'infinite-uploads')}><span className="dashicons dashicons-clock"></span> {secondsToTime(video.length)}</div>
            <div className="col" title={__('View Count', 'infinite-uploads')}><span className="dashicons dashicons-welcome-view-site"></span> {video.views}</div>
            <div className="col" title={__('Storage Size', 'infinite-uploads')}><span className="dashicons dashicons-media-video"></span> {sizeOf(video.storageSize)}</div>
          </small>
        </Card.Body>
      </Card>
    </a>
  )
}

export default VideoCard;
