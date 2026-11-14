"use client";

import { useMemo } from "react";
import { usePathname } from "next/navigation";

import { Box, Stack, Typography } from "@mui/material";

import TopBarHaIndicator from "./TopBarHaIndicator";
import TopBarProfile from "./TopBarProfile";
import TopBarZone from "./TopBarZone";

const PAGE_TITLES: Record<string, string> = {
  "alert-rule": "Alert Rules",
  incidents: "Incidents",
  runbooks: "Runbooks",
  status: "Status",
  debugging: "Debugging",
  endpoints: "Endpoints",
  users: "Users",
  teams: "Teams",
  "data-source": "Data Sources",
  clusters: "Clusters",
  "profile-services": "Profile Services",
  settings: "Settings",
  "admin-area": "Admin Area"
};

function resolvePageTitle(pathname: string) {
  const segments = pathname.split("/").filter(Boolean);
  const routeSegment = segments.find((segment) => PAGE_TITLES[segment]);
  if (routeSegment) return PAGE_TITLES[routeSegment];

  const fallback = segments[segments.length - 1] ?? "Dashboard";
  return fallback
    .split("-")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

export default function TopBar() {
  const pathname = usePathname();
  const pageTitle = useMemo(() => resolvePageTitle(pathname), [pathname]);

  return (
    <Box
      component="header"
      sx={{
        position: "sticky",
        top: 0,
        zIndex: 100,
        width: 1,
        flexShrink: 0,
        pl: { xs: 3, sm: 4 },
        pr: { xs: 2, sm: 2.5 },
        pt: { xs: 1.25, sm: 1.5 },
        pb: { xs: 1, sm: 1.25 },
        boxSizing: "border-box",
        backgroundColor: "transparent"
      }}
    >
      <Stack
        direction="row"
        sx={{
          alignItems: "center",
          justifyContent: "space-between",
          gap: 1.5,
          minHeight: 40
        }}
      >
        <Stack spacing={0.25} sx={{ minWidth: 0 }}>
          <Typography
            variant="caption"
            sx={{
              color: "text.secondary",
              fontSize: "0.65rem",
              letterSpacing: "0.28em",
              textTransform: "uppercase",
              lineHeight: 1
            }}
          >
            Skylogs
          </Typography>
          <Typography
            component="h1"
            sx={[
              {
                fontWeight: 700,
                fontSize: { xs: "1.05rem", sm: "1.15rem" },
                letterSpacing: "-0.02em",
                color: "text.primary",
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis",
                lineHeight: 1.1
              },
              (muiTheme) =>
                muiTheme.applyStyles("dark", {
                  color: "var(--mui-palette-secondary-main)"
                })
            ]}
          >
            {pageTitle}
          </Typography>
        </Stack>

        <Stack direction="row" spacing={0.75} sx={{ alignItems: "center", flexShrink: 0 }}>
          <TopBarHaIndicator />
          <TopBarZone />
          <TopBarProfile />
        </Stack>
      </Stack>

      <Box
        sx={[
          {
            mt: 1,
            height: "1px",
            background:
              "linear-gradient(90deg, transparent, color-mix(in srgb, var(--mui-palette-primary-main) 24%, transparent), transparent)"
          },
          (muiTheme) =>
            muiTheme.applyStyles("dark", {
              background:
                "linear-gradient(90deg, transparent, color-mix(in srgb, var(--mui-palette-primary-main) 20%, transparent), transparent)"
            })
        ]}
      />
    </Box>
  );
}
