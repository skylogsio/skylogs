import { alpha, Chip } from "@mui/material";
import { BsChatDotsFill, BsMicrosoftTeams, BsTelegram, BsTelephoneFill } from "react-icons/bs";
import { MdEmail } from "react-icons/md";
import { SiMattermost } from "react-icons/si";

export const ENDPOINT_TYPE_CHIP = {
  sms: {
    title: "SMS",
    color: "#13C82B",
    icon: <BsChatDotsFill style={{ padding: "0.2rem" }} color="#13C82B" />
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
    color: "#f6a645",
    icon: <MdEmail style={{ padding: "0.2rem" }} color="#f6a645" />
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
        backgroundColor: alpha(color, 0.1),
        color
      }}
      label={ENDPOINT_TYPE_CHIP[variant].title}
    />
  );
}
