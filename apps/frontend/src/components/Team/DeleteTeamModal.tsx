import { Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { ITeam } from "@/@types/team";
import { deleteTeam } from "@/api/team";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteTeamModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: ITeam }) {
  const { id, name, owner, userIds } = data;

  const { mutate: deleteTeamMutation, isPending } = useMutation({
    mutationFn: () => deleteTeam(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("Team Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteTeamMutation} isLoading={isPending}>
      <Stack spacing={1}>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Name:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {name}
          </Typography>
        </Stack>

        {owner && (
          <Stack direction="row" spacing={1}>
            <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
              Owner:
            </Typography>
            <Typography variant="subtitle2" color="text.secondary">
              {owner.name}
            </Typography>
          </Stack>
        )}

        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Members Count:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {userIds?.length || 0}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
