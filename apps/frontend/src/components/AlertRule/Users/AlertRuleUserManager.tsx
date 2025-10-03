import { useState } from "react";

import { alpha, Autocomplete, Button, Stack, TextField, IconButton, useTheme } from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { FaUsers } from "react-icons/fa";
import { HiOutlinePlusSm, HiTrash } from "react-icons/hi";

import type { IAlertRule } from "@/@types/alertRule";
import type { IUser } from "@/@types/user";
import {
  addUsersToAlertRule,
  getAlertRuleUsersList,
  removeUserFromAlertRule
} from "@/api/alertRule";
import EmptyList from "@/components/EmptyList";
import DataTable from "@/components/Table/DataTable";

interface AlertRuleUserManagerProps {
  alertId: IAlertRule["id"];
  onClose?: () => void;
}

export default function AlertRuleUserManager({ alertId }: AlertRuleUserManagerProps) {
  const { palette } = useTheme();
  const [selectedUsers, setSelectedUsers] = useState<IUser[]>([]);

  const { data: UsersList, refetch } = useQuery({
    queryKey: ["alert-rule-users", alertId],
    queryFn: () => getAlertRuleUsersList(alertId)
  });

  const { mutate: addUsers, isPending: isAddingUsers } = useMutation({
    mutationFn: (userIds: string[]) => addUsersToAlertRule(alertId, userIds),
    onSuccess: (data) => {
      if (data.status) {
        setSelectedUsers([]);
        refetch();
      }
    }
  });

  const { mutate: removeUser, isPending: isRemovingUser } = useMutation({
    mutationFn: (userId: string) => removeUserFromAlertRule(alertId, userId),
    onSuccess: (data) => {
      if (data.status) {
        refetch();
      }
    }
  });

  function handleAddUsers() {
    const userIds = selectedUsers.map((item) => item.id);
    if (userIds.length > 0) {
      addUsers(userIds);
    }
  }

  return (
    <Stack spacing={2} marginTop={1}>
      <Stack direction="row" spacing={1} alignItems="center">
        <Autocomplete
          multiple
          id="endpoints"
          size="small"
          options={UsersList?.selectableUsers ?? []}
          getOptionLabel={(option) => option.name}
          value={selectedUsers}
          onChange={(_, value) => setSelectedUsers(value)}
          sx={{ flex: 1 }}
          renderInput={(params) => (
            <TextField
              {...params}
              slotProps={{
                input: params.InputProps,
                inputLabel: params.InputLabelProps,
                htmlInput: params.inputProps
              }}
              variant="filled"
              label="Users"
            />
          )}
        />
        <Button
          onClick={() => handleAddUsers()}
          disabled={selectedUsers.length === 0 || isAddingUsers}
          variant="contained"
          color="primary"
          size="large"
          startIcon={<HiOutlinePlusSm size="1.3rem" />}
        >
          Add
        </Button>
      </Stack>
      {UsersList?.alertUsers && UsersList.alertUsers.length > 0 ? (
        <DataTable
          data={UsersList.alertUsers}
          columns={[
            { header: "Row", size: 50, accessorFn: (_, index) => ++index },
            { header: "Username", accessorKey: "username" },
            { header: "Name", accessorKey: "name" },
            {
              header: "Actions",
              cell: ({ row }) => (
                <IconButton
                  disabled={isRemovingUser}
                  onClick={() => removeUser(row.original.id)}
                  sx={({ palette }) => ({
                    color: palette.error.light,
                    backgroundColor: alpha(palette.error.light, 0.05)
                  })}
                >
                  <HiTrash size="1.4rem" />
                </IconButton>
              )
            }
          ]}
        />
      ) : (
        <EmptyList
          icon={<FaUsers size="3rem" style={{ color: palette.primary.light, marginBottom: 5 }} />}
          title="No Users Assigned"
          description="This alert rule doesn&#39;t have any users assigned yet. Use the form above to add users
            who should receive notifications for this alert."
        />
      )}
    </Stack>
  );
}
