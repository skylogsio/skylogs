import { Chip, alpha } from "@mui/material";

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
      sx={{
        backgroundColor: alpha(color, 0.1),
        color
      }}
      label={config.title}
    />
  );
}
