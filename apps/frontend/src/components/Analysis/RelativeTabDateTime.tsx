import { Box, MenuItem, Select, Stack, TextField, Typography, alpha } from "@mui/material";
import { pickersInputBaseClasses } from "@mui/x-date-pickers/PickersTextField";

import DateTimeInput from "../DateTimeInput";

type RelativeTimeUnit = "minutes" | "hours" | "days";

interface RelativeTimePickerProps {
  value?: number;
  unit?: RelativeTimeUnit;
  onValueChange?: (value: number) => void;
  onUnitChange?: (unit: RelativeTimeUnit) => void;
}

export function RelativeTimePicker({
  value,
  unit,
  onValueChange,
  onUnitChange
}: RelativeTimePickerProps) {
  return (
    <Stack spacing={2} sx={{ p: 2.5 }}>
      <Stack direction="row" spacing={2}>
        <TextField
          variant="filled"
          defaultValue={15}
          hiddenLabel
          size="small"
          type="number"
          value={value}
          onChange={(e) => onValueChange?.(Number(e.target.value))}
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
          defaultValue="minutes"
          onChange={(e) => onUnitChange?.(e.target.value as RelativeTimeUnit)}
          sx={{ borderRadius: 2 }}
        >
          <MenuItem value="minutes">Minutes ago</MenuItem>
          <MenuItem value="hours">Hours ago</MenuItem>
          <MenuItem value="days">Days ago</MenuItem>
        </Select>
      </Stack>

      <Box
        sx={(theme) => ({
          display: "flex",
          alignItems: "stretch",
          border: `1px solid ${theme.palette.divider}`,
          borderRadius: 2,
          overflow: "hidden"
        })}
      >
        <Typography
          variant="body2"
          sx={(theme) => ({
            bgcolor: alpha(theme.palette.divider, 0.1),
            px: 2,
            py: 1,
            borderRight: `1px solid ${theme.palette.divider}`,
            fontWeight: 600,
            whiteSpace: "nowrap",
            lineHeight: 1.5
          })}
        >
          Start date
        </Typography>

        <DateTimeInput
          calendar="gregorian"
          type="date-time"
          textfieldProps={{
            hiddenLabel: true,
            sx: {
              [`& .${pickersInputBaseClasses.root}`]: {
                backgroundColor: "transparent",
                borderRadius: 0
              }
            },
            slotProps: {
              inputLabel: { disabled: true }
            }
          }}
        />
      </Box>
    </Stack>
  );
}
