import { useEffect, useState } from "react";
import fetchData from "../../Utils/Functions/fetchInformation";
import { PaginationFooter } from "../../Utils/Components/PaginationFooter";
import AdminLayout from "../../Layout/AdminLayout";
import Search from "../../Utils/Components/Search";
import useApi from "../../Hooks/useApi";
import Loading from "../../Utils/Components/Loading";
import debounce from "../../Utils/Functions/debounce";

function Users() {
    const [page, setPage] = useState(1);
    const [paginationInformation,setPaginationInformation] = useState({to:0,from:0,total: 0});
    const [lastPage, setLastPage] = useState([]);
    const [routes, setRoutes] = useState([]);
    const [search, setSearch] = useState("");
    const [loading,setLoading] = useState(true);
    const api = useApi();
    const fetchRouteInformation = async () => {
      await fetchData( api.fetchUsers, page,setLastPage,setRoutes, search, setPaginationInformation,setLoading);
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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                          </tr>
                        </thead>
                        <tbody>
                          {routes.map((route, index) => (
                            <tr key={index}>
                              <td>{route.id}</td>
                              <td>{route.name}</td>
                              <td>
                                {route.email}
                              </td>
                              <td>{route.role}</td>
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
  
  export default Users;
  