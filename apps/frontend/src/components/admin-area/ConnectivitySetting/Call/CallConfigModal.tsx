import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid2 as Grid, TextField } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { ICallConfig } from "@/@types/admin-area/callConfig";
import type { CreateUpdateModal } from "@/@types/global";
import { createCallConfig, updateCallConfig } from "@/api/admin-area/callConfig";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const callConfigSchema = z.object({
  name: z.string().trim().nonempty("This field is Required."),
  provider: z.literal("kaveNegar"),
  apiToken: z.string().trim().nonempty("This field is Required.")
});

type CallConfigFormType = z.infer<typeof callConfigSchema>;

type CallConfigModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ICallConfig>;
  onSubmit: () => void;
};

const defaultValues: CallConfigFormType = {
  name: "",
  provider: "kaveNegar",
  apiToken: ""
};

export default function CallConfigModal({ data, open, onClose, onSubmit }: CallConfigModalProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors }
  } = useForm<CallConfigFormType>({
    resolver: zodResolver(callConfigSchema),
    defaultValues
  });

  const { mutate: createCallConfigMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: CallConfigFormType) => createCallConfig(body),
    onSuccess: () => {
      toast.success("Call Config Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  const { mutate: updateCallConfigMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: CallConfigFormType }) =>
      updateCallConfig(id, body),
    onSuccess: () => {
      toast.success("Call Config Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(body: CallConfigFormType) {
    if (data === "NEW") {
      createCallConfigMutation(body);
    } else if (data) {
      updateCallConfigMutation({ id: data.id, body });
    }
  }

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else {
      reset(data as CallConfigFormType);
    }
  }, [data, reset]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create" : "Update"} New Call Config`}
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
