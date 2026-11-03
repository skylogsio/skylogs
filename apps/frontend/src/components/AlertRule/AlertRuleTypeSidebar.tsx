"use client";

import { Box, Stack, Typography, useTheme } from "@mui/material";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";

import { ALERT_RULE_VARIANTS, type AlertRuleType } from "@/utils/alertRuleUtils";

const DISABLED_ALERT_RULE_TYPES: AlertRuleType[] = ["metabase", "splunk"];

interface AlertRuleTypeSidebarProps {
  selectedType: AlertRuleType;
  onSelect: (type: AlertRuleType) => void;
}

export default function AlertRuleTypeSidebar({
  selectedType,
  onSelect
}: AlertRuleTypeSidebarProps) {
  const { palette } = useTheme();
  const reduceMotion = useReducedMotion();

  const entries = Object.entries(ALERT_RULE_VARIANTS).filter(([key]) => key !== "health");

  return (
    <Stack
      component="nav"
      aria-label="Alert rule types"
      spacing={0.5}
      sx={{
        width: 240,
        flexShrink: 0,
        p: 1.25,
        borderRight: `1px solid ${palette.divider}`,
        background: `linear-gradient(180deg, color-mix(in srgb, ${palette.primary.main} 6%, transparent) 0%, transparent 60%)`
      }}
    >
      {entries.map(([key, value], index) => {
        const isActive = key === selectedType;
        const isDisabled = DISABLED_ALERT_RULE_TYPES.includes(key as AlertRuleType);

        return (
          <motion.button
            key={key}
            type="button"
            disabled={isDisabled}
            aria-current={isActive ? "true" : undefined}
            onClick={() => {
              if (!isDisabled) {
                onSelect(key as AlertRuleType);
              }
            }}
            initial={reduceMotion ? false : { opacity: 0, x: -12 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: reduceMotion ? 0 : index * 0.04, duration: 0.25 }}
            whileHover={isDisabled || reduceMotion ? undefined : { x: 3 }}
            whileTap={isDisabled || reduceMotion ? undefined : { scale: 0.98 }}
            style={{
              position: "relative",
              display: "flex",
              alignItems: "center",
              gap: 12,
              width: "100%",
              padding: "10px 14px",
              border: "none",
              borderRadius: 12,
              background: "none",
              cursor: isDisabled ? "not-allowed" : "pointer",
              textAlign: "left",
              fontFamily: "inherit",
              fontSize: "0.9375rem",
              fontWeight: isActive ? 700 : 500,
              textTransform: "capitalize",
              color: isDisabled
                ? palette.text.disabled
                : isActive
                  ? palette.primary.dark
                  : palette.text.secondary,
              opacity: isDisabled ? 0.55 : 1,
              pointerEvents: isDisabled ? "none" : "auto",
              outline: "none"
            }}
          >
            {isActive && (
              <AnimatePresence initial={false}>
                <motion.span
                  layoutId="alert-rule-type-indicator"
                  aria-hidden
                  transition={
                    reduceMotion ? { duration: 0 } : { type: "spring", stiffness: 420, damping: 34 }
                  }
                  style={{
                    position: "absolute",
                    inset: 0,
                    borderRadius: 8,
                    background: `linear-gradient(135deg, color-mix(in srgb, ${palette.primary.main} 22%, transparent) 0%, color-mix(in srgb, ${palette.primary.main} 8%, transparent) 100%)`,
                    boxShadow: `inset 3px 0 0 ${palette.primary.main}`,
                    zIndex: 0
                  }}
                />
              </AnimatePresence>
            )}
            <Box
              component="span"
              sx={{
                position: "relative",
                zIndex: 1,
                display: "inline-flex",
                alignItems: "center",
                lineHeight: 0
              }}
            >
              <value.Icon
                size={value.defaultSize}
                color={isDisabled ? undefined : value.defaultColor}
              />
            </Box>
            <Typography
              component="span"
              sx={{
                position: "relative",
                zIndex: 1,
                flex: 1,
                fontSize: "inherit",
                fontWeight: "inherit",
                color: "inherit",
                textTransform: "inherit",
                minWidth: 0,
                whiteSpace: "nowrap"
              }}
            >
              {isDisabled ? `${value.label} (Disabled)` : value.label}
            </Typography>
          </motion.button>
        );
      })}
    </Stack>
  );
}
