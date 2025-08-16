import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App.jsx";
import "./index.css";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import Login from "./Views/Login/Login.jsx";
import { RecoilRoot } from "recoil";
import Users from "./Views/Users/Users.jsx";

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <BrowserRouter>
      <RecoilRoot>
        <Routes>
          <Route path="/" element={<Login />}></Route>
          <Route path="/login" element={<Login />}></Route>
          <Route path="admin/routes" element={<App />}></Route>
          <Route path="/admin/users" element={<Users />}></Route>
        </Routes>
      </RecoilRoot>
    </BrowserRouter>
  </StrictMode>
);
