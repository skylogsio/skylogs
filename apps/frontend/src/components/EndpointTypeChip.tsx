"use client";

import { Chip, alpha, useTheme } from "@mui/material";

import { useCurrentTheme } from "@/hooks";
import { ENDPOINT_COLORS } from "@/provider/MuiProvider";
import { ENDPOINT_CONFIG, EndpointType } from "@/utils/endpointVariants";

export default function EndPointTypeChip({
  type,
  size = "medium"
}: {
  type: unknown;
  size?: "small" | "medium";
}) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  const variant = type as EndpointType;
  const config = ENDPOINT_CONFIG[variant];
  const color = ENDPOINT_COLORS[variant];
  const IconComponent = config.icon;

  return (
    <Chip
      size={size}
      avatar={<IconComponent style={{ padding: "0.2rem" }} color={color} />}
      sx={{
        height: 28,
        fontWeight: 700,
        letterSpacing: "0.04em",
        color,
        backgroundColor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
        border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }}
      label={config.title}
    />
  );
}
