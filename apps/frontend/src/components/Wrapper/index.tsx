"use client";

import { usePathname, useRouter } from "next/navigation";
import { type PropsWithChildren } from "react";

import { Box } from "@mui/material";
import { signOut, useSession } from "next-auth/react";

import SideBar from "@/components/Wrapper/SideBar";
import { useRole } from "@/hooks";

import TopBar from "./TopBar";

export default function Wrapper({ children }: PropsWithChildren) {
  const pathname = usePathname();
  const router = useRouter();
  const { userInfo, hasRole } = useRole();
  const session = useSession();

  if (session.data?.error === "RefreshTokenError") {
    signOut();
  }

  if (pathname.includes("/auth")) return children;

  if (pathname.includes("/data-source") && userInfo && !hasRole(["owner", "manager"])) {
    router.replace("/");
    return null;
  }
  if (pathname.includes("/users") && userInfo && !hasRole(["owner", "manager"])) {
    router.replace("/");
    return null;
  }
  if (pathname.includes("/settings/telegram") && userInfo && !hasRole(["owner", "manager"])) {
    router.replace("/");
    return null;
  }
  if (pathname.includes("/clusters") && userInfo && !hasRole(["owner"])) {
    router.replace("/");
    return null;
  }

  return (
    <Box
      width="100%"
      height="100vh"
      maxWidth="100%"
      maxHeight="100vh"
      sx={{
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
        width="20%"
        maxWidth="300px"
        sx={{ backgroundColor: ({ palette }) => palette.background.paper }}
      >
        <SideBar />
      </Box>
      <Box display="flex" flexDirection="column" flex={1} height="100%">
        <TopBar />
        <Box component="main" height="90%">
          <Box width="100%" height="100%" padding="1.7rem" overflow="auto">
            {children}
          </Box>
        </Box>
      </Box>
    </Box>
  );
}
