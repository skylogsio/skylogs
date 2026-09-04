"use client";
import { useRef, useState } from "react";

import { Stack, useTheme } from "@mui/material";

import type { IUser } from "@/@types/user";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme, useRole } from "@/hooks";
import { type RoleType } from "@/utils/userUtils";

import ChangePasswordModal from "./ChangePasswordModal";
import CreateUserModal from "./CreateUserModal";
import DeleteUserModal from "./DeleteUserModal";
import EditUserModal from "./EditUserModal";
import UserActionButtons from "./UserActionButtons";
import UserRoleChip from "./UserRoleChip";

export default function Users() {
  const tableRef = useRef<TableComponentRef>(null);
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const { hasRole } = useRole();
  const [openCreateModal, setOpenCreateModal] = useState(false);
  const [editModalUserData, setEditModalUserData] = useState<IUser | null>(null);
  const [selectedUserToChangePassword, setSelectedUserToChangePassword] = useState<string | null>(
    null
  );
  const [deleteModalData, setDeleteModalData] = useState<IUser | null>(null);

  function handleRefreshData() {
    if (tableRef.current) {
      tableRef.current.refreshData();
    }
  }

  function checkAccess(role: RoleType) {
    if (hasRole("owner")) return true;
    return hasRole("manager") && role === "member";
  }

  function handleDelete() {
    setDeleteModalData(null);
    handleRefreshData();
  }

  return (
    <>
      <Table<IUser>
        ref={tableRef}
        title="Users"
        url="user"
        searchKey="username"
        defaultPageSize={10}
        tablePaperSx={getGlassCardSx(theme, isDark)}
        onCreate={() => setOpenCreateModal(true)}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Username", accessorKey: "username" },
          { header: "Full Name", accessorKey: "name" },
          {
            header: "Role",
            cell: ({ row }) => (
              <Stack direction="row" spacing={0.75} sx={{ justifyContent: "center" }}>
                {row.original.roles.map((item) => (
                  <UserRoleChip key={item} role={item} />
                ))}
              </Stack>
            )
          },
          {
            header: "Action",
            cell: ({ row }) => (
              <UserActionButtons
                onEdit={
                  checkAccess(row.original.roles[0]) && row.original.username !== "admin"
                    ? () => setEditModalUserData(row.original)
                    : undefined
                }
                onChangePassword={
                  checkAccess(row.original.roles[0])
                    ? () => setSelectedUserToChangePassword(row.original.id)
                    : undefined
                }
                onDelete={
                  checkAccess(row.original.roles[0]) && row.original.username !== "admin"
                    ? () => setDeleteModalData(row.original)
                    : undefined
                }
              />
            )
          }
        ]}
      />
      {openCreateModal && (
        <CreateUserModal
          open={openCreateModal}
          onClose={() => setOpenCreateModal(false)}
          onSubmit={handleRefreshData}
        />
      )}
      {editModalUserData && (
        <EditUserModal
          open={!!editModalUserData}
          onClose={() => setEditModalUserData(null)}
          onSubmit={handleRefreshData}
          userData={editModalUserData}
        />
      )}
      {selectedUserToChangePassword && (
        <ChangePasswordModal
          open={!!selectedUserToChangePassword}
          onClose={() => setSelectedUserToChangePassword(null)}
          userId={selectedUserToChangePassword}
        />
      )}
      {deleteModalData && (
        <DeleteUserModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleDelete}
        />
      )}
    </>
  );
}
