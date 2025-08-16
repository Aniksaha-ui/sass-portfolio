import useAxios from "./useAxios";

const useApi = () => {
  const axiosClient = useAxios();

  /** get localstorage value */
  const getLocalStorageValue = () => {
    return {
      token: localStorage.getItem("token")
        ? localStorage.getItem("token")
        : null,
      email: localStorage.getItem("email")
        ? localStorage.getItem("email")
        : null,
      role: localStorage.getItem("role") ? localStorage.getItem("role") : null,
    };
  };

  /** calling login api */

  const login = async (data) => {
    const response = await axiosClient.apiClient("POST", "login", data);
    if (response) {
      if (response?.data) {
        return response.data;
      }
    } else {
      return { message: response.message, data: [] };
    }
    return null;
  };


  const fetchRoutes = async (page,search) => {
    const query = search ? `&search=${encodeURIComponent(search)}` : '';

    const response = await axiosClient.apiClient("GET", `admin/routes?page=${page}${query}`);
    if (response) {
      if (response?.data) {
        return response.data;
      }
    } else {
      return { message: response.message, data: [] };
    }
    return null;
  };



  /****************************************************Users Api ***********************************/
  const fetchUsers = async (page,search) => {
    const query = search ? `&search=${encodeURIComponent(search)}` : '';

    const response = await axiosClient.apiClient("GET", `admin/users?page=${page}${query}`);
    if (response) {
      if (response?.data) {
        return response.data;
      }
    } else {
      return { message: response.message, data: [] };
    }
    return null;
  };
  /****************************************************Users Api ***********************************/

  return {
    getLocalStorageValue,
    login,
    fetchRoutes,
    fetchUsers
  };
};

export default useApi;
