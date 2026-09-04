import { useState } from "react";

import { Autocomplete, Grid, IconButton, Stack, TextField, TextFieldProps } from "@mui/material";
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
          renderInput={(params) => (
            <TextField
              id={params.id}
              disabled={params.disabled}
              fullWidth={params.fullWidth}
              size={params.size}
              slotProps={params.slotProps}
              error={keyTextFieldProps.error}
              helperText={keyTextFieldProps.helperText}
              variant="filled"
              label="Key"
            />
          )}
        />
      </Grid>
      <Grid size={6}>
        <Stack
          direction="row"
          spacing={1}
          sx={{
            alignItems: "flex-start"
          }}
        >
          <Autocomplete
            options={prometheusLabelValues ?? []}
            freeSolo
            autoSelect
            value={valueTextFieldProps.value}
            onChange={(_, value) => valueTextFieldProps.onChange(value)}
            sx={{ flex: 1 }}
            renderInput={(params) => (
              <TextField
                id={params.id}
                disabled={params.disabled}
                fullWidth={params.fullWidth}
                size={params.size}
                slotProps={params.slotProps}
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
