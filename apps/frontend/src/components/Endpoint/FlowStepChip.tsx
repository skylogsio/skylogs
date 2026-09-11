"use client";

import { Chip, alpha, useTheme } from "@mui/material";
import { AiFillApi, AiFillClockCircle } from "react-icons/ai";

import { useCurrentTheme } from "@/hooks";

type FlowStepChipProps = {
  type: "wait" | "endpoint";
  duration?: number;
  timeUnit?: "s" | "m" | "h";
};

export default function FlowStepChip({ type, duration, timeUnit }: FlowStepChipProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  const isWait = type === "wait";
  const iconColor = isWait ? palette.warning.main : palette.primary.main;
  const Icon = isWait ? AiFillClockCircle : AiFillApi;

  const label = isWait ? `${duration ?? 0}${timeUnit ?? "s"}` : "Endpoint";

  return (
    <Chip
      size="small"
      icon={<Icon size={14} color={iconColor} />}
      label={label}
      sx={{
        height: 28,
        fontWeight: 700,
        letterSpacing: "0.04em",
        color: palette.text.primary,
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
