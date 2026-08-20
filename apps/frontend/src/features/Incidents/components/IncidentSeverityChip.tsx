"use client";

import { alpha, Chip, useTheme } from "@mui/material";

import { useCurrentTheme } from "@/hooks";

import type { IncidentSeverity } from "../incident.type";
import { INCIDENT_SEVERITY_COLORS } from "../incident.utils";

export default function IncidentSeverityChip({ severity }: { severity: IncidentSeverity }) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  return (
    <Chip
      label={severity}
      size="small"
      sx={{
        height: 28,
        fontWeight: 700,
        letterSpacing: "0.04em",
        color: INCIDENT_SEVERITY_COLORS[severity],
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
