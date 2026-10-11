"use client";

import { Chip } from "@mui/material";
import { mergeSx, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";


import type { IncidentStatus } from "../incident.type";
import { INCIDENT_STATUS_COLORS, INCIDENT_STATUS_LABELS } from "../incident.utils";

export default function IncidentStatusChip({ status }: { status: IncidentStatus }) {

  return (
    <Chip
      label={INCIDENT_STATUS_LABELS[status]}
      size="small"
      sx={mergeSx({
        height: 28,
        fontWeight: 600,
        letterSpacing: "0.02em",
        color: INCIDENT_STATUS_COLORS[status],
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }, pillSurfaceSx())}
    />
  );
}
