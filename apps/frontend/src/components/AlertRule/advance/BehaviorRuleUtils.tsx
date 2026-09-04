import { alpha } from "@mui/material";
import { AiFillNotification } from "react-icons/ai";
import { IoNotificationsOff } from "react-icons/io5";
import { MdSms } from "react-icons/md";

import type { BehaviorRuleType, BehaviorRuleFilterType } from "./BehaviorRuleType";

export const BEHAVIOR_RULE_TYPE_CONFIG: Record<
  BehaviorRuleType,
  { color: string; bgColor: string; icon: React.ReactNode; cardIconBg: string }
> = {
  template: {
    color: "#13C82B",
    bgColor: alpha("#13C82B", 0.12),
    icon: <MdSms size={28} />,
    cardIconBg: "#13C82B"
  },
  notification: {
    color: "#4880FF",
    bgColor: alpha("#4880FF", 0.12),
    icon: <AiFillNotification size={28} />,
    cardIconBg: "#4880FF"
  },
  silent: {
    color: "#F28D22",
    bgColor: alpha("#F28D22", 0.12),
    icon: <IoNotificationsOff size={28} />,
    cardIconBg: "#F28D22"
  }
};

export const BEHAVIOR_RULE_FILTER_COLORS: Record<BehaviorRuleFilterType, string> = {
  all: "#9E9E9E",
  template: "#13C82B",
  notification: "#4880FF",
  silent: "#F28D22"
};
