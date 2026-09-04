"use client";

import { alpha, Chip, useTheme } from "@mui/material";

import { useCurrentTheme } from "@/hooks";
import { ROLE_COLORS, type RoleType } from "@/utils/userUtils";

export default function UserRoleChip({ role }: { role: RoleType }) {
  const { palette } = useTheme();
  const { isDark } = useCurrentTheme();

  return (
    <Chip
      label={role}
      size="small"
      sx={{
        height: 28,
        textTransform: "capitalize",
        fontWeight: 600,
        letterSpacing: "0.02em",
        color: ROLE_COLORS[role],
        backgroundColor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
        border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }}
    />
  );
}
