import { Chip } from "@mui/material";

import type { IAlertRule } from "@/@types/alertRule";

interface AlertRuleAccessBadgeProps {
  accessLevel: IAlertRule["accessLevel"];
}

export default function AlertRuleAccessBadge({ accessLevel }: AlertRuleAccessBadgeProps) {

  if (accessLevel !== "readonly") {
    return null;
  }

  return (
    <Chip
      size="small"
      label="Readonly"
      sx={{
        height: 22,
        fontSize: 11,
        fontWeight: 600,
        color: "text.secondary",
        backgroundColor:
          "color-mix(in srgb, var(--mui-palette-text-secondary) 8%, transparent)",
        "& .MuiChip-icon": {
          color: "text.secondary",
          marginLeft: 0.75
        }
      }}
    />
  );
}
