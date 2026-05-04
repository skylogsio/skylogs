import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid2 as Grid, Stack, TextField, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import { createAlertRule, updateAlertRule } from "@/api/alertRule";
import AlertRuleGeneralFields from "@/components/AlertRule/Forms/AlertRuleGeneralFields";
import type { ModalContainerProps } from "@/components/Modal/types";

const clientApiSchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  type: z.literal("notification").default("notification"),
  endpointIds: z.array(z.string()).optional().default([]),
  userIds: z.array(z.string()).optional().default([]),
  teamIds: z.array(z.string()).optional().default([]),
  tags: z.array(z.string()).optional().default([]),
  description: z.string().optional().default(""),
  showAcknowledgeBtn: z.boolean().optional().default(false)
});

type NotificationFormType = z.infer<typeof clientApiSchema>;
type NotificationModalProps = Pick<ModalContainerProps, "onClose"> & {
  data: CreateUpdateModal<IAlertRule>;
  onSubmit: () => void;
};

const defaultValues: NotificationFormType = {
  name: "",
  type: "notification",
  userIds: [],
  teamIds: [],
  endpointIds: [],
  tags: [],
  description: "",
  showAcknowledgeBtn: false
};

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
    resolver: zodResolver(clientApiSchema),
    defaultValues
  });

  const { mutate: createNotificationMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: NotificationFormType) => createAlertRule(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Notification Alert Rule Created Successfully.");
        onSubmit();
        onClose?.();
      }
    },
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
    if (data === "NEW") {
      reset(defaultValues);
    } else if (data) {
      reset(data as unknown as NotificationFormType);
    }
  }, [reset, data]);

  return (
    <Stack
      component="form"
      width="100%"
      height="100%"
      onSubmit={handleSubmit(handleSubmitForm)}
      padding={2}
    >
      <Grid container spacing={2} flex={1} alignContent="flex-start">
        <Typography variant="h6" color="textPrimary" fontWeight="bold" component="div">
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
      <Stack direction="row" justifyContent="flex-end" spacing={2} marginTop={2}>
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
