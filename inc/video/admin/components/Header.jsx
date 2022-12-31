import Button from 'react-bootstrap/Button';
import {__, _x, _n, _nx} from '@wordpress/i18n';
import {useState} from '@wordpress/element';
import Form from 'react-bootstrap/Form';
import InputGroup from 'react-bootstrap/InputGroup';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import {VideoSize} from "./VideoAttributes";
import UploadModal from "./UploadModal";

function Header({orderBy, setOrderBy, search, setSearch, selectVideo, getVideos}) {

	const sizeOf = function (bytes) {
		if (bytes === 0) {
			return '0 B';
		}
		var e = Math.floor(Math.log(bytes) / Math.log(1024));
		return (
			(bytes / Math.pow(1024, e)).toFixed(1) +
			' ' +
			' KMGTP'.charAt(e) +
			'B'
		);
	};

	return (
		<Row className="align-items-center">
			<Col sm={8} md={3} className="mb-3 mb-lg-0">
				<InputGroup>
					<InputGroup.Text>
						<span className="dashicons dashicons-search"></span>
					</InputGroup.Text>
					<Form.Control
						placeholder={__('Search', 'infinite-uploads')}
						aria-label={__('Search', 'infinite-uploads')}
						value={search}
						onChange={(e) => setSearch(e.target.value)}
					/>
				</InputGroup>
			</Col>
			<Col sm={4} md={2} className="mb-3 mb-lg-0">
				<InputGroup>
					<InputGroup.Text>
						{__('Sort', 'infinite-uploads')}
					</InputGroup.Text>
					<Form.Select
						aria-label={__(
							'Sort by select',
							'infinite-uploads'
						)}
						value={orderBy}
						onChange={(e) => setOrderBy(e.target.value)}
					>
						>
						<option value="title">
							{__('Title', 'infinite-uploads')}
						</option>
						<option value="date">
							{__('Date', 'infinite-uploads')}
						</option>
					</Form.Select>
				</InputGroup>
			</Col>
			<Col className="mb-3 mb-lg-0">
				<Row className="justify-content-center flex-nowrap">
					<Col className="col-auto">
						<p className="mb-0">{__("Video Count", 'infinite-uploads')}</p>
						<span className="h4 text-nowrap">{IUP_VIDEO.settings.VideoCount}</span>
					</Col>
					<Col className="col-auto">
						<p className="mb-0">{__("Library Storage", 'infinite-uploads')}</p>
						<span className="h4 text-nowrap">{sizeOf(IUP_VIDEO.settings.StorageUsage)}</span>
					</Col>
					<Col className="col-auto">
						<p className="mb-0">{__("Video Bandwidth", 'infinite-uploads')}</p>
						<span className="h4 text-nowrap">{sizeOf(IUP_VIDEO.settings.TrafficUsage)}</span>
					</Col>
				</Row>
			</Col>
			<Col className="d-flex justify-content-end mb-3 mb-lg-0">
				<Button
					variant="outline-secondary"
					className="rounded-pill text-nowrap"
					href={IUP_VIDEO.settingsUrl}
				>
					<span className="dashicons dashicons-admin-generic"></span>
					{__('Settings', 'infinite-uploads')}
				</Button>
				{!selectVideo && (
					<UploadModal getVideos={getVideos}/>
				)}
			</Col>
		</Row>
	);
}

export default Header;
