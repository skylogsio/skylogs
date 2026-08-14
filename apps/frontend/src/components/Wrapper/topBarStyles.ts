import { alpha, type Theme } from "@mui/material";

/** Filled pill control — matches sign-in input surfaces, no glass blur/shadow. */
export function getDashboardPillSx(theme: Theme, isDark: boolean, options?: { minWidth?: number }) {
  const { palette } = theme;
  const backgroundColor = isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1";
  const hoverBackgroundColor = isDark ? "rgba(255, 255, 255, 0.13)" : "#E8E0D4";

  return {
    textTransform: "none" as const,
    px: 1.5,
    py: 0.75,
    minHeight: 40,
    borderRadius: "10px !important",
    minWidth: options?.minWidth ?? 118,
    justifyContent: "space-between",
    color: palette.text.primary,
    backgroundColor: `${backgroundColor} !important`,
    border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
    boxShadow: "none !important",
    transition: "background-color 200ms ease, border-color 200ms ease",
    "&:hover": {
      backgroundColor: `${hoverBackgroundColor} !important`,
      boxShadow: "none !important",
      borderColor: alpha(palette.primary.main, isDark ? 0.22 : 0.28)
    },
    "& .MuiButton-startIcon": {
      color: palette.primary.main,
      marginInlineEnd: 0.75
    },
    "& .MuiButton-endIcon": {
      color: palette.text.secondary,
      marginInlineStart: 0.75
    }
  };
}

export function getDashboardMenuPaperSx(theme: Theme, isDark: boolean) {
  const { palette } = theme;

  return {
    mt: 1,
    minWidth: 220,
    borderRadius: 3,
    border: `1px solid ${palette.divider}`,
    backgroundColor: palette.background.paper,
    boxShadow: `0 12px 32px ${alpha("#0E0D0C", isDark ? 0.45 : 0.12)}`
  };
}

/** Profile menu — warm filled surface in light mode to match dashboard pills. */
export function getProfileMenuPaperSx(theme: Theme, isDark: boolean) {
  const { palette } = theme;

  if (isDark) {
    return {
      ...getDashboardMenuPaperSx(theme, isDark),
      backgroundColor: alpha(palette.background.paper, 0.96),
      backdropFilter: "blur(12px)"
    };
  }

  return {
    mt: 1,
    minWidth: 220,
    borderRadius: 2,
    border: `1px solid ${alpha(palette.primary.main, 0.2)}`,
    backgroundColor: "#F8F4ED",
    backdropFilter: "blur(12px)",
    boxShadow: `0 10px 28px ${alpha("#0E0D0C", 0.1)}`
  };
}

export function getDashboardCaptionSx(theme: Theme) {
  return {
    display: "block" as const,
    color: theme.palette.text.secondary,
    fontSize: "0.65rem",
    letterSpacing: "0.04em",
    textTransform: "uppercase" as const,
    lineHeight: 1.1
  };
}

export function getAppBackgroundSx(theme: Theme, isDark: boolean) {
  const { palette } = theme;

  return {
    backgroundColor: palette.background.default,
    backgroundImage: isDark
      ? `radial-gradient(ellipse 80% 60% at 20% 10%, ${alpha(palette.primary.main, 0.16)}, transparent 55%),
         radial-gradient(ellipse 70% 50% at 90% 80%, ${alpha("#3A2E22", 0.45)}, transparent 50%)`
      : `radial-gradient(ellipse 80% 55% at 15% 0%, ${alpha(palette.secondary.light, 0.95)}, transparent 50%),
         radial-gradient(ellipse 70% 50% at 100% 100%, ${alpha(palette.primary.main, 0.22)}, transparent 55%)`
  };
}
