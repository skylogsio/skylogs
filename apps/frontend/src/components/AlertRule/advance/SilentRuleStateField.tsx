import { alpha, Button, Stack, Typography, useTheme } from "@mui/material";

import type { TriggerStateType } from "./BehaviorRuleType";

interface SilentRuleStateFieldProps {
  value: TriggerStateType;
  onChange: (value: TriggerStateType) => void;
}

const SilentRuleStateField: React.FC<SilentRuleStateFieldProps> = ({ value, onChange }) => {
  const { palette } = useTheme();

  return (
    <Stack direction="row" spacing={2} sx={{ alignItems: "center" }}>
      <Typography variant="body2" sx={{ mb: 1, fontWeight: "bold", color: palette.text.secondary }}>
        Trigger State
      </Typography>
      <Stack
        direction="row"
        sx={{
          display: "inline-flex",
          backgroundColor:
            palette.mode === "dark" ? "rgba(255, 255, 255, 0.05)" : "rgba(0, 0, 0, 0.04)",
          borderRadius: 2,
          padding: 0.75,
          gap: 0.75
        }}
      >
        <Button
          onClick={() => onChange("resolved")}
          sx={{
            minWidth: 100,
            py: 0.5,
            textTransform: "none",
            fontSize: 12,
            borderRadius: 1.5,
            backgroundColor:
              value === "resolved" ? alpha(palette.success.main, 0.1) : "transparent",
            color: value === "resolved" ? palette.success.main : palette.text.secondary,
            border:
              value === "resolved"
                ? `1.5px solid ${alpha(palette.success.main, 0.2)}`
                : "1.5px solid transparent",
            "&:hover": {
              backgroundColor: alpha(palette.success.main, value === "resolved" ? 0.15 : 0.08),
              border: `1.5px solid ${alpha(palette.success.main, 0.8)}`,
              color: palette.success.main
            },
            transition: "all 0.2s ease"
          }}
        >
          Resolved
        </Button>
        <Button
          onClick={() => onChange("critical")}
          sx={{
            minWidth: 100,
            py: 0.5,
            textTransform: "none",
            fontSize: 12,
            borderRadius: 1.5,
            backgroundColor: value === "critical" ? alpha(palette.error.main, 0.1) : "transparent",
            color: value === "critical" ? palette.error.main : palette.text.secondary,
            border:
              value === "critical"
                ? `1.5px solid ${alpha(palette.error.main, 0.2)}`
                : "1.5px solid transparent",
            "&:hover": {
              backgroundColor: alpha(palette.error.main, value === "critical" ? 0.15 : 0.08),
              border: `1.5px solid ${alpha(palette.error.main, 0.8)}`,
              color: palette.error.main
            },
            transition: "all 0.2s ease"
          }}
        >
          Critical
        </Button>
      </Stack>
    </Stack>
  );
};

export default SilentRuleStateField;
