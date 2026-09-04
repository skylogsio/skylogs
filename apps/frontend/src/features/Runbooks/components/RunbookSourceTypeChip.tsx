"use client";

import { alpha, Chip, useTheme } from "@mui/material";

import { useCurrentTheme } from "@/hooks";

import type { RunbookSourceType } from "../runbook.type";
import { RUNBOOK_SOURCE_TYPE_COLORS, RUNBOOK_SOURCE_TYPE_LABELS } from "../runbook.utils";

export default function RunbookSourceTypeChip({ sourceType }: { sourceType: RunbookSourceType }) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  return (
    <Chip
      label={RUNBOOK_SOURCE_TYPE_LABELS[sourceType]}
      size="small"
      sx={{
        height: 28,
        fontWeight: 600,
        letterSpacing: "0.02em",
        color: RUNBOOK_SOURCE_TYPE_COLORS[sourceType],
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
