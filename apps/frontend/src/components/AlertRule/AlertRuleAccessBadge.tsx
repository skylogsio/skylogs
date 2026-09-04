import { alpha, Chip, useTheme } from "@mui/material";

import type { IAlertRule } from "@/@types/alertRule";

interface AlertRuleAccessBadgeProps {
  accessLevel: IAlertRule["accessLevel"];
}

export default function AlertRuleAccessBadge({ accessLevel }: AlertRuleAccessBadgeProps) {
  const { palette } = useTheme();

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
        color: palette.text.secondary,
        backgroundColor: alpha(palette.text.secondary, 0.08),
        "& .MuiChip-icon": {
          color: palette.text.secondary,
          marginLeft: 0.75
        }
      }}
    />
  );
}
