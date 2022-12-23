import {useEffect, useState} from '@wordpress/element';
import {__, _x, _n, _nx} from '@wordpress/i18n';
import VideoCard from "./components/VideoCard";
import Container from 'react-bootstrap/Container';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import Header from "./components/Header";
import Paginator from "./components/Paginator";
import Spinner from 'react-bootstrap/Spinner';

export default function Page() {
  const [videos, setVideos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [orderBy, setOrderBy] = useState('date');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const [itemsPerPage, setItemsPerPage] = useState(60);

  //get videos on render
  useEffect(() => {
	  console.log('getting videos');
	  if (!loading) {
		  getVideos()
	  }
  }, [orderBy, page]);

  useEffect(() => {
    if (search.length > 2 || search.length === 0) {
      getVideos()
    }
  }, [search]);

  function getVideos() {
    setLoading(true);
    const options = {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        AccessKey: IUP_VIDEO.apiKey
      },
    };

    fetch(`https://video.bunnycdn.com/library/${IUP_VIDEO.libraryId}/videos?page=${page}&itemsPerPage=${itemsPerPage}&orderBy=${orderBy}&search=${search}`, options)
      .then((response) => response.json())
      .then((data) => {
        console.log("Videos:", data);
        setVideos(data.items);
        setTotalItems(data.totalItems);
        setItemsPerPage(data.itemsPerPage);
        setLoading(false);
      })
      .catch((error) => {
        console.error(error);
        setLoading(false);
      });
  }

  return (
    <>
      <h1 className="text-muted mb-3">
	      <img src={IUP_VIDEO.assetBase + "/img/iu-logo-gray.svg"} alt="Infinite Uploads Logo" height="32" width="32" className="me-2"/>
        {__('Video Library', 'infinite-uploads')}
      </h1>
      <Container fluid>

        <Header {...{orderBy, setOrderBy, search, setSearch}}/>

        {!loading ? (
          <Container fluid>
            <Row xs={1} sm={1} md={2} lg={2} xl={3} xxl={4}>
              {videos.map((video, index) => {
                return (
                  <Col key={index + video.guid}>
	                  <VideoCard {...{video, setVideos}} />
                  </Col>
                )
              })}
            </Row>
            <Paginator {...{page, setPage, totalItems, itemsPerPage}} />
          </Container>
        ) : (
	        <Container className="d-flex justify-content-center align-middle my-5">
		        <Spinner animation="grow" role="status" className="my-5">
			        <span className="visually-hidden">Loading...</span>
		        </Spinner>
	        </Container>
        )}

      </Container>
    </>
  );
}
