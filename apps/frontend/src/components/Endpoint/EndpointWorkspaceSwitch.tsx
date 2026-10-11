"use client";

import { Box, type Theme } from "@mui/material";
import { motion, useReducedMotion } from "framer-motion";
import { AiOutlineApi } from "react-icons/ai";
import { TiFlowChildren } from "react-icons/ti";

import { getPrimaryGradient } from "@/components/Wrapper/topBarStyles";

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
  const reduceMotion = useReducedMotion();

  const gradient = getPrimaryGradient();

  const thumbDarkSx = (muiTheme: Theme) =>
    muiTheme.applyStyles("dark", {
      boxShadow:
        "0 -2px 8px color-mix(in srgb, var(--mui-palette-primary-main) 35%, transparent)"
    });

  const thumbSx = {
    position: "absolute" as const,
    inset: 0,
    borderRadius: "9px",
    background: gradient,
    boxShadow:
      "0 -2px 8px color-mix(in srgb, var(--mui-palette-primary-main) 80%, transparent)",
    pointerEvents: "none" as const
  };

  return (
    <Box
      component="nav"
      aria-label="Endpoint workspace"
      sx={[
        {
          display: "inline-flex",
          alignItems: "center",
          gap: 0.5,
          p: "3px",
          borderRadius: "11px",
          backgroundColor:
            "color-mix(in srgb, var(--mui-palette-primary-main) 10%, transparent)",
          border:
            "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 22%, transparent)"
        },
        (muiTheme) =>
          muiTheme.applyStyles("dark", {
            backgroundColor: "rgba(255, 255, 255, 0.06)",
            border:
              "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 18%, transparent)"
          })
      ]}
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
              color: isActive ? "primary.contrastText" : "text.secondary",
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
                color: isActive ? "primary.contrastText" : "text.primary",
                filter: isActive ? "brightness(1.04)" : "none"
              },
              "&:focus-visible": {
                outline: "2px solid var(--mui-palette-primary-main)",
                outlineOffset: 2
              }
            }}
          >
            {isActive &&
              (reduceMotion ? (
                <Box component="span" aria-hidden sx={[thumbSx, thumbDarkSx]} />
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
