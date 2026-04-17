"use client";

import { Box, Stack } from "@mui/material";
// import TopBarLanguage from "./TopBarLanguage";

import ThemeSwitch from "./ThemeSwitch";
import TopBarProfile from "./TopBarProfile";
// import TopBarSearch from "./TopBarSearch";

export default function TopBar() {
  return (
    <Box
      width="100%"
      display="flex"
      flexDirection="row-reverse"
      justifyContent="flex-start"
      alignItems="center"
      sx={{
        position: "sticky",
        top: 0,
        zIndex: 100,
        backgroundColor: ({ palette }) => palette.background.paper,
        boxSizing: "border-box",
        padding: "0.7rem 0.5rem"
      }}
    >
      {/*<TopBarSearch />*/}
      {/*<TopBarLanguage />*/}
      {/* <Stack direction="row"> */}
      <Stack direction="row" alignItems="center" spacing={2}>
        <ThemeSwitch />
        <TopBarProfile />
      </Stack>
      {/* </Stack> */}
    </Box>
  );
}
