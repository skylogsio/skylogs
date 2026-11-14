"use client";

import { Chip } from "@mui/material";
import { mergeSx, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";


import type { RunbookStatus } from "../runbook.type";
import { RUNBOOK_STATUS_COLORS, RUNBOOK_STATUS_LABELS } from "../runbook.utils";

export default function RunbookStatusChip({ status }: { status: RunbookStatus }) {

  return (
    <Chip
      label={RUNBOOK_STATUS_LABELS[status]}
      size="small"
      sx={mergeSx({
        height: 28,
        fontWeight: 600,
        letterSpacing: "0.02em",
        color: RUNBOOK_STATUS_COLORS[status],
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }, pillSurfaceSx())}
    />
  );
}
