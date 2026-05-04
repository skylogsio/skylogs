import { cookies } from "next/headers";

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

async function getZoneHeader() {
  if (typeof window === "undefined") {
    const cookieStore = await cookies();
    const zoneCookie = cookieStore.get("X-Cluster");
    return zoneCookie?.value || "";
  } else {
    const cookies = document.cookie.split(";");
    const zoneCookie = cookies.find((c) => c.trim().startsWith("X-Cluster="));
    if (zoneCookie) {
      return zoneCookie.split("=")[1];
    }
    return "";
  }
}

const axiosInstance = axios.create({
  baseURL: process.env.BASE_URL,
  headers: { "Content-Type": "application/json", Accept: "application/json" }
});

axiosInstance.interceptors.request.use(
  async function (config) {
    config.headers.Authorization = await getAuthorizationHeader();

    const zone = await getZoneHeader();

    config.headers["X-Cluster"] = zone;

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
