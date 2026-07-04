"use client";

import { useState } from "react";

import { Box, Typography, Stack, Button, useTheme } from "@mui/material";
import { HiCalendar, HiOutlineInformationCircle } from "react-icons/hi";

import AnalysisTimeRangePopover from "@/components/Debugging/DebuggingTimeRangePopover";
import {
  DebuggingTimeRangeProvider,
  formatDebuggingTimeLabel,
  useDebuggingTimeRange
} from "@/context/DebuggingTimeRangeContext";

function AnalysisPageContent() {
  const { palette } = useTheme();
  const { start, end } = useDebuggingTimeRange();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [currentDatePopover, setCurrentDatePopover] = useState<null | "start" | "end">(null);

  const handleOpenPicker = (
    event: React.MouseEvent<HTMLButtonElement>,
    variant: "start" | "end"
  ) => {
    setAnchorEl(event.currentTarget);
    setCurrentDatePopover(variant);
  };

  const handleClosePicker = () => {
    setAnchorEl(null);
    setCurrentDatePopover(null);
  };

  return (
    <Stack spacing={3} sx={{ px: 2, py: 1, width: "100%", boxSizing: "border-box" }}>
      <Box
        sx={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          flexWrap: "wrap",
          gap: 2
        }}
      >
        <Box>
          <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
            <Typography variant="h5" sx={{ fontWeight: 700, color: "text.primary" }}>
              Debugging & Root Cause Analysis
            </Typography>
            <HiOutlineInformationCircle
              size={20}
              style={{ color: palette.text.disabled, cursor: "pointer" }}
            />
          </Stack>
          <Typography variant="body2" sx={{ mt: 0.5, color: "text.secondary" }}>
            Correlate alerts across data sources and analyze the root cause with AI.
          </Typography>
        </Box>

        <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
          <AnalysisTimeRangePopover
            anchorEl={anchorEl}
            open={Boolean(anchorEl)}
            onClose={handleClosePicker}
            variant={currentDatePopover!}
            arrowPosition={currentDatePopover === "start" ? "center" : "right"}
          />

          <Stack
            direction="row"
            spacing={1}
            sx={{
              alignItems: "center",
              borderRadius: "8px",
              height: "40px",
              px: 2,
              bgcolor: "background.paper"
            }}
          >
            <Button
              onClick={(e) => handleOpenPicker(e, "start")}
              variant="text"
              sx={{
                fontFamily: start.mode === "absolute" ? "monospace" : "inherit",
                color: "text.primary",
                fontSize: "0.85rem",
                fontWeight: start.mode === "absolute" ? 400 : 600
              }}
            >
              {formatDebuggingTimeLabel(start)}
            </Button>
            <Typography variant="body2" color="textDisabled">
              →
            </Typography>
            <Button
              onClick={(e) => handleOpenPicker(e, "end")}
              variant="text"
              sx={{
                fontFamily: end.mode === "absolute" ? "monospace" : "inherit",
                color: "text.primary",
                fontSize: "0.85rem",
                fontWeight: end.mode === "absolute" ? 400 : 600
              }}
            >
              {formatDebuggingTimeLabel(end)}
            </Button>
            <HiCalendar size={20} style={{ color: palette.text.disabled, marginLeft: 10 }} />
          </Stack>
        </Stack>
      </Box>
    </Stack>
  );
}

export default function AnalysisPage() {
  return (
    <DebuggingTimeRangeProvider>
      <AnalysisPageContent />
    </DebuggingTimeRangeProvider>
  );
}
