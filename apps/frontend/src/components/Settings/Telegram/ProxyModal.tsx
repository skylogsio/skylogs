import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid2 as Grid, TextField } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { CreateUpdateModal } from "@/@types/global";
import type { ITelegramProxy } from "@/@types/settings/telegram";
import { createTelegramProxy, updateTelegramProxy } from "@/api/setttings/telegram";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";


const proxySchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  url: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    })
});

type ProxyFormType = z.infer<typeof proxySchema>;

type ProxyModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ITelegramProxy>;
  onSubmit: () => void;
};

const defaultValues: ProxyFormType = {
  url: "",
  name: ""
};

export default function ProxyModal({ data, open, onClose, onSubmit }: ProxyModalProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors }
  } = useForm<ProxyFormType>({
    resolver: zodResolver(proxySchema),
    defaultValues
  });

  const { mutate: createTelegramProxyMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: ProxyFormType) => createTelegramProxy(body),
    onSuccess: () => {
      toast.success("Proxy Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });
  const { mutate: updateTelegramProxyMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: ProxyFormType }) =>
      updateTelegramProxy(id, body),
    onSuccess: () => {
      toast.success("Proxy Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(body: ProxyFormType) {
    if (data === "NEW") {
      createTelegramProxyMutation(body);
    } else if (data) {
      updateTelegramProxyMutation({ id: data.id, body });
    }
  }

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else {
      reset(data as ProxyFormType);
    }
  }, [data, reset]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Proxy`}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
    >
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm, (error) => console.log(error))}
        container
        spacing={2}
        width="100%"
        display="flex"
        marginTop="2rem"
      >
        <Grid size={6}>
          <TextField
            label="Name"
            variant="filled"
            error={!!errors.name}
            helperText={errors.name?.message}
            {...register("name")}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Url"
            variant="filled"
            error={!!errors.url}
            helperText={errors.url?.message}
            {...register("url")}
          />
        </Grid>
        <Grid size={12} marginTop="1rem">
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
