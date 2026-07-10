"use client";

import { useEffect, useState } from "react";

import {
  Box,
  Typography,
  Stack,
  Button,
  useTheme,
  CircularProgress,
  Alert,
  Tooltip,
  Chip
} from "@mui/material";
import { HiCalendar, HiOutlineExclamationCircle, HiOutlineInformationCircle } from "react-icons/hi";

import AlertRuleSelector from "@/features/Debugging/components/AlertRuleSelector";
import DebuggingBar from "@/features/Debugging/components/DebuggingBar";
import AnalysisTimeRangePopover from "@/features/Debugging/components/DebuggingTimeRangePopover";
import TimelineComparisonEmptyState from "@/features/Debugging/components/TimelineComparisonEmptyState";
import { ControlBarProvider } from "@/features/Debugging/context/ControlBar.context";
import {
  DebuggingTimeRangeProvider,
  formatDebuggingTimeLabel,
  useDebuggingTimeRange
} from "@/features/Debugging/context/DebuggingTimeRange.context";
import type { AlertRuleOption } from "@/features/Debugging/debugging.type";
import { useDebuggingData } from "@/features/Debugging/hooks/useDebuggingData";

function AnalysisPageContent() {
  const { palette } = useTheme();
  const { start, end, isTimeRangeInvalid, timeRangeError } = useDebuggingTimeRange();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [currentDatePopover, setCurrentDatePopover] = useState<null | "start" | "end">(null);
  const [selectedAlertRules, setSelectedAlertRules] = useState<AlertRuleOption[]>([]);
  const [removingAlertRuleIds, setRemovingAlertRuleIds] = useState<Set<string>>(() => new Set());

  const alertRuleIds = selectedAlertRules.map((rule) => rule.id);

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

  const handleRemoveAlertRule = (alertRuleId: string) => {
    setRemovingAlertRuleIds((prev) => new Set(prev).add(alertRuleId));
    setSelectedAlertRules((prev) => prev.filter((rule) => rule.id !== alertRuleId));
  };

  useEffect(() => {
    setRemovingAlertRuleIds((prev) => {
      const next = new Set(prev);
      let changed = false;

      for (const id of prev) {
        if (!rules.some((rule) => rule.alertRuleId === id)) {
          next.delete(id);
          changed = true;
        }
      }

      return changed ? next : prev;
    });
  }, [rules]);

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
              borderRadius: 3,
              height: 40,
              px: 2,
              bgcolor: "background.paper",
              border: "1px solid",
              borderColor: isTimeRangeInvalid ? "error.main" : "transparent"
            }}
          >
            <Button
              onClick={(e) => handleOpenPicker(e, "start")}
              variant="text"
              sx={{
                fontFamily: start.mode === "absolute" ? "monospace" : "inherit",
                color: isTimeRangeInvalid ? "error.main" : "text.primary",
                fontSize: 14,
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
                color: isTimeRangeInvalid ? "error.main" : "text.primary",
                fontSize: 14,
                fontWeight: end.mode === "absolute" ? 400 : 600
              }}
            >
              {formatDebuggingTimeLabel(end)}
            </Button>
            {isTimeRangeInvalid ? (
              <Tooltip title={timeRangeError} arrow placement="top">
                <Box sx={{ display: "flex", alignItems: "center", ml: 1.25 }}>
                  <HiOutlineExclamationCircle size={20} style={{ color: palette.error.main }} />
                </Box>
              </Tooltip>
            ) : (
              <HiCalendar size={20} style={{ color: palette.text.disabled, marginLeft: 10 }} />
            )}
          </Stack>
        </Stack>
      </Box>
      <Box sx={{ width: "100%", backgroundColor: palette.background.paper, p: 2, borderRadius: 4 }}>
        <Stack
          direction="row"
          spacing={2}
          sx={{ justifyContent: "space-between", alignItems: "flex-start", mb: 1 }}
        >
          <Stack>
            <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
              <Typography variant="h6" sx={{ fontWeight: 600 }}>
                Timeline Comparison
              </Typography>
              {selectedAlertRules.length > 0 && (
                <Chip
                  label={`${selectedAlertRules.length} selected`}
                  size="small"
                  color="primary"
                  variant="outlined"
                />
              )}
            </Stack>
            <Typography variant="body2" sx={{ color: "text.secondary", mb: 3 }}>
              Compare alert occurrences across data sources{" "}
            </Typography>
          </Stack>
          <AlertRuleSelector value={selectedAlertRules} onChange={setSelectedAlertRules} />
        </Stack>

        {selectedAlertRules.length === 0 && <TimelineComparisonEmptyState />}

        {selectedAlertRules.length > 0 && isLoading && (
          <Stack sx={{ alignItems: "center", py: 4 }}>
            <CircularProgress size={28} />
          </Stack>
        )}

        {selectedAlertRules.length > 0 && error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.message}
          </Alert>
        )}

        {selectedAlertRules.length > 0 && !isLoading && !error && rules.length === 0 && (
          <Typography color="text.secondary">
            No timeline data found for the selected rules.
          </Typography>
        )}

        <ControlBarProvider>
          {selectedAlertRules.length > 0 &&
            !isLoading &&
            !error &&
            rules.map((rule) => (
              <DebuggingBar
                key={rule.alertRuleId}
                rule={rule}
                isRemoving={removingAlertRuleIds.has(rule.alertRuleId)}
                onRemove={() => handleRemoveAlertRule(rule.alertRuleId)}
              />
            ))}
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
