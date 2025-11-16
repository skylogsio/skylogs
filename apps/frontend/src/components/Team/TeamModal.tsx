import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid2 as Grid, MenuItem, Stack, TextField, Chip } from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Controller, useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { CreateUpdateModal } from "@/@types/global";
import type { ITeam } from "@/@types/team";
import { createTeam, updateTeam } from "@/api/team";
import { getAllUsers } from "@/api/user";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const teamSchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  ownerId: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  userIds: z.array(z.string()).min(1, "At least one user is required."),
  description: z.string().optional().default("")
});

type TeamFormType = z.infer<typeof teamSchema>;

type TeamModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ITeam>;
  onSubmit: () => void;
};

const defaultValues: TeamFormType = {
  name: "",
  ownerId: "",
  userIds: [],
  description: ""
};

export default function TeamModal({ open, onClose, data, onSubmit }: TeamModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    getValues,
    control,
    formState: { errors }
  } = useForm<TeamFormType>({
    resolver: zodResolver(teamSchema),
    defaultValues
  });

  const { data: allUsers } = useQuery({ queryKey: ["all-users"], queryFn: () => getAllUsers() });

  const { mutate: createTeamMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: TeamFormType) => createTeam(body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Team Created Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  const { mutate: updateTeamMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: TeamFormType }) => updateTeam(id, body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Team Updated Successfully.");
        onSubmit();
        onClose?.();
      }
    }
  });

  function handleSubmitForm(values: TeamFormType) {
    if (data === "NEW") {
      createTeamMutation(values);
    } else if (data) {
      updateTeamMutation({ id: data.id, body: values });
    }
  }

  const handleRemoveUserChip = (userId: string) => {
    const selected = getValues("userIds");
    setValue(
      "userIds",
      selected.filter((id) => id !== userId)
    );
  };

  const renderUserChips = (selectedIds: unknown) => {
    const selected = allUsers?.filter((user) => (selectedIds as string[]).includes(user.id)) ?? [];
    return (
      <Stack
        gap={1}
        direction="row"
        flexWrap="wrap"
        justifyContent="flex-start"
        sx={{ float: "left" }}
        onMouseDown={(event) => event.stopPropagation()}
      >
        {selected.map((user) => (
          <Chip
            key={user.id}
            label={user.name}
            size="small"
            onDelete={() => handleRemoveUserChip(user.id)}
          />
        ))}
      </Stack>
    );
  };

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else if (data) {
      reset({
        name: data.name,
        ownerId: data.ownerId,
        userIds: data.userIds,
        description: data.description
      });
    }
  }, [data, reset]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Team`}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
    >
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2}
        width="100%"
        display="flex"
        marginTop="2rem"
      >
        <Grid size={12}>
          <TextField
            label="Team Name"
            variant="filled"
            error={!!errors.name}
            helperText={errors.name?.message}
            {...register("name")}
            fullWidth
          />
        </Grid>

        <Grid size={12}>
          <TextField
            select
            label="Owner"
            variant="filled"
            error={!!errors.ownerId}
            helperText={errors.ownerId?.message as string}
            value={watch("ownerId")}
            {...register("ownerId")}
          >
            {allUsers?.map((user) => (
              <MenuItem key={user.id} value={user.id}>
                {user.name}
              </MenuItem>
            ))}
          </TextField>
        </Grid>

        <Grid size={12}>
          <Controller
            control={control}
            name="userIds"
            render={({ field }) => (
              <TextField
                {...field}
                select
                label="Users"
                variant="filled"
                error={!!errors.userIds}
                helperText={errors.userIds?.message as string}
                value={field.value ?? []}
                slotProps={{
                  select: {
                    multiple: true,
                    renderValue: renderUserChips
                  }
                }}
              >
                {allUsers?.map((user) => (
                  <MenuItem key={user.id} value={user.id}>
                    {user.name}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />
        </Grid>
        <Grid size={12}>
          <TextField
            {...register("description")}
            label="Description"
            variant="filled"
            error={!!errors.description}
            helperText={errors.description?.message as string}
            multiline
            minRows={3}
            maxRows={8}
            fullWidth
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
