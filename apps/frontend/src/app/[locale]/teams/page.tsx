"use client";

import { useRef, useState } from "react";

import type { CreateUpdateModal } from "@/@types/global";
import type { ITeam } from "@/@types/team";
import TagsCell from "@/app/[locale]/alert-rule/TagsCell";
import ActionColumn from "@/components/ActionColumn";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";
import DeleteTeamModal from "@/components/Team/DeleteTeamModal";
import TeamModal from "@/components/Team/TeamModal";

export default function TeamsPage() {
  const tableRef = useRef<TableComponentRef>(null);
  const [modalData, setModalData] = useState<CreateUpdateModal<ITeam>>(null);
  const [deleteModalData, setDeleteModalData] = useState<ITeam | null>(null);

  function handleRefreshData() {
    if (tableRef.current) {
      tableRef.current.refreshData();
    }
  }

  function handleDelete() {
    setDeleteModalData(null);
    handleRefreshData();
  }

  return (
    <>
      <Table<ITeam>
        ref={tableRef}
        title="Teams"
        url="team"
        searchKey="name"
        defaultPageSize={10}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Name", accessorKey: "name" },
          {
            header: "Owner",
            accessorFn: (row) => row.owner?.name || "-"
          },
          {
            header: "Members",
            cell: ({ row }) => <TagsCell tags={row.original.members} />
          },
          {
            header: "Action",
            cell: ({ row }) => (
              <ActionColumn
                onEdit={() => setModalData(row.original)}
                onDelete={() => setDeleteModalData(row.original)}
              />
            )
          }
        ]}
        onCreate={() => setModalData("NEW")}
      />

      {modalData && (
        <TeamModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}

      {deleteModalData && (
        <DeleteTeamModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleDelete}
        />
      )}
    </>
  );
}
