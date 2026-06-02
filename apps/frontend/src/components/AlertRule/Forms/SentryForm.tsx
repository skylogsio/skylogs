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
  getDataSourceAlertName,
  updateAlertRule
} from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const sentryAlertRuleSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  type: z.literal("sentry"),
  endpointIds: z.array(z.string()),
  userIds: z.array(z.string()),
  teamIds: z.array(z.string()),
  tags: z.array(z.string()),
  dataSourceIds: z.array(z.string()).min(1, "This field is Required."),
  dataSourceAlertName: z.string().trim().min(1, "This field is Required."),
  description: z.string(),
  showAcknowledgeBtn: z.boolean()
});

type SentryFromType = z.infer<typeof sentryAlertRuleSchema>;
type SentryAlertRuleModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const emptyFormValues: SentryFromType = {
  name: "",
  type: "sentry",
  userIds: [],
  teamIds: [],
  endpointIds: [],
  tags: [],
  dataSourceIds: [],
  dataSourceAlertName: "",
  description: "",
  showAcknowledgeBtn: false
};

function getFormValues(data: CreateUpdateModal<IAlertRule>): SentryFromType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return data as unknown as SentryFromType;
}

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
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });

  const [{ data: alertRuleNameList }, { data: dataSourceList }] = useQueries({
    queries: [
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

  useEffect(() => {
    reset(getFormValues(data));
  }, [reset, data]);

  const selectedDataSources = (dataSourceList ?? []).filter((ds) =>
    (watch("dataSourceIds") ?? []).includes(ds.id)
  );

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
          methods={{ control, getValues, setValue, watch }}
          errors={errors}
        >
          <Grid size={6}>
            <Autocomplete
              multiple
              options={dataSourceList ?? []}
              getOptionLabel={(option) => option.name}
              value={selectedDataSources}
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
                    ...params.slotProps,
                    input: params.slotProps.input,
                    inputLabel: params.slotProps.inputLabel,
                    htmlInput: params.slotProps.htmlInput
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
