"use client";

import { useEffect, useState } from "react";

import { TextFieldProps } from "@mui/material";
import { AdapterDateFns } from "@mui/x-date-pickers/AdapterDateFns";
import { AdapterDateFnsJalali } from "@mui/x-date-pickers/AdapterDateFnsJalali";
import { DatePicker, DatePickerSlotProps } from "@mui/x-date-pickers/DatePicker";
import { DateTimePicker } from "@mui/x-date-pickers/DateTimePicker";
import { LocalizationProvider } from "@mui/x-date-pickers/LocalizationProvider";
import { pickersInputBaseClasses } from "@mui/x-date-pickers/PickersTextField";
import { TimePicker } from "@mui/x-date-pickers/TimePicker";
import { format as formatGregorian } from "date-fns";
import { enUS } from "date-fns/locale";
import { format as formatJalali } from "date-fns-jalali";
import { faIR as faIRJalali } from "date-fns-jalali/locale";

export type CalendarType = "gregorian" | "persian";
export type PickerType = "time" | "date" | "date-time";

type SingleChangePayload = {
  iso: string | null;
  formatted: string;
  calendar: CalendarType;
  type: PickerType;
};

type Props = {
  calendar: CalendarType;
  type: PickerType;
  value?: string | null;
  onChange?: (payload: SingleChangePayload) => void;
  label?: string;
  disabled?: boolean;
  textfieldProps?: TextFieldProps;
};

const DISPLAY_FORMAT: Record<PickerType, string> = {
  time: "HH:mm",
  date: "yyyy/MM/dd",
  "date-time": "yyyy/MM/dd HH:mm"
};

export default function DateTimeInput({
  calendar,
  type,
  value = null,
  onChange,
  label,
  disabled = false,
  textfieldProps
}: Props) {
  const isPersian = calendar === "persian";
  const Adapter = isPersian ? AdapterDateFnsJalali : AdapterDateFns;
  const adapterLocale = isPersian ? faIRJalali : enUS;

  const [open, setOpen] = useState(false);

  const [isoValue, setIsoValue] = useState<string | null>(value);

  useEffect(() => {
    setIsoValue(value ?? null);
  }, [value]);

  const toDate = (iso: string | null): Date | null => {
    if (!iso) return null;
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
  };

  const formatValue = (date: Date, mode: PickerType) => {
    const fmt = DISPLAY_FORMAT[mode];
    return isPersian
      ? formatJalali(date, fmt, { locale: faIRJalali })
      : formatGregorian(date, fmt, { locale: enUS });
  };

  const getPickerDate = (pickerValue: unknown): Date | null =>
    pickerValue instanceof Date && !Number.isNaN(pickerValue.getTime()) ? pickerValue : null;

  const commonTextFieldProps: TextFieldProps = {
    size: "small" as const,
    fullWidth: true,
    ...textfieldProps,
    variant: "filled" as const,
    slotProps: {
      ...textfieldProps?.slotProps,
      input: { disableUnderline: true, ...textfieldProps?.slotProps?.input }
    },
    onClick: () => {
      if (!disabled) setOpen(true);
    },
    sx: {
      [`& .${pickersInputBaseClasses.root}`]: {
        borderRadius: "0.55rem"
      },

      ...textfieldProps?.sx
    }
  };

  const commonSlotProps: DatePickerSlotProps = {
    textField: commonTextFieldProps as never,
    openPickerButton: { sx: { display: "none" } },
    popper: {
      placement: "bottom-start",
      modifiers: [
        {
          name: "flip",
          enabled: true,
          options: {
            fallbackPlacements: ["top-start", "bottom-end", "top-end"],
            padding: 8
          }
        },
        {
          name: "preventOverflow",
          enabled: true,
          options: {
            altAxis: true,
            tether: true,
            padding: 8,
            rootBoundary: "viewport"
          }
        }
      ]
    },
    desktopPaper: {
      sx: {
        maxHeight: "calc(100vh - 32px)",
        overflow: "auto"
      }
    }
  };

  const pickerValue = toDate(isoValue);
  const hasValue = pickerValue !== null;

  const handleClear = (pickerType: PickerType) => {
    setIsoValue(null);
    onChange?.({
      iso: null,
      formatted: "",
      calendar,
      type: pickerType
    });
  };

  const handleSingleChange = (newVal: Date | null, pickerType: PickerType) => {
    if (!newVal || Number.isNaN(newVal.getTime())) {
      handleClear(pickerType);
      return;
    }

    const iso = newVal.toISOString();
    setIsoValue(iso);

    onChange?.({
      iso,
      formatted: formatValue(newVal, pickerType),
      calendar,
      type: pickerType
    });
  };

  const getSlotProps = (pickerType: PickerType): DatePickerSlotProps => ({
    ...commonSlotProps,
    field: {
      clearable: hasValue && !disabled,
      onClear: (event) => {
        event.stopPropagation();
        handleClear(pickerType);
      }
    },
    clearButton: {
      size: "small",
      onClick: (event) => {
        event.stopPropagation();
        handleClear(pickerType);
      },
      sx: {
        p: 0.5,
        mr: 0.5
      }
    },
    openPickerButton: { sx: { display: "none" } }
  });

  return (
    <LocalizationProvider
      key={`${calendar}-${type}`}
      dateAdapter={Adapter}
      adapterLocale={adapterLocale}
    >
      {type === "time" && (
        <TimePicker
          label={label}
          value={pickerValue}
          onChange={(newVal) => handleSingleChange(getPickerDate(newVal), "time")}
          ampm={false}
          format={DISPLAY_FORMAT.time}
          disabled={disabled}
          open={open}
          onOpen={() => setOpen(true)}
          onClose={() => setOpen(false)}
          slotProps={getSlotProps("time")}
        />
      )}

      {type === "date" && (
        <DatePicker
          label={label}
          value={pickerValue}
          onChange={(newVal) => handleSingleChange(getPickerDate(newVal), "date")}
          format={DISPLAY_FORMAT.date}
          disabled={disabled}
          open={open}
          onOpen={() => setOpen(true)}
          onClose={() => setOpen(false)}
          slotProps={getSlotProps("date")}
        />
      )}

      {type === "date-time" && (
        <DateTimePicker
          label={label}
          value={pickerValue}
          onChange={(newVal) => handleSingleChange(getPickerDate(newVal), "date-time")}
          ampm={false}
          format={DISPLAY_FORMAT["date-time"]}
          disabled={disabled}
          open={open}
          onOpen={() => setOpen(true)}
          onClose={() => setOpen(false)}
          slotProps={getSlotProps("date-time")}
        />
      )}
    </LocalizationProvider>
  );
}
