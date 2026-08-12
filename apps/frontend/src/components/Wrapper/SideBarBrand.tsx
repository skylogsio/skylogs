import { Box, Stack, Typography, alpha, useTheme } from "@mui/material";

import { useSideBar } from "@/context/SideBarContext";

export default function SideBarBrand() {
  const { palette } = useTheme();
  const { collapsed } = useSideBar();
  const isDark = palette.mode === "dark";

  return (
    <Stack
      spacing={collapsed ? 0 : 1}
      sx={{
        alignItems: "center",
        px: collapsed ? 1 : 2,
        pt: collapsed ? 2 : 2.5,
        pb: collapsed ? 1.5 : 2,
        mb: 1,
        borderBottom: `1px solid ${alpha(palette.primary.main, isDark ? 0.18 : 0.2)}`,
        backgroundColor: palette.background.paper,
        position: "relative",
        zIndex: 300
      }}
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
          sx={{
            color: isDark ? palette.secondary.main : palette.text.primary,
            fontWeight: 700,
            fontSize: "0.8rem",
            letterSpacing: "0.28em",
            textTransform: "uppercase",
            lineHeight: 1
          }}
        >
          Skylogs
        </Typography>
      )}
    </Stack>
  );
}
