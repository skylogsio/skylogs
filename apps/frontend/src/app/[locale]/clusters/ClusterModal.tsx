"use client";

import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid, TextField } from "@mui/material";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { ICluster } from "@/@types/cluster";
import { type CreateUpdateModal } from "@/@types/global";
import { createCluster, updateCluster } from "@/api/cluster";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const clusterSchema = z.object({
  name: z.string().trim().min(1, "This field is required."),
  type: z.enum(["agent", "main"]),
  url: z.string().trim().min(1, "This field is required.").pipe(z.url("Please enter a valid URL."))
});

type ClusterFormType = z.infer<typeof clusterSchema>;
type ClusterModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ICluster>;
  onSubmit: () => void;
};

const emptyFormValues: ClusterFormType = {
  name: "",
  type: "agent",
  url: ""
};

function getFormValues(data: CreateUpdateModal<ICluster>): ClusterFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name || "",
    type: (data.type as "agent" | "main") || "agent",
    url: data.url || ""
  };
}

export default function ClusterModal({ open, onClose, data, onSubmit }: ClusterModalProps) {
  const queryClient = useQueryClient();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors }
  } = useForm<ClusterFormType>({
    resolver: zodResolver(clusterSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });

  const { mutate: createClusterMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: ClusterFormType) => createCluster(body),
    onSuccess: async () => {
      toast.success("Agent Cluster Created Successfully.");
      await queryClient.invalidateQueries({ queryKey: ["all-clusters"] });
      await queryClient.invalidateQueries({ queryKey: ["all-zone-list"] });
      await queryClient.invalidateQueries({ queryKey: ["agent-cluster-status"] });
      onSubmit();
      onClose?.();
    },
    onError: (error: any) => {
      toast.error(error?.message || "Failed to create cluster");
    }
  });

  const { mutate: updateClusterMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: ClusterFormType }) => updateCluster(id, body),
    onSuccess: async () => {
      toast.success("Cluster Updated Successfully.");
      await queryClient.invalidateQueries({ queryKey: ["all-clusters"] });
      await queryClient.invalidateQueries({ queryKey: ["all-zone-list"] });
      await queryClient.invalidateQueries({ queryKey: ["agent-cluster-status"] });
      onSubmit();
      onClose?.();
    },
    onError: (error: any) => {
      toast.error(error?.message || "Failed to update cluster");
    }
  });

  function handleSubmitForm(body: ClusterFormType) {
    const payload: ClusterFormType = {
      name: body.name.trim(),
      type: body.type || (data !== "NEW" && data?.type) || "agent",
      url: body.url.trim()
    };

    if (data === "NEW") {
      createClusterMutation(payload);
    } else if (data) {
      const clusterId = data.id || (data as any)?._id;
      if (clusterId) {
        updateClusterMutation({ id: clusterId, body: payload });
      }
    }
  }

  useEffect(() => {
    reset(getFormValues(data));
  }, [data, reset]);

  const isNew = data === "NEW";

  return (
    <ModalContainer
      title={isNew ? "Add Agent Cluster" : "Update Cluster"}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
      maxWidth={480}
    >
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2.5}
        sx={{
          width: "100%",
          display: "flex",
          mt: 2
        }}
      >
        <input type="hidden" {...register("type")} />
        <Grid size={12}>
          <TextField
            label="Name"
            variant="filled"
            placeholder="agent-east"
            error={!!errors.name}
            helperText={errors.name?.message}
            {...register("name")}
            fullWidth
          />
        </Grid>

        <Grid size={12}>
          <TextField
            label="URL"
            variant="filled"
            error={!!errors.url}
            helperText={errors.url?.message}
            {...register("url")}
            placeholder="http://172.28.8.11:7000"
            fullWidth
          />
        </Grid>

        <Grid size={12} sx={{ mt: 1 }}>
          <Button
            disabled={isCreating || isUpdating}
            type="submit"
            variant="contained"
            size="large"
            fullWidth
            sx={{
              borderRadius: "8px",
              py: 1.25,
              fontWeight: 600,
              textTransform: "none"
            }}
          >
            {isNew ? "Add Agent Cluster" : "Update"}
          </Button>
        </Grid>
      </Grid>
    </ModalContainer>
  );
}
