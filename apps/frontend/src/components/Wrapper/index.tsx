"use client";

import { type PropsWithChildren } from "react";
import { usePathname, useRouter } from "next/navigation";

import { Box, IconButton, Tooltip } from "@mui/material";
import { signOut, useSession } from "next-auth/react";
import {
  HiOutlineChevronDoubleLeft,
  HiOutlineChevronDoubleRight,
  HiOutlineChevronLeft,
  HiOutlineChevronRight
} from "react-icons/hi";

import SideBar from "@/components/Wrapper/SideBar";
import { useSideBar } from "@/context/SideBarContext";
import { useRole } from "@/hooks";

import AdminSideBar from "./AdminSideBar";
import TopBar from "./TopBar";

const SKYLOGS_VERSION = "0.15.0";
const DEFAULT_REDIRECT_PATH = "/alert-rule";
const SIDEBAR_EXPANDED_WIDTH = 260;
const SIDEBAR_COLLAPSED_WIDTH = 64;

export default function Wrapper({ children }: PropsWithChildren) {
  const pathname = usePathname();
  const router = useRouter();
  const { userInfo, hasRole } = useRole();
  const session = useSession();
  const { collapsed, toggleCollapsed } = useSideBar();

  const isAdminArea = pathname.includes("admin-area");

  if (session.data?.error === "RefreshTokenError") {
    signOut();
  }

  if (pathname.includes("/auth")) return children;

  if (pathname.includes("/data-source") && userInfo && !hasRole(["owner", "manager"])) {
    router.replace(DEFAULT_REDIRECT_PATH);
    return null;
  }
  if (pathname.includes("/users") && userInfo && !hasRole(["owner", "manager"])) {
    router.replace(DEFAULT_REDIRECT_PATH);
    return null;
  }
  if (pathname.includes("/settings/telegram") && userInfo && !hasRole(["owner", "manager"])) {
    router.replace(DEFAULT_REDIRECT_PATH);
    return null;
  }
  if (pathname.includes("/clusters") && userInfo && !hasRole(["owner"])) {
    router.replace(DEFAULT_REDIRECT_PATH);
    return null;
  }

  if (isAdminArea && !hasRole("owner")) {
    router.replace(DEFAULT_REDIRECT_PATH);
    return null;
  }

  return (
    <Box
      sx={{
        width: 1,
        height: "100vh",
        maxWidth: 1,
        maxHeight: "100vh",
        boxSizing: "border-box",
        padding: 0,
        margin: 0,
        border: "none",
        backgroundColor: ({ palette }) => palette.background.default,
        display: "flex",
        justifyContent: "space-between"
      }}
    >
      <Box
        component="aside"
        sx={{
          position: "relative",
          width: collapsed ? SIDEBAR_COLLAPSED_WIDTH : SIDEBAR_EXPANDED_WIDTH,
          minWidth: collapsed ? SIDEBAR_COLLAPSED_WIDTH : SIDEBAR_EXPANDED_WIDTH,
          maxWidth: collapsed ? SIDEBAR_COLLAPSED_WIDTH : SIDEBAR_EXPANDED_WIDTH,
          flexShrink: 0,
          backgroundColor: ({ palette }) => palette.background.paper,
          transition: "width 220ms ease, min-width 220ms ease, max-width 220ms ease",
          borderRight: ({ palette }) => `1px solid ${palette.divider}`
        }}
      >
        {isAdminArea ? (
          <AdminSideBar version={SKYLOGS_VERSION} />
        ) : (
          <SideBar version={SKYLOGS_VERSION} />
        )}

        <Tooltip title={collapsed ? "Expand sidebar" : "Collapse sidebar"} placement="right">
          <IconButton
            onClick={toggleCollapsed}
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
            size="small"
            sx={{
              position: "absolute",
              top: 18,
              right: -14,
              zIndex: 200,
              width: 28,
              height: 28,
              paddingRight: 0,
              backgroundColor: ({ palette }) => palette.background.paper,
              border: ({ palette }) => `1px solid ${palette.divider}`,
              color: "text.secondary",
              boxShadow: ({ palette }) =>
                `0 2px 8px ${palette.mode === "dark" ? "rgba(0,0,0,.35)" : "rgba(0,0,0,.08)"}`,
              "&:hover": {
                backgroundColor: ({ palette }) => palette.background.paper,
                color: "primary.main"
              }
            }}
          >
            {collapsed ? <HiOutlineChevronRight size={14} /> : <HiOutlineChevronLeft size={14} />}
          </IconButton>
        </Tooltip>
      </Box>
      <Box
        sx={{
          display: "flex",
          flexDirection: "column",
          flex: 1,
          minWidth: 0,
          height: 1
        }}
      >
        <TopBar />
        <Box
          component="main"
          sx={{
            height: "90%"
          }}
        >
          <Box
            sx={{
              width: 1,
              height: 1,
              padding: 3,
              overflow: "auto"
            }}
          >
            {children}
          </Box>
        </Box>
      </Box>
    </Box>
  );
}
