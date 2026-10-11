import type { SxProps, Theme } from "@mui/material";
import type { SystemStyleObject } from "@mui/system";

type SxItem = SystemStyleObject<Theme> | ((theme: Theme) => SystemStyleObject<Theme>);

export function mergeSx(...parts: Array<SxProps<Theme> | undefined>): SxProps<Theme> {
  const out: SxItem[] = [];
  for (const part of parts) {
    if (!part) continue;
    if (Array.isArray(part)) {
      for (const item of part) {
        if (typeof item === "boolean" || item == null) continue;
        out.push(item as SxItem);
      }
    } else {
      out.push(part as SxItem);
    }
  }
  return out;
}

function mix(varName: string, percent: number): string {
  return `color-mix(in srgb, var(${varName}) ${percent}%, transparent)`;
}

function mixHex(hex: string, percent: number): string {
  return `color-mix(in srgb, ${hex} ${percent}%, transparent)`;
}

const PAPER = "--mui-palette-background-paper";
const PRIMARY_MAIN = "--mui-palette-primary-main";
const PRIMARY_LIGHT = "--mui-palette-primary-light";
const PRIMARY_DARK = "--mui-palette-primary-dark";
const SECONDARY_LIGHT = "--mui-palette-secondary-light";
const SECONDARY_MAIN = "--mui-palette-secondary-main";
const COMMON_BLACK = "--mui-palette-common-black";

export function getDashboardPillSx(options?: { minWidth?: number }): SxProps<Theme> {
  return [
    {
      textTransform: "none",
      px: 1.5,
      py: 0.75,
      minHeight: 40,
      borderRadius: "10px !important",
      minWidth: options?.minWidth ?? 118,
      justifyContent: "space-between",
      color: "text.primary",
      backgroundColor: "#F1EBE1 !important",
      border: `1px solid ${mix(PRIMARY_MAIN, 18)}`,
      boxShadow: "none !important",
      transition: "background-color 200ms ease, border-color 200ms ease",
      "&:hover": {
        backgroundColor: "#E8E0D4 !important",
        boxShadow: "none !important",
        borderColor: mix(PRIMARY_MAIN, 28)
      },
      "& .MuiButton-startIcon": {
        color: "primary.main",
        marginInlineEnd: 0.75
      },
      "& .MuiButton-endIcon": {
        color: "text.secondary",
        marginInlineStart: 0.75
      }
    },
    (muiTheme) =>
      muiTheme.applyStyles("dark", {
        backgroundColor: "rgba(255, 255, 255, 0.09) !important",
        border: `1px solid ${mix(PRIMARY_MAIN, 14)}`,
        "&:hover": {
          backgroundColor: "rgba(255, 255, 255, 0.13) !important",
          borderColor: mix(PRIMARY_MAIN, 22)
        }
      })
  ];
}

export function getDashboardMenuPaperSx(): SxProps<Theme> {
  return [
    {
      mt: 1,
      minWidth: 220,
      borderRadius: 3,
      border: "1px solid var(--mui-palette-divider)",
      backgroundColor: "background.paper",
      boxShadow: `0 12px 32px ${mix(COMMON_BLACK, 12)}`
    },
    (muiTheme) =>
      muiTheme.applyStyles("dark", {
        boxShadow: `0 12px 32px ${mix(COMMON_BLACK, 45)}`
      })
  ];
}

export function getProfileMenuPaperSx(): SxProps<Theme> {
  return [
    {
      mt: 1,
      minWidth: 220,
      borderRadius: 2,
      border: `1px solid ${mix(PRIMARY_MAIN, 20)}`,
      backgroundColor: "#F8F4ED",
      backdropFilter: "blur(12px)",
      boxShadow: `0 10px 28px ${mix(COMMON_BLACK, 10)}`
    },
    (muiTheme) =>
      muiTheme.applyStyles("dark", {
        borderRadius: 3,
        border: "1px solid var(--mui-palette-divider)",
        backgroundColor: mix(PAPER, 96),
        backdropFilter: "blur(12px)",
        boxShadow: `0 12px 32px ${mix(COMMON_BLACK, 45)}`
      })
  ];
}

