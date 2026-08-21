"use client";

import { Stack, Typography, useTheme } from "@mui/material";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "react-toastify";

import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import { deleteIncidentPolicy } from "../incident-policy.api";
import type { IIncidentPolicy } from "../incident-policy.type";

export default function DeleteIncidentPolicyModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IIncidentPolicy }) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();

  const { mutate, isPending } = useMutation({
    mutationFn: () => deleteIncidentPolicy(data.id),
    onSuccess(response) {
      if (response.status) {
        void queryClient.invalidateQueries({ queryKey: ["incident-policy"] });
        onAfterDelete?.();
        toast.success("Incident Policy Deleted Successfully.");
        return;
      }
      toast.error(response.message);
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={mutate}
      isLoading={isPending}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <Stack spacing={1.25} sx={{ width: 1 }}>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Name:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {data.name}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Enabled:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {data.enabled ? "Yes" : "No"}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
