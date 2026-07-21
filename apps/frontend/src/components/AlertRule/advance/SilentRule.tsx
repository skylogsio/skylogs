import { useParams } from "next/navigation";
import React, { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Autocomplete, Box, Button, Chip, Grid, Stack, TextField, Typography } from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm, useFieldArray, Controller } from "react-hook-form";
import { HiPlus } from "react-icons/hi";
import { toast } from "react-toastify";
import * as z from "zod";

import { CreateUpdateModal } from "@/@types/global";
import {
  addBehaviorRuleToAlertRule,
  editBehaviorRuleToAlertRule,
  getSelectableAlertRulesForBehaviorRule
} from "@/api/alertRule";
import DateTimeInput from "@/components/DateTimeInput";
import ModalContainer from "@/components/Modal";

import type { SilentRuleItem, TriggerStateType } from "./BehaviorRuleType";
import SilentRuleStateField from "./SilentRuleStateField";
import ExtraField from "../Forms/ExtraField";

const filtersSchema = z.object({
  key: z.string().trim(),
  value: z.string().trim()
});

const silentConditionMessage =
  "At least one silent condition is required: Alert Rules, From, To or Key-Value.";

function hasSilentCondition(data: {
  dependsOnAlertRuleIds: string[];
  filters: Array<{ key: string; value: string }>;
  startsAt: number | null;
  endsAt: number | null;
}) {
  return (
    data.dependsOnAlertRuleIds.length > 0 ||
    data.filters.some((filter) => filter.key.trim() && filter.value.trim()) ||
    data.startsAt !== null ||
    data.endsAt !== null
  );
}

const silentRuleSchema = z
  .object({
    name: z.string().min(1, "Name is required"),
    dependsOnAlertRuleIds: z.array(z.string()),
    triggerState: z.enum(["resolved", "critical"]),
    startsAt: z.number().nullable(),
    endsAt: z.number().nullable(),
    filters: z.array(filtersSchema)
  })
  .superRefine((data, ctx) => {
    data.filters.forEach((filter, index) => {
      if (!filter.key.trim()) {
        ctx.addIssue({
          code: "custom",
          message: "This field is Required.",
          path: ["filters", index, "key"]
        });
      }

      if (!filter.value.trim()) {
        ctx.addIssue({
          code: "custom",
          message: "This field is Required.",
          path: ["filters", index, "value"]
        });
      }
    });

    if (data.startsAt !== null && data.endsAt !== null && data.endsAt <= data.startsAt) {
      ctx.addIssue({
        code: "custom",
        message: "To date/time must be after from date/time",
        path: ["endsAt"]
      });
    }
  })
  .refine(hasSilentCondition, {
    message: silentConditionMessage
  });

type SilentRuleFormType = z.infer<typeof silentRuleSchema>;

type SilentRulePayload = SilentRuleFormType & {
  type: "silent";
};

const defaultKeyValue = { key: "", value: "" };

interface SilentRuleModalProps {
  open: boolean;
  onClose: () => void;
  data: "NEW" | SilentRuleItem;
}

interface SelectableAlertRule {
  id: string;
  name: string;
}

const emptyFormValues: SilentRuleFormType = {
  name: "",
  dependsOnAlertRuleIds: [],
  triggerState: "resolved",
  startsAt: null,
  endsAt: null,
  filters: []
};

function normalizeTriggerState(value: TriggerStateType | null | undefined): TriggerStateType {
  return value === "resolved" || value === "critical" ? value : "resolved";
}

function toIsoValue(timestamp: number | null): string | null {
  if (timestamp === null) return null;
  const date = new Date(timestamp);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

function getFormValues(
  data: CreateUpdateModal<SilentRuleItem>
): SilentRuleFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    dependsOnAlertRuleIds: data.dependsOnAlertRuleIds ?? [],
    triggerState: normalizeTriggerState(data.triggerState),
    startsAt: data.startsAt ?? null,
    endsAt: data.endsAt ?? null,
    filters: data.filters ?? []
  };
}

