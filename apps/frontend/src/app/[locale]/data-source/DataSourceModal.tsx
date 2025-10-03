import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Button,
  Grid2 as Grid,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IDataSource } from "@/@types/dataSource";
import { type CreateUpdateModal } from "@/@types/global";
import { createDataSource, updateDataSource } from "@/api/dataSource";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { DATA_SOURCE_VARIANTS } from "@/utils/dataSourceUtils";

const schema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  type: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  url: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  api_token: z.string().optional(),
  username: z.string().optional(),
  password: z.string().optional()
});

type DataSourceFormType = z.infer<typeof schema> & { chatId?: string };
type DataSourceModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IDataSource>;
  onSubmit: () => void;
};

const defaultValues = {
  name: "",
  type: "",
  url: "",
  api_token: "",
  username: "",
  password: ""
};

export default function DataSourceModal({ open, onClose, data, onSubmit }: DataSourceModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors }
  } = useForm<DataSourceFormType>({
    resolver: zodResolver(schema),
    defaultValues
  });
  const { palette } = useTheme();
  const [showPassword, setShowPassword] = useState(false);

  const { mutate: createDataSourceMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: DataSourceFormType) => createDataSource(body),
    onSuccess: () => {
      toast.success("DataSource Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });
  const { mutate: updateDataSourceMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: DataSourceFormType }) =>
      updateDataSource(id, body),
    onSuccess: () => {
      toast.success("DataSource Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function renderDataSourceList() {
    return Object.entries(DATA_SOURCE_VARIANTS).map(([key, value]) => (
      <MenuItem key={key} value={key}>
        <Stack direction="row" alignItems="center" spacing={1}>
          <value.Icon size={value.defaultSize} color={value.defaultColor} />
          <Typography component="span">{value.label}</Typography>
        </Stack>
      </MenuItem>
    ));
  }

  function handleSubmitForm(body: DataSourceFormType) {
    if (data === "NEW") {
      createDataSourceMutation(body);
    } else if (data) {
      updateDataSourceMutation({ id: data.id, body });
    }
  }

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else {
      reset(data as DataSourceFormType);
    }
  }, [data, reset]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Data Source`}
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
        <Grid size={12}>
          <TextField
            label="Type Of Data Source"
            variant="filled"
            error={!!errors.type}
            helperText={errors.type?.message}
            {...register("type")}
            value={watch("type") ?? ""}
            select
          >
            {renderDataSourceList()}
          </TextField>
        </Grid>
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
        <Grid size={6}>
          <TextField
            label="Username"
            variant="filled"
            error={!!errors.username}
            helperText={errors.username?.message}
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
            label="API Token"
            variant="filled"
            error={!!errors.api_token}
            helperText={errors.api_token?.message}
            {...register("api_token")}
            multiline
            minRows={2}
            maxRows={8}
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
