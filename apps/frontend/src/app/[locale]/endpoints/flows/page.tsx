"use client";

import { useRef, useState } from "react";

import { Box, useTheme } from "@mui/material";
import { alpha } from "@mui/material";

import type { IFlow } from "@/@types/flow";
import { CreateUpdateModal } from "@/@types/global";
import DeleteFlowModal from "@/app/[locale]/endpoints/DeleteFlowModal";
import FlowDetailsModal from "@/app/[locale]/endpoints/FlowDetailsModal";
import FlowModal from "@/app/[locale]/endpoints/FlowModal";
import EndpointActionButtons from "@/components/Endpoint/EndpointActionButtons";
import EndpointWorkspaceToolbar from "@/components/Endpoint/EndpointWorkspaceToolbar";
import FlowStepChip from "@/components/Endpoint/FlowStepChip";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";
import { useCurrentTheme } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

type FlowsProps = {
  tablePaperSx?: Record<string, unknown>;
  tabValue: "endpoints" | "flows";
  onTabChange: (value: "endpoints" | "flows") => void;
  labels: Record<"endpoints" | "flows", string>;
};

export default function Flows({ tablePaperSx, tabValue, onTabChange, labels }: FlowsProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();
  const t = useScopedI18n("endpoints");

  const tableRef = useRef<TableComponentRef>(null);
  const [modalData, setModalData] = useState<CreateUpdateModal<IFlow>>(null);
  const [viewModalData, setViewModalData] = useState<IFlow | null>(null);
  const [deleteModalData, setDeleteModalData] = useState<IFlow | null>(null);

  function handleEdit(data: IFlow) {
    setModalData(data);
  }

  function handleRefreshData() {
    if (tableRef.current) {
      tableRef.current.refreshData();
    }
  }

  function handleDelete() {
    setDeleteModalData(null);
    handleRefreshData();
  }

  const renderSteps = (steps: IFlow["steps"]) => {
    if (!steps || steps.length === 0) {
      return t("flow.emptySteps");
    }

    const visible = steps.slice(0, 5);
    const remaining = steps.length - visible.length;

    return (
      <Box sx={{ display: "inline-flex", alignItems: "center", gap: 0.5, flexWrap: "wrap" }}>
        {visible.map((step, index) => (
          <Box key={index} sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}>
            {index > 0 && (
              <Box
                sx={{
                  width: 6,
                  height: 6,
                  borderRadius: "50%",
                  bgcolor: alpha(palette.primary.main, 0.4)
                }}
              />
            )}
            <FlowStepChip type={step.type} duration={step.duration} timeUnit={step.timeUnit} />
          </Box>
        ))}
        {remaining > 0 && (
          <Box
            component="span"
            sx={{
              fontSize: "0.75rem",
              fontWeight: 600,
              color: "text.secondary",
              ml: 0.25
            }}
          >
            +{remaining}
          </Box>
        )}
      </Box>
    );
  };

  return (
    <>
      <Table<IFlow>
        ref={tableRef}
        title={t("flow.title")}
        url="endpoint/indexFlow"
        defaultPageSize={10}
        tablePaperSx={tablePaperSx}
        renderToolbar={(slots) => (
          <EndpointWorkspaceToolbar
            slots={slots}
            value={tabValue}
            onChange={onTabChange}
            labels={labels}
          />
        )}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: t("list.column.name"), accessorKey: "name" },
          {
            header: t("flow.column.steps"),
            cell: ({ row }) => renderSteps(row.original.steps)
          },
          {
            header: "Action",
            cell: ({ row }) =>
              row.original.hasActionAccess ? (
                <EndpointActionButtons
                  onView={() => setViewModalData(row.original)}
                  onEdit={() => handleEdit(row.original)}
                  onDelete={() => setDeleteModalData(row.original)}
                />
              ) : null
          }
        ]}
        onCreate={() => setModalData("NEW")}
      />
      {modalData && (
        <FlowModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {deleteModalData && (
        <DeleteFlowModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleDelete}
        />
      )}
      {viewModalData && (
        <FlowDetailsModal
          open={!!viewModalData}
          onClose={() => setViewModalData(null)}
          data={viewModalData}
        />
      )}
    </>
  );
}
