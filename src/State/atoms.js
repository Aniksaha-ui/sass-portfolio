import { atom } from "recoil";

export const userInformationAtom = atom({
  key: "userInfo",
  default: localStorage.getItem("user") ? localStorage.getItem("user") : "",
});
