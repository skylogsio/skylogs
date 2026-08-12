import { usePathname } from "next/navigation";

import { Box, List, Stack, Typography } from "@mui/material";
import { AiOutlineAppstore, AiOutlineApi, AiOutlineSetting } from "react-icons/ai";

import { useSideBar } from "@/context/SideBarContext";
import { useRole } from "@/hooks";

import SideBarBrand from "./SideBarBrand";
import { SideBarItem } from "./SideBarItem";
import SideBarLoading from "./SideBarLoading";
import type { URLType } from "./types";

const URLS: Array<URLType> = [
  { pathname: "/admin-area", label: "Overview", icon: AiOutlineAppstore, iconScale: 1.06 },
  {
    pathname: "/admin-area/core-setting",
    label: "Core Setting",
    icon: AiOutlineSetting,
    iconScale: 1.1
  },
  {
    pathname: "/admin-area/connectivity-setting",
    label: "Connectivity Setting",
    icon: AiOutlineApi,
    iconScale: 1.2
  }
];

export default function AdminSideBar({ version }: { version: string }) {
  const pathname = usePathname();
  const { collapsed } = useSideBar();
  const { isLoading } = useRole();

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
          {URLS.map((url, index) => {
            const isActive =
              url.pathname === "/admin-area"
                ? pathname === url.pathname
                : pathname.includes(url.pathname);
            return (
              <SideBarItem key={url.pathname} url={url} isActive={isActive} index={index} />
            );
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
