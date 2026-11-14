"use client";

import { Button, type ButtonProps } from "@mui/material";

import { getGradientCtaSx, mergeSx } from "@/components/Wrapper/topBarStyles";

type GradientSubmitButtonProps = Omit<ButtonProps, "variant" | "size"> & {
  compact?: boolean;
};

export default function GradientSubmitButton({
  children,
  compact = false,
  sx,
  ...props
}: GradientSubmitButtonProps) {
  return (
    <Button
      variant="contained"
      size={compact ? "medium" : "large"}
      loadingPosition="start"
      {...props}
      sx={mergeSx(getGradientCtaSx({ compact }), sx)}
    >
      {children}
    </Button>
  );
}
