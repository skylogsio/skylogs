import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Button,
  Grid,
  IconButton,
  TextField,
  ToggleButton,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { BasicCreateOrUpdateModalProps } from "@/@types/global";
import { createUser } from "@/api/user";
import ModalContainer from "@/components/Modal";
import ToggleButtonGroup from "@/components/ToggleButtonGroup";
import { useRole } from "@/hooks";
import { ROLE_TYPES } from "@/utils/userUtils";

const createUserSchema = z
  .object({
    name: z.string().trim().min(1, "This field is Required."),
    role: z.enum(ROLE_TYPES, "This field is Required."),
    username: z.string().trim().min(1, "This field is Required."),
    //TODO: Add more validation for password
    password: z.string().trim().min(1, "This field is Required."),
    confirmPassword: z.string().trim().min(1, "This field is Required.")
  })
  .refine((data) => data.password === data.confirmPassword, {
    message: "Confirm Password does not match.",
    path: ["confirmPassword"]
  });

type UserFormType = z.infer<typeof createUserSchema>;

const defaultValues: UserFormType = {
  name: "",
  role: "member",
  username: "",
  password: "",
  confirmPassword: ""
};

export default function CreateUserModal({
  open,
  onClose,
  onSubmit
}: BasicCreateOrUpdateModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    formState: { errors }
  } = useForm<UserFormType>({
    resolver: zodResolver(createUserSchema),
    defaultValues,
    mode: "onSubmit"
  });
  const { palette } = useTheme();
  const { hasRole } = useRole();
  const [showPassword, setShowPassword] = useState(false);

  const { mutate: createUserMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: UserFormType) => createUser(body),
    onSuccess: (data) => {
      if (data!.status) {
        toast.success("User Created Successfully.");
        onSubmit();
        onClose?.();
      } else {
        toast.error(data?.message as string);
      }
    }
  });

  function handleSubmitForm(data: UserFormType) {
    createUserMutation(data);
  }

  useEffect(() => {
    reset(defaultValues);
  }, [reset, open]);

  return (
    <ModalContainer title="Create New User" open={open} onClose={onClose} disableEscapeKeyDown>
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2}
        sx={{
          width: 1,
          display: "flex",
          marginTop: "2rem"
        }}
      >
        {hasRole("owner") && (
          <Grid
            size={12}
            sx={{
              display: "flex",
              justifyContent: "flex-start",
              alignItems: "center"
            }}
          >
            <Typography
              variant="body1"
              component="div"
              sx={{
                marginRight: 1
              }}
            >
              Role:
            </Typography>
            <ToggleButtonGroup
              exclusive
              value={watch("role")}
              onChange={(_, value) => setValue("role", value)}
            >
              {ROLE_TYPES.filter((role) => role !== "owner").map((role) => (
                <ToggleButton
                  key={role}
                  value={role}
                  sx={{ textTransform: "capitalize !important" }}
                >
                  {role}
                </ToggleButton>
              ))}
            </ToggleButtonGroup>
          </Grid>
        )}
        <Grid size={6}>
          <TextField
            label="Username"
            variant="filled"
            {...register("username")}
            error={!!errors.username}
            helperText={errors.username?.message}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Full Name"
            variant="filled"
            {...register("name")}
            error={!!errors.name}
            helperText={errors.name?.message}
          />
        </Grid>

        <Grid size={6}>
          <TextField
            label="Password"
            type={showPassword ? "text" : "password"}
            variant="filled"
            {...register("password")}
            error={!!errors.password}
            helperText={errors.password?.message}
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
        <Grid size={6}>
          <TextField
            label="Confirm Password"
            type={showPassword ? "text" : "password"}
            variant="filled"
            {...register("confirmPassword")}
            error={!!errors.confirmPassword}
            helperText={errors.confirmPassword?.message}
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
          <Button type="submit" variant="contained" size="large" fullWidth disabled={isCreating}>
            Create
          </Button>
        </Grid>
      </Grid>
    </ModalContainer>
  );
}
