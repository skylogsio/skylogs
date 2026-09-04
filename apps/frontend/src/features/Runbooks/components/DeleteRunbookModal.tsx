"use client";

import { Stack, Typography, useTheme } from "@mui/material";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "react-toastify";

import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import { deleteRunbook } from "../runbook.api";
import type { IRunbook } from "../runbook.type";
import { RUNBOOK_SOURCE_TYPE_LABELS, RUNBOOK_STATUS_LABELS } from "../runbook.utils";

export default function DeleteRunbookModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IRunbook }) {
  const { id, name, status, sourceType } = data;
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();

  const { mutate: deleteRunbookMutation, isPending } = useMutation({
    mutationFn: () => deleteRunbook(id),
    onSuccess(response) {
      if (response.status) {
        void queryClient.invalidateQueries({ queryKey: ["runbook"] });
        void queryClient.invalidateQueries({ queryKey: ["published-runbooks"] });
        onAfterDelete?.();
        toast.success("Runbook Deleted Successfully.");
        return;
      }
      toast.error(response.message);
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={deleteRunbookMutation}
      isLoading={isPending}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <Stack spacing={1.25} sx={{ width: 1 }}>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Name:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {name}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Status:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {RUNBOOK_STATUS_LABELS[status]}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Source:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {RUNBOOK_SOURCE_TYPE_LABELS[sourceType]}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
