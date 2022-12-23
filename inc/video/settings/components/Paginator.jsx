import Pagination from 'react-bootstrap/Pagination';
import Container from 'react-bootstrap/Container';

function Paginator({page, setPage, totalItems, itemsPerPage}) {
  if (totalItems <= itemsPerPage) {
    return null;
  }

  let active = page;
  const lastPage = Math.ceil(totalItems / itemsPerPage);
  let items = [];
  for (let number = 1; number <= lastPage; number++) {
    items.push(
      <Pagination.Item key={number} active={number === active} onClick={() => setPage(number)}>
        {number}
      </Pagination.Item>,
    );
  }

  return (
    <Pagination className="justify-content-center mt-4">
      <Pagination.First onClick={() => setPage(1)} disabled={page === 1}/>
      {items}
      <Pagination.Last onClick={() => setPage(lastPage)} disabled={page === lastPage}/>
    </Pagination>
  );
}

export default Paginator;
