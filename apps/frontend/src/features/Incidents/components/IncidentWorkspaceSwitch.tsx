"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { Box, alpha, useTheme } from "@mui/material";
import { motion, useReducedMotion } from "framer-motion";
import { AiOutlineFileProtect, AiOutlineWarning } from "react-icons/ai";

import { useCurrentTheme } from "@/hooks";

const VIEWS = [
  {
    href: "/incidents",
    label: "Incidents",
    icon: AiOutlineWarning,
    id: "incidents" as const
  },
  {
    href: "/incidents/policies",
    label: "Policies",
    icon: AiOutlineFileProtect,
    id: "policies" as const
  }
];

export default function IncidentWorkspaceSwitch() {
  const pathname = usePathname();
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();
  const reduceMotion = useReducedMotion();
  const activeView = pathname.includes("/incidents/policies") ? "policies" : "incidents";

  const gradient = `linear-gradient(135deg, ${palette.secondary.main} 0%, ${palette.primary.main} 100%)`;

  const thumbSx = {
    position: "absolute" as const,
    inset: 0,
    borderRadius: "9px",
    background: gradient,
    boxShadow: `0 2px 8px ${alpha(palette.primary.main, isDark ? 0.35 : 0.28)}`,
    pointerEvents: "none" as const
  };

  return (
    <Box
      component="nav"
      aria-label="Incident workspace"
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
        const isActive = view.id === activeView;
        const Icon = view.icon;

        return (
          <Box
            key={view.href}
            component={Link}
            href={view.href}
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
              textDecoration: "none",
              color: isActive ? palette.primary.contrastText : palette.text.secondary,
              fontSize: "0.875rem",
              fontWeight: 700,
              letterSpacing: "0.02em",
              whiteSpace: "nowrap",
              lineHeight: 1,
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
                  layoutId="incident-workspace-thumb"
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
              {view.label}
            </Box>
          </Box>
        );
      })}
    </Box>
  );
}
