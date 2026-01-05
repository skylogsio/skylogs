import { Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { ISmsConfig } from "@/@types/admin-area/smsConfig";
import { deleteSmsConfig } from "@/api/admin-area/smsConfig";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteSmsConfigModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: ISmsConfig }) {
  const { id, name, apiToken, senderNumber } = data;

  const { mutate: deleteSmsConfigMutation, isPending } = useMutation({
    mutationFn: () => deleteSmsConfig(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("SMS Config Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteSmsConfigMutation} isLoading={isPending}>
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
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Sender Number:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {senderNumber}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
