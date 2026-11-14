"use client";

import { useRef, useState } from "react";

import { Box, Typography, useTheme } from "@mui/material";

import type { CreateUpdateModal } from "@/@types/global";
import TagsCell from "@/app/[locale]/alert-rule/TagsCell";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import type { IIncident } from "../incident.type";
import { formatIncidentDateTime } from "../incident.utils";

import DeleteIncidentModal from "./DeleteIncidentModal";
import IncidentActionButtons from "./IncidentActionButtons";
import IncidentDetailsModal from "./IncidentDetailsModal";
import IncidentFilter from "./IncidentFilter";
import IncidentModal from "./IncidentModal";
import IncidentSeverityChip from "./IncidentSeverityChip";
import IncidentStatusChip from "./IncidentStatusChip";
import IncidentWorkspaceToolbar from "./IncidentWorkspaceToolbar";
import ResolveIncidentModal from "./ResolveIncidentModal";

export default function IncidentList() {
  const tableRef = useRef<TableComponentRef>(null);
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const [modalData, setModalData] = useState<CreateUpdateModal<IIncident>>(null);
  const [detailsIncidentId, setDetailsIncidentId] = useState<string | null>(null);
  const [deleteModalData, setDeleteModalData] = useState<IIncident | null>(null);
  const [resolveModalData, setResolveModalData] = useState<IIncident | null>(null);

  function handleRefreshData() {
    tableRef.current?.refreshData();
  }

  function handleAfterDelete() {
    setDeleteModalData(null);
    handleRefreshData();
  }

  return (
    <>
      <Table<IIncident>
        ref={tableRef}
        title="Incidents"
        url="incident"
        searchKey="title"
        defaultPageSize={10}
        tablePaperSx={getGlassCardSx(theme, isDark)}
        onCreate={() => setModalData("NEW")}
        onRowClick={(row) => setDetailsIncidentId(row.id)}
        filterComponent={({ onChange }) => <IncidentFilter onChange={onChange} />}
        renderToolbar={(slots) => <IncidentWorkspaceToolbar slots={slots} />}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          {
            header: "Title",
            cell: ({ row }) => (
              <Typography
                component="span"
                sx={{
                  fontWeight: 600,
                  color: "text.primary",
                  "&:hover": { textDecoration: "underline" }
                }}
              >
                {row.original.title}
              </Typography>
            )
          },
          {
            header: "Severity",
            cell: ({ row }) => <IncidentSeverityChip severity={row.original.severity} />
          },
          {
            header: "Status",
            cell: ({ row }) => <IncidentStatusChip status={row.original.status} />
          },
          {
            header: "Teams",
            cell: ({ row }) => (
              <TagsCell tags={row.original.teams?.map((team) => team.name) ?? []} />
            )
          },
          {
            header: "Tags",
            cell: ({ row }) => <TagsCell tags={row.original.tags ?? []} />
          },
          {
            header: "Started At",
            accessorFn: (row) => formatIncidentDateTime(row.startedAt)
          },
          {
            header: "Action",
            cell: ({ row }) => (
              <Box onClick={(event) => event.stopPropagation()}>
                <IncidentActionButtons
                  onEdit={row.original.canEdit ? () => setModalData(row.original) : undefined}
                  onResolve={
                    row.original.canResolve && row.original.status !== "resolved"
                      ? () => setResolveModalData(row.original)
                      : undefined
                  }
                  onDelete={
                    row.original.canDelete ? () => setDeleteModalData(row.original) : undefined
                  }
                />
              </Box>
            )
          }
        ]}
      />
      {modalData && (
        <IncidentModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {detailsIncidentId && (
        <IncidentDetailsModal
          open={!!detailsIncidentId}
          onClose={() => setDetailsIncidentId(null)}
          incidentId={detailsIncidentId}
        />
      )}
      {deleteModalData && (
        <DeleteIncidentModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleAfterDelete}
        />
      )}
      {resolveModalData && (
        <ResolveIncidentModal
          open={!!resolveModalData}
          onClose={() => setResolveModalData(null)}
          data={resolveModalData}
          onSubmit={handleRefreshData}
        />
      )}
    </>
  );
}
