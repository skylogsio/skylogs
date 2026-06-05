"use client";
import { useEffect, useMemo } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  alpha,
  Box,
  Button,
  Grid,
  MenuItem,
  TextField,
  Typography,
  useColorScheme
} from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Editor } from "prism-react-editor";
import { BasicSetup } from "prism-react-editor/setups";
import { loadTheme } from "prism-react-editor/themes";
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
import "prism-react-editor/search.css";

const profileServiceSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  ownerId: z.string().trim().min(1, "This field is Required."),
  config: z
    .string()
    .trim()
    .min(1, "This field is Required.")
    .refine(
      (value) => {
        try {
          JSON.parse(value);
          return true;
          // eslint-disable-next-line @typescript-eslint/no-unused-vars
        } catch (error) {
          return false;
        }
      },
      { error: "Invalid JSON Format." }
    )
});

type ProfileServiceFormType = z.infer<typeof profileServiceSchema>;
type ProfileServiceModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ProfileServiceFormType & { id: string }>;
  onSubmit: () => void;
};

const emptyFormValues: ProfileServiceFormType = {
  name: "",
  ownerId: "",
  config: ""
};

function getFormValues(
  data: CreateUpdateModal<ProfileServiceFormType & { id: string }>
): ProfileServiceFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    ownerId: data.ownerId,
    config: data.config
  };
}

export default function ProfileServiceModal({
  open,
  onClose,
  data,
  onSubmit
}: ProfileServiceModalProps) {
  const { systemMode, mode } = useColorScheme();
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
    defaultValues: getFormValues(data),
    mode: "onSubmit"
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
    reset(getFormValues(data));
  }, [data, open, reset]);

  useEffect(() => {
    const isDark = (systemMode || mode) === "dark";
    const style = document.querySelector("style");

    loadTheme(isDark ? "vs-code-dark" : "vs-code-light").then((theme) => {
      if (style && style.textContent) style.textContent = theme ?? "";
    });
  }, [systemMode, mode]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Endpoint`}
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
              backgroundColor: ({ palette }) => alpha(palette.primary.dark, 0.06),
              "&:hover": {
                backgroundColor: ({ palette }) => alpha(palette.primary.dark, 0.1)
              },
              "& .prism-code-editor": {
                backgroundColor: "transparent",
                "& .pce-line.active-line::after": { border: "none" }
              }
            }}
          >
            <Box
              sx={{
                width: 1,
                height: 1,
                maxHeight: 500,
                overflow: "auto",
                padding: 1,
                paddingLeft: 0
              }}
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
            <Typography
              variant="caption"
              color="error"
              sx={{
                paddingLeft: 2
              }}
            >
              {errors.config.message}
            </Typography>
          )}
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
