"use client";

import { Stack, Typography } from "@mui/material";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { ICluster } from "@/@types/cluster";
import { deleteCluster } from "@/api/cluster";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteClusterModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: ICluster }) {
  const { id, name, type, url } = data;
  const queryClient = useQueryClient();

  const { mutate: deleteClusterMutation, isPending } = useMutation({
    mutationFn: () => deleteCluster(id),
    onSuccess() {
      toast.success("Cluster Deleted Successfully.");
      queryClient.invalidateQueries({ queryKey: ["all-clusters"] });
      queryClient.invalidateQueries({ queryKey: ["all-zone-list"] });
      onAfterDelete?.();
    }
  });

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteClusterMutation} isLoading={isPending}>
      <Stack spacing={1}>
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            Name:
          </Typography>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary"
            }}
          >
            {name}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            Type:
          </Typography>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary"
            }}
          >
            {type}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            URL:
          </Typography>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary"
            }}
          >
            {url}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
