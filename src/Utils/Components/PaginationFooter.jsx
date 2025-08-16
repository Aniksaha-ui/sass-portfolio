const createPaginationLinks = (lastPage, currentPage, setCurrentPage) => {
  const links = Array.from({ length: lastPage }).map((_, i) => (
    <li
      key={i + 1}
      className={`page-item ${i + 1 === currentPage ? "active" : ""}`}
    >
      <button className="page-link" onClick={() => setCurrentPage(i + 1)}>
        {i + 1}
      </button>
    </li>
  ));
  return links;
};


export const PaginationFooter = ({ paginationInformation, lastPage, page, setPage }) => {
  return (
    <div className="card-footer d-flex align-items-center">
    <p className="m-0 text-muted">
      Showing <span>{paginationInformation.from}</span> to <span>{paginationInformation.to}</span> of{" "}
      <span>{paginationInformation.total}</span> entries
    </p>
    <ul className="pagination m-0 ms-auto">
      {createPaginationLinks(lastPage, page, setPage)}
      <li className="page-item">
        <a className="page-link" href="#">
          next
          <svg
            xmlns="http://www.w3.org/2000/svg"
            className="icon"
            width={24}
            height={24}
            viewBox="0 0 24 24"
            strokeWidth={2}
            stroke="currentColor"
            fill="none"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
            <path d="M9 6l6 6l-6 6" />
          </svg>
        </a>
      </li>
    </ul>
  </div>
  );
};