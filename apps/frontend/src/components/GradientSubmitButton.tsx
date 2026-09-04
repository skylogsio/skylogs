"use client";

import { Button, type ButtonProps, useTheme } from "@mui/material";

import { getGradientCtaSx } from "@/components/Wrapper/topBarStyles";

type GradientSubmitButtonProps = Omit<ButtonProps, "variant" | "size"> & {
  compact?: boolean;
};

export default function GradientSubmitButton({
  children,
  compact = false,
  sx,
  ...props
}: GradientSubmitButtonProps) {
  const theme = useTheme();

  return (
    <Button
      variant="contained"
      size={compact ? "medium" : "large"}
      loadingPosition="start"
      {...props}
      sx={{
        ...getGradientCtaSx(theme, { compact }),
        ...(typeof sx === "object" && sx !== null && !Array.isArray(sx) ? sx : {})
      }}
    >
      {children}
    </Button>
  );
}
