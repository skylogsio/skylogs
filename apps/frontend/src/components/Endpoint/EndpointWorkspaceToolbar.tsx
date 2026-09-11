"use client";

import { Stack } from "@mui/material";

import type { SmartTableToolbarSlots } from "@/components/Table/SmartTable/types";

import EndpointWorkspaceSwitch from "./EndpointWorkspaceSwitch";

type EndpointWorkspaceToolbarProps = {
  slots: SmartTableToolbarSlots;
  value: "endpoints" | "flows";
  onChange: (value: "endpoints" | "flows") => void;
  labels: Record<"endpoints" | "flows", string>;
};

export default function EndpointWorkspaceToolbar({ slots, value, onChange, labels }: EndpointWorkspaceToolbarProps) {
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
      <EndpointWorkspaceSwitch value={value} onChange={onChange} labels={labels} />
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
