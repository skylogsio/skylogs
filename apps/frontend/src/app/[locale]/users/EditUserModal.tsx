import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Grid, TextField, Typography, useTheme } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { BasicCreateOrUpdateModalProps } from "@/@types/global";
import type { IUser } from "@/@types/user";
import { updateUser } from "@/api/user";
import ModalContainer from "@/components/Modal";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme, useRole } from "@/hooks";
import { ROLE_TYPES } from "@/utils/userUtils";

import GradientSubmitButton from "@/components/GradientSubmitButton";
import UserModalBody from "./UserModalBody";
import UserRoleToggle from "./UserRoleToggle";

const updateUserSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  role: z.enum(ROLE_TYPES, "This field is Required."),
  username: z.string().trim().min(1, "This field is Required.")
});

type UserFormType = z.infer<typeof updateUserSchema>;

const emptyFormValues: UserFormType = {
  name: "",
  role: "member",
  username: ""
};

function getFormValues(userData?: IUser): UserFormType {
  if (!userData) {
    return emptyFormValues;
  }

  return {
    name: userData.name,
    role: userData.roles[0],
    username: userData.username
  };
}

export default function EditUserModal({
  open,
  onClose,
  onSubmit,
  userData
}: BasicCreateOrUpdateModalProps & { userData: IUser }) {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    formState: { errors }
  } = useForm<UserFormType>({
    resolver: zodResolver(updateUserSchema),
    defaultValues: getFormValues(userData),
    mode: "onSubmit"
  });
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const { hasRole } = useRole();

  const { mutate: updateUserMutation, isPending: isUpdating } = useMutation({
    mutationFn: (body: UserFormType) => updateUser(userData.id, body),
    onSuccess: () => {
      toast.success("User Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(data: UserFormType) {
    updateUserMutation(data);
  }

  useEffect(() => {
    reset(getFormValues(userData));
  }, [reset, open, userData]);

  return (
    <ModalContainer
      title="Edit User"
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
          {watch("role") !== "owner" && hasRole("owner") && (
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
          <Grid size={12}>
            <GradientSubmitButton type="submit" fullWidth loading={isUpdating}>
              Save
            </GradientSubmitButton>
          </Grid>
        </Grid>
      </UserModalBody>
    </ModalContainer>
  );
}
