/* eslint-disable @next/next/no-img-element */
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

import { useZone } from "@/context/ZoneContext";

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
  const { selectedZone } = useZone();
  const pathname = usePathname();
  const { palette } = useTheme();

  const filteredURLS = URLS.filter(
    (item) => (selectedZone && item.label !== "Clusters") || !selectedZone
  );

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
          {filteredURLS.map((url) => {
            const isActive =
              url.pathname === "/" ? pathname === url.pathname : pathname.includes(url.pathname);
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
