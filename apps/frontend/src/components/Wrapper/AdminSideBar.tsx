/* eslint-disable @next/next/no-img-element */
import { usePathname } from "next/navigation";

import { Box, List, Stack, Typography, useTheme } from "@mui/material";
import { AiOutlineAppstore, AiOutlineSetting } from "react-icons/ai";
import { PiPlugsConnected } from "react-icons/pi";

import { SideBarItem } from "./SideBarItem";
import type { URLType } from "./types";

const URLS: Array<URLType> = [
  { pathname: "/admin-area", label: "Overview", icon: AiOutlineAppstore },
  { pathname: "/admin-area/core-setting", label: "Core Setting", icon: AiOutlineSetting },
  {
    pathname: "/admin-area/connectivity-setting",
    label: "Connectivity Setting",
    icon: PiPlugsConnected
  }
];

export default function AdminSideBar({ version }: { version: string }) {
  const pathname = usePathname();
  const { palette } = useTheme();
  return (
    <Box
      sx={{
        height: 1,
        overflow: "auto",
        direction: "rtl"
      }}
    >
      <Stack
        sx={{
          width: 1,
          height: 1,
          direction: "ltr"
        }}
      >
        <Box
          sx={{
            paddingX: 3,
            display: "flex",
            justifyContent: "center",
            marginY: "-5%"
          }}
        >
          <img
            src="/static/images/logo.png"
            alt="Skylogs Logo"
            style={{
              filter: `drop-shadow(0px 0px 16px ${palette.primary.light})`,
              width: "100%",
              maxWidth: 150
            }}
          />
        </Box>
        <List>
          {URLS.map((url) => {
            const isActive =
              url.pathname === "/admin-area"
                ? pathname === url.pathname
                : pathname.includes(url.pathname);
            return <SideBarItem key={url.pathname} url={url} isActive={isActive} />;
          })}
        </List>
        <Stack
          sx={{
            alignItems: "center",
            marginTop: "auto"
          }}
        >
          <Typography
            variant="caption"
            sx={{
              color: "text.secondary",
              fontSize: 10
            }}
          >
            version {version}
          </Typography>
        </Stack>
      </Stack>
    </Box>
  );
}
