import Link from "next/link";

import { alpha, Box, ListItem, ListItemButton, Stack, useTheme } from "@mui/material";

import { useRole } from "@/hooks";

import type { URLType } from "./types";

interface SideBarItemProps {
  url: URLType;
  isActive: boolean;
}

export function SideBarItem({ url, isActive }: SideBarItemProps) {
  const { palette } = useTheme();
  const { hasRole } = useRole();

  if (url.role) {
    if (!hasRole(url.role)) return;
  }

  const IconComponent = url.icon;

  return (
    <ListItem
      key={url.pathname}
      sx={{
        position: "relative",
        paddingY: 0,
        paddingRight: 2,
        paddingLeft: isActive ? 0 : 2
      }}
    >
      <Stack direction="row" spacing={2} width="100%">
        {isActive && (
          <Box
            sx={{
              content: "''",
              display: "inline-block",
              height: "100%",
              width: 5,
              backgroundColor: `${palette.primary.main}!important`,
              position: "absolute",
              top: 0,
              left: 0,
              borderRadius: "0 0.6rem 0.6rem 0"
            }}
          ></Box>
        )}
        <ListItemButton
          component={Link}
          href={url.pathname}
          sx={{
            paddingY: 2,
            borderRadius: "0.6rem",
            backgroundColor: isActive
              ? `${alpha(palette.primary.main, 0.15)}!important`
              : "transparent",
            color: "inherit",
            display: "flex",
            alignItems: "center",
            gap: 1.5
          }}
        >
          <IconComponent size="1.4rem" />
          {url.label}
        </ListItemButton>
      </Stack>
    </ListItem>
  );
}
