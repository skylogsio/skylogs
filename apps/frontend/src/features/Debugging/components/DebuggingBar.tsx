import { Box, Typography, Stack, Tooltip } from "@mui/material";
import { format } from "date-fns";

import type { DebuggingBarType } from "../debugging.type";
import useSegmentColor from "../hooks/useSegmentColor";

export default function DebuggingBar({ rule }: { rule: DebuggingBarType }) {
  const statusColors = useSegmentColor();
  const minTime = Math.min(...rule.segments.map((s) => s.fromTime));
  const maxTime = Math.max(...rule.segments.map((s) => s.toTime));

  return (
    <Stack direction="row" spacing={1} sx={{ mb: 2, borderRadius: 2 }}>
      <Stack sx={{ alignItems: "flex-start", width: 170, cursor: "default" }}>
        <Typography
          sx={{
            fontWeight: 600,
            textWrap: "nowrap",
            textOverflow: "ellipsis",
            width: "100%",
            overflow: "hidden"
          }}
        >
          {rule.name}
        </Typography>
        <Typography variant="caption" color="textDisabled">
          ({rule.type})
        </Typography>
      </Stack>

      <Stack sx={{ flex: 1 }}>
        <Stack direction="row" spacing={0.25} sx={{ width: "100%", borderRadius: 1 }}>
          {rule.segments.map((segment) => {
            return Array.from({ length: segment.count }).map((_, index) => (
              <Tooltip key={index} title={segment.summary} arrow placement="top">
                <Box
                  key={index}
                  sx={{
                    flex: 1,
                    height: 28,
                    backgroundColor: statusColors[segment.status] ?? statusColors.unknown,
                    cursor: "pointer",
                    borderRadius: 1,
                    transition: "all 50ms ease-out",
                    "&:hover": { opacity: 0.8, transform: "scale(1.3)" }
                  }}
                />
              </Tooltip>
            ));
          })}
        </Stack>

        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
          <Typography variant="caption" color="text.secondary">
            {format(minTime, "yyyy/MM/dd HH:mm:ss")}
          </Typography>
          <Typography variant="caption" color="text.secondary">
            {format(maxTime, "yyyy/MM/dd HH:mm:ss")}
          </Typography>
        </Stack>
      </Stack>
    </Stack>
  );
}
