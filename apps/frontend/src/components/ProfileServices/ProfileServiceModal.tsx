import { useEffect, useMemo } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Box, Button, Grid2 as Grid, MenuItem, TextField, Typography } from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Editor } from "prism-react-editor";
import { BasicSetup } from "prism-react-editor/setups";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { CreateUpdateModal } from "@/@types/global";
import { createProfileService, updateProfileService } from "@/api/profileService";
import { getAllUsers } from "@/api/user";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

import "prism-react-editor/prism/languages/json";
import "prism-react-editor/languages/json";
import "prism-react-editor/layout.css";
import "prism-react-editor/themes/vs-code-light.css";
import "prism-react-editor/search.css";

const profileServiceSchema = z.object({
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
  config: z.string().refine(
    (value) => {
      try {
        JSON.parse(value);
        return true;
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
      } catch (error) {
        return false;
      }
    },
    { message: "Invalid JSON Format." }
  )
});

type ProfileServiceFormType = z.infer<typeof profileServiceSchema>;
type ProfileServiceModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ProfileServiceFormType & { id: string }>;
  onSubmit: () => void;
};

const defaultValues: ProfileServiceFormType = {
  name: "",
  ownerId: "",
  config: ""
};

export default function ProfileServiceModal({
  open,
  onClose,
  data,
  onSubmit
}: ProfileServiceModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    trigger,
    formState: { errors }
  } = useForm<ProfileServiceFormType>({
    resolver: zodResolver(profileServiceSchema),
    defaultValues
  });

  const { data: allUsers } = useQuery({ queryKey: ["all-users"], queryFn: () => getAllUsers() });

  const { mutate: createProfileServiceMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: ProfileServiceFormType) => createProfileService(body),
    onSuccess: () => {
      toast.success("Profile Service Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });
  const { mutate: updateProfileServiceMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: ProfileServiceFormType }) =>
      updateProfileService(id, body),
    onSuccess: () => {
      toast.success("Profile Service Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(body: ProfileServiceFormType) {
    console.log(body);
    if (data === "NEW") {
      createProfileServiceMutation(body);
    } else if (data) {
      updateProfileServiceMutation({ id: data.id, body });
    }
  }

  const editorValue = useMemo(
    () => (data === "NEW" ? "" : (data as unknown as ProfileServiceFormType).config),
    [data]
  );

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else {
      reset(data as ProfileServiceFormType);
    }
  }, [data, open, reset]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Endpoint`}
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
            select
            label="User"
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
          <Box
            sx={{
              borderRadius: 3,
              overflow: "hidden",
              backgroundColor: "#F1F4F9",
              "& .prism-code-editor": { backgroundColor: "transparent" }
            }}
          >
            <Box
              width="100%"
              height="100%"
              maxHeight={500}
              overflow="auto"
              padding={1}
              paddingLeft={0}
            >
              <Editor
                language="json"
                value={editorValue}
                onUpdate={(value) => {
                  setValue("config", value);
                  trigger("config");
                }}
                textareaProps={{ id: "profile-service-config-json" }}
              >
                {(editor) => <BasicSetup editor={editor} />}
              </Editor>
            </Box>
          </Box>
          {errors.config && (
            <Typography variant="caption" color="error" paddingLeft={2}>
              {errors.config.message}
            </Typography>
          )}
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
