import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Grid, IconButton, TextField, Typography, useTheme } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { BasicCreateOrUpdateModalProps } from "@/@types/global";
import { createUser } from "@/api/user";
import ModalContainer from "@/components/Modal";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme, useRole } from "@/hooks";
import { ROLE_TYPES } from "@/utils/userUtils";

import GradientSubmitButton from "@/components/GradientSubmitButton";
import UserModalBody from "./UserModalBody";
import UserRoleToggle from "./UserRoleToggle";

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
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();
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
    <ModalContainer
      title="Create New User"
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <UserModalBody>
        <Grid
          component="form"
          onSubmit={handleSubmit(handleSubmitForm)}
          container
          spacing={2}
          sx={{
            width: 1,
            display: "flex"
          }}
        >
          {hasRole("owner") && (
            <Grid
              size={12}
              sx={{
                display: "flex",
                justifyContent: "flex-start",
                alignItems: "center",
                gap: 1.5
              }}
            >
              <Typography
                variant="body1"
                component="div"
                sx={{
                  color: "text.secondary",
                  fontWeight: 600
                }}
              >
                Role
              </Typography>
              <UserRoleToggle value={watch("role")} onChange={(role) => setValue("role", role)} />
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
                    <IconButton
                      disableRipple
                      aria-label={showPassword ? "Hide password" : "Show password"}
                      onClick={() => setShowPassword((prev) => !prev)}
                    >
                      {showPassword ? (
                        <HiEyeOff color={palette.grey[400]} size={20} />
                      ) : (
                        <HiEye color={palette.grey[400]} size={20} />
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
                    <IconButton
                      disableRipple
                      aria-label={showPassword ? "Hide password" : "Show password"}
                      onClick={() => setShowPassword((prev) => !prev)}
                    >
                      {showPassword ? (
                        <HiEyeOff color={palette.grey[400]} size={20} />
                      ) : (
                        <HiEye color={palette.grey[400]} size={20} />
                      )}
                    </IconButton>
                  )
                }
              }}
            />
          </Grid>
          <Grid size={12}>
            <GradientSubmitButton type="submit" fullWidth loading={isCreating}>
              Create
            </GradientSubmitButton>
          </Grid>
        </Grid>
      </UserModalBody>
    </ModalContainer>
  );
}
