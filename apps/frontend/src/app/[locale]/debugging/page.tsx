"use client";

import { useEffect, useState } from "react";

import {
  Box,
  Typography,
  Stack,
  Button,
  useTheme,
  Alert,
  Tooltip,
  Chip,
  Badge,
  IconButton,
  alpha
} from "@mui/material";
import {
  HiCalendar,
  HiOutlineExclamationCircle,
  HiOutlineInformationCircle,
  HiOutlineZoomOut
} from "react-icons/hi";

import AlertRuleSelector from "@/features/Debugging/components/AlertRuleSelector";
import DebuggingBar from "@/features/Debugging/components/DebuggingBar";
import DebuggingBarLoading from "@/features/Debugging/components/DebuggingBarLoading";
import DebuggingRangeSync from "@/features/Debugging/components/DebuggingRangeSync";
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
  const { start, end, isTimeRangeInvalid, timeRangeError, zoomLevel, canZoomOut, zoomOut } =
    useDebuggingTimeRange();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [currentDatePopover, setCurrentDatePopover] = useState<null | "start" | "end">(null);
  const [selectedAlertRules, setSelectedAlertRules] = useState<AlertRuleOption[]>([]);
  const [removingAlertRuleIds, setRemovingAlertRuleIds] = useState<Set<string>>(() => new Set());

  const alertRuleIds = selectedAlertRules
    .filter((rule) => !removingAlertRuleIds.has(rule.id))
    .map((rule) => rule.id);

  const {
    data: alertRules = [],
    isFetching,
    isDebouncing,
    error
  } = useDebuggingData({
    alertRuleIds
  });

  const isPendingData = isDebouncing || isFetching;

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
  };

  useEffect(() => {
    if (removingAlertRuleIds.size === 0) return;

    const resolvedIds = [...removingAlertRuleIds].filter(
      (id) => !alertRules.some((alertRule) => alertRule.alertRuleId === id)
    );

    if (resolvedIds.length === 0) return;

    setRemovingAlertRuleIds((prev) => {
      const next = new Set(prev);
      resolvedIds.forEach((id) => next.delete(id));
      return next;
    });
    setSelectedAlertRules((prev) => prev.filter((rule) => !resolvedIds.includes(rule.id)));
  }, [alertRules, removingAlertRuleIds]);

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
              Compare alert occurrences across data sources
            </Typography>
          </Stack>
          <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
            {canZoomOut && (
              <Tooltip title="Zoom out to previous range" arrow placement="top">
                <Badge
                  badgeContent={zoomLevel}
                  color="primary"
                  overlap="circular"
                  anchorOrigin={{ vertical: "top", horizontal: "right" }}
                  sx={{
                    "& .MuiBadge-badge": {
                      fontWeight: 700,
                      fontSize: 11,
                      minWidth: 18,
                      height: 18,
                      boxShadow: ({ palette }) => `0 0 0 2px ${palette.background.paper}`
                    }
                  }}
                >
                  <IconButton
                    onClick={zoomOut}
                    aria-label="Zoom out"
                    sx={({ palette }) => ({
                      color: palette.primary.main,
                      backgroundColor: alpha(palette.primary.main, 0.08),
                      border: "1px solid",
                      borderColor: alpha(palette.primary.main, 0.24),
                      transition: "all 150ms ease",
                      "&:hover": {
                        backgroundColor: alpha(palette.primary.main, 0.16),
                        borderColor: palette.primary.main,
                        transform: "scale(1.05)"
                      }
                    })}
                  >
                    <HiOutlineZoomOut size={20} />
                  </IconButton>
                </Badge>
              </Tooltip>
            )}
            <AlertRuleSelector value={selectedAlertRules} onChange={setSelectedAlertRules} />
          </Stack>
        </Stack>

        {selectedAlertRules.length === 0 && <TimelineComparisonEmptyState />}

        {selectedAlertRules.length > 0 && error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.message}
          </Alert>
        )}

        {selectedAlertRules.length > 0 && !isPendingData && !error && alertRules.length === 0 && (
          <Typography color="textSecondary">
            No timeline data found for the selected rules.
          </Typography>
        )}

        <ControlBarProvider>
          <DebuggingRangeSync />
          {selectedAlertRules.map((selected, index) => {
            const rule = alertRules.find((item) => item.alertRuleId === selected.id);

            if (rule) {
              return (
                <DebuggingBar
                  key={selected.id}
                  rule={rule}
                  isRemoving={removingAlertRuleIds.has(selected.id)}
                  onRemove={() => handleRemoveAlertRule(selected.id)}
                />
              );
            }

            if (isPendingData) {
              return (
                <DebuggingBarLoading
                  key={selected.id}
                  alertRule={selected}
                  index={index}
                  onRemove={() => handleRemoveAlertRule(selected.id)}
                />
              );
            }

            return null;
          })}
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
