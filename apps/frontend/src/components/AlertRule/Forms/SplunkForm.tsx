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

const splunkAlertRuleSchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  type: z.literal("splunk"),
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

type SplunkFromType = z.infer<typeof splunkAlertRuleSchema>;
type SplunkAlertRuleModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const defaultValues: SplunkFromType = {
  name: "",
  type: "splunk",
  userIds: [],
  endpointIds: [],
  tags: [],
  dataSourceIds: [],
  dataSourceAlertName: "",
  description: "",
  showAcknowledgeBtn: false
};

export default function SplunkAlertRuleForm({
  data,
  onSubmit,
  onClose
}: SplunkAlertRuleModalProps) {
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
  } = useForm<SplunkFromType>({
    resolver: zodResolver(splunkAlertRuleSchema),
    defaultValues
  });

  const [{ data: tagsList }, { data: alertRuleNameList }, { data: dataSourceList }] = useQueries({
    queries: [
      {
        queryKey: ["all-alert-rule-tags"],
        queryFn: () => getAlertRuleTags()
      },
      {
        queryKey: ["all-alert-rule-names", "splunk"],
        queryFn: () => getDataSourceAlertName("splunk")
      },
      {
        queryKey: ["alert-rule-data-source", "splunk"],
        queryFn: () => getAlertRuleDataSourcesByAlertType("splunk")
      }
    ]
  });

  const { mutate: createSplunkMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: SplunkFromType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Splunk Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updateSplunkMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IAlertRule["id"]; body: SplunkFromType }) =>
      updateAlertRule(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Splunk Alert Rule Updated Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleSubmitForm(values: SplunkFromType) {
    if (data === "NEW") {
      createSplunkMutation(values);
    } else if (data) {
      updateSplunkMutation({ id: data.id, body: values });
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
      reset(data as unknown as SplunkFromType);
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
          {data === "NEW" ? "Create" : "Update"} Splunk Alert
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
        <AlertRuleGeneralFields<SplunkFromType>
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
