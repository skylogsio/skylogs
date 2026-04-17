import { Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IEndpoint } from "@/@types/endpoint";
import { deleteEndpoint } from "@/api/endpoint";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import EndPointTypeChip from "@/components/EndpointTypeChip";

export default function DeleteEndPointModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IEndpoint }) {
  const { id, name, value, type, chatId } = data;

  const { mutate: deleteEndpointMutation, isPending } = useMutation({
    mutationFn: () => deleteEndpoint(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("EndPoint Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteEndpointMutation} isLoading={isPending}>
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
            Type:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            <EndPointTypeChip type={type} size="small" />
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Value:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary" sx={{ wordBreak: "break-word" }}>
            {type === "telegram" ? chatId : value}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