export function getDashboardCaptionSx(): SxProps<Theme> {
  return {
    display: "block",
    color: "text.secondary",
    fontSize: "0.65rem",
    letterSpacing: "0.04em",
    textTransform: "uppercase",
    lineHeight: 1.1
  };
}

export function getAppBackgroundSx(): SxProps<Theme> {
  return [
    {
      backgroundColor: "background.default",
      backgroundImage: `radial-gradient(ellipse 80% 55% at 15% 0%, ${mix(SECONDARY_LIGHT, 95)}, transparent 50%),
         radial-gradient(ellipse 70% 50% at 100% 100%, ${mix(PRIMARY_MAIN, 22)}, transparent 55%)`
    },
    (muiTheme) =>
      muiTheme.applyStyles("dark", {
        backgroundImage: `radial-gradient(ellipse 80% 60% at 20% 10%, ${mix(PRIMARY_MAIN, 16)}, transparent 55%),
         radial-gradient(ellipse 70% 50% at 90% 80%, ${mixHex("#3A2E22", 45)}, transparent 50%)`
      })
  ];
}

export function getPrimaryGradient(options?: { midStop?: number }): string {
  const midStop = options?.midStop ?? 48;

  return `linear-gradient(145deg, var(${PRIMARY_LIGHT}) 0%, var(${PRIMARY_MAIN}) ${midStop}%, var(${PRIMARY_DARK}) 100%)`;
}

export function getGlassCardSx(): SxProps<Theme> {
  return [
    {
      backgroundColor: mix(PAPER, 98),
      border: `1px solid ${mix(PRIMARY_MAIN, 40)}`,
      boxShadow: `0 28px 64px ${mix(COMMON_BLACK, 10)}`,
      borderRadius: 4,
      overflow: "hidden"
    },
    (muiTheme) =>
      muiTheme.applyStyles("dark", {
        backgroundColor: mix(PAPER, 94),
        border: `1px solid ${mix(PRIMARY_MAIN, 20)}`,
        boxShadow: `0 28px 64px ${mix(COMMON_BLACK, 50)}`
      })
  ];
}

export function getGradientCtaSx(options?: { compact?: boolean }): SxProps<Theme> {
  const gradient = `linear-gradient(135deg, var(${SECONDARY_MAIN}) 0%, var(${PRIMARY_MAIN}) 100%)`;
  const compact = options?.compact;

  return {
    position: "relative",
    py: compact ? 1 : 1.5,
    px: compact ? 2 : undefined,
    minHeight: compact ? 40 : 52,
    fontWeight: 600,
    letterSpacing: "0.04em",
    fontSize: compact ? "0.8125rem" : undefined,
    textTransform: "none",
    color: "primary.contrastText",
    background: gradient,
    transition: "filter 220ms ease, opacity 220ms ease",
    overflow: "hidden",
    "&.Mui-disabled": {
      color: "primary.contrastText",
      background: gradient,
      opacity: 0.92
    },
    "&:hover": {
      filter: "brightness(1.04)",
      background: gradient
    },
    "& .MuiButton-startIcon": {
      color: "primary.contrastText",
      marginInlineEnd: 0.5
    }
  };
}

export function pillSurfaceSx(): SxProps<Theme> {
  return [
    {
      backgroundColor: "#F1EBE1",
      border: `1px solid ${mix(PRIMARY_MAIN, 18)}`
    },
    (muiTheme) =>
      muiTheme.applyStyles("dark", {
        backgroundColor: "rgba(255, 255, 255, 0.09)",
        border: `1px solid ${mix(PRIMARY_MAIN, 14)}`
      })
  ];
}

export function mixPrimary(percent: number): string {
  return mix(PRIMARY_MAIN, percent);
}

export function pillSurfaceHoverSx(): SxProps<Theme> {
  return [
    {
      backgroundColor: "#E8E0D4",
      borderColor: mix(PRIMARY_MAIN, 28)
    },
    (muiTheme) =>
      muiTheme.applyStyles("dark", {
        backgroundColor: "rgba(255, 255, 255, 0.13)",
        borderColor: mix(PRIMARY_MAIN, 22)
      })
  ];
}
