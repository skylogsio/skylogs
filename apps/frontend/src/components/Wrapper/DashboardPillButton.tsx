"use client";

import { type ReactNode } from "react";

import { Box, Button, Typography, useTheme } from "@mui/material";
import { IoChevronDown } from "react-icons/io5";

import { useCurrentTheme } from "@/hooks";

import { getDashboardCaptionSx, getDashboardPillSx } from "./topBarStyles";

type DashboardPillButtonProps = {
  label: ReactNode;
  value: ReactNode;
  onClick: (event: React.MouseEvent<HTMLButtonElement>) => void;
  ariaLabel: string;
  open?: boolean;
  disabled?: boolean;
  minWidth?: number;
  startIcon?: ReactNode;
  /** Name on top, role/subtitle on bottom — for the account pill */
  profileLayout?: boolean;
};

export default function DashboardPillButton({
  label,
  value,
  onClick,
  ariaLabel,
  open = false,
  disabled = false,
  minWidth,
  startIcon,
  profileLayout = false
}: DashboardPillButtonProps) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();

  return (
    <Button
      onClick={onClick}
      disabled={disabled}
      aria-haspopup="menu"
      aria-expanded={open}
      aria-label={ariaLabel}
      disableRipple
      variant="text"
      startIcon={startIcon}
      endIcon={
        <Box
          component="span"
          sx={{
            display: "flex",
            lineHeight: 0,
            transform: open ? "rotate(180deg)" : "rotate(0deg)",
            transition: "transform 200ms ease"
          }}
        >
          <IoChevronDown size={14} />
        </Box>
      }
      sx={getDashboardPillSx(theme, isDark, { minWidth })}
    >
      <Box sx={{ textAlign: "start", lineHeight: 1.15, minWidth: 0 }}>
        {profileLayout ? (
          <>
            <Typography
              component="span"
              variant="body2"
              sx={{
                display: "block",
                fontWeight: 600,
                fontSize: "0.8125rem",
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis"
              }}
            >
              {label}
            </Typography>
            <Typography
              component="span"
              variant="caption"
              sx={{
                display: "block",
                color: theme.palette.text.secondary,
                fontSize: "0.65rem",
                letterSpacing: "0.02em",
                textTransform: "capitalize",
                lineHeight: 1.1,
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis"
              }}
            >
              {value}
            </Typography>
          </>
        ) : (
          <>
            <Typography component="span" variant="caption" sx={getDashboardCaptionSx(theme)}>
              {label}
            </Typography>
            <Typography
              component="span"
              variant="body2"
              sx={{
                display: "block",
                fontWeight: 600,
                fontSize: "0.8125rem",
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis"
              }}
            >
              {value}
            </Typography>
          </>
        )}
      </Box>
    </Button>
  );
}