const SilentRuleModal: React.FC<SilentRuleModalProps> = ({ open, onClose, data }) => {
  const queryClient = useQueryClient();
  const { alertId } = useParams<{ alertId: string }>();
  const ruleId = data !== "NEW" ? (data as SilentRuleFormType & { id: string }).id : "";

  const {
    register,
    trigger,
    control,
    handleSubmit,
    formState: { errors, submitCount },
    setValue,
    watch,
    reset
  } = useForm<SilentRuleFormType>({
    resolver: zodResolver(silentRuleSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit",
    reValidateMode: "onChange"
  });

  const { data: selectableAlertRules } = useQuery<SelectableAlertRule[]>({
    queryKey: ["selectable-alert-rules", alertId],
    queryFn: () => getSelectableAlertRulesForBehaviorRule(alertId),
    enabled: open
  });

  const handleClose = () => {
    onClose();
    queryClient.invalidateQueries({ queryKey: ["get-behavior-rule"] });
  };

  const { mutate: addSilentRule, isPending: isAddingSilentRule } = useMutation({
    mutationFn: (body: SilentRulePayload) => addBehaviorRuleToAlertRule(alertId, body),
    onSuccess: () => {
      toast.success("Silent Rule Created Successfully.");
      handleClose();
    }
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "filters"
  });

  const { mutate: editSilentRule, isPending: isEditingSilentRule } = useMutation({
    mutationFn: (body: SilentRulePayload) => editBehaviorRuleToAlertRule(alertId, ruleId, body),
    onSuccess: () => {
      toast.success("Silent Rule Updated Successfully.");
      handleClose();
    }
  });

  const handleFormSubmit = (formData: SilentRuleFormType) => {
    if (!hasSilentCondition(formData)) {
      return;
    }

    const body: SilentRulePayload = {
      ...formData,
      type: "silent",
      filters: formData.filters.filter((filter) => filter.key.trim() && filter.value.trim())
    };

    if (data === "NEW") {
      addSilentRule(body);
    } else {
      editSilentRule(body);
    }
  };

  useEffect(() => {
    reset(getFormValues(data));
  }, [data, open, reset]);

  const isLoading = isAddingSilentRule || isEditingSilentRule;
  const alertRules = selectableAlertRules ?? [];
  const selectedAlertRuleIds = watch("dependsOnAlertRuleIds");
  const isTriggerStateEnabled = selectedAlertRuleIds.length > 0;
  const silentConditionValues = watch(["dependsOnAlertRuleIds", "filters", "startsAt", "endsAt"]);
  const hasValidSilentCondition = hasSilentCondition({
    dependsOnAlertRuleIds: silentConditionValues[0],
    filters: silentConditionValues[1],
    startsAt: silentConditionValues[2],
    endsAt: silentConditionValues[3]
  });
  const showSilentConditionError = submitCount > 0 && !hasValidSilentCondition;

  return (
    <ModalContainer
      open={open}
      onClose={handleClose}
      title="Silent Rule"
      width="90%"
      maxWidth="600px"
    >
      <form
        onSubmit={handleSubmit(handleFormSubmit, () => {
          void trigger();
        })}
      >
        <Box sx={{ mt: 2 }}>
          <Grid container spacing={2}>
            <Grid size={12}>
              <TextField
                label="Name"
                variant="filled"
                fullWidth
                error={!!errors.name}
                helperText={errors.name?.message}
                {...register("name")}
              />
            </Grid>

            <Grid size={8}>
              <Controller
                control={control}
                name="dependsOnAlertRuleIds"
                render={({ field }) => {
                  const selectedAlertRules = alertRules.filter((rule: SelectableAlertRule) =>
                    field.value?.includes(rule.id)
                  );

                  return (
                    <Autocomplete
                      multiple
                      options={alertRules}
                      getOptionLabel={(option) => option.name}
                      value={selectedAlertRules}
                      onChange={(_, newValue) => {
                        field.onChange(newValue.map((rule) => rule.id));
                        void trigger();
                      }}
                      isOptionEqualToValue={(option, value) => option.id === value.id}
                      renderValue={(value, getItemProps) =>
                        value.map((option, index) => {
                          const { key, ...itemProps } = getItemProps({ index });
                          return <Chip key={key} label={option.name} size="small" {...itemProps} />;
                        })
                      }
                      renderInput={(params) => (
                        <TextField
                          {...params}
                          slotProps={{
                            ...params.slotProps,
                            input: params.slotProps.input,
                            inputLabel: params.slotProps.inputLabel,
                            htmlInput: params.slotProps.htmlInput
                          }}
                          variant="filled"
                          label="Alert Rules"
                          error={!!errors.dependsOnAlertRuleIds}
                          helperText={errors.dependsOnAlertRuleIds?.message as string}
                        />
                      )}
                    />
                  );
                }}
              />
            </Grid>
            <Grid size={4}>
              <Controller
                control={control}
                name="triggerState"
                render={({ field }) => (
                  <SilentRuleStateField
                    value={field.value}
                    onChange={(value) => field.onChange(value)}
                    disabled={!isTriggerStateEnabled}
                  />
                )}
              />
            </Grid>

            <Grid size={6}>
              <Controller
                control={control}
                name="startsAt"
                render={({ field }) => (
                  <DateTimeInput
                    calendar="gregorian"
                    type="date-time"
                    label="From"
                    value={toIsoValue(field.value)}
                    onChange={(payload) => {
                      field.onChange(payload.iso ? new Date(payload.iso).getTime() : null);
                      void trigger();
                    }}
                    textfieldProps={{
                      error: !!errors.startsAt,
                      helperText: errors.startsAt?.message
                    }}
                  />
                )}
              />
            </Grid>
            <Grid size={6}>
              <Controller
                control={control}
                name="endsAt"
                render={({ field }) => (
                  <DateTimeInput
                    calendar="gregorian"
                    type="date-time"
                    label="To"
                    value={toIsoValue(field.value)}
                    onChange={(payload) => {
                      field.onChange(payload.iso ? new Date(payload.iso).getTime() : null);
                      void trigger();
                    }}
                    textfieldProps={{
                      error: !!errors.endsAt,
                      helperText: errors.endsAt?.message
                    }}
                  />
                )}
              />
            </Grid>
          </Grid>

          <Stack spacing={1} sx={{ mt: 3 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: "bold" }}>
              Key Value
            </Typography>
            {fields.map((field, index) => (
              <ExtraField
                key={field.id}
                keyTextFieldProps={{
                  value: watch(`filters.${index}.key`),
                  onChange: (value) => {
                    setValue(`filters.${index}.key`, value ?? "", { shouldValidate: true });
                  },
                  error: !!errors.filters?.[index]?.key,
                  helperText: errors.filters?.[index]?.key?.message
                }}
                valueTextFieldProps={{
                  value: watch(`filters.${index}.value`),
                  onChange: (value) => {
                    setValue(`filters.${index}.value`, value ?? "", { shouldValidate: true });
                  },
                  error: !!errors.filters?.[index]?.value,
                  helperText: errors.filters?.[index]?.value?.message
                }}
                onDelete={() => {
                  remove(index);
                  void trigger();
                }}
              />
            ))}
            {!!errors.filters && (
              <Typography variant="caption" color="error">
                {errors.filters?.message}
              </Typography>
            )}
            <Button
              startIcon={<HiPlus />}
              variant="outlined"
              fullWidth
              onClick={() => {
                append(defaultKeyValue);
                void trigger();
              }}
            >
              Add New Key Value
            </Button>
          </Stack>

          {showSilentConditionError && (
            <Grid size={12} sx={{ mt: 1 }}>
              <Typography variant="caption" color={"error"} sx={{ display: "block" }}>
                {silentConditionMessage}
              </Typography>
            </Grid>
          )}

          <Box sx={{ display: "flex", justifyContent: "flex-end", gap: 2, mt: 3 }}>
            <Button onClick={handleClose} variant="outlined" color="primary">
              Cancel
            </Button>
            <Button
              type="submit"
              variant="contained"
              color="primary"
              loading={isLoading}
              loadingPosition="end"
            >
              {data === "NEW" ? "Create" : "Update"}
            </Button>
          </Box>
        </Box>
      </form>
    </ModalContainer>
  );
};

export default SilentRuleModal;
