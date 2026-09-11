"use client";

import { Box, alpha, useTheme } from "@mui/material";
import { motion, useReducedMotion } from "framer-motion";
import { AiOutlineApi } from "react-icons/ai";
import { TiFlowChildren } from "react-icons/ti";

import { getPrimaryGradient } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

type EndpointWorkspaceSwitchProps = {
  value: "endpoints" | "flows";
  onChange: (value: "endpoints" | "flows") => void;
  labels: Record<"endpoints" | "flows", string>;
};

const VIEWS = [
  { value: "endpoints" as const, icon: AiOutlineApi },
  { value: "flows" as const, icon: TiFlowChildren }
];

export default function EndpointWorkspaceSwitch({ value, onChange, labels }: EndpointWorkspaceSwitchProps) {
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
      aria-label="Endpoint workspace"
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
      {VIEWS.map((view) => {
        const isActive = value === view.value;
        const Icon = view.icon;

        return (
          <Box
            key={view.value}
            component="button"
            type="button"
            onClick={() => onChange(view.value)}
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
              textTransform: "none",
              color: isActive ? palette.primary.contrastText : palette.text.secondary,
              fontSize: "0.875rem",
              fontWeight: 700,
              letterSpacing: "0.02em",
              whiteSpace: "nowrap",
              lineHeight: 1,
              transition: "color 180ms ease, filter 220ms ease",
              background: "none",
              border: "none",
              cursor: "pointer",
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
                  layoutId="endpoint-workspace-thumb"
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
              <Icon size={20} />
            </Box>
            <Box component="span" sx={{ position: "relative", zIndex: 1 }}>
              {labels[view.value]}
            </Box>
          </Box>
        );
      })}
    </Box>
  );
}
