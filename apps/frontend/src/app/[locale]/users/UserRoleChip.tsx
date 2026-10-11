"use client";

import { Chip } from "@mui/material";
import { mergeSx, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";

import { ROLE_COLORS, type RoleType } from "@/utils/userUtils";

export default function UserRoleChip({ role }: { role: RoleType }) {

  return (
    <Chip
      label={role}
      size="small"
      sx={mergeSx({
        height: 28,
        textTransform: "capitalize",
        fontWeight: 600,
        letterSpacing: "0.02em",
        color: ROLE_COLORS[role],
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }, pillSurfaceSx())}
    />
  );
}
