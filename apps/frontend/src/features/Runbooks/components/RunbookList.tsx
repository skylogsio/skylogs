"use client";

import { useRef, useState } from "react";

import { Box, useTheme } from "@mui/material";

import type { CreateUpdateModal } from "@/@types/global";
import TagsCell from "@/app/[locale]/alert-rule/TagsCell";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme, useRole } from "@/hooks";

import type { IRunbook } from "../runbook.type";
import { formatRunbookDateTime } from "../runbook.utils";

import DeleteRunbookModal from "./DeleteRunbookModal";
import RunbookActionButtons from "./RunbookActionButtons";
import RunbookDetailsModal from "./RunbookDetailsModal";
import RunbookFilter from "./RunbookFilter";
import RunbookModal from "./RunbookModal";
import RunbookSourceTypeChip from "./RunbookSourceTypeChip";
import RunbookStatusChip from "./RunbookStatusChip";

export default function RunbookList() {
  const tableRef = useRef<TableComponentRef>(null);
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const { hasRole } = useRole();
  const canWrite = hasRole(["owner", "manager"]);

  const [modalData, setModalData] = useState<CreateUpdateModal<IRunbook>>(null);
  const [detailsRunbookId, setDetailsRunbookId] = useState<string | null>(null);
  const [deleteModalData, setDeleteModalData] = useState<IRunbook | null>(null);

  function handleRefreshData() {
    tableRef.current?.refreshData();
  }

  function handleAfterDelete() {
    setDeleteModalData(null);
    handleRefreshData();
  }

  return (
    <>
      <Table<IRunbook>
        ref={tableRef}
        title="Runbooks"
        url="runbook"
        searchKey="name"
        defaultPageSize={10}
        tablePaperSx={getGlassCardSx(theme, isDark)}
        onCreate={canWrite ? () => setModalData("NEW") : undefined}
        onRowClick={(row) => setDetailsRunbookId(row.id)}
        filterComponent={({ onChange }) => <RunbookFilter onChange={onChange} />}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Name", accessorKey: "name" },
          {
            header: "Status",
            cell: ({ row }) => <RunbookStatusChip status={row.original.status} />
          },
          {
            header: "Source",
            cell: ({ row }) => <RunbookSourceTypeChip sourceType={row.original.sourceType} />
          },
          {
            header: "Teams",
            cell: ({ row }) => (
              <TagsCell
                tags={row.original.teams?.map((team) => team.name) ?? row.original.teamIds ?? []}
              />
            )
          },
          {
            header: "Tags",
            cell: ({ row }) => <TagsCell tags={row.original.tags ?? []} />
          },
          {
            header: "Updated At",
            accessorFn: (row) => formatRunbookDateTime(row.updatedAt)
          },
          {
            header: "Action",
            cell: ({ row }) => (
              <Box onClick={(event) => event.stopPropagation()}>
                <RunbookActionButtons
                  onEdit={canWrite ? () => setModalData(row.original) : undefined}
                  onDelete={canWrite ? () => setDeleteModalData(row.original) : undefined}
                />
              </Box>
            )
          }
        ]}
      />
      {modalData && (
        <RunbookModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {detailsRunbookId && (
        <RunbookDetailsModal
          open={!!detailsRunbookId}
          onClose={() => setDetailsRunbookId(null)}
          runbookId={detailsRunbookId}
        />
      )}
      {deleteModalData && (
        <DeleteRunbookModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleAfterDelete}
        />
      )}
    </>
  );
}
