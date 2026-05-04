import { Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { ICallConfig } from "@/@types/admin-area/callConfig";
import { deleteCallConfig } from "@/api/admin-area/callConfig";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteCallConfigModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: ICallConfig }) {
  const { id, name, apiToken } = data;

  const { mutate: deleteCallConfigMutation, isPending } = useMutation({
    mutationFn: () => deleteCallConfig(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("Call Config Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteCallConfigMutation} isLoading={isPending}>
      <Stack spacing={1}>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Name:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {name}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            API Token:
          </Typography>
          <Typography
            variant="subtitle2"
            color="text.secondary"
            sx={{ wordBreak: "break-word", flex: 1 }}
          >
            {apiToken}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
