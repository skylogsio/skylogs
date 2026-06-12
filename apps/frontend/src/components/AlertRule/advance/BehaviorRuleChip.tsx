import React from "react";

import { Chip, ChipProps } from "@mui/material";
import { alpha, styled } from "@mui/system";

import type { BehaviorRuleFilterType } from "./BehaviorRuleType";
import { BEHAVIOR_RULE_FILTER_COLORS } from "./BehaviorRuleUtils";

interface StyledChipProps extends ChipProps {
  chipcolor: string;
  active: string;
}

const StyledChip = styled(Chip)<StyledChipProps>(({ chipcolor, active, size }) => ({
  backgroundColor: alpha(chipcolor, 0.1),
  fontSize: size === "small" ? 11 : 14,
  height: size === "small" ? 18 : 32,
  textTransform: "capitalize",
  color: chipcolor,
  "&:hover": {
    backgroundColor: alpha(chipcolor, 0.2)
  },
  borderColor: `${alpha(chipcolor, 0.7)}!important`,
  border: active === "true" ? "1px solid" : "none",
  fontWeight: 500
}));

interface BehaviorRuleChipProps extends Omit<ChipProps, "label"> {
  label: BehaviorRuleFilterType;
  active?: boolean;
  onClick?: () => void;
}

const BehaviorRuleChip: React.FC<BehaviorRuleChipProps> = ({
  label,
  active = false,
  onClick,
  ...props
}) => {
  const color = BEHAVIOR_RULE_FILTER_COLORS[label];

  return (
    <StyledChip
      label={label}
      chipcolor={color}
      active={String(active)}
      onClick={onClick}
      {...props}
    />
  );
};

export default BehaviorRuleChip;
