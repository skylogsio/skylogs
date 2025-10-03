import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid2 as Grid, TextField, ToggleButton, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import * as z from "zod";

import type { BasicCreateOrUpdateModalProps } from "@/@types/global";
import type { IUser } from "@/@types/user";
import { updateUser } from "@/api/user";
import ModalContainer from "@/components/Modal";
import ToggleButtonGroup from "@/components/ToggleButtonGroup";
import { useRole } from "@/hooks";
import { ROLE_TYPES } from "@/utils/userUtils";

const updateUserSchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  role: z.enum(ROLE_TYPES, {
    required_error: "This field is Required.",
    message: "This field is Required."
  }),
  username: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    })
});

type UserFormType = z.infer<typeof updateUserSchema>;

const defaultValues: UserFormType = {
  name: "",
  role: "member",
  username: ""
};

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
    defaultValues
  });
  const { hasRole } = useRole();

  const { mutate: updateUserMutation, isPending: isCreating } = useMutation({
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
    if (userData) {
      const temp = { ...userData, role: userData.roles[0] };
      reset(temp as UserFormType);
    } else {
      reset(defaultValues);
    }
  }, [reset, open, userData]);

  return (
    <ModalContainer title="Edit User" open={open} onClose={onClose} disableEscapeKeyDown>
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2}
        width="100%"
        display="flex"
        marginTop="2rem"
      >
        {watch("role") !== "owner" && hasRole("owner") && (
          <Grid size={12} display="flex" justifyContent="flex-start" alignItems="center">
            <Typography variant="body1" component="div" marginRight="0.7rem">
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
        <Grid size={12} marginTop="1rem">
          <Button type="submit" variant="contained" size="large" fullWidth disabled={isCreating}>
            Edit
          </Button>
        </Grid>
      </Grid>
    </ModalContainer>
  );
}
