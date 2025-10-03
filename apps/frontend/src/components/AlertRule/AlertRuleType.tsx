import { Stack, Typography, type TypographyProps } from "@mui/material";

import { ALERT_RULE_VARIANTS, type AlertRuleType } from "@/utils/alertRuleUtils";

interface IAlertRuleTypeProps {
  type: AlertRuleType;
  typographyProps?: TypographyProps;
  iconSize?: string | number;
  iconColor?: string;
}

export default function AlertRuleType({
  type,
  typographyProps,
  iconColor,
  iconSize
}: IAlertRuleTypeProps) {
  const alertRuleType = ALERT_RULE_VARIANTS[type];

  if (!alertRuleType) return;

  return (
    <Stack direction="row" spacing={1} alignItems="center" justifyContent="center">
      <alertRuleType.Icon
        size={iconSize ?? alertRuleType.defaultSize}
        color={iconColor ?? alertRuleType.defaultColor}
      />
      <Typography component="div" textTransform="capitalize" {...typographyProps}>
        {alertRuleType.label}
      </Typography>
    </Stack>
  );
}
