import Link from "next/link";

import { alpha, Box, ListItem, ListItemButton, Stack, Tooltip, useTheme } from "@mui/material";
import { motion, useReducedMotion } from "framer-motion";

import { useSideBar } from "@/context/SideBarContext";
import { useRole } from "@/hooks";

import type { URLType } from "./types";

interface SideBarItemProps {
  url: URLType;
  isActive: boolean;
  index?: number;
}

const ICON_BOX_SIZE = 22;
const easeOut = [0.22, 1, 0.36, 1] as const;

export function SideBarItem({ url, isActive, index = 0 }: SideBarItemProps) {
  const { palette } = useTheme();
  const { hasRole } = useRole();
  const { collapsed } = useSideBar();
  const reduceMotion = useReducedMotion();

  if (url.role) {
    if (!hasRole(url.role)) return;
  }

  const IconComponent = url.icon;
  const opticalScale = url.iconScale ?? 1;
  const activeScale = collapsed && isActive ? 1.06 : 1;
  const iconScale = opticalScale * activeScale;

  const button = (
    <ListItemButton
      component={Link}
      href={url.pathname}
      sx={{
        ...(collapsed
          ? {
              width: 44,
              height: 44,
              minWidth: 44,
              minHeight: 44,
              maxWidth: 44,
              maxHeight: 44,
              padding: 0,
              aspectRatio: "1 / 1"
            }
          : {
              paddingY: 2,
              paddingX: 2
            }),
        borderRadius: collapsed ? 2.25 : 3,
        border: "none",
        background: isActive
          ? collapsed
            ? `linear-gradient(145deg, ${palette.primary.light} 0%, ${palette.primary.main} 48%, ${palette.primary.dark} 100%) !important`
            : undefined
          : "transparent",
        backgroundColor: isActive
          ? collapsed
            ? "transparent !important"
            : `${alpha(palette.primary.main, 0.15)}!important`
          : "transparent",
        color:
          isActive && collapsed
            ? palette.mode === "light"
              ? palette.secondary.light
              : palette.primary.contrastText
            : "inherit",
        display: "flex",
        alignItems: "center",
        justifyContent: collapsed ? "center" : "flex-start",
        gap: collapsed ? 0 : 1.5,
        textWrap: "nowrap",
        whiteSpace: "nowrap",
        transition: "background 180ms ease, transform 180ms ease",
        boxShadow:
          collapsed && isActive ? `0 6px 14px ${alpha(palette.primary.dark, 0.28)}` : "none",
        "&:hover": collapsed
          ? {
              background: isActive
                ? `linear-gradient(145deg, ${palette.primary.light} 0%, ${palette.primary.main} 40%, ${palette.primary.dark} 100%) !important`
                : alpha(palette.primary.main, 0.1),
              backgroundColor: isActive
                ? "transparent !important"
                : alpha(palette.primary.main, 0.1)
            }
          : undefined
      }}
    >
      <Box
        component="span"
        sx={{
          width: ICON_BOX_SIZE,
          height: ICON_BOX_SIZE,
          flexShrink: 0,
          display: "inline-flex",
          alignItems: "center",
          justifyContent: "center",
          lineHeight: 0,
          "& > svg": {
            width: "100%",
            height: "100%",
            display: "block",
            transform: `scale(${iconScale})`,
            transformOrigin: "center",
            transition: "transform 180ms ease"
          }
        }}
      >
        <IconComponent />
      </Box>
      {!collapsed && url.label}
    </ListItemButton>
  );

  return (
    <ListItem
      component={motion.li}
      initial={
        reduceMotion
          ? false
          : {
              opacity: 0,
              x: collapsed ? 0 : -14,
              y: collapsed ? 10 : 0
            }
      }
      animate={{ opacity: 1, x: 0, y: 0 }}
      transition={{
        duration: 0.38,
        delay: 0.04 + index * 0.05,
        ease: easeOut
      }}
      sx={{
        position: "relative",
        paddingY: collapsed ? 0.5 : 0,
        paddingRight: collapsed ? 0 : 2,
        paddingLeft: collapsed ? 0 : isActive ? 0 : 2,
        display: "flex",
        justifyContent: collapsed ? "center" : "flex-start"
      }}
    >
      <Stack
        direction="row"
        spacing={collapsed ? 0 : 2}
        sx={{
          width: 1,
          justifyContent: collapsed ? "center" : "flex-start"
        }}
      >
        {isActive && !collapsed && (
          <Box
            sx={{
              content: "''",
              display: "inline-block",
              height: 1,
              width: 5,
              backgroundColor: `${palette.primary.main}!important`,
              position: "absolute",
              top: 0,
              left: 0,
              borderRadius: "0 0.6rem 0.6rem 0"
            }}
          />
        )}
        {collapsed ? (
          <Tooltip title={url.label} placement="right" arrow>
            {button}
          </Tooltip>
        ) : (
          button
        )}
      </Stack>
    </ListItem>
  );
}
