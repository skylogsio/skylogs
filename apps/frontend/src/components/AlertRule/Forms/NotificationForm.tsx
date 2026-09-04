import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid, Stack, TextField, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import { createAlertRule, updateAlertRule } from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const notificationSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  type: z.literal("notification"),
  endpointIds: z.array(z.string()),
  userIds: z.array(z.string()),
  teamIds: z.array(z.string()),
  tags: z.array(z.string()),
  description: z.string(),
  showAcknowledgeBtn: z.boolean()
});

type NotificationFormType = z.infer<typeof notificationSchema>;
type NotificationModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const emptyFormValues: NotificationFormType = {
  name: "",
  type: "notification",
  userIds: [],
  teamIds: [],
  endpointIds: [],
  tags: [],
  description: "",
  showAcknowledgeBtn: false
};

function getFormValues(data: CreateUpdateModal<IAlertRule>): NotificationFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return data as unknown as NotificationFormType;
}

export default function NotificationForm({ onClose, onSubmit, data }: NotificationModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    control,
    getValues,
    reset,
    formState: { errors }
  } = useForm<NotificationFormType>({
    resolver: zodResolver(notificationSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });

  const { mutate: createNotificationMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: NotificationFormType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Notification Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updateClientAPIMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IAlertRule["id"]; body: NotificationFormType }) =>
      updateAlertRule(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Client Api Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleSubmitForm(values: NotificationFormType) {
    if (data === "NEW") {
      createNotificationMutation(values);
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
        width: "100%",
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
          {data === "NEW" ? "Create" : "Update"} Notification Alert
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
        <AlertRuleGeneralFields<NotificationFormType>
          methods={{ control, getValues, setValue, watch }}
          errors={errors}
        />
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
