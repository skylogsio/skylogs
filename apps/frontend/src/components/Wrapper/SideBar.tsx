import { usePathname } from "next/navigation";

import { Box, List, Stack, Typography } from "@mui/material";
import {
  AiOutlineApi,
  AiOutlineUser,
  AiOutlineDatabase,
  AiOutlineCluster,
  AiOutlineAlert,
  AiOutlineCloud,
  AiOutlineSetting,
  AiOutlineFundProjectionScreen,
  AiOutlineTeam,
  AiOutlineBug,
  AiOutlineWarning,
  AiOutlineFileProtect,
  AiOutlineBook
} from "react-icons/ai";

import { useSideBar } from "@/context/SideBarContext";
import { useZone } from "@/context/ZoneContext";
import { useRole } from "@/hooks";

import SideBarBrand from "./SideBarBrand";
import { SideBarItem } from "./SideBarItem";
import SideBarLoading from "./SideBarLoading";
import { URLType } from "./types";

const URLS: Array<URLType> = [
  // { pathname: "/", label: "Home", icon: AiOutlineHome },
  { pathname: "/alert-rule", label: "Alert Rules", icon: AiOutlineAlert, iconScale: 1.08 },
  { pathname: "/incidents", label: "Incidents", icon: AiOutlineWarning, iconScale: 1.08 },
  {
    pathname: "/incident-policy",
    label: "Incident Policies",
    icon: AiOutlineFileProtect,
    iconScale: 1.05
  },
  {
    pathname: "/runbooks",
    label: "Runbooks",
    icon: AiOutlineBook,
    iconScale: 1.05
  },
  {
    pathname: "/status",
    label: "Status",
    icon: AiOutlineFundProjectionScreen,
    iconScale: 1.04
  },
  { pathname: "/debugging", label: "Debugging", icon: AiOutlineBug, iconScale: 1 },
  { pathname: "/endpoints", label: "Endpoints", icon: AiOutlineApi, iconScale: 1.2 },
  {
    pathname: "/users",
    label: "Users",
    role: ["owner", "manager"],
    icon: AiOutlineUser,
    iconScale: 1
  },
  { pathname: "/teams", label: "Teams", icon: AiOutlineTeam, iconScale: 1.16 },
  {
    pathname: "/data-source",
    label: "Data Sources",
    role: ["owner", "manager"],
    icon: AiOutlineDatabase,
    iconScale: 1.06
  },
  {
    pathname: "/clusters",
    label: "Clusters",
    role: ["owner"],
    icon: AiOutlineCluster,
    iconScale: 1.2
  },
  {
    pathname: "/profile-services",
    label: "Profile Services",
    role: "owner",
    icon: AiOutlineCloud,
    iconScale: 1.12
  },
  {
    pathname: "/settings",
    label: "Settings",
    role: "owner",
    icon: AiOutlineSetting,
    iconScale: 1.1
  }
];

export default function SideBar({ version }: { version: string }) {
  const { selectedZone } = useZone();
  const pathname = usePathname();
  const { collapsed } = useSideBar();
  const { isLoading, hasRole } = useRole();

  const filteredURLS = URLS.filter(
    (item) => (selectedZone && item.label !== "Clusters") || !selectedZone
  ).filter((item) => !item.role || hasRole(item.role));

  if (isLoading) {
    return <SideBarLoading />;
  }

  return (
    <Box
      sx={{
        height: 1,
        overflow: "auto",
        overflowX: "hidden",
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
        <SideBarBrand />
        <List sx={{ px: 0 }}>
          {filteredURLS.map((url, index) => {
            const isActive =
              url.pathname === "/" ? pathname === url.pathname : pathname.includes(url.pathname);
            return <SideBarItem key={url.pathname} url={url} isActive={isActive} index={index} />;
          })}
        </List>
        <Stack
          sx={{
            alignItems: "center",
            marginTop: "auto",
            pb: 1.5,
            px: 1
          }}
        >
          {!collapsed && (
            <Typography
              variant="caption"
              sx={{
                color: "text.secondary",
                fontSize: 10
              }}
            >
              version {version}
            </Typography>
          )}
        </Stack>
      </Stack>
    </Box>
  );
}
