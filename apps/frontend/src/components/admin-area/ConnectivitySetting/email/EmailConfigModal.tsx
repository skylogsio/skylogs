import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid, IconButton, TextField, useTheme } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IEmailConfig } from "@/@types/admin-area/emailConfig";
import type { CreateUpdateModal } from "@/@types/global";
import { createEmailConfig, updateEmailConfig } from "@/api/admin-area/emailConfig";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const emailConfigSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  host: z.string().trim().min(1, "This field is Required."),
  port: z.number("This field is Required.").int().min(0).max(65535),
  username: z.string().trim().min(1, "This field is Required."),
  password: z.string().trim().min(1, "This field is Required."),
  fromAddress: z
    .string()
    .trim()
    .min(1, "This field is Required.")
    .pipe(z.email({ error: "Invalid email address" }))
});

type EmailConfigFormType = z.infer<typeof emailConfigSchema>;

type EmailConfigModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IEmailConfig>;
  onSubmit: () => void;
};

const emptyFormValues: EmailConfigFormType = {
  name: "",
  host: "",
  port: 587,
  username: "",
  password: "",
  fromAddress: ""
};

function getFormValues(data: CreateUpdateModal<IEmailConfig>): EmailConfigFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    host: data.host,
    port: data.port,
    username: data.usrename,
    password: data.password,
    fromAddress: data.fromAddress
  };
}

export default function EmailConfigModal({ data, open, onClose, onSubmit }: EmailConfigModalProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors }
  } = useForm<EmailConfigFormType>({
    resolver: zodResolver(emailConfigSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });
  const { palette } = useTheme();
  const [showPassword, setShowPassword] = useState(false);

  const { mutate: createEmailConfigMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: EmailConfigFormType) => createEmailConfig(body),
    onSuccess: () => {
      toast.success("Email Config Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  const { mutate: updateEmailConfigMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: EmailConfigFormType }) =>
      updateEmailConfig(id, body),
    onSuccess: () => {
      toast.success("Email Config Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(body: EmailConfigFormType) {
    if (data === "NEW") {
      createEmailConfigMutation(body);
    } else if (data) {
      updateEmailConfigMutation({ id: data.id, body });
    }
  }

  useEffect(() => {
    reset(getFormValues(data));
  }, [data, reset]);

  return (
    <ModalContainer
      title="Create New Email Config"
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
    >
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2}
        sx={{
          width: 1,
          display: "flex",
          marginTop: 2
        }}
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
        <Grid size={6}>
          <TextField
            label="Host"
            variant="filled"
            fullWidth
            error={!!errors.host}
            helperText={errors.host?.message}
            {...register("host")}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Port"
            variant="filled"
            fullWidth
            type="number"
            error={!!errors.port}
            helperText={errors.port?.message}
            {...register("port", { valueAsNumber: true })}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Username"
            variant="filled"
            fullWidth
            error={!!errors.username}
            helperText={errors.username?.message}
            slotProps={{ input: { autoComplete: "false" } }}
            {...register("username")}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Password"
            variant="filled"
            fullWidth
            type={showPassword ? "text" : "password"}
            error={!!errors.password}
            helperText={errors.password?.message}
            {...register("password")}
            slotProps={{
              input: {
                autoComplete: "false",
                endAdornment: (
                  <IconButton disableRipple onClick={() => setShowPassword((prev) => !prev)}>
                    {showPassword ? (
                      <HiEyeOff color={palette.secondary.main} size="1.2rem" />
                    ) : (
                      <HiEye color={palette.secondary.main} size="1.2rem" />
                    )}
                  </IconButton>
                )
              }
            }}
          />
        </Grid>
        <Grid size={12}>
          <TextField
            label="From Address"
            variant="filled"
            fullWidth
            type="email"
            error={!!errors.fromAddress}
            helperText={errors.fromAddress?.message}
            {...register("fromAddress")}
          />
        </Grid>
        <Grid
          size={12}
          sx={{
            marginTop: 1
          }}
        >
          <Button
            disabled={isCreating || isUpdating}
            type="submit"
            variant="contained"
            size="large"
            fullWidth
          >
            CREATE
          </Button>
        </Grid>
      </Grid>
    </ModalContainer>
  );
}
