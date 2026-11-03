"use client";

import { Chip } from "@mui/material";
import { mergeSx, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";

import { ENDPOINT_COLORS } from "@/provider/MuiProvider";
import { ENDPOINT_CONFIG, EndpointType } from "@/utils/endpointVariants";

export default function EndPointTypeChip({
  type,
  size = "medium"
}: {
  type: unknown;
  size?: "small" | "medium";
}) {

  const variant = type as EndpointType;
  const config = ENDPOINT_CONFIG[variant];
  const color = ENDPOINT_COLORS[variant];
  const IconComponent = config.icon;

  return (
    <Chip
      size={size}
      avatar={<IconComponent style={{ padding: "0.2rem" }} color={color} />}
      sx={mergeSx({
        height: 28,
        fontWeight: 700,
        letterSpacing: "0.04em",
        color,
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }, pillSurfaceSx())}
      label={config.title}
    />
  );
}
