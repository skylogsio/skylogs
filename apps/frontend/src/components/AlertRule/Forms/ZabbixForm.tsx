"use client";

import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Autocomplete, Button, Chip, Grid, Stack, TextField, Typography } from "@mui/material";
import { useMutation, useQueries } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import {
  createAlertRule,
  getAlertRuleDataSourcesByAlertType,
  getZabbixCreateData,
  updateAlertRule
} from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const zabbixAlertRuleSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  type: z.literal("zabbix"),
  endpointIds: z.array(z.string()),
  userIds: z.array(z.string()),
  teamIds: z.array(z.string()),
  tags: z.array(z.string()),
  actions: z.array(z.string()),
  hosts: z.array(z.string()),
  severities: z.array(z.string()),
  dataSourceIds: z.array(z.string()).min(1, "This field is Required."),
  description: z.string(),
  showAcknowledgeBtn: z.boolean()
});

type ZabbixFromType = z.infer<typeof zabbixAlertRuleSchema>;
type ZabbixAlertRuleModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const emptyFormValues: ZabbixFromType = {
  name: "",
  type: "zabbix",
  userIds: [],
  teamIds: [],
  endpointIds: [],
  tags: [],
  actions: [],
  hosts: [],
  dataSourceIds: [],
  severities: [],
  description: "",
  showAcknowledgeBtn: false
};

function getFormValues(data: CreateUpdateModal<IAlertRule>): ZabbixFromType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return data as unknown as ZabbixFromType;
}

export default function ZabbixAlertRuleForm({
  data,
  onSubmit,
  onClose
}: ZabbixAlertRuleModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    reset,
    control,
    getValues,
    formState: { errors }
  } = useForm<ZabbixFromType>({
    resolver: zodResolver(zabbixAlertRuleSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });

  const [{ data: dataSourceList }, { data: createData }] = useQueries({
    queries: [
      {
        queryKey: ["alert-rule-data-source", "zabbix"],
        queryFn: () => getAlertRuleDataSourcesByAlertType("zabbix")
      },
      {
        queryKey: ["zabbix-create-date"],
        queryFn: () => getZabbixCreateData()
      }
    ]
  });

  const { mutate: createZabbixMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: ZabbixFromType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Zabbix Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updateZabbixMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IAlertRule["id"]; body: ZabbixFromType }) =>
      updateAlertRule(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Zabbix Alert Rule Updated Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleSubmitForm(values: ZabbixFromType) {
    if (data === "NEW") {
      createZabbixMutation(values);
    } else if (data) {
      updateZabbixMutation({ id: data.id, body: values });
    }
  }

  useEffect(() => {
    reset(getFormValues(data));
  }, [reset, data]);

  return (
    <Stack
      component="form"
      onSubmit={handleSubmit(handleSubmitForm)}
      sx={{
        height: "100%",
        padding: 2,
        flex: 1
      }}
    >
      <Grid
        container
        spacing={2}
        sx={{
          flex: 1,
          alignContent: "flex-start"
        }}
      >
        <Typography
          variant="h6"
          color="textPrimary"
          component="div"
          sx={{
            textTransform: "capitalize",
            fontWeight: "bold"
          }}
        >
          {data === "NEW" ? "Create" : "Update"} Zabbix Alert
        </Typography>
        <Grid size={12}>
          <TextField
            label="Name"
            variant="filled"
            error={!!errors.name}
            helperText={errors.name?.message}
            {...register("name")}
          />
        </Grid>
        <AlertRuleGeneralFields<ZabbixFromType>
          methods={{ control, getValues, setValue, watch }}
          errors={errors}
        >
          <Grid size={6}>
            <Autocomplete
              multiple
              options={dataSourceList ?? []}
              getOptionLabel={(option) => option.name}
              value={(dataSourceList ?? []).filter((ds) =>
                (watch("dataSourceIds") ?? []).includes(ds.id)
              )}
              onChange={(_, newValue) => {
                setValue(
                  "dataSourceIds",
                  newValue.map((ds) => ds.id)
                );
              }}
              isOptionEqualToValue={(option, value) => option.id === value.id}
              renderValue={(value, getItemProps) =>
                value.map((option, index) => {
                  const { key, ...itemProps } = getItemProps({ index });
                  return <Chip key={key} size="small" label={option.name} {...itemProps} />;
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
                  label="Data Source"
                  error={!!errors.dataSourceIds}
                  helperText={errors.dataSourceIds?.message}
                />
              )}
            />
          </Grid>
          <Grid size={6}>
            <Autocomplete
              multiple
              options={createData?.severities ?? []}
              getOptionLabel={(option) => option}
              value={watch("severities") ?? []}
              onChange={(_, newValue) => {
                setValue("severities", newValue);
              }}
              isOptionEqualToValue={(option, value) => option === value}
              renderValue={(value, getItemProps) =>
                value.map((option, index) => {
                  const { key, ...itemProps } = getItemProps({ index });
                  return <Chip key={key} size="small" label={option} {...itemProps} />;
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
                  label="Severity"
                  error={!!errors.severities}
                  helperText={errors.severities?.message}
                />
              )}
            />
          </Grid>
          <Grid size={6}>
            <Autocomplete
              multiple
              options={createData?.actions ?? []}
              getOptionLabel={(option) => option}
              value={watch("actions") ?? []}
              onChange={(_, newValue) => {
                setValue("actions", newValue);
              }}
              isOptionEqualToValue={(option, value) => option === value}
              renderValue={(value, getItemProps) =>
                value.map((option, index) => {
                  const { key, ...itemProps } = getItemProps({ index });
                  return <Chip key={key} size="small" label={option} {...itemProps} />;
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
                  label="Actions"
                  error={!!errors.actions}
                  helperText={errors.actions?.message}
                />
              )}
            />
          </Grid>
          <Grid size={6}>
            <Autocomplete
              multiple
              options={createData?.hosts ?? []}
              getOptionLabel={(option) => option}
              value={watch("hosts") ?? []}
              onChange={(_, newValue) => {
                setValue("hosts", newValue);
              }}
              isOptionEqualToValue={(option, value) => option === value}
              renderValue={(value, getItemProps) =>
                value.map((option, index) => {
                  const { key, ...itemProps } = getItemProps({ index });
                  return <Chip key={key} size="small" label={option} {...itemProps} />;
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
                  label="Hosts"
                  error={!!errors.hosts}
                  helperText={errors.hosts?.message}
                />
              )}
            />
          </Grid>
        </AlertRuleGeneralFields>
      </Grid>
      <Stack
        direction="row"
        spacing={2}
        sx={{
          justifyContent: "flex-end",
          paddingTop: 2
        }}
      >
        <Button variant="outlined" disabled={isCreating || isUpdating} onClick={onClose}>
          Cancel
        </Button>
        <Button type="submit" variant="contained" disabled={isCreating || isUpdating}>
          {data === "NEW" ? "Create" : "Update"}
        </Button>
      </Stack>
    </Stack>
  );
}
