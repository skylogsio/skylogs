import { memo, useMemo } from "react";

import { Typography, Stack } from "@mui/material";
import { format } from "date-fns";

import type { DebuggingBarType } from "../debugging.type";
import Segment from "./Segment";
import useSegmentColor from "../hooks/useSegmentColor";

function DebuggingBar({ rule }: { rule: DebuggingBarType }) {
  const statusColors = useSegmentColor();
  const minTime = Math.min(...rule.segments.map((s) => s.fromTime));
  const maxTime = Math.max(...rule.segments.map((s) => s.toTime));

  const dots = useMemo(() => {
    let dotIndex = -1;
    return rule.segments.flatMap((segment) =>
      Array.from({ length: segment.count }).map(() => {
        dotIndex += 1;
        return {
          index: dotIndex,
          summary: segment.summary,
          color: statusColors[segment.status] ?? statusColors.unknown
        };
      })
    );
  }, [rule.segments, statusColors]);

  return (
    <Stack direction="row" spacing={1} sx={{ mb: 2, borderRadius: 2 }}>
      <Stack sx={{ alignItems: "flex-start", width: 170, cursor: "default" }}>
        <Typography
          sx={{
            textWrap: "nowrap",
            textOverflow: "ellipsis",
            width: "100%",
            overflow: "hidden"
          }}
        >
          {rule.name}
        </Typography>
        <Typography
          variant="caption"
          color="textDisabled"
          sx={{ textTransform: "uppercase", textDecoration: "dot" }}
        >
          ({rule.type})
        </Typography>
      </Stack>

      <Stack sx={{ flex: 1 }}>
        <Stack direction="row" spacing={0.25} sx={{ width: "100%", borderRadius: 1 }}>
          {dots.map((dot) => (
            <Segment key={dot.index} index={dot.index} summary={dot.summary} color={dot.color} />
          ))}
        </Stack>

        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
          <Typography variant="caption" color="textSecondary">
            {format(minTime, "yyyy/MM/dd HH:mm:ss")}
          </Typography>
          <Typography variant="caption" color="textSecondary">
            {format(maxTime, "yyyy/MM/dd HH:mm:ss")}
          </Typography>
        </Stack>
      </Stack>
    </Stack>
  );
}

export default memo(DebuggingBar);
