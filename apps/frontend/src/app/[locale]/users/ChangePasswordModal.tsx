import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Button,
  Grid2 as Grid,
  IconButton,
  TextField,
  useTheme
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import * as z from "zod";

import { changePassword } from "@/api/user";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const changePasswordSchema = z
  .object({
    //TODO: Add more validation for password
    password: z
      .string({ required_error: "This field is Required." })
      .refine((data) => data.trim() !== "", {
        message: "This field is Required."
      }),
    confirmPassword: z
      .string({ required_error: "This field is Required." })
      .refine((data) => data.trim() !== "", {
        message: "This field is Required."
      })
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
    defaultValues
  });
  const { palette } = useTheme();
  const [showPassword, setShowPassword] = useState(false);

  const { mutate: changePasswordMutation, isPending: isCreating } = useMutation({
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
    <ModalContainer title="Change User Password" open={open} onClose={onClose} disableEscapeKeyDown>
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2}
        width="100%"
        display="flex"
        marginTop="2rem"
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
            label="Confirm New Password"
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

        <Grid size={12} marginTop="1rem">
          <Button type="submit" variant="contained" size="large" fullWidth disabled={isCreating}>
            Change Password
          </Button>
        </Grid>
      </Grid>
    </ModalContainer>
  );
}
