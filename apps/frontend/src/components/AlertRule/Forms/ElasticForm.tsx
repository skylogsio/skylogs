"use client";

import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Autocomplete,
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

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import {
  createAlertRule,
  getAlertRuleDataSourcesByAlertType,
  getAlertRuleTags,
  updateAlertRule
} from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const CONDITION_TYPES = ["greaterOrEqual", "lessOrEqual"] as const;

const elasticAlertRuleSchema = z.object({
  name: z.string().trim().nonempty("This field is Required."),
  type: z.literal("elastic"),
  endpointIds: z.array(z.string()).optional().default([]),
  userIds: z.array(z.string()).optional().default([]),
  teamIds: z.array(z.string()).optional().default([]),
  tags: z.array(z.string()).optional().default([]),
  dataSourceId: z.string().trim().nonempty("This field is Required."),
  queryString: z.string().trim().nonempty("This field is Required."),
  dataviewName: z.string().trim().nonempty("This field is Required."),
  dataviewTitle: z.string().trim().nonempty("This field is Required."),
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

type ElasticFormType = z.infer<typeof elasticAlertRuleSchema>;
type ElasticAlertRuleModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const defaultValues: ElasticFormType = {
  name: "",
  type: "elastic",
  userIds: [],
  teamIds: [],
  endpointIds: [],
  tags: [],
  dataSourceId: "",
  queryString: "",
  dataviewName: "",
  dataviewTitle: "",
  conditionType: "greaterOrEqual",
  countDocument: 5,
  minutes: 5,
  description: "",
  showAcknowledgeBtn: false
};

export default function ElasticAlertRuleForm({
  data,
  onSubmit,
  onClose
}: ElasticAlertRuleModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    reset,
    control,
    getValues,
    formState: { errors }
  } = useForm<ElasticFormType>({
    resolver: zodResolver(elasticAlertRuleSchema),
    defaultValues
  });

  const [{ data: tagsList }, { data: dataSourceList }] = useQueries({
    queries: [
      {
        queryKey: ["all-alert-rule-tags"],
        queryFn: () => getAlertRuleTags()
      },
      {
        queryKey: ["alert-rule-data-source", "elastic"],
        queryFn: () => getAlertRuleDataSourcesByAlertType("elastic")
      }
    ]
  });

  const { mutate: createElasticMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: ElasticFormType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Elastic Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updateElasticMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IAlertRule["id"]; body: ElasticFormType }) =>
      updateAlertRule(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Elastic Alert Rule Updated Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleSubmitForm(values: ElasticFormType) {
    if (data === "NEW") {
      createElasticMutation(values);
    } else if (data) {
      updateElasticMutation({ id: data.id, body: values });
    }
  }

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else if (data) {
      reset(data as unknown as ElasticFormType);
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
          {data === "NEW" ? "Create" : "Update"} Elastic Alert
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
        <AlertRuleGeneralFields<ElasticFormType>
          methods={{ control, getValues, setValue, watch }}
          errors={errors}
        >
          <Grid size={12}>
            <TextField
              label="Data Source"
              variant="filled"
              error={!!errors.dataSourceId}
              helperText={errors.dataSourceId?.message}
              {...register("dataSourceId")}
              value={watch("dataSourceId")}
              select
            >
              {dataSourceList?.map((dataSource) => (
                <MenuItem key={dataSource.id} value={dataSource.id}>
                  {dataSource.name}
                </MenuItem>
              ))}
            </TextField>
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
          <Grid size={6}>
            <TextField
              label="Dataview Name"
              variant="filled"
              placeholder="My dataview"
              error={!!errors.dataviewName}
              helperText={errors.dataviewName?.message}
              {...register("dataviewName")}
            />
          </Grid>
          <Grid size={6}>
            <TextField
              label="Dataview Title"
              variant="filled"
              placeholder="responses*"
              error={!!errors.dataviewTitle}
              helperText={errors.dataviewTitle?.message}
              {...register("dataviewTitle")}
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
