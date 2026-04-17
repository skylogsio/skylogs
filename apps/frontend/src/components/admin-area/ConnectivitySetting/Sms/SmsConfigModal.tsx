import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid2 as Grid, TextField } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { ISmsConfig } from "@/@types/admin-area/smsConfig";
import type { CreateUpdateModal } from "@/@types/global";
import { createSmsConfig, updateSmsConfig } from "@/api/admin-area/smsConfig";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const smsConfigSchema = z.object({
  name: z.string().trim().nonempty("This field is Required."),
  provider: z.literal("kaveNegar"),
  apiToken: z.string().trim().nonempty("This field is Required."),
  senderNumber: z.string().trim().nonempty("This field is Required.")
});

type SmsConfigFormType = z.infer<typeof smsConfigSchema>;

type SmsConfigModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ISmsConfig>;
  onSubmit: () => void;
};

const defaultValues: SmsConfigFormType = {
  name: "",
  provider: "kaveNegar",
  apiToken: "",
  senderNumber: ""
};

export default function SmsConfigModal({ data, open, onClose, onSubmit }: SmsConfigModalProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors }
  } = useForm<SmsConfigFormType>({
    resolver: zodResolver(smsConfigSchema),
    defaultValues
  });

  const { mutate: createSmsConfigMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: SmsConfigFormType) => createSmsConfig(body),
    onSuccess: () => {
      toast.success("SMS Config Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  const { mutate: updateSmsConfigMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: SmsConfigFormType }) =>
      updateSmsConfig(id, body),
    onSuccess: () => {
      toast.success("SMS Config Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(body: SmsConfigFormType) {
    if (data === "NEW") {
      createSmsConfigMutation(body);
    } else if (data) {
      updateSmsConfigMutation({ id: data.id, body });
    }
  }

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else {
      reset(data as unknown as SmsConfigFormType);
    }
  }, [data, reset]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create" : "Update"} New Sms Config`}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
    >
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2}
        width="100%"
        display="flex"
        marginTop="1rem"
      >
        <Grid size={12}>
          <TextField
            label="Name"
            variant="filled"
            fullWidth
            error={!!errors.name}
            helperText={errors.name?.message}
            {...register("name")}
          />
        </Grid>
        <Grid size={12}>
          <TextField
            label="API Token"
            variant="filled"
            fullWidth
            error={!!errors.apiToken}
            helperText={errors.apiToken?.message}
            {...register("apiToken")}
          />
        </Grid>
        <Grid size={12}>
          <TextField
            label="Sender Number"
            variant="filled"
            fullWidth
            error={!!errors.senderNumber}
            helperText={errors.senderNumber?.message}
            {...register("senderNumber")}
          />
        </Grid>
        <Grid size={12} marginTop="0.5rem">
          <Button
            disabled={isCreating || isUpdating}
            type="submit"
            variant="contained"
            size="large"
            fullWidth
          >
            {data === "NEW" ? "Create" : "Update"}
          </Button>
        </Grid>
      </Grid>
    </ModalContainer>
  );
}
