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
  getAlertRuleCreateData
} from "@/api/alertRule";
import ModalContainer from "@/components/Modal";

import type { NotificationRuleItem } from "./BehaviorRuleType";
import ExtraField from "../Forms/ExtraField";

const filtersSchema = z.object({
  key: z.string().trim().min(1, "This field is Required."),
  value: z.string().trim().min(1, "This field is Required.")
});
const notificationRuleSchema = z.object({
  name: z.string().min(1, "Name is required"),
  endpointIds: z.array(z.string()).min(1, "At least one Endpoint is required"),
  filters: z.array(filtersSchema).min(1, "At least one key-value pair is required")
});

type NotificationRuleFormType = z.infer<typeof notificationRuleSchema>;

const defaultKeyValue = { key: "", value: "" };

interface NotificationRuleModalProps {
  open: boolean;
  onClose: () => void;
  data: "NEW" | NotificationRuleItem;
}

const emptyFormValues: NotificationRuleFormType = {
  name: "",
  endpointIds: [],
  filters: [defaultKeyValue]
};

function getFormValues(
  data: CreateUpdateModal<NotificationRuleFormType & { id: string }>
): NotificationRuleFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    endpointIds: data.endpointIds,
    filters: data.filters
  };
}

const NotificationRuleModal: React.FC<NotificationRuleModalProps> = ({ open, onClose, data }) => {
  const queryClient = useQueryClient();
  const { alertId } = useParams<{ alertId: string }>();
  const ruleId = data !== "NEW" ? (data as NotificationRuleFormType & { id: string }).id : "";
  const {
    register,
    trigger,
    control,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
    reset
  } = useForm<NotificationRuleFormType>({
    resolver: zodResolver(notificationRuleSchema),
    defaultValues: getFormValues(data)
  });

  const { data: endpointsData } = useQuery({
    queryKey: ["alert-rule-create-data"],
    queryFn: () => getAlertRuleCreateData()
  });

  const handleClose = () => {
    onClose();
    queryClient.invalidateQueries({ queryKey: ["get-behavior-rule"] });
  };

  const { mutate: addNotificationRule } = useMutation({
    mutationFn: (body: NotificationRuleFormType) => addBehaviorRuleToAlertRule(alertId, body),
    onSuccess: () => {
      toast.success("Notification Rule Created Successfully.");
      handleClose();
    }
  });

  const { mutate: editNotificationRule } = useMutation({
    mutationFn: (body: NotificationRuleFormType) =>
      editBehaviorRuleToAlertRule(alertId, ruleId, body),
    onSuccess: () => {
      toast.success("Notification Rule Updated Successfully.");
      handleClose();
    }
  });

  const endpoints = endpointsData?.endpoints ?? [];

  const { fields, append, remove } = useFieldArray({
    control,
    name: "filters"
  });

  const handleFormSubmit = (formData: NotificationRuleFormType) => {
    const body = { ...formData, type: "notification" };
    if (data === "NEW") {
      addNotificationRule(body);
    } else {
      editNotificationRule(body);
    }
  };

  useEffect(() => {
    reset(getFormValues(data));
  }, [data, open, reset]);

  return (
    <ModalContainer
      open={open}
      onClose={handleClose}
      title="Notification Rule"
      width="90%"
      maxWidth="600px"
    >
      <form onSubmit={handleSubmit(handleFormSubmit)}>
        <Box sx={{ mt: 2 }}>
          <Grid container spacing={2}>
            <Grid size={6}>
              <TextField
                label="Name"
                variant="filled"
                error={!!errors.name}
                helperText={errors.name?.message}
                {...register("name")}
              />
            </Grid>

            <Grid size={6}>
              <Controller
                control={control}
                name={"endpointIds"}
                render={({ field }) => {
                  const selectedEndpoints = endpoints.filter((ep) => field.value?.includes(ep.id));

                  return (
                    <Autocomplete
                      multiple
                      options={endpoints}
                      getOptionLabel={(option) => option.name}
                      value={selectedEndpoints}
                      onChange={(_, newValue) => {
                        field.onChange(newValue.map((ep) => ep.id));
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
                          label="Endpoints"
                          error={!!errors.endpointIds}
                          helperText={errors.endpointIds?.message as string}
                        />
                      )}
                    />
                  );
                }}
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
                    setValue(`filters.${index}.key`, value ?? "");
                    trigger(`filters.${index}.key`);
                  },
                  error: !!errors.filters?.[index]?.key,
                  helperText: errors.filters?.[index]?.key?.message
                }}
                valueTextFieldProps={{
                  value: watch(`filters.${index}.value`),
                  onChange: (value) => {
                    setValue(`filters.${index}.value`, value ?? "");
                    trigger(`filters.${index}.value`);
                  },
                  error: !!errors.filters?.[index]?.value,
                  helperText: errors.filters?.[index]?.value?.message
                }}
                onDelete={() => remove(index)}
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
              onClick={() => append(defaultKeyValue)}
            >
              Add New Key Value
            </Button>
          </Stack>

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

export default NotificationRuleModal;
