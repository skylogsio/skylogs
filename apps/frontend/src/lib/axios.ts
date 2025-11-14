import axios, { AxiosError } from "axios";
import { getServerSession } from "next-auth";
import { getSession, signOut } from "next-auth/react";

import { authOptions } from "@/services/next-auth/authOptions";

async function getAuthorizationHeader() {
  if (typeof window === "undefined") {
    const session = await getServerSession(authOptions);
    if (session && session.error !== "RefreshTokenError") {
      return `Bearer ${session.user.token}`;
    } else {
      //TODO: User should log out
      return null;
    }
  } else {
    const session = await getSession();
    if (session && session.error !== "RefreshTokenError") {
      return `Bearer ${session?.user.token}`;
    } else {
      await signOut();
      return null;
    }
  }
}

const axiosInstance = axios.create({
  baseURL: process.env.BASE_URL,
  headers: { "Content-Type": "application/json" }
});

axiosInstance.interceptors.request.use(
  async function (config) {
    config.headers.Authorization = await getAuthorizationHeader();
    return config;
  },
  function (error) {
    return Promise.reject(error);
  }
);

axiosInstance.interceptors.response.use(
  function (response) {
    return response;
  },
  async function (error: AxiosError) {
    if (error.response?.status === 401 && error.config) {
      try {
        error.config.headers.Authorization = await getAuthorizationHeader();
        return axiosInstance.request(error.request.config);
      } catch (tokenRefreshError) {
        return Promise.reject(tokenRefreshError);
      }
    }
    return Promise.reject(error);
  }
);

export default axiosInstance;
