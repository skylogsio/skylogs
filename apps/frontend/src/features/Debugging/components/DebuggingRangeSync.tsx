"use client";

import { useControlBarActions, useOnSelectionEnd } from "../context/ControlBar.context";
import { useDebuggingTimeRange } from "../context/DebuggingTimeRange.context";

export default function DebuggingRangeSync() {
  const { zoomToRange } = useDebuggingTimeRange();
  const { clearSelection } = useControlBarActions();

  useOnSelectionEnd(({ start, end }) => {
    zoomToRange(new Date(start), new Date(end));
    clearSelection();
  });

  return null;
}
