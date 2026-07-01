"use client";

import { useState } from "react";

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

const TIME_SLOTS = Array.from({ length: 48 }, (_, i) => {
  const hours = Math.floor(i / 2)
    .toString()
    .padStart(2, "0");
  const minutes = i % 2 === 0 ? "00" : "30";
  return `${hours}:${minutes}`;
});

export const AbsoluteTabDateTime = ({ calendar }: { calendar: "persian" | "gregorian" }) => {
  const { palette } = useTheme();
  const [viewDate, setViewDate] = useState<Date>(new Date());
  const [selectedDate, setSelectedDate] = useState<Date | null>(new Date());
  const [selectedTime, setSelectedTime] = useState("11:00");

  const adapter = calendar === "persian" ? AdapterDateFnsJalali : AdapterDateFns;
  const locale = calendar === "persian" ? faIR : enUS;

  return (
    <LocalizationProvider dateAdapter={adapter} adapterLocale={locale}>
      <Box sx={{ display: "flex", flexDirection: "column" }}>
        <Box sx={{ display: "flex", height: 340 }}>
          <Box sx={{ flex: 1, p: 1 }}>
            <DateCalendar
              value={selectedDate}
              referenceDate={viewDate}
              onChange={(newDate) => setSelectedDate(newDate)}
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
                  onClick={() => setSelectedTime(time)}
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

        {/* فوتر نمایش تاریخ انتخاب شده */}
        <Box sx={{ p: 1.5 }}>
          <Box
            sx={{
              display: "flex",
              border: `1px solid ${palette.divider}`,
              borderRadius: "8px",
              overflow: "hidden",
              fontSize: "0.85rem"
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
              Start date
            </Typography>
            <Typography variant="body1" sx={{ flex: 1, px: 2, py: 1 }}>
              {selectedDate ? format(selectedDate, "MMM dd, yyyy") : "---"} @ {selectedTime}:00.000
            </Typography>
          </Box>
        </Box>
      </Box>
    </LocalizationProvider>
  );
};
