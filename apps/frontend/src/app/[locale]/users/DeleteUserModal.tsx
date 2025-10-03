import { alpha, Chip, Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IUser } from "@/@types/user";
import { deleteUser } from "@/api/user";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import { ROLE_COLORS } from "@/utils/userUtils";

export default function DeleteUserModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IUser }) {
  const { id, name, username, roles } = data;

  const { mutate: deleteUserMutation, isPending } = useMutation({
    mutationFn: () => deleteUser(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("User Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteUserMutation} isLoading={isPending}>
      <Stack spacing={1}>
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
            Full Name:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {name}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Role:
          </Typography>
          {roles.map((item, index) => (
            <Chip
              key={index}
              label={item}
              size="small"
              sx={{
                textTransform: "capitalize",
                color: ROLE_COLORS[item],
                backgroundColor: alpha(ROLE_COLORS[item], 0.1)
              }}
            />
          ))}
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
