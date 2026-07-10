import { useState } from "react";

import { Autocomplete, Stack, TextField } from "@mui/material";

import { ALERT_RULE_VARIANTS, type AlertRuleType } from "@/utils/alertRuleUtils";

import type { AlertRuleOption } from "../debugging.type";
import { useAllAlertRules } from "../hooks/useAllAlertRules";

interface AlertRuleSelectorProps {
  value: AlertRuleOption[];
  onChange: (selected: AlertRuleOption[]) => void;
}

function AlertRuleSelector({ value, onChange }: AlertRuleSelectorProps) {
  const { data: options = [], isLoading } = useAllAlertRules();
  const [inputValue, setInputValue] = useState("");

  const selectedIds = new Set(value.map((rule) => rule.id));
  const availableOptions = options.filter((option) => !selectedIds.has(option.id));

  return (
    <Autocomplete
      loading={isLoading}
      options={availableOptions}
      value={null}
      inputValue={inputValue}
      disableCloseOnSelect
      blurOnSelect={false}
      getOptionLabel={(option) => option.name}
      isOptionEqualToValue={(option, selected) => option.id === selected.id}
      onInputChange={(_, newInputValue, reason) => {
        if (reason === "reset") {
          setInputValue("");
          return;
        }
        setInputValue(newInputValue);
      }}
      onChange={(_, option) => {
        if (option) {
          onChange([...value, option]);
          setInputValue("");
        }
      }}
      renderOption={(props, option) => {
        const { key, ...optionProps } = props;
        const variant = ALERT_RULE_VARIANTS[option.type as AlertRuleType];

        return (
          <li key={key} {...optionProps}>
            <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
              {variant && <variant.Icon size={variant.defaultSize} color={variant.defaultColor} />}
              <span>{option.name}</span>
            </Stack>
          </li>
        );
      }}
      renderInput={(params) => (
        <TextField {...params} variant="filled" label="Alert Rules" placeholder="Add alert rule" />
      )}
      sx={{ width: 350, flexShrink: 0 }}
    />
  );
}

export default AlertRuleSelector;
