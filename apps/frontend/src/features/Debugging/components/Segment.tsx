import { memo } from "react";

import { Box, Tooltip } from "@mui/material";

import { useControlBarActions, useIsSegmentHovered } from "../context/ControlBar.context";

interface SegmentProps {
  index: number;
  summary?: string;
  color: string;
}

const Segment = memo(function SegmentDot({ index, summary, color }: SegmentProps) {
  const isHovered = useIsSegmentHovered(index);
  const { setHoveredIndex, clearHoveredIndex } = useControlBarActions();

  return (
    <Tooltip title={summary} arrow placement="top" enterDelay={200} disableInteractive>
      <Box
        onMouseEnter={() => setHoveredIndex(index)}
        onMouseLeave={clearHoveredIndex}
        sx={{
          flex: 1,
          height: 28,
          backgroundColor: color,
          cursor: "pointer",
          borderRadius: 1,
          transition: "all 50ms ease-out",
          ...(isHovered
            ? { opacity: 0.8, transform: "scale(1.3)" }
            : { "&:hover": { opacity: 0.8, transform: "scale(1.3)" } })
        }}
      />
    </Tooltip>
  );
});

export default Segment;
