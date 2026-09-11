"use client";

import { useRef, useState } from "react";

import { Box, useTheme } from "@mui/material";

import type { IEndpoint } from "@/@types/endpoint";
import { CreateUpdateModal } from "@/@types/global";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import EndPointTypeChip from "@/components/EndpointTypeChip";
import EndpointActionButtons from "@/components/Endpoint/EndpointActionButtons";
import EndpointWorkspaceToolbar from "@/components/Endpoint/EndpointWorkspaceToolbar";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";
import { useCurrentTheme } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

import DeleteEndPointModal from "./DeleteEndPointModal";
import EndPointModal from "./EndPointModal";
import Flows from "./flows/page";

export default function EndPoints() {
  const tableRef = useRef<TableComponentRef>(null);
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const t = useScopedI18n("endpoints");

  const [tabValue, setTabValue] = useState<"endpoints" | "flows">("endpoints");
  const [modalData, setModalData] = useState<CreateUpdateModal<IEndpoint>>(null);
  const [deleteModalData, setDeleteModalData] = useState<IEndpoint | null>(null);

  const labels = {
    endpoints: t("workspace.endpoints"),
    flows: t("workspace.flows")
  };

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
    <Box sx={{ width: "100%" }}>
      {tabValue === "endpoints" && (
        <Table<IEndpoint>
          ref={tableRef}
          title={t("list.title")}
          url="endpoint"
          defaultPageSize={10}
          tablePaperSx={getGlassCardSx(theme, isDark)}
          renderToolbar={(slots) => (
            <EndpointWorkspaceToolbar
              slots={slots}
              value={tabValue}
              onChange={setTabValue}
              labels={labels}
            />
          )}
          columns={[
            { header: "Row", accessorFn: (_, index) => ++index },
            { header: t("list.column.name"), accessorKey: "name" },
            {
              header: t("list.column.type"),
              accessorKey: "type",
              cell: ({ cell }) => <EndPointTypeChip type={cell.getValue()} />
            },
            {
              header: t("list.column.value"),
              accessorFn: (row) =>
                row.type === "telegram" || row.type === "bale" ? row.chatId ?? row.value : row.value
            },
            {
              header: "Action",
              cell: ({ row }) =>
                row.original.hasActionAccess ? (
                  <EndpointActionButtons
                    onEdit={() => setModalData(row.original)}
                    onDelete={() => setDeleteModalData(row.original)}
                  />
                ) : null
            }
          ]}
          onCreate={() => setModalData("NEW")}
        />
      )}

      {tabValue === "flows" && (
        <Flows
          tablePaperSx={getGlassCardSx(theme, isDark)}
          tabValue={tabValue}
          onTabChange={setTabValue}
          labels={labels}
        />
      )}

      {modalData && (
        <EndPointModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {deleteModalData && (
        <DeleteEndPointModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleDelete}
        />
      )}
    </Box>
  );
}
