"use client";

import { alpha, ToggleButton, ToggleButtonGroup, useTheme } from "@mui/material";

import { useCurrentTheme } from "@/hooks";
import { ROLE_TYPES, type RoleType } from "@/utils/userUtils";

type UserRoleToggleProps = {
  value: RoleType;
  onChange: (role: RoleType) => void;
};

export default function UserRoleToggle({ value, onChange }: UserRoleToggleProps) {
  const { palette } = useTheme();
  const { isDark } = useCurrentTheme();

  return (
    <ToggleButtonGroup
      exclusive
      value={value}
      onChange={(_, next) => {
        if (next) onChange(next);
      }}
      aria-label="User role"
      size="small"
      sx={{
        p: 0.25,
        borderRadius: "8px",
        backgroundColor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
        border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
        "& .MuiToggleButtonGroup-grouped": {
          border: "none",
          borderRadius: "6px !important",
          px: 1.25,
          py: 0.25,
          fontSize: "0.8125rem",
          textTransform: "capitalize",
          fontWeight: 600,
          letterSpacing: "0.02em",
          color: palette.text.secondary,
          "&.Mui-selected": {
            backgroundColor: alpha(palette.primary.main, 0.16),
            color: palette.primary.main,
            "&:hover": {
              backgroundColor: alpha(palette.primary.main, 0.24)
            }
          }
        }
      }}
    >
      {ROLE_TYPES.filter((role) => role !== "owner").map((role) => (
        <ToggleButton key={role} value={role}>
          {role}
        </ToggleButton>
      ))}
    </ToggleButtonGroup>
  );
}
