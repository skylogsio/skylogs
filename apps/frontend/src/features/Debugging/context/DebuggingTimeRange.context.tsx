"use client";

import { createContext, useCallback, useContext, useState, type PropsWithChildren } from "react";

import { format } from "date-fns";

export type RelativeTimeUnit = "minutes" | "hours" | "days" | "weeks" | "months" | "years";
export type DebuggingTimeMode = "absolute" | "relative" | "now";

export interface RelativeTimeSelection {
  value: number;
  unit: RelativeTimeUnit;
}

export interface DebuggingTimeSelection {
  dateTime: Date;
  mode: DebuggingTimeMode;
  relative?: RelativeTimeSelection;
}

const UNIT_LABELS: Record<RelativeTimeUnit, { singular: string; plural: string }> = {
  minutes: { singular: "Minute ago", plural: "Minutes ago" },
  hours: { singular: "Hour ago", plural: "Hours ago" },
  days: { singular: "Day ago", plural: "Days ago" },
  weeks: { singular: "Week ago", plural: "Weeks ago" },
  months: { singular: "Month ago", plural: "Months ago" },
  years: { singular: "Year ago", plural: "Years ago" }
};

export function computeRelativeDate(
  value: number,
  unit: RelativeTimeUnit,
  referenceDate: Date
): Date {
  const date = new Date(referenceDate);

  switch (unit) {
    case "minutes":
      date.setMinutes(date.getMinutes() - value);
      break;
    case "hours":
      date.setHours(date.getHours() - value);
      break;
    case "days":
      date.setDate(date.getDate() - value);
      break;
    case "weeks":
      date.setDate(date.getDate() - value * 7);
      break;
    case "months":
      date.setMonth(date.getMonth() - value);
      break;
    case "years":
      date.setFullYear(date.getFullYear() - value);
      break;
  }

  return date;
}

export function formatRelativeTimeLabel(value: number, unit: RelativeTimeUnit): string {
  const labels = UNIT_LABELS[unit];
  const label = value === 1 ? labels.singular : labels.plural;
  return `${value} ${label}`;
}

export function formatDebuggingDateTime(date: Date): string {
  return format(date, "yyyy/MM/dd HH:mm:ss");
}

export function formatDebuggingTimeLabel(selection: DebuggingTimeSelection): string {
  if (selection.mode === "now") {
    return "Now";
  }

  if (selection.mode === "relative" && selection.relative) {
    return formatRelativeTimeLabel(selection.relative.value, selection.relative.unit);
  }

  return formatDebuggingDateTime(selection.dateTime);
}

function createInitialState() {
  const referenceNow = new Date();

  return {
    referenceNow,
    start: {
      dateTime: computeRelativeDate(15, "minutes", referenceNow),
      mode: "relative" as const,
      relative: { value: 15, unit: "minutes" as const }
    },
    end: {
      dateTime: referenceNow,
      mode: "now" as const
    }
  };
}
interface DebuggingTimeRangeContextType {
  referenceNow: Date;
  start: DebuggingTimeSelection;
  end: DebuggingTimeSelection;
  startDateTime: Date;
  endDateTime: Date;
  isTimeRangeInvalid: boolean;
  timeRangeError: string | null;
  setStartAbsolute: (date: Date) => void;
  setEndAbsolute: (date: Date) => void;
  setStartRelative: (value: number, unit: RelativeTimeUnit) => void;
  setEndRelative: (value: number, unit: RelativeTimeUnit) => void;
  setStartNow: () => void;
  setEndNow: () => void;
}

const DebuggingTimeRangeContext = createContext<DebuggingTimeRangeContextType | undefined>(
  undefined
);

export function DebuggingTimeRangeProvider({ children }: PropsWithChildren) {
  const [{ referenceNow, start: initialStart, end: initialEnd }] = useState(createInitialState);
  const [start, setStart] = useState<DebuggingTimeSelection>(initialStart);
  const [end, setEnd] = useState<DebuggingTimeSelection>(initialEnd);

  const setStartAbsolute = (date: Date) => {
    setStart({ dateTime: date, mode: "absolute" });
  };

  const setEndAbsolute = (date: Date) => {
    setEnd({ dateTime: date, mode: "absolute" });
  };

  const setStartRelative = useCallback(
    (value: number, unit: RelativeTimeUnit) => {
      setStart({
        dateTime: computeRelativeDate(value, unit, referenceNow),
        mode: "relative",
        relative: { value, unit }
      });
    },
    [referenceNow]
  );

  const setEndRelative = useCallback(
    (value: number, unit: RelativeTimeUnit) => {
      setEnd({
        dateTime: computeRelativeDate(value, unit, referenceNow),
        mode: "relative",
        relative: { value, unit }
      });
    },
    [referenceNow]
  );

  const setStartNow = useCallback(() => {
    setStart({ dateTime: referenceNow, mode: "now" });
  }, [referenceNow]);

  const setEndNow = useCallback(() => {
    setEnd({ dateTime: referenceNow, mode: "now" });
  }, [referenceNow]);
  const isTimeRangeInvalid = end.dateTime.getTime() <= start.dateTime.getTime();
  const timeRangeError = isTimeRangeInvalid ? "End time must be after the start time" : null;

  return (
    <DebuggingTimeRangeContext.Provider
      value={{
        referenceNow,
        start,
        end,
        startDateTime: start.dateTime,
        endDateTime: end.dateTime,
        isTimeRangeInvalid,
        timeRangeError,
        setStartAbsolute,
        setEndAbsolute,
        setStartRelative,
        setEndRelative,
        setStartNow,
        setEndNow
      }}
    >
      {children}
    </DebuggingTimeRangeContext.Provider>
  );
}

export function useDebuggingTimeRange() {
  const context = useContext(DebuggingTimeRangeContext);
  if (context === undefined) {
    throw new Error("useDebuggingTimeRange must be used within a DebuggingTimeRangeProvider");
  }
  return context;
}
