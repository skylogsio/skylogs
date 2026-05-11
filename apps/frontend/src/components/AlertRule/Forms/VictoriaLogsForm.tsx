"use client";

import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Autocomplete,
  Button,
  Grid2 as Grid,
  MenuItem,
  Stack,
  TextField,
  Typography
} from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import {
  createAlertRule,
  getAlertRuleDataSourcesByAlertType,
  updateAlertRule
} from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const CONDITION_TYPES = ["greaterOrEqual", "lessOrEqual"] as const;

const schema = z.object({
  name: z.string().trim().nonempty("This field is Required."),
  type: z.literal("victoria_logs"),
  endpointIds: z.array(z.string()).optional().default([]),
  userIds: z.array(z.string()).optional().default([]),
  teamIds: z.array(z.string()).optional().default([]),
  tags: z.array(z.string()).optional().default([]),
  dataSourceId: z.string().trim().nonempty("This field is Required."),
  queryString: z.string().trim().nonempty("This field is Required."),
  conditionType: z.enum(CONDITION_TYPES),
  countDocument: z.coerce
    .number({ required_error: "This field is Required." })
    .min(1, "Must be at least 1"),
  minutes: z.coerce
    .number({ required_error: "This field is Required." })
    .min(1, "Must be at least 1"),
  description: z.string().optional().default(""),
  showAcknowledgeBtn: z.boolean().optional().default(false)
});

type VictoriaLogsFormType = z.infer<typeof schema>;
type VictoriaLogsAlertRuleModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const defaultValues: VictoriaLogsFormType = {
  name: "",
  type: "victoria_logs",
  userIds: [],
  teamIds: [],
  endpointIds: [],
  tags: [],
  dataSourceId: "",
  queryString: "",
  conditionType: "greaterOrEqual",
  countDocument: 5,
  minutes: 5,
  description: "",
  showAcknowledgeBtn: false
};

export default function VictoriaLogsAlertRuleForm({
  data,
  onSubmit,
  onClose
}: VictoriaLogsAlertRuleModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    reset,
    control,
    getValues,
    formState: { errors }
  } = useForm<VictoriaLogsFormType>({
    resolver: zodResolver(schema),
    defaultValues
  });

  const { data: dataSourceList } = useQuery({
    queryKey: ["alert-rule-data-source", "victoria_logs"],
    queryFn: () => getAlertRuleDataSourcesByAlertType("victoria_logs")
  });

  const { mutate: createVictoriaLogsMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: VictoriaLogsFormType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Victoria Logs Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updateVictoriaLogsMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IAlertRule["id"]; body: VictoriaLogsFormType }) =>
      updateAlertRule(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Victoria Logs Alert Rule Updated Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleSubmitForm(values: VictoriaLogsFormType) {
    if (data === "NEW") {
      createVictoriaLogsMutation(values);
    } else if (data) {
      updateVictoriaLogsMutation({ id: data.id, body: values });
    }
  }

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else if (data) {
      reset(data as unknown as VictoriaLogsFormType);
    }
  }, [reset, data]);

  return (
    <Stack
      component="form"
      height="100%"
      onSubmit={handleSubmit(handleSubmitForm)}
      padding={2}
      overflow="auto"
    >
      <Grid container spacing={2} flex={1} alignContent="flex-start">
        <Typography
          variant="h6"
          color="textPrimary"
          textTransform="capitalize"
          fontWeight="bold"
          component="div"
        >
          {data === "NEW" ? "Create" : "Update"} Victoria Logs Alert
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
        <AlertRuleGeneralFields<VictoriaLogsFormType>
          methods={{ control, getValues, setValue, watch }}
          errors={errors}
        >
          <Grid size={12}>
            <Autocomplete
              options={dataSourceList ?? []}
              getOptionLabel={(option) => option.name}
              value={dataSourceList?.find((ds) => ds.id === watch("dataSourceId")) ?? null}
              onChange={(_, newValue) => {
                setValue("dataSourceId", newValue?.id ?? "");
              }}
              isOptionEqualToValue={(option, value) => option.id === value.id}
              renderInput={(params) => (
                <TextField
                  {...params}
                  slotProps={{
                    input: params.InputProps,
                    inputLabel: params.InputLabelProps,
                    htmlInput: params.inputProps
                  }}
                  variant="filled"
                  label="Data Source"
                  error={!!errors.dataSourceId}
                  helperText={errors.dataSourceId?.message}
                />
              )}
            />
          </Grid>
          <Grid size={12}>
            <TextField
              label="Query String"
              variant="filled"
              placeholder="OriginStatus:>=400 name:myName"
              error={!!errors.queryString}
              helperText={errors.queryString?.message}
              {...register("queryString")}
            />
          </Grid>
          <Grid size={4}>
            <TextField
              label="Condition Type"
              variant="filled"
              error={!!errors.conditionType}
              helperText={errors.conditionType?.message}
              {...register("conditionType")}
              value={watch("conditionType")}
              select
            >
              {CONDITION_TYPES.map((condition) => (
                <MenuItem key={condition} value={condition}>
                  {condition.replace(/([A-Z])/g, " $1").trim()}
                </MenuItem>
              ))}
            </TextField>
          </Grid>
          <Grid size={4}>
            <TextField
              label="Count Document"
              variant="filled"
              type="number"
              error={!!errors.countDocument}
              helperText={errors.countDocument?.message}
              {...register("countDocument")}
            />
          </Grid>
          <Grid size={4}>
            <TextField
              label="Minutes"
              variant="filled"
              type="number"
              error={!!errors.minutes}
              helperText={errors.minutes?.message}
              {...register("minutes")}
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
