import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid, Stack, Switch, TextField, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import { createAlertRule, updateAlertRule } from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const clientApiSchema = z
  .object({
    name: z.string().trim().min(1, "This field is Required."),
    type: z.literal("api"),
    enableAutoResolve: z.boolean(),
    autoResolveMinutes: z.number("This field is Required."),
    endpointIds: z.array(z.string()),
    userIds: z.array(z.string()),
    teamIds: z.array(z.string()),
    description: z.string(),
    tags: z.array(z.string()),
    showAcknowledgeBtn: z.boolean()
  })
  .superRefine((data, ctx) => {
    if (data.enableAutoResolve) {
      if (data.autoResolveMinutes === undefined) {
        ctx.addIssue({
          path: ["autoResolveMinutes"],
          message: "This field is Required.",
          code: "custom"
        });
      } else if (!Number.isInteger(data.autoResolveMinutes) || data.autoResolveMinutes <= 0) {
        ctx.addIssue({
          path: ["autoResolveMinutes"],
          message: "Must be a positive integer.",
          code: "custom"
        });
      }
    }
  });

type ClientAPIFormType = z.infer<typeof clientApiSchema>;
type ClientAPIModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const emptyFormValues: ClientAPIFormType = {
  name: "",
  type: "api",
  userIds: [],
  teamIds: [],
  endpointIds: [],
  enableAutoResolve: false,
  autoResolveMinutes: 0,
  tags: [],
  description: "",
  showAcknowledgeBtn: false
};

function getFormValues(data: CreateUpdateModal<IAlertRule>): ClientAPIFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return data as unknown as ClientAPIFormType;
}

export default function ClientAPIForm({ onClose, onSubmit, data }: ClientAPIModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    control,
    getValues,
    reset,
    clearErrors,
    formState: { errors }
  } = useForm<ClientAPIFormType>({
    resolver: zodResolver(clientApiSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });

  const { mutate: createClientAPIMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: ClientAPIFormType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Client Api Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updateClientAPIMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IAlertRule["id"]; body: ClientAPIFormType }) =>
      updateAlertRule(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Client Api Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleAutoResolve(event: React.ChangeEvent<HTMLInputElement>) {
    if (!event.target.checked) {
      clearErrors("autoResolveMinutes");
      setValue("autoResolveMinutes", 0);
    }
    setValue("enableAutoResolve", event.target.checked);
  }

  function handleSubmitForm(values: ClientAPIFormType) {
    if (data === "NEW") {
      createClientAPIMutation(values);
    } else if (data) {
      updateClientAPIMutation({ id: data.id, body: values });
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
        padding: 2
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
            fontWeight: "bold"
          }}
        >
          {data === "NEW" ? "Create" : "Update"} Client API Alert
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
        <AlertRuleGeneralFields<ClientAPIFormType>
          methods={{ control, getValues, setValue, watch }}
          errors={errors}
        >
          <Grid size={6}>
            <Stack
              direction="row"
              spacing={1}
              sx={{
                height: "100%",
                alignItems: "center"
              }}
            >
              <Typography>Auto Resolve</Typography>
              <Switch checked={watch("enableAutoResolve")} onChange={handleAutoResolve} />
            </Stack>
          </Grid>
          <Grid size={6}>
            <TextField
              label="Auto Resolve After (Minutes)"
              variant="filled"
              type="number"
              disabled={!watch("enableAutoResolve")}
              error={!!errors.autoResolveMinutes}
              helperText={errors.autoResolveMinutes?.message}
              {...register("autoResolveMinutes", {
                valueAsNumber: true,
                setValueAs: (value) => parseInt(value)
              })}
            />
          </Grid>
        </AlertRuleGeneralFields>
      </Grid>
      <Stack
        direction="row"
        spacing={2}
        sx={{
          justifyContent: "flex-end",
          marginTop: 2
        }}
      >
        <Button disabled={isCreating || isUpdating} variant="outlined" onClick={onClose}>
          Cancel
        </Button>
        <Button disabled={isCreating || isUpdating} type="submit" variant="contained">
          {data === "NEW" ? "Create" : "Update"}
        </Button>
      </Stack>
    </Stack>
  );
}
