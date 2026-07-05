import { memo } from "react";

import { Box, Tooltip, useColorScheme } from "@mui/material";

import {
  useControlBarActions,
  useIsSegmentHovered,
  useIsSegmentSelected
} from "../context/ControlBar.context";

interface SegmentProps {
  index: number;
  summary?: string;
  color: string;
}

const Segment = memo(function SegmentDot({ index, summary, color }: SegmentProps) {
  const { mode, systemMode } = useColorScheme();
  const isHovered = useIsSegmentHovered(index);
  const isSelected = useIsSegmentSelected(index);
  const { setHoveredIndex, clearHoveredIndex, startSelection, updateSelection } =
    useControlBarActions();

  const isDark = (systemMode || mode) === "dark";

  return (
    <Tooltip title={summary} arrow placement="top" enterDelay={200} disableInteractive>
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
            outline: "2px solid",
            ...(isHovered && { opacity: 0.6, transform: "scale(1.1)" }),
            outlineColor: ({ palette }) => (isDark ? palette.common.white : palette.common.black)
          })
        }}
      />
    </Tooltip>
  );
});

export default Segment;
