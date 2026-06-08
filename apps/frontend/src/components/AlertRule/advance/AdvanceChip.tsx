import React from "react";

import { Chip, ChipProps, useColorScheme } from "@mui/material";
import { grey } from "@mui/material/colors";
import { alpha, styled } from "@mui/system";

import { AdvanceType } from "./AdvanceUtils";

type ChipType = "all" | AdvanceType;

const chipColor = (theme: "light" | "dark"): Record<ChipType, string> => ({
  all: theme === "light" ? grey[600] : grey[300],
  template: "#13C82B",
  notification: "#4880FF",
  silent: "#F28D22"
});

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

interface AdvanceChipProps extends Omit<ChipProps, "label"> {
  label: ChipType;
  active?: boolean;
  onClick?: () => void;
}

const AdvanceChip: React.FC<AdvanceChipProps> = ({ label, active = false, onClick, ...props }) => {
  const { systemMode, mode } = useColorScheme();
  const theme = (mode === "system" ? systemMode : mode) ?? "light";
  const color = chipColor(theme)[label];

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

export default AdvanceChip;
