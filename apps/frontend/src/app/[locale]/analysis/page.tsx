"use client";
import { useState } from "react";

import { Chip, FormControl, InputLabel, MenuItem, Select, Stack, TextField } from "@mui/material";

import DateTimeInput2 from "@/components/DateTimeInput";
type CalendarType = "gregorian" | "persian";
export default function MyForm() {
  const [calendar, setCalendar] = useState<CalendarType>("persian");
  const [result, setResult] = useState<{
    iso: string | null;
    formatted: string;
    calendar: CalendarType;
    type?: "time" | "date" | "date-time";
  } | null>(null);
  return (
    <Stack spacing={3}>
      <FormControl fullWidth>
        <InputLabel id="calendar-label">Calendar</InputLabel>
        <Select
          labelId="calendar-label"
          label="Calendar"
          value={calendar}
          onChange={(e) => setCalendar(e.target.value as CalendarType)}
        >
          <MenuItem value="gregorian">Gregorian</MenuItem>
          <MenuItem value="persian">Persian (Jalali)</MenuItem>
        </Select>
      </FormControl>

      <DateTimeInput2
        calendar={calendar}
        type="date-time"
        onChange={setResult}
        textfieldProps={{
          hiddenLabel: true,
          slotProps: {
            input: {
              startAdornment: <Chip label="Start Date" size="small" sx={{ marginRight: 1}} />
            },
            inputLabel: { disabled: true }
          }
        }}
      />
      <TextField variant="filled" label="Selected value" value={result?.formatted ?? ""} />
    </Stack>
  );
}
