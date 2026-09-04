import { Stack, Typography, useTheme } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IUser } from "@/@types/user";
import { deleteUser } from "@/api/user";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import UserRoleChip from "./UserRoleChip";

export default function DeleteUserModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IUser }) {
  const { id, name, username, roles } = data;
  const theme = useTheme();
  const { isDark } = useCurrentTheme();

  const { mutate: deleteUserMutation, isPending } = useMutation({
    mutationFn: () => deleteUser(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("User Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={deleteUserMutation}
      isLoading={isPending}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <Stack spacing={1.25} sx={{ width: 1 }}>
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: 700
            }}
          >
            Username:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {username}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: 700
            }}
          >
            Full Name:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {name}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: 700
            }}
          >
            Role:
          </Typography>
          {roles.map((item) => (
            <UserRoleChip key={item} role={item} />
          ))}
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
