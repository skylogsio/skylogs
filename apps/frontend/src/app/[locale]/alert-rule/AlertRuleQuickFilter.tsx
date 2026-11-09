"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useMemo } from "react";

import { alpha, Box, Button, Stack, useTheme } from "@mui/material";

import { cleanFilters, parseFiltersFromUrl } from "@/components/Table/SmartTable/utils";

export type QuickFilterKey = "firing" | "acknowledged" | "silenced" | "all";

export default function AlertRuleQuickFilter() {
  const theme = useTheme();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const currentFilters = useMemo(
    () => parseFiltersFromUrl(searchParams.get("filters")),
    [searchParams]
  );

  const activeKey: QuickFilterKey = useMemo(() => {
    if (currentFilters.status === "critical") return "firing";
    if (currentFilters.silentStatus === "silent") return "silenced";
    if (currentFilters.acknowledged === true || currentFilters.acknowledged === "true") {
      return "acknowledged";
    }
    return "all";
  }, [currentFilters]);

  const handleSelect = useCallback(
    (key: QuickFilterKey) => {
      const next = { ...currentFilters };

      // Clear the quick filter targets first
      if (next.status === "critical") delete next.status;
      if (next.silentStatus === "silent") delete next.silentStatus;
      delete next.acknowledged;

      if (key !== "all" && activeKey !== key) {
        if (key === "firing") {
          next.status = "critical";
        } else if (key === "silenced") {
          next.silentStatus = "silent";
        } else if (key === "acknowledged") {
          next.acknowledged = "true";
        }
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
    },
    [activeKey, currentFilters, pathname, router, searchParams]
  );

  return (
    <Stack
      direction="row"
      spacing={1}
      sx={{
        alignItems: "center",
        flexWrap: "wrap",
        rowGap: 0.75
      }}
    >
      <Button
        size="small"
        disableElevation
        onClick={() => handleSelect("firing")}
        aria-pressed={activeKey === "firing"}
        sx={{
          display: "inline-flex",
          alignItems: "center",
          gap: 0.75,
          minWidth: "auto",
          height: 28,
          minHeight: 28,
          px: 1.4,
          borderRadius: "999px",
          fontSize: "0.78rem",
          fontWeight: 600,
          textTransform: "none",
          lineHeight: 1,
          border: "1px solid",
          cursor: "pointer",
          transition: "all 0.18s ease-in-out",
          ...(activeKey === "firing"
            ? {
                bgcolor: "error.light",
                borderColor: "error.light",
                color: "#fff",
                boxShadow: `0 2px 6px ${alpha(theme.palette.error.main, 0.35)}`,
                "&:hover": {
                  bgcolor: "error.dark",
                  borderColor: "error.dark"
                }
              }
            : {
                bgcolor: alpha(theme.palette.error.main, 0.06),
                borderColor: alpha(theme.palette.error.main, 0.2),
                color: "error.main",
                ...theme.applyStyles("dark", {
                  bgcolor: alpha(theme.palette.error.main, 0.08),
                  borderColor: alpha(theme.palette.error.main, 0.25),
                  color: "error.main"
                }),
                "&:hover": {
                  bgcolor: alpha(theme.palette.error.main, 0.12),
                  borderColor: alpha(theme.palette.error.main, 0.35)
                }
              }),
        }}
      >
        <Box
          component="span"
          sx={{
            width: 7,
            height: 7,
            borderRadius: "50%",
            bgcolor: activeKey === "firing" ? "#fff" : "error.main",
            flexShrink: 0
          }}
        />
        <span>Firing</span>
      </Button>

      <Button
        size="small"
        disableElevation
        onClick={() => handleSelect("acknowledged")}
        aria-pressed={activeKey === "acknowledged"}
        sx={{
          display: "inline-flex",
          alignItems: "center",
          gap: 0.75,
          minWidth: "auto",
          height: 28,
          minHeight: 28,
          px: 1.4,
          borderRadius: "999px",
          fontSize: "0.78rem",
          fontWeight: 600,
          textTransform: "none",
          lineHeight: 1,
          border: "1px solid",
          cursor: "pointer",
          transition: "all 0.18s ease-in-out",
          ...(activeKey === "acknowledged"
            ? {
                bgcolor: "info.light",
                borderColor: "info.light",
                color: "#fff",
                boxShadow: `0 2px 6px ${alpha(theme.palette.info.main, 0.35)}`,
                "&:hover": {
                  bgcolor: "info.dark",
                  borderColor: "info.dark"
                }
              }
            : {
                bgcolor: alpha(theme.palette.info.main, 0.06),
                borderColor: alpha(theme.palette.info.main, 0.2),
                color: "info.main",
                ...theme.applyStyles("dark", {
                  bgcolor: alpha(theme.palette.info.main, 0.08),
                  borderColor: alpha(theme.palette.info.main, 0.25),
                  color: "info.main"
                }),
                "&:hover": {
                  bgcolor: alpha(theme.palette.info.main, 0.12),
                  borderColor: alpha(theme.palette.info.main, 0.35)
                }
              }),
        }}
      >
        <Box
          component="span"
          sx={{
            width: 7,
            height: 7,
            borderRadius: "50%",
            bgcolor: activeKey === "acknowledged" ? "#fff" : "info.main",
            flexShrink: 0
          }}
        />
        <span>Acknowledged</span>
      </Button>

      <Button
        size="small"
        disableElevation
        onClick={() => handleSelect("silenced")}
        aria-pressed={activeKey === "silenced"}
        sx={{
          display: "inline-flex",
          alignItems: "center",
          gap: 0.75,
          minWidth: "auto",
          height: 28,
          minHeight: 28,
          px: 1.4,
          borderRadius: "999px",
          fontSize: "0.78rem",
          fontWeight: 600,
          textTransform: "none",
          lineHeight: 1,
          border: "1px solid",
          cursor: "pointer",
          transition: "all 0.18s ease-in-out",
          ...(activeKey === "silenced"
            ? {
                bgcolor: theme.palette.text.secondary,
                borderColor: theme.palette.text.secondary,
                color: theme.palette.background.paper,
                boxShadow: "0 2px 6px rgba(0, 0, 0, 0.15)",
                "&:hover": {
                  bgcolor: theme.palette.text.primary,
                  borderColor: theme.palette.text.primary
                }
              }
            : {
                bgcolor: alpha(theme.palette.text.secondary, 0.04),
                borderColor: alpha(theme.palette.text.secondary, 0.18),
                color: "text.secondary",
                ...theme.applyStyles("dark", {
                  bgcolor: alpha(theme.palette.text.secondary, 0.08),
                  borderColor: alpha(theme.palette.text.secondary, 0.22)
                }),
                "&:hover": {
                  bgcolor: alpha(theme.palette.text.secondary, 0.09),
                  borderColor: alpha(theme.palette.text.secondary, 0.3)
                }
              }),
        }}
      >
        <Box
          component="span"
          sx={{
            width: 7,
            height: 7,
            borderRadius: "50%",
            bgcolor:
              activeKey === "silenced"
                ? theme.palette.background.paper
                : alpha(theme.palette.text.secondary, 0.6),
            flexShrink: 0
          }}
        />
        <span>Silenced</span>
      </Button>

      <Button
        size="small"
        disableElevation
        onClick={() => handleSelect("all")}
        aria-pressed={activeKey === "all"}
        sx={{
          display: "inline-flex",
          alignItems: "center",
          justifyContent: "center",
          minWidth: "auto",
          height: 28,
          minHeight: 28,
          px: 1.75,
          borderRadius: "999px",
          fontSize: "0.78rem",
          fontWeight: 600,
          textTransform: "none",
          lineHeight: 1,
          border: "1px solid",
          cursor: "pointer",
          transition: "all 0.18s ease-in-out",
          ...(activeKey === "all"
            ? {
                bgcolor: theme.palette.primary.main,
                borderColor: "transparent",
                color: theme.palette.primary.contrastText,
                boxShadow: `0 2px 6px ${alpha(theme.palette.primary.main, 0.35)}`,
                "&:hover": {
                  bgcolor: theme.palette.primary.dark,
                  borderColor: "transparent"
                }
              }
            : {
                bgcolor: "transparent",
                borderColor: alpha(theme.palette.text.primary, 0.15),
                color: "text.secondary",
                "&:hover": {
                  borderColor: alpha(theme.palette.primary.main, 0.3),
                  bgcolor: alpha(theme.palette.primary.main, 0.06)
                }
              }),
        }}
      >
        <span>All</span>
      </Button>
    </Stack>
  );
}
