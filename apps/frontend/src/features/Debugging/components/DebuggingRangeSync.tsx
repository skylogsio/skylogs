"use client";

import { useControlBarActions, useOnSelectionEnd } from "../context/ControlBar.context";
import { useDebuggingTimeRange } from "../context/DebuggingTimeRange.context";

export default function DebuggingRangeSync() {
  const { setStartAbsolute, setEndAbsolute } = useDebuggingTimeRange();
  const { clearSelection } = useControlBarActions();

  useOnSelectionEnd(({ start, end }) => {
    setStartAbsolute(new Date(start));
    setEndAbsolute(new Date(end));
    clearSelection();
  });

  return null;
}
