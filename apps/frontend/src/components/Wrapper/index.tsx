"use client";

import { usePathname } from "next/navigation";
import { type PropsWithChildren } from "react";

import { Box } from "@mui/material";

import TopBar from "./TopBar";

export default function Wrapper({ children }: PropsWithChildren) {
  const pathname = usePathname();
  if (pathname.includes("/auth")) return children;

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
        {/* //TODO: Place SideBar component here. */}
      </Box>
      <Box display="flex" flexDirection="column" flex={1} height="100%">
        <TopBar />
        <Box component="main" height="90%">
          {children}
        </Box>
      </Box>
    </Box>
  );
}
