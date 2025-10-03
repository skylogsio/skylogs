"use client";
import { useRef, useState } from "react";

import { alpha, Chip } from "@mui/material";

import type { IUser } from "@/@types/user";
import ActionColumn from "@/components/ActionColumn";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";
import { useRole } from "@/hooks";
import { ROLE_COLORS, RoleType } from "@/utils/userUtils";

import ChangePasswordModal from "./ChangePasswordModal";
import CreateUserModal from "./CreateUserModal";
import DeleteUserModal from "./DeleteUserModal";
import EditUserModal from "./EditUserModal";

export default function Users() {
  const tableRef = useRef<TableComponentRef>(null);
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
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Username", accessorKey: "username" },
          { header: "Full Name", accessorKey: "name" },
          {
            header: "Role",
            cell: ({ row }) =>
              row.original.roles.map((item, index) => (
                <Chip
                  key={index}
                  label={item}
                  sx={{
                    textTransform: "capitalize",
                    color: ROLE_COLORS[item],
                    backgroundColor: alpha(ROLE_COLORS[item], 0.1)
                  }}
                />
              ))
          },
          {
            header: "Action",
            cell: ({ row }) => (
              <ActionColumn
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
        onCreate={() => setOpenCreateModal(true)}
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
