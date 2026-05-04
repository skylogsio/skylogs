import { Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IEmailConfig } from "@/@types/admin-area/emailConfig";
import { deleteEmailConfig } from "@/api/admin-area/emailConfig";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteEmailConfigModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IEmailConfig }) {
  const { id, name, fromAddress, host, port } = data;

  const { mutate: deleteEmailConfigMutation, isPending } = useMutation({
    mutationFn: () => deleteEmailConfig(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("Email Config Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={deleteEmailConfigMutation}
      isLoading={isPending}
    >
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
            From Address:
          </Typography>
          <Typography
            variant="subtitle2"
            color="text.secondary"
            sx={{ wordBreak: "break-word", flex: 1 }}
          >
            {fromAddress}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Mail Host:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {host}:{port}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
