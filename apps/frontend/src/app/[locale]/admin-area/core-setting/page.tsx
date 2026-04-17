"use client";

import { Box, Typography } from "@mui/material";

export default function CoreSetting() {
  return (
    <Box minHeight="100%">
      <Box textAlign="center" my={3}>
        <Typography
          variant="h3"
          fontWeight="800"
          component="h1"
          sx={{
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
          fontWeight="400"
          sx={{ maxWidth: 600, mx: "auto" }}
        >
          Configure cluster settings and agent connections for distributed operations
        </Typography>
      </Box>
    </Box>
  );
}
