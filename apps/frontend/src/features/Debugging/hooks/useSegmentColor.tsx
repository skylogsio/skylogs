import { useTheme } from "@mui/material";

import { DebuggingBarType } from "../debugging.type";

export default function useSegmentColor() {
  const { palette } = useTheme();
  const statusColors: Record<DebuggingBarType["type"], string> = {
    unknown: palette.info.main,
    resolved: palette.success.main,
    warning: palette.warning.main,
    critical: palette.error.main
  };
  return statusColors;
}
