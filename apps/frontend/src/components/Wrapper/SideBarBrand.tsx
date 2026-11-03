import { Box, Stack, Typography } from "@mui/material";

import { useSideBar } from "@/context/SideBarContext";
export default function SideBarBrand() {
  const { collapsed } = useSideBar();

  return (
    <Stack
      spacing={collapsed ? 0 : 1}
      sx={[
        {
          alignItems: "center",
          px: collapsed ? 1 : 2,
          pt: collapsed ? 2 : 2.5,
          pb: collapsed ? 1.5 : 2,
          mb: 1,
          borderBottom:
            "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 20%, transparent)",
          backgroundColor: "background.paper",
          position: "relative",
          zIndex: 300
        },
        (muiTheme) =>
          muiTheme.applyStyles("dark", {
            borderBottom:
              "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 18%, transparent)"
          })
      ]}
    >
      <Box
        sx={{
          width: 1,
          maxWidth: collapsed ? 44 : 148,
          aspectRatio: collapsed ? "1 / 1" : "3 / 2",
          borderRadius: 2,
          overflow: "hidden",
          backgroundColor: "transparent",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          px: collapsed ? 0.25 : 1,
          transition: "max-width 220ms ease"
        }}
      >
        <Box
          component="img"
          src="/static/images/logo.png"
          alt="Skylogs"
          sx={{
            width: 1,
            height: "auto",
            display: "block",
            objectFit: "contain",
            objectPosition: collapsed ? "left center" : "center"
          }}
        />
      </Box>
      {!collapsed && (
        <Typography
          component="span"
          sx={[
            {
              color: "text.primary",
              fontWeight: 700,
              fontSize: "0.8rem",
              letterSpacing: "0.28em",
              textTransform: "uppercase",
              lineHeight: 1
            },
            (muiTheme) =>
              muiTheme.applyStyles("dark", {
                color: "var(--mui-palette-secondary-main)"
              })
          ]}
        >
          Skylogs
        </Typography>
      )}
    </Stack>
  );
}
