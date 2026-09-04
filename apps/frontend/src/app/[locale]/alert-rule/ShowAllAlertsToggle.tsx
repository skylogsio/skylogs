"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useMemo } from "react";

import { Box, Button, alpha, useTheme } from "@mui/material";
import { AnimatePresence, motion } from "framer-motion";
import { HiOutlineGlobeAlt, HiOutlineUser } from "react-icons/hi";

import { cleanFilters, parseFiltersFromUrl } from "@/components/Table/SmartTable/utils";

const iconMotion = {
  initial: { opacity: 0, scale: 0.6, rotate: -40 },
  animate: { opacity: 1, scale: 1, rotate: 0 },
  exit: { opacity: 0, scale: 0.6, rotate: 40 }
};

export default function ShowAllAlertsToggle() {
  const { palette } = useTheme();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const showAllAlerts = useMemo(() => {
    const filters = parseFiltersFromUrl(searchParams.get("filters"));
    return filters.scope === "organization";
  }, [searchParams]);

  const handleToggle = useCallback(() => {
    const current = parseFiltersFromUrl(searchParams.get("filters"));
    const next = { ...current };

    if (showAllAlerts) {
      delete next.scope;
    } else {
      next.scope = "organization";
    }

    const cleaned = cleanFilters(next);
    const params = new URLSearchParams(searchParams.toString());

    if (Object.keys(cleaned).length > 0) {
      params.set("filters", encodeURIComponent(JSON.stringify(cleaned)));
    } else {
      params.delete("filters");
    }
    params.set("page", "1");
    router.push(`${pathname}?${params.toString()}`);
  }, [pathname, router, searchParams, showAllAlerts]);

  return (
    <Button
      size="small"
      variant="outlined"
      onClick={handleToggle}
      aria-pressed={showAllAlerts}
      startIcon={
        <Box
          sx={{
            position: "relative",
            width: "0.95rem",
            height: "0.95rem",
            display: "flex",
            alignItems: "center",
            justifyContent: "center"
          }}
        >
          <AnimatePresence mode="wait" initial={false}>
            <Box
              component={motion.span}
              key={showAllAlerts ? "globe" : "user"}
              initial={iconMotion.initial}
              animate={iconMotion.animate}
              exit={iconMotion.exit}
              transition={{ duration: 0.12, ease: [0.22, 1, 0.36, 1] }}
              sx={{
                position: "absolute",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                lineHeight: 0
              }}
            >
              {showAllAlerts ? (
                <HiOutlineGlobeAlt size="0.95rem" />
              ) : (
                <HiOutlineUser size="0.95rem" />
              )}
            </Box>
          </AnimatePresence>
        </Box>
      }
      sx={{
        height: 33,
        minHeight: 33,
        borderRadius: 2,
        px: 1.35,
        fontSize: 12.5,
        textTransform: "none",
        fontWeight: 400,
        whiteSpace: "nowrap",
        transition: "color 0.12s ease, background-color 0.12s ease, border-color 0.12s ease",
        "& .MuiButton-startIcon": {
          mr: 0.5
        },
        borderColor: showAllAlerts
          ? alpha(palette.primary.main, 0.45)
          : alpha(palette.text.primary, 0.12),
        bgcolor: showAllAlerts ? alpha(palette.primary.main, 0.1) : palette.background.paper,
        color: showAllAlerts ? palette.primary.main : palette.text.secondary,
        "&:hover": {
          borderColor: alpha(palette.primary.main, 0.45),
          bgcolor: alpha(palette.primary.main, 0.08)
        }
      }}
    >
      Show All Alerts
    </Button>
  );
}
