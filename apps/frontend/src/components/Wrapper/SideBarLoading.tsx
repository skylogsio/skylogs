"use client";

import { Box, List, ListItem, Skeleton, Stack } from "@mui/material";

import { useSideBar } from "@/context/SideBarContext";

import SideBarBrand from "./SideBarBrand";

const SKELETON_COUNT = 11;

export default function SideBarLoading() {
  const { collapsed } = useSideBar();

  return (
    <Box
      sx={{
        height: 1,
        overflow: "hidden",
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
          {Array.from({ length: SKELETON_COUNT }).map((_, index) => (
            <ListItem
              key={index}
              sx={{
                position: "relative",
                paddingY: collapsed ? 0.5 : 0.75,
                paddingRight: collapsed ? 0 : 2,
                paddingLeft: collapsed ? 0 : 2,
                display: "flex",
                justifyContent: collapsed ? "center" : "flex-start"
              }}
            >
              {collapsed ? (
                <Skeleton
                  variant="rounded"
                  width={44}
                  height={44}
                  sx={{ borderRadius: 2.25, flexShrink: 0 }}
                />
              ) : (
                <Stack
                  direction="row"
                  spacing={1.5}
                  sx={{
                    width: 1,
                    alignItems: "center",
                    px: 2,
                    py: 1.5,
                    borderRadius: 3
                  }}
                >
                  <Skeleton variant="rounded" width={22} height={22} sx={{ flexShrink: 0 }} />
                  <Skeleton variant="rounded" height={14} sx={{ flex: 1, maxWidth: 120 }} />
                </Stack>
              )}
            </ListItem>
          ))}
        </List>
      </Stack>
    </Box>
  );
}
