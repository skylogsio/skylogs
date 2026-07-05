"use client";

import { useState } from "react";

import { Box, Typography, Stack, Button, useTheme, CircularProgress, Alert } from "@mui/material";
import { HiCalendar, HiOutlineInformationCircle } from "react-icons/hi";

import DebuggingBar from "@/features/Debugging/components/DebuggingBar";
import AnalysisTimeRangePopover from "@/features/Debugging/components/DebuggingTimeRangePopover";
import { ControlBarProvider } from "@/features/Debugging/context/ControlBar.context";
import {
  DebuggingTimeRangeProvider,
  formatDebuggingTimeLabel,
  useDebuggingTimeRange
} from "@/features/Debugging/context/DebuggingTimeRange.context";
import { useDebuggingData } from "@/features/Debugging/hooks/useDebuggingData";

////   JUST TEMP   ////
const alertRuleIds = [
  "69345f5cef730078340875a2",
  "696bcb9bae8c9d232803c1af",
  "6a46bf2ee7498e1c3b0aa582",
  "696bc74fae8c9d232803c1a9"
];

function AnalysisPageContent() {
  const { palette } = useTheme();
  const { start, end } = useDebuggingTimeRange();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [currentDatePopover, setCurrentDatePopover] = useState<null | "start" | "end">(null);

  const {
    data: rules = [],
    isLoading,
    error
  } = useDebuggingData({
    alertRuleIds
  });

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
      <Box sx={{ width: "100%", backgroundColor: palette.background.paper, p: 2, borderRadius: 4 }}>
        <Typography variant="h6" sx={{ fontWeight: 600 }} gutterBottom>
          Timeline Comparison
        </Typography>
        <Typography variant="body2" sx={{ color: "text.secondary", mb: 3 }}>
          Compare alert occurrences across data sources{" "}
        </Typography>

        {isLoading && (
          <Stack sx={{ alignItems: "center", py: 4 }}>
            <CircularProgress size={28} />
          </Stack>
        )}

        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.toString()}
          </Alert>
        )}

        {!isLoading && !error && rules.length === 0 && (
          <Typography color="text.secondary">No alert rules found.</Typography>
        )}

        <ControlBarProvider>
          {!isLoading &&
            !error &&
            rules.map((rule) => <DebuggingBar key={rule.alertRuleId} rule={rule} />)}
        </ControlBarProvider>
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
