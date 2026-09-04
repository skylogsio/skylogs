"use client";

import { Box, Stack } from "@mui/material";
// import TopBarLanguage from "./TopBarLanguage";

import ThemeSwitch from "./ThemeSwitch";
import TopBarProfile from "./TopBarProfile";
import TopBarZone from "./TopBarZone";
// import TopBarSearch from "./TopBarSearch";

export default function TopBar() {
  return (
    <Box
      sx={{
        width: 1,
        display: "flex",
        flexDirection: "row-reverse",
        justifyContent: "flex-start",
        alignItems: "center",
        position: "sticky",
        top: 0,
        zIndex: 100,
        backgroundColor: ({ palette }) => palette.background.paper,
        boxSizing: "border-box",
        paddingY: 1,
        paddingX: 1
      }}
    >
      {/*<TopBarSearch />*/}
      {/*<TopBarLanguage />*/}
      {/* <Stack direction="row"> */}
      <Stack
        direction="row"
        spacing={2}
        sx={{
          alignItems: "center"
        }}
      >
        <TopBarZone />
        <ThemeSwitch />
        <TopBarProfile />
      </Stack>
      {/* </Stack> */}
    </Box>
  );
}
