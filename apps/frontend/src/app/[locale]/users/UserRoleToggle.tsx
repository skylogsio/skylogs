"use client";

import { ToggleButton, ToggleButtonGroup } from "@mui/material";

import { ROLE_TYPES, type RoleType } from "@/utils/userUtils";

type UserRoleToggleProps = {
  value: RoleType;
  onChange: (role: RoleType) => void;
};

export default function UserRoleToggle({ value, onChange }: UserRoleToggleProps) {

  return (
    <ToggleButtonGroup
      exclusive
      value={value}
      onChange={(_, next) => {
        if (next) onChange(next);
      }}
      aria-label="User role"
      size="small"
      sx={[
        {
          p: 0.25,
          borderRadius: "8px",
          backgroundColor: "#F1EBE1",
          border:
            "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 18%, transparent)",
          "& .MuiToggleButtonGroup-grouped": {
            border: "none",
            borderRadius: "6px !important",
            px: 1.25,
            py: 0.25,
            fontSize: "0.8125rem",
            textTransform: "capitalize",
            fontWeight: 600,
            letterSpacing: "0.02em",
            color: "text.secondary",
            "&.Mui-selected": {
              backgroundColor:
                "color-mix(in srgb, var(--mui-palette-primary-main) 16%, transparent)",
              color: "primary.main",
              "&:hover": {
                backgroundColor:
                  "color-mix(in srgb, var(--mui-palette-primary-main) 24%, transparent)"
              }
            }
          }
        },
        (muiTheme) =>
          muiTheme.applyStyles("dark", {
            backgroundColor: "rgba(255, 255, 255, 0.09)",
            border:
              "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 14%, transparent)"
          })
      ]}
    >
      {ROLE_TYPES.filter((role) => role !== "owner").map((role) => (
        <ToggleButton key={role} value={role}>
          {role}
        </ToggleButton>
      ))}
    </ToggleButtonGroup>
  );
}
