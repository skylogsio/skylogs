"use client";
import { useRef, useState } from "react";

import { Step, StepLabel, Stepper, useTheme } from "@mui/material";
import { AiFillApi, AiFillClockCircle } from "react-icons/ai";

import type { IFlow } from "@/@types/flow";
import { CreateUpdateModal } from "@/@types/global";
import DeleteFlowModal from "@/app/[locale]/endpoints/DeleteFlowModal";
import FlowModal from "@/app/[locale]/endpoints/FlowModal";
import ActionColumn from "@/components/ActionColumn";
import Table from "@/components/Table/SmartTable";
import { type TableComponentRef } from "@/components/Table/types";

export default function Flows() {
  const { palette } = useTheme();
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
      <Stepper
        activeStep={1}
        alternativeLabel
        sx={{
          "& .MuiStepConnector-root": {
            top: "50%",
            transfrom: "translateY(-50%)"
          }
        }}
      >
        {steps.map((step, index) => (
          <Step key={index}>
            <StepLabel
              sx={{
                "& .MuiStepLabel-iconContainer": {
                  backgroundColor: `${palette.grey[100]}!important`,
                  padding: 1,
                  borderRadius: "50%",
                  aspectRatio: "1/1"
                }
              }}
              icon={
                step.type === "wait" ? (
                  <AiFillClockCircle size={20} color={palette.warning.main} />
                ) : (
                  <AiFillApi size={20} color={palette.primary.main} />
                )
              }
            ></StepLabel>
          </Step>
        ))}
      </Stepper>
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
