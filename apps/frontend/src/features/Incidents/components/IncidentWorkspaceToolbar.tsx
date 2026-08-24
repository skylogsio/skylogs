"use client";

import { Stack } from "@mui/material";

import type { SmartTableToolbarSlots } from "@/components/Table/SmartTable/types";

import IncidentWorkspaceSwitch from "./IncidentWorkspaceSwitch";

type IncidentWorkspaceToolbarProps = {
  slots: SmartTableToolbarSlots;
};

export default function IncidentWorkspaceToolbar({ slots }: IncidentWorkspaceToolbarProps) {
  return (
    <Stack
      direction={{ xs: "column", sm: "row" }}
      spacing={1.5}
      sx={{
        alignItems: { xs: "stretch", sm: "center" },
        justifyContent: "space-between",
        width: 1
      }}
    >
      <IncidentWorkspaceSwitch />
      <Stack
        direction="row"
        spacing={0.75}
        useFlexGap
        sx={{
          alignItems: "center",
          flexWrap: "wrap",
          justifyContent: { xs: "flex-start", sm: "flex-end" }
        }}
      >
        {slots.actions}
      </Stack>
    </Stack>
  );
}
