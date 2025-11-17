"use client";

import { type ReactNode, useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Autocomplete,
  Box,
  Button,
  Chip,
  Grid2 as Grid,
  MenuItem,
  Stack,
  TextField,
  Typography
} from "@mui/material";
import { useMutation, useQueries } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IAlertRule, IZabbixCreateData } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import {
  createAlertRule,
  getAlertRuleDataSourcesByAlertType,
  getAlertRuleTags,
  getZabbixCreateData,
  updateAlertRule
} from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const zabbixAlertRuleSchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  type: z.literal("zabbix"),
  endpointIds: z.array(z.string()).optional().default([]),
  userIds: z.array(z.string()).optional().default([]),
  teamIds: z.array(z.string()).optional().default([]),
  tags: z.array(z.string()).optional().default([]),
  actions: z.array(z.string()).optional().default([]),
  hosts: z.array(z.string()).optional().default([]),
  severities: z.array(z.string()).optional().default([]),
  dataSourceIds: z.array(z.string()).min(1, "This field is Required."),
  description: z.string().optional().default(""),
  showAcknowledgeBtn: z.boolean().optional().default(false)
});

type ZabbixFromType = z.infer<typeof zabbixAlertRuleSchema>;
type ZabbixAlertRuleModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const defaultValues: ZabbixFromType = {
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
    defaultValues
  });

  const [{ data: tagsList }, { data: dataSourceList }, { data: createData }] = useQueries({
    queries: [
      {
        queryKey: ["all-alert-rule-tags"],
        queryFn: () => getAlertRuleTags()
      },

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

  function renderDataSourceChip(selectedDataSourceIds: unknown): ReactNode {
    const selectedDataSources = dataSourceList?.filter((dataSource) =>
      (selectedDataSourceIds as string[]).includes(dataSource.id)
    );
    const selectedDataSourceNames = selectedDataSources?.map((dataSource) => dataSource.name) ?? [];
    return (
      <Box sx={{ display: "flex", flexWrap: "wrap", gap: 0.5 }}>
        {selectedDataSourceNames.map((value, index) => (
          <Chip size="small" key={index} label={value} />
        ))}
      </Box>
    );
  }

  function removeChip(
    item: string,
    key: keyof Pick<IZabbixCreateData, "actions" | "hosts" | "severities">
  ) {
    const allItems = getValues(key);
    setValue(
      key,
      allItems.filter((value) => value !== item)
    );
  }

  function renderChip(
    selectedItems: unknown,
    key: keyof Pick<IZabbixCreateData, "actions" | "hosts" | "severities">
  ): ReactNode {
    return (
      <Box sx={{ display: "flex", flexWrap: "wrap", gap: 0.5 }}>
        {(selectedItems as string[]).map((value) => (
          <Chip
            size="small"
            key={value}
            label={value}
            onMouseDown={(event) => event.stopPropagation()}
            onDelete={() => removeChip(value, key)}
          />
        ))}
      </Box>
    );
  }

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else if (data) {
      reset(data as unknown as ZabbixFromType);
    }
  }, [reset, data]);

  return (
    <Stack
      component="form"
      height="100%"
      onSubmit={handleSubmit(handleSubmitForm)}
      padding={2}
      flex={1}
    >
      <Grid container spacing={2} flex={1} alignContent="flex-start">
        <Typography
          variant="h6"
          color="textPrimary"
          textTransform="capitalize"
          fontWeight="bold"
          component="div"
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
            <TextField
              label="Data Source"
              variant="filled"
              error={!!errors.dataSourceIds}
              helperText={errors.dataSourceIds?.message}
              {...register("dataSourceIds")}
              value={watch("dataSourceIds") ?? ""}
              select
              slotProps={{ select: { multiple: true, renderValue: renderDataSourceChip } }}
            >
              {dataSourceList?.map((dataSource) => (
                <MenuItem key={dataSource.id} value={dataSource.id}>
                  {dataSource.name}
                </MenuItem>
              ))}
            </TextField>
          </Grid>
          <Grid size={6}>
            <TextField
              label="Severity"
              variant="filled"
              error={!!errors.severities}
              helperText={errors.severities?.message}
              {...register("severities")}
              value={watch("severities") ?? ""}
              select
              slotProps={{
                select: { multiple: true, renderValue: (value) => renderChip(value, "severities") }
              }}
            >
              {createData?.severities?.map((item) => (
                <MenuItem key={item} value={item}>
                  {item}
                </MenuItem>
              ))}
            </TextField>
          </Grid>
          <Grid size={6}>
            <TextField
              label="Actions"
              variant="filled"
              error={!!errors.actions}
              helperText={errors.actions?.message}
              {...register("actions")}
              value={watch("actions") ?? ""}
              select
              slotProps={{
                select: { multiple: true, renderValue: (value) => renderChip(value, "actions") }
              }}
            >
              {createData?.actions?.map((action) => (
                <MenuItem key={action} value={action}>
                  {action}
                </MenuItem>
              ))}
            </TextField>
          </Grid>
          <Grid size={6}>
            <TextField
              label="Hosts"
              variant="filled"
              error={!!errors.hosts}
              helperText={errors.hosts?.message}
              {...register("hosts")}
              value={watch("hosts") ?? ""}
              select
              slotProps={{
                select: { multiple: true, renderValue: (value) => renderChip(value, "hosts") }
              }}
            >
              {createData?.hosts?.map((host) => (
                <MenuItem key={host} value={host}>
                  {host}
                </MenuItem>
              ))}
            </TextField>
          </Grid>
          <Grid size={12}>
            <Autocomplete
              multiple
              id="alert-tags"
              options={tagsList ?? []}
              freeSolo
              value={watch("tags")}
              onChange={(_, value) => setValue("tags", value)}
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
                  variant="filled"
                  label="Tags"
                />
              )}
            />
          </Grid>
        </AlertRuleGeneralFields>
      </Grid>
      <Stack direction="row" justifyContent="flex-end" spacing={2} paddingTop={2}>
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
