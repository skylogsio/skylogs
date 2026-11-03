"use client";

import { Chip } from "@mui/material";
import { mergeSx, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";
import { AiFillApi, AiFillClockCircle } from "react-icons/ai";


type FlowStepChipProps = {
  type: "wait" | "endpoint";
  duration?: number;
  timeUnit?: "s" | "m" | "h";
};

export default function FlowStepChip({ type, duration, timeUnit }: FlowStepChipProps) {

  const isWait = type === "wait";
  const iconColor = isWait ? "var(--mui-palette-warning-main)" : "var(--mui-palette-primary-main)";
  const Icon = isWait ? AiFillClockCircle : AiFillApi;

  const label = isWait ? `${duration ?? 0}${timeUnit ?? "s"}` : "Endpoint";

  return (
    <Chip
      size="small"
      icon={<Icon size={14} color={iconColor} />}
      label={label}
      sx={mergeSx({
        height: 28,
        fontWeight: 700,
        letterSpacing: "0.04em",
        color: "text.primary",
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }, pillSurfaceSx())}
    />
  );
}
