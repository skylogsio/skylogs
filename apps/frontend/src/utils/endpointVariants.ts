import { type IconType } from "react-icons";
import { BsChatDotsFill, BsMicrosoftTeams, BsTelephoneFill } from "react-icons/bs";
import { MdEmail } from "react-icons/md";
import { RiTelegram2Fill } from "react-icons/ri";
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
  icon: IconType;
}

export const ENDPOINT_CONFIG: Record<EndpointType, EndpointTypeConfig> = {
  sms: {
    title: "SMS",
    icon: BsChatDotsFill
  },
  telegram: {
    title: "Telegram",
    icon: RiTelegram2Fill
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
