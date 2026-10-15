import { Box, Typography, Stack } from "@mui/material";
import { AiFillNotification } from "react-icons/ai";
import { HiOutlineCollection } from "react-icons/hi";
import { IoNotificationsOff } from "react-icons/io5";
import { MdSms } from "react-icons/md";

import type { BehaviorRuleFilterType } from "./BehaviorRuleType";
import { BEHAVIOR_RULE_FILTER_COLORS } from "./BehaviorRuleUtils";

interface EmptyBehaviorRuleStateProps {
  filter: BehaviorRuleFilterType;
  showNotification?: boolean;
  showTemplate?: boolean;
}

const EmptyBehaviorRuleState: React.FC<EmptyBehaviorRuleStateProps> = ({
  filter,
  showNotification,
  showTemplate
}) => {
  const getEmptyContent = () => {
    switch (filter) {
      case "template":
        return {
          title: "No Templates Yet",
          description: "Create your first template to customize alert messages and formatting.",
          icon: <MdSms size={40} />,
          color: BEHAVIOR_RULE_FILTER_COLORS.template
        };
      case "notification":
        return {
          title: "No Notification Rules Yet",
          description: "Set up notification rules to control when and how alerts are sent.",
          icon: <AiFillNotification size={40} />,
          color: BEHAVIOR_RULE_FILTER_COLORS.notification
        };
      case "silent":
        return {
          title: "No Silent Rules Yet",
          description:
            "Create silent rules to suppress notifications based on specific conditions.",
          icon: <IoNotificationsOff size={40} />,
          color: BEHAVIOR_RULE_FILTER_COLORS.silent
        };
      default:
        return {
          title: "No Behavior Rules Yet",
          description: `Get started by creating ${showTemplate ? "Templates," : ""} ${showNotification ? "Notification Rule," : ""} Silent Rules.`,
          icon: <HiOutlineCollection size={40} />,
          color: BEHAVIOR_RULE_FILTER_COLORS.all
        };
    }
  };

  const { title, description, icon, color } = getEmptyContent();

  return (
    <Box
      sx={{
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        py: 8,
        px: 3,
        backgroundColor:
          "color-mix(in srgb, var(--mui-palette-divider) 3%, transparent)",
        borderRadius: 3,
        border:
          "1px dashed color-mix(in srgb, var(--mui-palette-divider) 30%, transparent)"
      }}
    >
      <Box
        sx={{
          width: 80,
          height: 80,
          borderRadius: "50%",
          backgroundColor: `color-mix(in srgb, ${color} 10%, transparent)`,
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          mb: 3
        }}
      >
        <Box sx={{ color: `color-mix(in srgb, ${color} 70%, transparent)`, display: "flex" }}>
          {icon}
        </Box>
      </Box>
      <Stack spacing={1} sx={{ alignItems: "center", maxWidth: 370 }}>
        <Typography
          variant="h6"
          sx={{
            fontWeight: 600,
            color: "text.primary",
            textAlign: "center"
          }}
        >
          {title}
        </Typography>
        <Typography
          variant="body2"
          sx={{
            color: "text.secondary",
            textAlign: "center",
            lineHeight: 1.6
          }}
        >
          {description}
        </Typography>
      </Stack>
    </Box>
  );
};

export default EmptyBehaviorRuleState;
