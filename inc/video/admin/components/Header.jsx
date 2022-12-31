import Button from 'react-bootstrap/Button';
import {__, _x, _n, _nx} from '@wordpress/i18n';
import {useState} from '@wordpress/element';
import Form from 'react-bootstrap/Form';
import InputGroup from 'react-bootstrap/InputGroup';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';

function Header({orderBy, setOrderBy, search, setSearch, selectVideo}) {
	return (
		<Row>
			<Col sm={8} md={3} className="mb-3">
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
			<Col sm={4} md={2} className="mb-3">
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
			<Col className="d-flex justify-content-end mb-3">
				{!selectVideo && (
					<Button
						variant="primary"
						className="text-nowrap text-white px-3"
					>
						{__('New Video', 'infinite-uploads')}
					</Button>
				)}
			</Col>
		</Row>
	);
}

export default Header;
