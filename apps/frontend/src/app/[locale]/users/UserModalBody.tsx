"use client";

import type { ReactNode } from "react";

import { Box, alpha, useTheme } from "@mui/material";

import { useCurrentTheme } from "@/hooks";

export default function UserModalBody({ children }: { children: ReactNode }) {
  const { palette } = useTheme();
  const { isDark } = useCurrentTheme();

  return (
    <Box
      sx={{
        mt: 1.5,
        pt: 2.5,
        borderTop: `1px solid ${alpha(palette.primary.main, isDark ? 0.12 : 0.14)}`
      }}
    >
      {children}
    </Box>
  );
}
