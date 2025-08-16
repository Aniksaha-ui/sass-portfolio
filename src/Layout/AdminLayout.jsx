import { Fragment } from "react";
import Header from "./common/Header";
import Footer from "./common/Footer";

const AdminLayout = ({ children }) => {
  return (
    <Fragment>
      <div className="page">
        <Header></Header>
        {children}
        <Footer></Footer>
      </div>
    </Fragment>
  );
};

export default AdminLayout;
