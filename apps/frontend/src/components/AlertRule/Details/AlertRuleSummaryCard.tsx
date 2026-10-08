import { useRouter } from "next/navigation";

import { IconButton, Stack, Typography, alpha, useTheme } from "@mui/material";
import { IoIosArrowBack } from "react-icons/io";

import type { IAlertRule } from "@/@types/alertRule";
import AlertRuleAccessBadge from "@/components/AlertRule/AlertRuleAccessBadge";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import { ALERT_RULE_VARIANTS } from "@/utils/alertRuleUtils";

interface AlertRuleSummaryCardProps {
  alert: IAlertRule;
  isReadonly: boolean;
  alertId: string;
  onAfterResolve: () => void;
}

export default function AlertRuleSummaryCard({
  alert,
  isReadonly,
  alertId,
  onAfterResolve
}: AlertRuleSummaryCardProps) {
  const { Icon, defaultColor } = ALERT_RULE_VARIANTS[alert.type];
  const router = useRouter();
  const { palette } = useTheme();

  return (
    <Stack direction="row" spacing={2} sx={{ alignItems: "center" }}>
      <IconButton
        onClick={() => router.back()}
        aria-label="Back"
        sx={{
          color: palette.text.secondary,
          backgroundColor: alpha(palette.text.secondary, 0.06),
          "&:hover": {
            backgroundColor: alpha(palette.text.secondary, 0.12)
          }
        }}
      >
        <IoIosArrowBack size="1.25rem" />
      </IconButton>
      <Icon color={defaultColor} size="2rem" />
      <Stack
        direction="row"
        spacing={1}
        sx={{ alignItems: "center", flexWrap: "wrap", rowGap: 0.5 }}
      >
        <Typography variant="h6" sx={{ fontWeight: "bold" }}>
          {alert.name} <AlertRuleAccessBadge accessLevel={alert.accessLevel} />
        </Typography>
        <AlertRuleStatusIndicator
          status={alert.status_label}
          id={isReadonly ? undefined : alertId}
          onAfterResolve={onAfterResolve}
          showAcknowledge={!isReadonly && !alert.acknowledgedBy}
        />
      </Stack>
    </Stack>
  );
}
