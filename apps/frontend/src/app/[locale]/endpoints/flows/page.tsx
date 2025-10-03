"use client";
import { useRef, useState } from "react";

import { Box, Chip } from "@mui/material";
import { MdAccessTime, MdFlashOn } from "react-icons/md";

import type { IFlow } from "@/@types/flow";
import { CreateUpdateModal } from "@/@types/global";
import DeleteFlowModal from "@/app/[locale]/endpoints/DeleteFlowModal";
import FlowModal from "@/app/[locale]/endpoints/FlowModal";
import ActionColumn from "@/components/ActionColumn";
import Table from "@/components/Table/SmartTable";
import { type TableComponentRef } from "@/components/Table/types";

export default function Flows() {
  const tableRef = useRef<TableComponentRef>(null);
  const [modalData, setModalData] = useState<CreateUpdateModal<IFlow>>(null);
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
    return (
      <Box sx={{ display: "flex", flexWrap: "wrap", gap: 0.5 }}>
        {steps?.map((step, index) => (
          <Chip
            key={index}
            icon={step.type === "wait" ? <MdAccessTime size={14} /> : <MdFlashOn size={14} />}
            label={step.type === "wait" ? `${step.duration}${step.timeUnit}` : "Endpoint"}
            size="small"
            variant="outlined"
            sx={{
              color: step.type === "wait" ? "#ff9800" : "#2196f3",
              borderColor: step.type === "wait" ? "#ff9800" : "#2196f3",
              "& .MuiChip-icon": {
                color: step.type === "wait" ? "#ff9800" : "#2196f3"
              }
            }}
          />
        ))}
      </Box>
    );
  };

  return (
    <>
      <Table<IFlow>
        ref={tableRef}
        title="Flows"
        url="endpoint/indexFlow"
        defaultPageSize={10}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Name", accessorKey: "name" },
          {
            header: "Steps",
            cell: ({ row }) => renderSteps(row.original.steps)
          },
          {
            header: "Created At",
            cell: ({ row }) => row.original.createdAt
          },
          {
            header: "Actions",
            cell: ({ row }) => (
              <ActionColumn
                onEdit={() => handleEdit(row.original)}
                onDelete={() => setDeleteModalData(row.original)}
              />
            )
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
    </>
  );
}
