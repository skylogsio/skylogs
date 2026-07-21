import { MenuItem, TextField, Typography, useTheme } from "@mui/material";

import type { TriggerStateType } from "./BehaviorRuleType";

interface SilentRuleStateFieldProps {
  value: TriggerStateType;
  onChange: (value: TriggerStateType) => void;
  disabled?: boolean;
}

const TRIGGER_STATE_OPTIONS: Array<{ value: TriggerStateType; label: string }> = [
  { value: "resolved", label: "Resolved" },
  { value: "critical", label: "Critical" }
];

const SilentRuleStateField: React.FC<SilentRuleStateFieldProps> = ({
  value,
  onChange,
  disabled = false
}) => {
  const { palette } = useTheme();

  const getStateColor = (state: TriggerStateType) =>
    state === "resolved" ? palette.success.main : palette.error.main;

  return (
    <TextField
      select
      fullWidth
      variant="filled"
      label="Trigger State"
      value={value}
      disabled={disabled}
      onChange={(event) => onChange(event.target.value as TriggerStateType)}
      slotProps={{
        select: {
          renderValue: (selected) => (
            <Typography
              variant="body2"
              sx={{ fontWeight: 500, color: getStateColor(selected as TriggerStateType) }}
            >
              {TRIGGER_STATE_OPTIONS.find((option) => option.value === selected)?.label}
            </Typography>
          )
        }
      }}
    >
      {TRIGGER_STATE_OPTIONS.map((option) => (
        <MenuItem key={option.value} value={option.value}>
          <Typography variant="body2" sx={{ color: getStateColor(option.value) }}>
            {option.label}
          </Typography>
        </MenuItem>
      ))}
    </TextField>
  );
};

export default SilentRuleStateField;
