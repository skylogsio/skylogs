import Image from "next/image";
import { usePathname } from "next/navigation";

import { Box, List, Stack, Typography, useTheme } from "@mui/material";
import { AiOutlineAppstore } from "react-icons/ai";

import { SideBarItem } from "./SideBarItem";
import type { URLType } from "./types";

const URLS: Array<URLType> = [
  { pathname: "/admin-area", label: "Overview", icon: AiOutlineAppstore }
];

export default function AdminSideBar({ version }: { version: string }) {
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
              url.pathname === "/admin-area"
                ? pathname === url.pathname
                : pathname.includes(url.pathname);
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
