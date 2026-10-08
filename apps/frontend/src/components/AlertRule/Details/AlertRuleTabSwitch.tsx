"use client";

import { Box, alpha } from "@mui/material";
import { motion, useReducedMotion } from "framer-motion";
import { AiFillNotification } from "react-icons/ai";
import { FaUsers } from "react-icons/fa";
import { FaClockRotateLeft } from "react-icons/fa6";
import { HiCog, HiFire } from "react-icons/hi";
import { IoInformationCircle } from "react-icons/io5";

import { getPrimaryGradient } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";
import { useTheme } from "@mui/material";

export const ALERT_RULE_TABS = [
  "details",
  "fire",
  "users",
  "history",
  "notify",
  "advance"
] as const;
export type AlertRuleTabType = (typeof ALERT_RULE_TABS)[number];

const TAB_ICONS: Record<AlertRuleTabType, React.ReactElement> = {
  details: <IoInformationCircle size={20} />,
  fire: <HiFire size={20} />,
  users: <FaUsers size={20} />,
  history: <FaClockRotateLeft size={18} />,
  notify: <AiFillNotification size={20} />,
  advance: <HiCog size={20} />
};

interface AlertRuleTabSwitchProps {
  tabs: AlertRuleTabType[];
  activeTab: AlertRuleTabType;
  onChange: (tab: AlertRuleTabType) => void;
}

export default function AlertRuleTabSwitch({ tabs, activeTab, onChange }: AlertRuleTabSwitchProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();
  const reduceMotion = useReducedMotion();

  const gradient = getPrimaryGradient(theme);

  const thumbSx = {
    position: "absolute" as const,
    inset: 0,
    borderRadius: "9px",
    background: gradient,
    boxShadow: `0 -2px 8px ${alpha(palette.primary.main, isDark ? 0.35 : 0.8)}`,
    pointerEvents: "none" as const
  };

  return (
    <Box
      component="nav"
      aria-label="Alert rule tabs"
      sx={{
        display: "inline-flex",
        alignItems: "center",
        gap: 0.5,
        p: "3px",
        borderRadius: "11px",
        backgroundColor: isDark ? alpha("#fff", 0.06) : alpha(palette.primary.main, 0.1),
        border: `1px solid ${alpha(palette.primary.main, isDark ? 0.18 : 0.22)}`
      }}
    >
      {tabs.map((tab) => {
        const isActive = tab === activeTab;

        return (
          <Box
            key={tab}
            component="button"
            type="button"
            onClick={() => onChange(tab)}
            aria-current={isActive ? "page" : undefined}
            sx={{
              position: "relative",
              zIndex: 1,
              display: "inline-flex",
              alignItems: "center",
              justifyContent: "center",
              gap: 0.85,
              px: 2,
              minHeight: 38,
              borderRadius: "9px",
              border: "none",
              cursor: "pointer",
              background: "none",
              color: isActive ? palette.primary.contrastText : palette.text.secondary,
              fontSize: "0.875rem",
              fontWeight: 700,
              letterSpacing: "0.02em",
              whiteSpace: "nowrap",
              lineHeight: 1,
              textTransform: "capitalize",
              fontFamily: "inherit",
              transition: "color 180ms ease, filter 220ms ease",
              "&:hover": {
                color: isActive ? palette.primary.contrastText : palette.text.primary,
                filter: isActive ? "brightness(1.04)" : "none"
              },
              "&:focus-visible": {
                outline: `2px solid ${palette.primary.main}`,
                outlineOffset: 2
              }
            }}
          >
            {isActive &&
              (reduceMotion ? (
                <Box component="span" aria-hidden sx={thumbSx} />
              ) : (
                <Box
                  component={motion.span}
                  layoutId="alert-rule-tab-thumb"
                  initial={false}
                  transition={{ type: "spring", stiffness: 420, damping: 34 }}
                  aria-hidden
                  sx={thumbSx}
                />
              ))}
            <Box
              component="span"
              sx={{
                position: "relative",
                zIndex: 1,
                display: "inline-flex",
                alignItems: "center",
                justifyContent: "center",
                lineHeight: 0
              }}
            >
              {TAB_ICONS[tab]}
            </Box>
            <Box component="span" sx={{ position: "relative", zIndex: 1 }}>
              {tab}
            </Box>
          </Box>
        );
      })}
    </Box>
  );
}
