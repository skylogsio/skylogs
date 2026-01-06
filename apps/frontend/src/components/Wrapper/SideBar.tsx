import Image from "next/image";
import { usePathname } from "next/navigation";

import { Box, List, Stack, Typography, useTheme } from "@mui/material";
import {
  AiOutlineApi,
  AiOutlineUser,
  AiOutlineDatabase,
  AiOutlineCluster,
  AiOutlineAlert,
  AiOutlineCloud,
  AiOutlineSetting,
  AiOutlineFundProjectionScreen,
  AiOutlineTeam
} from "react-icons/ai";

import { SideBarItem } from "./SideBarItem";
import { URLType } from "./types";

const URLS: Array<URLType> = [
  // { pathname: "/", label: "Home", icon: AiOutlineHome },
  { pathname: "/alert-rule", label: "Alert Rules", icon: AiOutlineAlert },
  { pathname: "/status", label: "Status", icon: AiOutlineFundProjectionScreen },
  { pathname: "/endpoints", label: "Endpoints", icon: AiOutlineApi },
  { pathname: "/users", label: "Users", role: ["owner", "manager"], icon: AiOutlineUser },
  { pathname: "/teams", label: "Teams", icon: AiOutlineTeam },
  {
    pathname: "/data-source",
    label: "Data Sources",
    role: ["owner", "manager"],
    icon: AiOutlineDatabase
  },
  { pathname: "/clusters", label: "Clusters", role: ["owner"], icon: AiOutlineCluster },
  { pathname: "/profile-services", label: "Profile Services", role: "owner", icon: AiOutlineCloud },
  { pathname: "/settings", label: "Settings", role: "owner", icon: AiOutlineSetting }
];

export default function SideBar({ version }: { version: string }) {
  const pathname = usePathname();
  const { palette } = useTheme();
  return (
    <Box height="100%" overflow="auto" sx={{ direction: "rtl" }}>
      <Stack width="100%" height="100%" sx={{ direction: "ltr" }}>
        <Box paddingX={7} marginY="-10%">
          <Image
            src="/static/images/logo.png"
            alt="Skylogs Logo"
            width="400"
            height="120"
            style={{ filter: `drop-shadow(0px 0px 16px ${palette.primary.light})` }}
          />
        </Box>
        <List>
          {URLS.map((url) => {
            const isActive =
              url.pathname === "/" ? pathname === url.pathname : pathname.includes(url.pathname);
            return <SideBarItem key={url.pathname} url={url} isActive={isActive} />;
          })}
        </List>
        <Stack alignItems="center" marginTop="auto">
          <Typography variant="caption" color="text.secondary" fontSize={10}>
            version {version}
          </Typography>
        </Stack>
      </Stack>
    </Box>
  );
}
