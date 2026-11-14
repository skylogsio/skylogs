import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Grid, IconButton, TextField, useTheme } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import { changePassword } from "@/api/user";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import GradientSubmitButton from "@/components/GradientSubmitButton";
import UserModalBody from "./UserModalBody";

const changePasswordSchema = z
  .object({
    //TODO: Add more validation for password
    password: z.string().trim().min(1, "This field is Required."),
    confirmPassword: z.string().trim().min(1, "This field is Required.")
  })
  .refine((data) => data.password === data.confirmPassword, {
    message: "Confirm Password does not match.",
    path: ["confirmPassword"]
  });

type ChangePasswordFormType = z.infer<typeof changePasswordSchema>;

const defaultValues: ChangePasswordFormType = {
  password: "",
  confirmPassword: ""
};

export default function ChangePasswordModal({
  open,
  onClose,
  userId
}: Pick<ModalContainerProps, "open" | "onClose"> & { userId: string }) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors }
  } = useForm<ChangePasswordFormType>({
    resolver: zodResolver(changePasswordSchema),
    defaultValues,
    mode: "onSubmit"
  });
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();
  const [showPassword, setShowPassword] = useState(false);

  const { mutate: changePasswordMutation, isPending: isUpdating } = useMutation({
    mutationFn: (body: ChangePasswordFormType) => changePassword(userId, body),
    onSuccess: () => {
      toast.success("Password Changed Successfully.");
      onClose?.();
    }
  });

  function handleSubmitForm(data: ChangePasswordFormType) {
    changePasswordMutation(data);
  }

  useEffect(() => {
    reset(defaultValues);
  }, [reset, open]);

  return (
    <ModalContainer
      title="Change User Password"
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
              label="Confirm New Password"
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
            <GradientSubmitButton type="submit" fullWidth loading={isUpdating}>
              Change Password
            </GradientSubmitButton>
          </Grid>
        </Grid>
      </UserModalBody>
    </ModalContainer>
  );
}
