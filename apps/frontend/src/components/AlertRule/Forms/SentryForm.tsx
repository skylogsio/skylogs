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

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import {
  createAlertRule,
  getAlertRuleDataSourcesByAlertType,
  getAlertRuleTags,
  getDataSourceAlertName,
  updateAlertRule
} from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const sentryAlertRuleSchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  type: z.literal("sentry"),
  endpointIds: z.array(z.string()).optional().default([]),
  userIds: z.array(z.string()).optional().default([]),
  tags: z.array(z.string()).optional().default([]),
  dataSourceIds: z.array(z.string()).min(1, "This field is Required."),
  dataSourceAlertName: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  description: z.string().optional().default(""),
  showAcknowledgeBtn: z.boolean().optional().default(false)
});

type SentryFromType = z.infer<typeof sentryAlertRuleSchema>;
type SentryAlertRuleModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const defaultValues: SentryFromType = {
  name: "",
  type: "sentry",
  userIds: [],
  endpointIds: [],
  tags: [],
  dataSourceIds: [],
  dataSourceAlertName: "",
  description: "",
  showAcknowledgeBtn: false
};

export default function SentryAlertRuleForm({
  data,
  onSubmit,
  onClose
}: SentryAlertRuleModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    reset,
    control,
    getValues,
    clearErrors,
    trigger,
    formState: { errors }
  } = useForm<SentryFromType>({
    resolver: zodResolver(sentryAlertRuleSchema),
    defaultValues
  });

  const [{ data: tagsList }, { data: alertRuleNameList }, { data: dataSourceList }] = useQueries({
    queries: [
      {
        queryKey: ["all-alert-rule-tags"],
        queryFn: () => getAlertRuleTags()
      },
      {
        queryKey: ["all-alert-rule-names", "sentry"],
        queryFn: () => getDataSourceAlertName("sentry")
      },
      {
        queryKey: ["alert-rule-data-source", "sentry"],
        queryFn: () => getAlertRuleDataSourcesByAlertType("sentry")
      }
    ]
  });

  const { mutate: createSentryMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: SentryFromType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Sentry Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updateSentryMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IAlertRule["id"]; body: SentryFromType }) =>
      updateAlertRule(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Sentry Alert Rule Updated Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleSubmitForm(values: SentryFromType) {
    if (data === "NEW") {
      createSentryMutation(values);
    } else if (data) {
      updateSentryMutation({ id: data.id, body: values });
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

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else if (data) {
      reset(data as unknown as SentryFromType);
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
          {data === "NEW" ? "Create" : "Update"} Sentry Alert
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
        <AlertRuleGeneralFields<SentryFromType>
          methods={{ control, getValues, setValue }}
          errors={errors}
        >
          <Grid size={6}>
            <TextField
              label="Data Source"
              variant="filled"
              error={!!errors.dataSourceIds}
              helperText={errors.dataSourceIds?.message}
              {...register("dataSourceIds")}
              value={watch("dataSourceIds") ?? []}
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
            <Autocomplete
              id="data-source-alert-rule-name"
              options={alertRuleNameList ?? []}
              freeSolo
              value={watch("dataSourceAlertName")}
              onChange={(_, value) => {
                setValue("dataSourceAlertName", value ?? "");
                trigger("dataSourceAlertName");
              }}
              autoSelect
              renderInput={(params) => (
                <TextField
                  {...params}
                  slotProps={{
                    input: params.InputProps,
                    inputLabel: params.InputLabelProps,
                    htmlInput: params.inputProps
                  }}
                  onChange={() => clearErrors("dataSourceAlertName")}
                  error={!!errors.dataSourceAlertName}
                  helperText={errors.dataSourceAlertName?.message}
                  variant="filled"
                  label="DataSource Alert Name"
                />
              )}
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
