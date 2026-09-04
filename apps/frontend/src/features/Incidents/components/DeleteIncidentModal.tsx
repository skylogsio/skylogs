"use client";

import { Stack, Typography, useTheme } from "@mui/material";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "react-toastify";

import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import { deleteIncident } from "../incident.api";
import type { IIncident } from "../incident.type";

import IncidentSeverityChip from "./IncidentSeverityChip";
import IncidentStatusChip from "./IncidentStatusChip";

export default function DeleteIncidentModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IIncident }) {
  const { id, title, severity, status } = data;
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();

  const { mutate: deleteIncidentMutation, isPending } = useMutation({
    mutationFn: () => deleteIncident(id),
    onSuccess(response) {
      if (response.status) {
        void queryClient.invalidateQueries({ queryKey: ["incident"] });
        onAfterDelete?.();
        toast.success("Incident Deleted Successfully.");
        return;
      }
      toast.error(response.message);
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={deleteIncidentMutation}
      isLoading={isPending}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <Stack spacing={1.25} sx={{ width: 1 }}>
        <Typography variant="body2" sx={{ color: "text.secondary" }}>
          This also deletes the postmortem, timeline, documents, and action items.
        </Typography>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Title:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {title}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Severity:
          </Typography>
          <IncidentSeverityChip severity={severity} />
        </Stack>
        <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Status:
          </Typography>
          <IncidentStatusChip status={status} />
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
