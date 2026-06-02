import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Autocomplete,
  Button,
  Chip,
  Grid,
  Stack,
  TextField,
  ToggleButton,
  Typography
} from "@mui/material";
import { useMutation, useQueries } from "@tanstack/react-query";
import { useFieldArray, useForm } from "react-hook-form";
import { HiPlus } from "react-icons/hi";
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
import ExtraField from "@/components/AlertRule/Forms/ExtraField";
import type { ModalContainerProps } from "@/components/Modal/types";
import ToggleButtonGroup from "@/components/ToggleButtonGroup";
import { type DataSourceType } from "@/utils/dataSourceUtils";
import { capitalizeFirstLetter } from "@/utils/general";

const QUERY_TYPE = ["dynamic", "textQuery"] as const;
const ALERT_RULE_TYPES = ["prometheus", "pmm", "grafana"] as const;

const extraFieldSchema = z.object({
  key: z.string().trim().min(1, "This field is Required."),
  value: z.string().trim().min(1, "This field is Required.")
});

const generalAlertRuleSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  type: z.enum(ALERT_RULE_TYPES),
  endpointIds: z.array(z.string()),
  userIds: z.array(z.string()),
  teamIds: z.array(z.string()),
  extraField: z.array(extraFieldSchema),
  tags: z.array(z.string()),
  dataSourceIds: z.array(z.string()).min(1, "Select at least one Data Source."),
  queryType: z.enum(QUERY_TYPE),
  dataSourceAlertName: z.string().nullable().optional(),
  description: z.string(),
  showAcknowledgeBtn: z.boolean()
});

type GeneralAlertRuleType = z.infer<typeof generalAlertRuleSchema>;
type GeneralAlertRuleModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
  type: Extract<DataSourceType, "prometheus" | "pmm" | "grafana">;
};

const defaultKeyValue = { key: "", value: "" };

const emptyFormValues: GeneralAlertRuleType = {
  name: "",
  type: "prometheus",
  userIds: [],
  teamIds: [],
  endpointIds: [],
  extraField: [],
  tags: [],
  dataSourceIds: [],
  dataSourceAlertName: "",
  queryType: "dynamic",
  description: "",
  showAcknowledgeBtn: false
};

function getFormValues(
  data: CreateUpdateModal<IAlertRule>,
  alertType: GeneralAlertRuleModalProps["type"]
): GeneralAlertRuleType {
  if (!data || data === "NEW") {
    return { ...emptyFormValues, type: alertType };
  }

  return data as unknown as GeneralAlertRuleType;
}

export default function GeneralAlertRuleForm({
  data,
  onSubmit,
  onClose,
  type
}: GeneralAlertRuleModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    getValues,
    reset,
    control,
    formState: { errors }
  } = useForm<GeneralAlertRuleType>({
    resolver: zodResolver(generalAlertRuleSchema),
    defaultValues: getFormValues(data, type),
    mode: "onSubmit"
  });
  const {
    fields,
    append: appendNewKeyPair,
    remove: removeKeyPair
  } = useFieldArray({
    control,
    name: "extraField"
  });

  const [{ data: alertRuleNameList }, { data: dataSourceList }] = useQueries({
    queries: [
      {
        queryKey: ["all-alert-rule-names", type],
        queryFn: () => getDataSourceAlertName(type)
      },
      {
        queryKey: ["alert-rule-data-source", type],
        queryFn: () => getAlertRuleDataSourcesByAlertType(type)
      }
    ]
  });

  const { mutate: createPrometheusMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: GeneralAlertRuleType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success(`${capitalizeFirstLetter(type)} Alert Rule Created Successfully.`);
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updatePrometheusMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IAlertRule["id"]; body: GeneralAlertRuleType }) =>
      updateAlertRule(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success(`${capitalizeFirstLetter(type)} Alert Rule Updated Successfully.`);
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleSubmitForm(values: GeneralAlertRuleType) {
    if (data === "NEW") {
      createPrometheusMutation(values);
    } else if (data) {
      updatePrometheusMutation({ id: data.id, body: values });
    }
  }

  useEffect(() => {
    reset(getFormValues(data, type));
  }, [reset, data, type]);

  useEffect(() => {
    if (ALERT_RULE_TYPES.includes(type)) {
      setValue("type", type);
    }
  }, [setValue, type]);

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
          {data === "NEW" ? "Create" : "Update"} {type} Alert
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
        <AlertRuleGeneralFields<GeneralAlertRuleType>
          methods={{ control, getValues, setValue, watch }}
          errors={errors}
        >
          <Grid
            size={12}
            sx={{
              display: "flex",
              justifyContent: "center"
            }}
          >
            <ToggleButtonGroup
              exclusive
              value={watch("queryType")}
              onChange={(_, value) => value !== null && setValue("queryType", value)}
            >
              {QUERY_TYPE.map((value) => (
                <ToggleButton
                  key={value}
                  disabled={value === "textQuery"}
                  value={value}
                  sx={{ textTransform: "capitalize !important" }}
                >
                  {value}
                </ToggleButton>
              ))}
            </ToggleButtonGroup>
          </Grid>
          {watch("queryType") === "dynamic" ? (
            <Grid container size={12} spacing={2}>
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
                  freeSolo={type !== "prometheus"}
                  value={watch("dataSourceAlertName")}
                  onChange={(_, value) => setValue("dataSourceAlertName", value ?? "")}
                  autoSelect={type !== "prometheus"}
                  renderInput={(params) => (
                    <TextField
                      {...params}
                      slotProps={{
                        ...params.slotProps,
                        input: params.slotProps.input,
                        inputLabel: params.slotProps.inputLabel,
                        htmlInput: params.slotProps.htmlInput
                      }}
                      error={!!errors.dataSourceAlertName}
                      helperText={errors.dataSourceAlertName?.message}
                      variant="filled"
                      label="DataSource Alert Name"
                    />
                  )}
                />
              </Grid>
              {fields.map((field, index) => (
                <ExtraField
                  key={field.id}
                  keyTextFieldProps={{
                    value: watch(`extraField.${index}.key`),
                    onChange: (value) => setValue(`extraField.${index}.key`, value ?? ""),
                    error: !!errors.extraField?.[index]?.key,
                    helperText: errors.extraField?.[index]?.key?.message
                  }}
                  valueTextFieldProps={{
                    value: watch(`extraField.${index}.value`),
                    onChange: (value) => setValue(`extraField.${index}.value`, value ?? ""),
                    error: !!errors.extraField?.[index]?.value,
                    helperText: errors.extraField?.[index]?.value?.message
                  }}
                  onDelete={() => removeKeyPair(index)}
                />
              ))}
              <Button
                startIcon={<HiPlus />}
                variant="outlined"
                fullWidth
                onClick={() => appendNewKeyPair(defaultKeyValue)}
              >
                Add New Key Value
              </Button>
            </Grid>
          ) : (
            <Grid size={12}>
              <TextField label="Query" variant="filled" multiline minRows={4} />
            </Grid>
          )}
        </AlertRuleGeneralFields>
      </Grid>
      <Stack
        direction="row"
        spacing={2}
        sx={{
          justifyContent: "flex-end",
          paddingY: 2
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
