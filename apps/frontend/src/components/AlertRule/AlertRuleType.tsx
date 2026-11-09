import { Stack, type SxProps, type Theme, Typography, type TypographyProps } from "@mui/material";

import { ALERT_RULE_VARIANTS, type AlertRuleType } from "@/utils/alertRuleUtils";

interface IAlertRuleTypeProps {
  type: AlertRuleType;
  typographyProps?: TypographyProps;
  iconSize?: string | number;
  iconColor?: string;
  justifyContent?: "center" | "flex-start" | "flex-end";
  sx?: SxProps<Theme>;
}

export default function AlertRuleType({
  type,
  typographyProps,
  iconColor,
  iconSize,
  justifyContent = "center",
  sx: extraSx
}: IAlertRuleTypeProps) {
  const alertRuleType = ALERT_RULE_VARIANTS[type];
  const { sx, ...props } = typographyProps ?? { sx: {} };

  if (!alertRuleType) return null;

  return (
    <Stack
      direction="row"
      spacing={1}
      sx={{
        alignItems: "center",
        justifyContent,
        ...extraSx
      }}
    >
      <alertRuleType.Icon
        size={iconSize ?? alertRuleType.defaultSize}
        color={iconColor ?? alertRuleType.defaultColor}
      />
      <Typography component="div" {...props} sx={{ textTransform: "capitalize", ...sx }}>
        {alertRuleType.label}
      </Typography>
    </Stack>
  );
}
