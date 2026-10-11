"use client";

import type { ReactNode } from "react";

import { Box } from "@mui/material";

export default function IncidentPolicyModalBody({ children }: { children: ReactNode }) {
  return (
    <Box
      sx={[
        {
          mt: 1.5,
          pt: 2.5,
          minWidth: 0,
          borderTop:
            "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 14%, transparent)"
        },
        (muiTheme) =>
          muiTheme.applyStyles("dark", {
            borderTop:
              "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 12%, transparent)"
          })
      ]}
    >
      {children}
    </Box>
  );
}
