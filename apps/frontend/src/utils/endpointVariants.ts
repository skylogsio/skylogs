import { BsChatDotsFill, BsMicrosoftTeams, BsTelegram, BsTelephoneFill } from "react-icons/bs";
import { MdEmail } from "react-icons/md";
import { SiDiscord, SiMattermost } from "react-icons/si";
import { TiFlowChildren } from "react-icons/ti";

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

export const ENDPOINT_CONFIG: Record<EndpointType, EndpointTypeConfig> = {
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

export function getEndpointConfig(type: EndpointType): EndpointTypeConfig {
  return ENDPOINT_CONFIG[type];
}
