import { useParams } from "next/navigation";
import React, { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Autocomplete, Box, Button, Chip, Grid, TextField } from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm, Controller } from "react-hook-form";
import { toast } from "react-toastify";
import * as z from "zod";

import { CreateUpdateModal } from "@/@types/global";
import {
  addBehaviorRuleToAlertRule,
  editBehaviorRuleToAlertRule,
  getSelectableAlertRulesForBehaviorRule
} from "@/api/alertRule";
import ModalContainer from "@/components/Modal";

import type { SilentRuleItem } from "./BehaviorRuleType";
import SilentRuleStateField from "./SilentRuleStateField";

const silentRuleSchema = z.object({
  name: z.string().min(1, "Name is required"),
  dependsOnAlertRuleIds: z.array(z.string()).min(1, "At least one Alert Rule is required"),
  triggerState: z.enum(["resolved", "critical"])
});

type SilentRuleFormType = z.infer<typeof silentRuleSchema>;

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
  triggerState: "resolved"
};

function getFormValues(
  data: CreateUpdateModal<SilentRuleFormType & { id: string }>
): SilentRuleFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    dependsOnAlertRuleIds: data.dependsOnAlertRuleIds,
    triggerState: data.triggerState
  };
}

const SilentRuleModal: React.FC<SilentRuleModalProps> = ({ open, onClose, data }) => {
  const queryClient = useQueryClient();
  const { alertId } = useParams<{ alertId: string }>();
  const ruleId = data !== "NEW" ? (data as SilentRuleFormType & { id: string }).id : "";

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
    reset
  } = useForm<SilentRuleFormType>({
    resolver: zodResolver(silentRuleSchema),
    defaultValues: getFormValues(data)
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

  const { mutate: addSilentRule } = useMutation({
    mutationFn: (body: SilentRuleFormType & { type: string }) =>
      addBehaviorRuleToAlertRule(alertId, body),
    onSuccess: () => {
      toast.success("Silent Rule Created Successfully.");
      handleClose();
    }
  });

  const { mutate: editSilentRule } = useMutation({
    mutationFn: (body: SilentRuleFormType & { type: string }) =>
      editBehaviorRuleToAlertRule(alertId, ruleId, body),
    onSuccess: () => {
      toast.success("Silent Rule Updated Successfully.");
      handleClose();
    }
  });

  const handleFormSubmit = (formData: SilentRuleFormType) => {
    const body = { ...formData, type: "silent" };
    if (data === "NEW") {
      addSilentRule(body);
    } else {
      editSilentRule(body);
    }
  };

  useEffect(() => {
    reset(getFormValues(data));
  }, [data, open, reset]);

  const alertRules = selectableAlertRules ?? [];

  return (
    <ModalContainer
      open={open}
      onClose={handleClose}
      title="Silent Rule"
      width="90%"
      maxWidth="600px"
    >
      <form onSubmit={handleSubmit(handleFormSubmit, (error) => console.log(error))}>
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

            <Grid size={12}>
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
            <Grid size={12}>
              <Controller
                control={control}
                name="triggerState"
                render={({ field }) => (
                  <SilentRuleStateField
                    value={field.value}
                    onChange={(value) => field.onChange(value)}
                  />
                )}
              />
            </Grid>
          </Grid>

          <Box sx={{ display: "flex", justifyContent: "flex-end", gap: 2, mt: 3 }}>
            <Button onClick={handleClose} variant="outlined" color="primary">
              Cancel
            </Button>
            <Button type="submit" variant="contained" color="primary">
              {data === "NEW" ? "Create" : "Update"}
            </Button>
          </Box>
        </Box>
      </form>
    </ModalContainer>
  );
};

export default SilentRuleModal;
