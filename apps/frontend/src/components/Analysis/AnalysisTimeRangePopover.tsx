"use client";

import { useState } from "react";

import { Box, Popover, Tabs, Tab, useTheme } from "@mui/material";

import { AbsoluteTabDateTime } from "./AbsoluteTabDateTime";
import { RelativeTimePicker } from "./RelativeTabDateTime";

interface AnalysisTimeRangePopoverProps {
  anchorEl: HTMLElement | null;
  open: boolean;
  onClose: () => void;
}

export default function AnalysisTimeRangePopover({
  anchorEl,
  open,
  onClose
}: AnalysisTimeRangePopoverProps) {
  const { palette } = useTheme();
  const [tabValue, setTabValue] = useState<"absolute" | "relative" | "now">("relative");

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
              left: "50%",
              transform: "translateX(-50%) rotate(45deg)",
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
          onChange={(_, val) => setTabValue(val)}
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

      {tabValue === "relative" && <RelativeTimePicker />}
      {tabValue === "absolute" && <AbsoluteTabDateTime calendar="gregorian" />}
    </Popover>
  );
}
