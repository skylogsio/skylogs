import { Box } from "@mui/material";

import TopBarLanguage from "./TopBarLanguage";
import TopBarProfile from "./TopBarProfile";
import TopBarSearch from "./TopBarSearch";

export default function TopBar() {
  return (
    <Box
      width="100%"
      display="flex"
      justifyContent="space-between"
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
      <TopBarSearch />
      <TopBarLanguage />
      <TopBarProfile />
    </Box>
  );
}
