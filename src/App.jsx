import { useEffect, useState } from "react";
import AdminLayout from "./Layout/AdminLayout";
import "./index.css";
import useApi from "./Hooks/useApi";
import fetchData from "./Utils/Functions/fetchInformation";
import Search from "./Utils/Components/Search";
import { PaginationFooter } from "./Utils/Components/PaginationFooter";
import Loading from "./Utils/Components/Loading";

function App() {
  const [page, setPage] = useState(1);
  const [paginationInformation,setPaginationInformation] = useState({to:0,from:0,total: 0});
  const [lastPage, setLastPage] = useState([]);
  const [routes, setRoutes] = useState([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState("");
  const api = useApi();
  const fetchRouteInformation = async () => {
    await fetchData( api.fetchRoutes, page,setLastPage,setRoutes, search, setPaginationInformation,setLoading);
  };

  useEffect(() => {
    if (search != "") {
      setPage(1);
    }
    fetchRouteInformation();
  }, [page, search]);

  if(loading){
    return <Loading />
  }

  return (
    <AdminLayout>
      <div className="page-wrapper">
        <div className="page-header d-print-none">
          <div className="container-xl">
            <div className="row g-2 align-items-center">
              <div className="col"></div>
            </div>
          </div>
        </div>
        <div className="page-body">
          <div className="container-xl">
            <div className="row row-cards">
              <div className="col-12">
                <div className="card">
                  <div className="card-header d-flex align-items-center justify-content-between">
                    <h3 className="card-title">Route List</h3>
                    <div className="btn btn-primary">Add New</div>
                  </div>                   
                  <Search search={search} setSearch={setSearch} />   {/* search */}
                  <div className="table-responsive mx-2 mt-1">
                    <table className="table table-bordered">
                      <thead>
                        <tr>
                          <th>SL</th>
                          <th>Origin</th>
                          <th>Destination</th>
                          <th>Route Name</th>
                        </tr>
                      </thead>
                      <tbody>
                        {routes.map((route, index) => (
                          <tr key={index}>
                            <td>{route.id}</td>
                            <td>{route.origin}</td>
                            <td>
                              <span className="badge bg-success me-1" />
                              {route.destination}
                            </td>
                            <td>{route.route_name}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  <PaginationFooter paginationInformation={paginationInformation} lastPage={lastPage} page={page} setPage={setPage} />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}

export default App;
