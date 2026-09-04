"use client";

import { Box, Typography } from "@mui/material";

export default function CoreSetting() {
  return (
    <Box
      sx={{
        minHeight: 1
      }}
    >
      <Box
        sx={{
          textAlign: "center",
          my: 3
        }}
      >
        <Typography
          variant="h3"
          component="h1"
          sx={{
            fontWeight: 800,

            background: ({ palette }) =>
              `linear-gradient(135deg, ${palette.primary.main}, ${palette.secondary.main})`,

            backgroundClip: "text",
            WebkitBackgroundClip: "text",
            WebkitTextFillColor: "transparent",
            mb: 1
          }}
        >
          Core Setting
        </Typography>
        <Typography
          variant="h6"
          color="textSecondary"
          sx={{
            fontWeight: 400,
            maxWidth: 600,
            mx: "auto"
          }}
        >
          Configure cluster settings and agent connections for distributed operations
        </Typography>
      </Box>
    </Box>
  );
}
