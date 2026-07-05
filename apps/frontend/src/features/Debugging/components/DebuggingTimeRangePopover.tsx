"use client";

import { useEffect, useState } from "react";

import { Box, Popover, Tabs, Tab, useTheme, alpha, Typography } from "@mui/material";
import { format } from "date-fns";

import { useDebuggingTimeRange } from "@/features/Debugging/context/DebuggingTimeRange.context";

import AbsoluteDateTime from "./AbsoluteDateTime";
import RelativeDateTime from "./RelativeDateTime";

interface AnalysisTimeRangePopoverProps {
  anchorEl: HTMLElement | null;
  open: boolean;
  onClose: () => void;
  variant?: "start" | "end";
  arrowPosition?: "center" | "left" | "right";
}

export default function AnalysisTimeRangePopover({
  anchorEl,
  open,
  onClose,
  variant,
  arrowPosition = "center"
}: AnalysisTimeRangePopoverProps) {
  const { palette } = useTheme();
  const { start, end, setStartNow, setEndNow } = useDebuggingTimeRange();
  const selection = variant === "end" ? end : start;
  const [tabValue, setTabValue] = useState<"absolute" | "relative" | "now">("relative");

  useEffect(() => {
    if (!open || !variant) {
      return;
    }

    setTabValue(selection.mode);
  }, [open, variant, selection.mode]);

  function handleTabChange(_: React.SyntheticEvent, val: "absolute" | "relative" | "now") {
    setTabValue(val);
    if (val === "now") {
      if (variant === "end") {
        setEndNow();
      } else {
        setStartNow();
      }
    }
  }

  function getArrowPosition() {
    switch (arrowPosition) {
      case "center":
        return {
          left: "50%",
          transform: "translateX(-50%) rotate(45deg)"
        };
      case "left":
        return {
          left: "25%",
          transform: "rotate(45deg)"
        };
      case "right":
        return {
          left: "auto",
          right: "25%",
          transform: "rotate(45deg)"
        };
    }
  }

  return (
    <Popover
      open={open}
      anchorEl={anchorEl}
      onClose={onClose}
      anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
      transformOrigin={{ vertical: "top", horizontal: "center" }}
      slotProps={{
        paper: {
          sx: {
            minWidth: 380,
            maxWidth: 520,
            mt: 1.5,
            borderRadius: 3,
            boxShadow: "0px 10px 30px rgba(0,0,0,0.1)",
            overflow: "visible",
            position: "relative",
            backgroundColor: palette.background.paper,
            backgroundImage: "none",
            "&::before": {
              content: "''",
              position: "absolute",
              top: -8,
              ...getArrowPosition(),
              width: 14,
              height: 14,
              backgroundColor: palette.background.paper,
              borderTop: "1px solid",
              borderLeft: "1px solid",
              borderColor: palette.background.paper,
              zIndex: 0
            }
          }
        }
      }}
    >
      <Box sx={{ borderBottom: 1, borderColor: "divider" }}>
        <Tabs
          value={tabValue}
          onChange={handleTabChange}
          variant="fullWidth"
          sx={{
            minHeight: 48,
            "& .MuiTab-root": {
              textTransform: "none",
              fontWeight: 700,
              fontSize: 14,
              transition: "all 300ms ease",
              "&.Mui-selected": { fontSize: 15 }
            }
          }}
        >
          <Tab label="Absolute" value="absolute" />
          <Tab label="Relative" value="relative" />
          <Tab label="Now" value="now" />
        </Tabs>
      </Box>

      {tabValue === "relative" && <RelativeDateTime variant={variant} />}
      {tabValue === "absolute" && <AbsoluteDateTime calendar="gregorian" variant={variant} />}
      {tabValue === "now" && (
        <Box sx={{ padding: 1 }}>
          <Box
            sx={{
              display: "flex",
              border: `1px solid ${palette.divider}`,
              borderRadius: "8px",
              overflow: "hidden",
              fontSize: 14
            }}
          >
            <Typography
              variant="body2"
              sx={{
                bgcolor: alpha(palette.divider, 0.1),
                px: 2,
                py: 1,
                borderRight: `1px solid ${palette.divider}`,
                fontWeight: 600,
                textWrap: "nowrap",
                lineHeight: 1.5
              }}
            >
              {variant === "start" ? "Start date" : "End date"}
            </Typography>
            <Typography variant="body1" sx={{ flex: 1, px: 2, py: 1 }}>
              {selection.dateTime ? format(selection.dateTime, "MMM dd, yyyy") : "---"} @{" "}
              {format(selection.dateTime, "HH:mm")}:00.000
            </Typography>
          </Box>
        </Box>
      )}
    </Popover>
  );
}
