import { useEffect, useState } from "react";

const Search = ({ search, setSearch }) => {
  const [debonceSearch, setDebounceSearch] = useState("");

  useEffect(() => {
    const handler = setTimeout(() => {
      setSearch(debonceSearch);
    }, 500);
    return () => {
      clearTimeout(handler);
    };
  }, [debonceSearch]);

  return (
    <div className="card-body border-bottom py-3">
      <div className="d-flex">
        <div></div>
        <div className="ms-auto text-muted">
          Search:
          <div className="ms-2 d-inline-block">
            <input
              onChange={(e) => setDebounceSearch(e.target.value)}
              type="text"
              className="form-control form-control-sm"
              aria-label="Search invoice"
            />
          </div>
        </div>
      </div>
    </div>
  );
};

export default Search;
