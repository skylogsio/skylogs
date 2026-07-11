import { memo } from "react";

import { Box, Stack, Tooltip, Typography, useColorScheme } from "@mui/material";
import { format } from "date-fns";

import type { AlertRuleStatus } from "@/@types/alertRule";

import {
  useControlBarActions,
  useIsSegmentHovered,
  useIsSegmentSelected
} from "../context/ControlBar.context";

interface SegmentProps {
  index: number;
  summary?: string;
  color: string;
  status: Exclude<AlertRuleStatus, "triggered">;
  startTime: number;
  endTime: number;
}

const Segment = memo(function SegmentDot({
  index,
  summary,
  color,
  status,
  startTime,
  endTime
}: SegmentProps) {
  const { mode, systemMode } = useColorScheme();
  const isHovered = useIsSegmentHovered(index);
  const isSelected = useIsSegmentSelected(index);
  const { setHoveredIndex, clearHoveredIndex, startSelection, updateSelection } =
    useControlBarActions();

  const isDark = (systemMode || mode) === "dark";
  const showTooltip = status === "warning" || status === "critical";

  const tooltipTitle = showTooltip && (
    <Stack spacing={0.5} sx={{ py: 0.25, maxWidth: 500, maxHeight: 500, overflowY: "auto" }}>
      {summary && (
        <Typography
          component="pre"
          variant="caption"
          sx={{
            color: "inherit",
            opacity: 0.85,
            lineHeight: 1.4,
            fontFamily: "inherit",
            margin: 0,
            whiteSpace: "pre-wrap",
            overflowWrap: "break-word"
          }}
        >
          {summary}
        </Typography>
      )}
      <Stack direction="row" spacing={0.75} sx={{ alignItems: "center" }}>
        <Box
          sx={{
            width: 12,
            height: 12,
            borderRadius: "50%",
            bgcolor: color,
            flexShrink: 0
          }}
        />
        <Typography
          variant="caption"
          sx={{ textWrap: "nowrap", fontFamily: "monospace", color: "inherit", lineHeight: 1.4 }}
        >
          {format(startTime, "yyyy/MM/dd HH:mm:ss")} → {format(endTime, "yyyy/MM/dd HH:mm:ss")}
        </Typography>
      </Stack>
    </Stack>
  );

  const Bar = (
    <Box
      onMouseDown={() => startSelection(index)}
      onMouseEnter={() => {
        setHoveredIndex(index);
        updateSelection(index);
      }}
      onMouseLeave={clearHoveredIndex}
      sx={{
        flex: 1,
        height: 28,
        backgroundColor: color,
        cursor: "pointer",
        borderRadius: 1,
        transition: "all 50ms ease-out",
        ...(isHovered && { opacity: 0.6, transform: "scale(1.3)" }),
        ...(isSelected && {
          outline: "1px solid",
          ...(isHovered && { opacity: 0.6, transform: "scale(1.1)" }),
          outlineColor: ({ palette }) => (isDark ? palette.common.white : palette.common.black)
        })
      }}
    />
  );

  if (!showTooltip) {
    return Bar;
  }

  return (
    <Tooltip
      title={tooltipTitle}
      arrow
      placement="auto"
      slotProps={{
        popper: {
          modifiers: [{ name: "preventOverflow", options: { padding: 8 } }]
        },
        tooltip: {
          sx: {
            minWidth: 500,
            px: 1.25,
            py: 1
          }
        }
      }}
    >
      {Bar}
    </Tooltip>
  );
});

export default Segment;
