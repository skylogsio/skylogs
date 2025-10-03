import { Chip, Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IProfileService } from "@/@types/profileService";
import { deleteProfileService } from "@/api/profileService";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteProfileServiceModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IProfileService }) {
  const {
    id,
    name,
    envs,
    user: { username },
    createdAlertRuleIds
  } = data;

  const { mutate: deletesProfileServiceMutation, isPending } = useMutation({
    mutationFn: () => deleteProfileService(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("Profile Service Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={deletesProfileServiceMutation}
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
            Username:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {username}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            envs:
          </Typography>
          <Stack direction="row" spacing={1} justifyContent="center" flexWrap="wrap">
            {envs.map((env) => (
              <Chip key={env} label={env} size="small" />
            ))}
          </Stack>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Alert Rules Count:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {createdAlertRuleIds?.length ?? 0}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
