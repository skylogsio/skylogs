"use client";

import { useEffect, useState } from "react";

import {
  Box,
  Typography,
  Divider,
  List,
  ListItemButton,
  ListItemText,
  alpha,
  useTheme
} from "@mui/material";
import { LocalizationProvider, DateCalendar } from "@mui/x-date-pickers";
import { AdapterDateFns } from "@mui/x-date-pickers/AdapterDateFns";
import { AdapterDateFnsJalali } from "@mui/x-date-pickers/AdapterDateFnsJalali";
import { format } from "date-fns";
import { enUS } from "date-fns/locale";
import { faIR } from "date-fns-jalali/locale";

import { useDebuggingTimeRange } from "@/features/Debugging/DebuggingTimeRange.context";

const TIME_SLOTS = Array.from({ length: 48 }, (_, i) => {
  const hours = Math.floor(i / 2)
    .toString()
    .padStart(2, "0");
  const minutes = i % 2 === 0 ? "00" : "30";
  return `${hours}:${minutes}`;
});

function roundTimeToSlot(date: Date): string {
  const hours = date.getHours().toString().padStart(2, "0");
  const minutes = date.getMinutes() < 30 ? "00" : "30";
  return `${hours}:${minutes}`;
}

function combineDateAndTime(date: Date, time: string): Date {
  const [hours, minutes] = time.split(":").map(Number);
  const combined = new Date(date);
  combined.setHours(hours, minutes, 0, 0);
  return combined;
}

export default function AbsoluteDateTime({
  calendar,
  variant = "start"
}: {
  calendar: "persian" | "gregorian";
  variant?: "start" | "end";
}) {
  const { palette } = useTheme();
  const { start, end, setStartAbsolute, setEndAbsolute } = useDebuggingTimeRange();
  const selection = variant === "end" ? end : start;
  const contextDateTime = selection.dateTime;

  const [viewDate, setViewDate] = useState<Date>(() => new Date(contextDateTime));
  const [selectedDate, setSelectedDate] = useState<Date | null>(() => new Date(contextDateTime));
  const [selectedTime, setSelectedTime] = useState(() => roundTimeToSlot(contextDateTime));

  const adapter = calendar === "persian" ? AdapterDateFnsJalali : AdapterDateFns;
  const locale = calendar === "persian" ? faIR : enUS;

  useEffect(() => {
    if (selection.mode !== "absolute") {
      return;
    }

    setViewDate(new Date(selection.dateTime));
    setSelectedDate(new Date(selection.dateTime));
    setSelectedTime(roundTimeToSlot(selection.dateTime));
  }, [variant, selection.mode, selection.dateTime]);

  const updateDateTime = (date: Date, time: string) => {
    const combined = combineDateAndTime(date, time);

    if (variant === "end") {
      setEndAbsolute(combined);
    } else {
      setStartAbsolute(combined);
    }
  };

  return (
    <LocalizationProvider dateAdapter={adapter} adapterLocale={locale}>
      <Box sx={{ display: "flex", flexDirection: "column" }}>
        <Box sx={{ display: "flex", height: 340 }}>
          <Box sx={{ flex: 1, p: 1 }}>
            <DateCalendar
              value={selectedDate}
              referenceDate={viewDate}
              onChange={(newDate) => {
                setSelectedDate(newDate);
                if (newDate) {
                  updateDateTime(newDate, selectedTime);
                }
              }}
              onMonthChange={(date) => setViewDate(date)}
            />
          </Box>

          <Divider orientation="vertical" flexItem />

          <Box sx={{ width: 140, overflowY: "auto", bgcolor: alpha(palette.divider, 0.02) }}>
            <List sx={{ p: 1 }}>
              {TIME_SLOTS.map((time) => (
                <ListItemButton
                  key={time}
                  selected={selectedTime === time}
                  onClick={() => {
                    setSelectedTime(time);
                    if (selectedDate) {
                      updateDateTime(selectedDate, time);
                    }
                  }}
                  sx={{
                    py: 0.5,
                    borderRadius: 1,
                    mb: 0.5,
                    justifyContent: "center",
                    "&.Mui-selected": {
                      bgcolor: alpha(palette.primary.main, 0.1),
                      color: "primary.main"
                    }
                  }}
                >
                  <ListItemText primary={time} sx={{ textAlign: "center" }} />
                </ListItemButton>
              ))}
            </List>
          </Box>
        </Box>

        <Divider />

        <Box sx={{ p: 1.5 }}>
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
              {selectedDate ? format(selectedDate, "MMM dd, yyyy") : "---"} @ {selectedTime}:00.000
            </Typography>
          </Box>
        </Box>
      </Box>
    </LocalizationProvider>
  );
}
