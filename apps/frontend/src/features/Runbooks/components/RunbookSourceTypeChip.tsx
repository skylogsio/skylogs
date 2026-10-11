"use client";

import { Chip } from "@mui/material";
import { mergeSx, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";


import type { RunbookSourceType } from "../runbook.type";
import { RUNBOOK_SOURCE_TYPE_COLORS, RUNBOOK_SOURCE_TYPE_LABELS } from "../runbook.utils";

export default function RunbookSourceTypeChip({ sourceType }: { sourceType: RunbookSourceType }) {

  return (
    <Chip
      label={RUNBOOK_SOURCE_TYPE_LABELS[sourceType]}
      size="small"
      sx={mergeSx({
        height: 28,
        fontWeight: 600,
        letterSpacing: "0.02em",
        color: RUNBOOK_SOURCE_TYPE_COLORS[sourceType],
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }, pillSurfaceSx())}
    />
  );
}
