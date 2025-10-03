import { useState } from "react";

import {
  Autocomplete,
  Chip,
  Grid2 as Grid,
  IconButton,
  Stack,
  TextField,
  TextFieldProps
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { HiX } from "react-icons/hi";

import { getAlertRuleLabels, getAlertRuleLabelValues } from "@/api/alertRule";

interface ExtraFieldProps {
  keyTextFieldProps: { value: string; onChange: (selectedValue: string | null) => void } & Pick<
    TextFieldProps,
    "error" | "helperText"
  >;
  valueTextFieldProps: { value: string; onChange: (selectedValue: string | null) => void } & Pick<
    TextFieldProps,
    "error" | "helperText"
  >;
  onDelete: () => void;
}

export default function ExtraField({
  keyTextFieldProps,
  valueTextFieldProps,
  onDelete
}: ExtraFieldProps) {
  const [selectedLabel, setSelectedLabel] = useState<string | null>(null);
  const { data: prometheusLabels } = useQuery({
    queryKey: ["prometheus-label"],
    queryFn: () => getAlertRuleLabels()
  });

  const { data: prometheusLabelValues } = useQuery({
    queryKey: ["prometheus-label-value", selectedLabel],
    queryFn: () => getAlertRuleLabelValues(selectedLabel as string),
    enabled: !!selectedLabel
  });

  return (
    <Grid container size={12} spacing={2}>
      <Grid size={6}>
        <Autocomplete
          options={prometheusLabels ?? []}
          freeSolo
          autoSelect
          value={keyTextFieldProps.value}
          onChange={(_, value) => {
            keyTextFieldProps.onChange(value);
            setSelectedLabel(value);
          }}
          renderTags={(value: readonly string[], getItemProps) =>
            value.map((option: string, index: number) => {
              const { key, ...itemProps } = getItemProps({ index });
              return <Chip variant="filled" label={option} key={key} {...itemProps} />;
            })
          }
          renderInput={(params) => (
            <TextField
              {...params}
              slotProps={{
                input: params.InputProps,
                inputLabel: params.InputLabelProps,
                htmlInput: params.inputProps
              }}
              error={keyTextFieldProps.error}
              helperText={keyTextFieldProps.helperText}
              variant="filled"
              label="Key"
            />
          )}
        />
      </Grid>
      <Grid size={6}>
        <Stack direction="row" alignItems="flex-start" spacing={1}>
          <Autocomplete
            options={prometheusLabelValues ?? []}
            freeSolo
            autoSelect
            value={valueTextFieldProps.value}
            onChange={(_, value) => valueTextFieldProps.onChange(value)}
            renderTags={(value: readonly string[], getItemProps) =>
              value.map((option: string, index: number) => {
                const { key, ...itemProps } = getItemProps({ index });
                return <Chip variant="filled" label={option} key={key} {...itemProps} />;
              })
            }
            sx={{ flex: 1 }}
            renderInput={(params) => (
              <TextField
                {...params}
                slotProps={{
                  input: params.InputProps,
                  inputLabel: params.InputLabelProps,
                  htmlInput: params.inputProps
                }}
                error={valueTextFieldProps.error}
                helperText={valueTextFieldProps.helperText}
                variant="filled"
                label="Value"
              />
            )}
          />
          <IconButton
            color="error"
            onClick={onDelete}
            sx={{ marginTop: ({ spacing }) => `${spacing(1)} !important` }}
          >
            <HiX />
          </IconButton>
        </Stack>
      </Grid>
    </Grid>
  );
}
