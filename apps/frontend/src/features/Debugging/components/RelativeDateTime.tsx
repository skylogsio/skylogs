"use client";

import { useCallback, useEffect, useState } from "react";

import {
  Box,
  MenuItem,
  Select,
  Stack,
  TextField,
  Typography,
  alpha,
  useTheme
} from "@mui/material";
import { format } from "date-fns";

import {
  computeRelativeDate,
  useDebuggingTimeRange,
  type RelativeTimeUnit
} from "@/features/Debugging/context/DebuggingTimeRange.context";

interface RelativeDateTimeProps {
  variant?: "start" | "end";
}

export default function RelativeDateTime({ variant = "start" }: RelativeDateTimeProps) {
  const { palette } = useTheme();
  const { referenceNow, start, end, setStartRelative, setEndRelative } = useDebuggingTimeRange();

  const [value, setValue] = useState(15);
  const [unit, setUnit] = useState<RelativeTimeUnit>("minutes");

  const date = computeRelativeDate(value, unit, referenceNow);

  const updateDateTime = useCallback(
    (nextValue: number, nextUnit: RelativeTimeUnit) => {
      if (variant === "end") {
        setEndRelative(nextValue, nextUnit);
      } else {
        setStartRelative(nextValue, nextUnit);
      }
    },
    [setEndRelative, setStartRelative, variant]
  );

  useEffect(() => {
    const selection = variant === "end" ? end : start;
    if (selection.relative) {
      setValue(selection.relative.value);
      setUnit(selection.relative.unit);
    } else {
      updateDateTime(15, "minutes");
    }
  }, [variant, end, start, updateDateTime]);

  return (
    <Stack spacing={2} sx={{ p: 2.5 }}>
      <Stack direction="row" spacing={2}>
        <TextField
          variant="filled"
          hiddenLabel
          size="small"
          type="number"
          value={value}
          slotProps={{ htmlInput: { min: 1 } }}
          onChange={(e) => {
            const nextValue = Math.max(1, Number(e.target.value));
            setValue(nextValue);
            updateDateTime(nextValue, unit);
          }}
          sx={{
            "& input[type=number]": {
              MozAppearance: "auto"
            },
            "& input[type=number]::-webkit-outer-spin-button, & input[type=number]::-webkit-inner-spin-button":
              {
                WebkitAppearance: "inner-spin-button",
                opacity: 1,
                margin: 0
              }
          }}
        />

        <Select
          fullWidth
          size="small"
          variant="filled"
          hiddenLabel
          value={unit}
          onChange={(e) => {
            const nextUnit = e.target.value as RelativeTimeUnit;
            setUnit(nextUnit);
            updateDateTime(value, nextUnit);
          }}
          sx={{ borderRadius: 2 }}
        >
          <MenuItem value="minutes">Minutes ago</MenuItem>
          <MenuItem value="hours">Hours ago</MenuItem>
          <MenuItem value="days">Days ago</MenuItem>
          <MenuItem value="weeks">Week ago</MenuItem>
          <MenuItem value="months">Month ago</MenuItem>
          <MenuItem value="years">Year ago</MenuItem>
        </Select>
      </Stack>

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
          {date ? format(date, "MMM dd, yyyy") : "---"} @ {date.toLocaleTimeString().split(" ")[0]}
          :00.000
        </Typography>
      </Box>
    </Stack>
  );
}
