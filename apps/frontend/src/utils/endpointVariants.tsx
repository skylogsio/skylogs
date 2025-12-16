import { alpha, Chip } from "@mui/material";
import { BsChatDotsFill, BsMicrosoftTeams, BsTelegram, BsTelephoneFill } from "react-icons/bs";
import { MdEmail } from "react-icons/md";
import { SiDiscord, SiMattermost } from "react-icons/si";
import { TiFlowChildren } from "react-icons/ti";

import { ENDPOINT_COLORS } from "@/provider/MuiProvider";

export type EndpointType =
  | "sms"
  | "telegram"
  | "teams"
  | "call"
  | "email"
  | "flow"
  | "discord"
  | "matter-most";

interface EndpointTypeConfig {
  title: string;
  icon: React.ComponentType<{ style?: React.CSSProperties; color?: string }>;
}

const ENDPOINT_CONFIG: Record<EndpointType, EndpointTypeConfig> = {
  sms: {
    title: "SMS",
    icon: BsChatDotsFill
  },
  telegram: {
    title: "Telegram",
    icon: BsTelegram
  },
  teams: {
    title: "Teams",
    icon: BsMicrosoftTeams
  },
  call: {
    title: "Call",
    icon: BsTelephoneFill
  },
  email: {
    title: "Email",
    icon: MdEmail
  },
  flow: {
    title: "Flow",
    icon: TiFlowChildren
  },
  discord: {
    title: "Discord",
    icon: SiDiscord
  },
  "matter-most": {
    title: "Matter Most",
    icon: SiMattermost
  }
};

export function RenderEndPointChip({
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

export function renderEndPointChip(type: unknown, size: "small" | "medium" = "medium") {
  return <RenderEndPointChip type={type} size={size} />;
}

export function getEndpointConfig(type: EndpointType): EndpointTypeConfig {
  return ENDPOINT_CONFIG[type];
}
