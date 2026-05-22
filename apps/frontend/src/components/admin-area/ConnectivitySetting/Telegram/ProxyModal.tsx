import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid, IconButton, MenuItem, TextField, useTheme } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { CreateUpdateModal } from "@/@types/global";
import type { ITelegramProxy } from "@/@types/settings/telegram";
import { createTelegramProxy, updateTelegramProxy } from "@/api/setttings/telegram";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const PROXY_TYPES = ["http", "socks5"] as const;

const proxySchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  type: z.enum(PROXY_TYPES),
  host: z.string().trim().min(1, "This field is Required."),
  port: z.number("This field is Required.").int().min(0).max(65535),
  username: z.string().optional(),
  password: z.string().optional()
});

type ProxyFormType = z.infer<typeof proxySchema>;

type ProxyModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ITelegramProxy>;
  onSubmit: () => void;
};

const emptyFormValues: ProxyFormType = {
  name: "",
  type: "http",
  host: "",
  port: 1080,
  username: "",
  password: ""
};

function getFormValues(data: CreateUpdateModal<ITelegramProxy>): ProxyFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    type: data.type,
    host: data.host,
    port: data.port,
    username: data.username ?? "",
    password: data.password ?? ""
  };
}

export default function ProxyModal({ data, open, onClose, onSubmit }: ProxyModalProps) {
  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors }
  } = useForm<ProxyFormType>({
    resolver: zodResolver(proxySchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });
  const { palette } = useTheme();
  const [showPassword, setShowPassword] = useState(false);

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
    reset(getFormValues(data));
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
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2}
        sx={{
          width: 1,
          display: "flex",
          marginTop: 4
        }}
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
            label="Type"
            variant="filled"
            error={!!errors.type}
            helperText={errors.type?.message}
            {...register("type")}
            value={watch("type") ?? ""}
            select
          >
            {PROXY_TYPES.map((item) => (
              <MenuItem key={item} value={item} sx={{ textTransform: "capitalize" }}>
                {item.replace("-", " ")}
              </MenuItem>
            ))}
          </TextField>
        </Grid>
        <Grid size={6}>
          <TextField
            label="Host"
            variant="filled"
            error={!!errors.host}
            helperText={errors.host?.message}
            {...register("host")}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Port"
            variant="filled"
            error={!!errors.port}
            helperText={errors.port?.message}
            type="number"
            {...register("port", { valueAsNumber: true })}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Username"
            variant="filled"
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
        <Grid
          size={12}
          sx={{
            marginTop: 2
          }}
        >
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
