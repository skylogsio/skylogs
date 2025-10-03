"use client";

import { useRef, useState } from "react";

import { Chip, Stack } from "@mui/material";

import type { CreateUpdateModal } from "@/@types/global";
import type { IProfileService } from "@/@types/profileService";
import ActionColumn from "@/components/ActionColumn";
import DeleteProfileServiceModal from "@/components/ProfileServices/DeleteProfileServiceModal";
import ProfileServiceModal from "@/components/ProfileServices/ProfileServiceModal";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";

export default function ProfileServicePage() {
  const tableRef = useRef<TableComponentRef>(null);
  const [modalData, setModalData] =
    useState<CreateUpdateModal<{ id: string; name: string; ownerId: string; config: string }>>(
      null
    );
  const [deleteModalData, setDeleteModalData] = useState<IProfileService | null>(null);

  function handleRefreshData() {
    if (tableRef.current) {
      tableRef.current.refreshData();
    }
  }

  function handleEdit(row: IProfileService) {
    setModalData({ id: row.id, name: row.name, ownerId: row.ownerId, config: row.config });
  }

  function renderEnvironments(envs: IProfileService["envs"]) {
    return (
      <Stack direction="row" spacing={2} justifyContent="center">
        {envs.map((env) => (
          <Chip key={env} label={env} />
        ))}
      </Stack>
    );
  }

  function handleDelete() {
    setDeleteModalData(null);
    handleRefreshData();
  }


  return (
    <>
      <Table<IProfileService>
        ref={tableRef}
        title="Profile Services"
        url="profile/asset"
        defaultPageSize={10}
        onCreate={() => setModalData("NEW")}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Name", accessorKey: "name" },
          { header: "Username", accessorFn: (item) => item.user.username },
          { header: "Environments", cell: ({ row }) => renderEnvironments(row.original.envs) },
          {
            header: "Action",
            cell: ({ row }) => (
              <ActionColumn
                onEdit={() => handleEdit(row.original)}
                onDelete={() => setDeleteModalData(row.original)}
              />
            )
          }
        ]}
      />
      {modalData && (
        <ProfileServiceModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {deleteModalData && (
        <DeleteProfileServiceModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleDelete}
        />
      )}
    </>
  );
}
