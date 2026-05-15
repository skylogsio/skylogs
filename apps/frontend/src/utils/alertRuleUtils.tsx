import { green, lightBlue, yellow } from "@mui/material/colors";
import type { IconType } from "react-icons";
import { BsShieldFillCheck } from "react-icons/bs";
import { FaBell, FaCloud } from "react-icons/fa";

import { DATA_SOURCE_VARIANTS, type DataSourceType } from "@/utils/dataSourceUtils";

export type AlertRuleType = DataSourceType | "api" | "health" | "notification";

export const ALERT_RULE_VARIANTS: Record<
  AlertRuleType,
  {
    label: string;
    Icon: IconType;
    defaultColor: string;
    defaultSize: string;
  }
> = {
  api: {
    label: "Api",
    defaultColor: lightBlue[500],
    defaultSize: "1.2rem",
    Icon: FaCloud
  },
  notification: {
    label: "Notification",
    Icon: FaBell,
    defaultColor: yellow[600],
    defaultSize: "1.2rem"
  },
  health: {
    label: "Health",
    defaultColor: green[400],
    defaultSize: "1.2rem",
    Icon: BsShieldFillCheck
  },
  ...DATA_SOURCE_VARIANTS
};
