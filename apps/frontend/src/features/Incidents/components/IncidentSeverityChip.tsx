"use client";

import { Chip } from "@mui/material";
import { mergeSx, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";


import type { IncidentSeverity } from "../incident.type";
import { INCIDENT_SEVERITY_COLORS } from "../incident.utils";

export default function IncidentSeverityChip({ severity }: { severity: IncidentSeverity }) {

  return (
    <Chip
      label={severity}
      size="small"
      sx={mergeSx({
        height: 28,
        fontWeight: 700,
        letterSpacing: "0.04em",
        color: INCIDENT_SEVERITY_COLORS[severity],
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }, pillSurfaceSx())}
    />
  );
}
