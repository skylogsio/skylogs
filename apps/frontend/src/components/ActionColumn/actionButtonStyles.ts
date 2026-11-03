import type { SxProps, Theme } from "@mui/material";

import { mergeSx, mixPrimary, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";

export function actionButtonSx(
  color?: string | ((theme: Theme) => string),
  extraSx?: SxProps<Theme>
): SxProps<Theme> {
  return mergeSx(
    {
      width: 36,
      height: 36,
      borderRadius: "10px",
      ...(typeof color === "string" ? { color } : {}),
      transition: "background-color 200ms ease, border-color 200ms ease, transform 150ms ease",
      "&:active": {
        transform: "scale(0.94)"
      }
    },
    typeof color === "function" ? (theme: Theme) => ({ color: color(theme) }) : undefined,
    pillSurfaceSx(),
    { "&:hover": { backgroundColor: "#E8E0D4", borderColor: mixPrimary(28) } },
    (muiTheme) =>
      muiTheme.applyStyles("dark", {
        "&:hover": {
          backgroundColor: "rgba(255, 255, 255, 0.13)",
          borderColor: mixPrimary(22)
        }
      }),
    extraSx
  );
}

