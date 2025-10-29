import { alpha, Chip } from "@mui/material";
import { BsChatDotsFill, BsMicrosoftTeams, BsTelegram, BsTelephoneFill } from "react-icons/bs";
import { MdEmail } from "react-icons/md";
import { SiDiscord, SiMattermost } from "react-icons/si";
import { TiFlowChildren } from "react-icons/ti";

export const ENDPOINT_TYPE_CHIP = {
  sms: {
    title: "SMS",
    color: "#11AC26",
    icon: <BsChatDotsFill style={{ padding: "0.2rem" }} color="#11AC26" />
  },
  telegram: {
    title: "Telegram",
    color: "#4880FF",
    icon: <BsTelegram style={{ padding: "0.2rem" }} color="#4880FF" />
  },
  teams: {
    title: "Teams",
    color: "#454DB3",
    icon: <BsMicrosoftTeams style={{ padding: "0.2rem" }} color="#454DB3" />
  },
  call: {
    title: "Call",
    color: "#B65DFE",
    icon: <BsTelephoneFill style={{ padding: "0.2rem" }} color="#B65DFE" />
  },
  email: {
    title: "Email",
    color: "#C54040",
    icon: <MdEmail style={{ padding: "0.2rem" }} color="#C54040" />
  },
  flow: {
    title: "Flow",
    color: "#FF00FF",
    icon: <TiFlowChildren style={{ padding: "0.2rem" }} color="#FF00FF" />
  },
  discord: {
    title: "Discord",
    color: "#5865F2",
    icon: <SiDiscord style={{ padding: "0.2rem" }} color="#5865F2" />
  },
  "matter-most": {
    title: "Matter Most",
    color: "#284077",
    icon: <SiMattermost style={{ padding: "0.2rem" }} color="#284077" />
  }
};

export function renderEndPointChip(type: unknown, size: "small" | "medium" = "medium") {
  const variant = type as keyof typeof ENDPOINT_TYPE_CHIP;
  const Avatar = ENDPOINT_TYPE_CHIP[variant].icon;
  const color = ENDPOINT_TYPE_CHIP[variant].color;
  return (
    <Chip
      size={size}
      avatar={Avatar}
      sx={{
        backgroundColor: alpha(color, 0.07),
        color
      }}
      label={ENDPOINT_TYPE_CHIP[variant].title}
    />
  );
}
