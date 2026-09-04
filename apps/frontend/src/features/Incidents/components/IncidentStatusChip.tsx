"use client";

import { alpha, Chip, useTheme } from "@mui/material";

import { useCurrentTheme } from "@/hooks";

import type { IncidentStatus } from "../incident.type";
import { INCIDENT_STATUS_COLORS, INCIDENT_STATUS_LABELS } from "../incident.utils";

export default function IncidentStatusChip({ status }: { status: IncidentStatus }) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  return (
    <Chip
      label={INCIDENT_STATUS_LABELS[status]}
      size="small"
      sx={{
        height: 28,
        fontWeight: 600,
        letterSpacing: "0.02em",
        color: INCIDENT_STATUS_COLORS[status],
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
