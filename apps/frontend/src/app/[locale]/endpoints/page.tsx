"use client";
import { useRef, useState } from "react";

import { Box, Tab, Tabs } from "@mui/material";

import type { IEndpoint } from "@/@types/endpoint";
import { CreateUpdateModal } from "@/@types/global";
import DeleteEndPointModal from "@/app/[locale]/endpoints/DeleteEndPointModal";
import ActionColumn from "@/components/ActionColumn";
import Table from "@/components/Table/SmartTable";
import { type TableComponentRef } from "@/components/Table/types";
import { renderEndPointChip } from "@/utils/endpointVariants";
import { truncateLongString } from "@/utils/general";

import EndPointModal from "./EndPointModal";
import Flows from "./flows/page";

interface TabPanelProps {
  children?: React.ReactNode;
  index: number;
  value: number;
}

function CustomTabPanel(props: TabPanelProps) {
  const { children, value, index, ...other } = props;

  return (
    <div
      role="tabpanel"
      hidden={value !== index}
      id={`endpoint-tabpanel-${index}`}
      aria-labelledby={`endpoint-tab-${index}`}
      {...other}
    >
      {value === index && <Box sx={{ pt: 3 }}>{children}</Box>}
    </div>
  );
}

function a11yProps(index: number) {
  return {
    id: `endpoint-tab-${index}`,
    "aria-controls": `endpoint-tabpanel-${index}`
  };
}

export default function EndPoints() {
  const tableRef = useRef<TableComponentRef>(null);
  const [tabValue, setTabValue] = useState(0);
  const [modalData, setModalData] = useState<CreateUpdateModal<IEndpoint>>(null);
  const [deleteModalData, setDeleteModalData] = useState<IEndpoint | null>(null);

  const handleTabChange = (event: React.SyntheticEvent, newValue: number) => {
    setTabValue(newValue);
  };

  function handleEdit(data: IEndpoint) {
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

  return (
    <Box sx={{ width: "100%" }}>
      <Tabs
        value={tabValue}
        onChange={handleTabChange}
        aria-label="endpoint tabs"
        centered
        sx={{
          "& .MuiTabs-fixed": {
            display: "inline-block",
            flex: "none",
            marginX: "auto",
            width: "auto",
            boxSizing: "border-box",
            border: "1px solid",
            borderColor: ({ palette }) => palette.grey[300],
            borderRadius: 2,
            overflow: "hidden"
          },
          "& .MuiTab-root": {
            textTransform: "none",
            fontSize: "1rem",
            fontWeight: 600,
            position: "relative",
            zIndex: 3,
            transition: "all 300ms ease-out",
            "&.Mui-selected": {
              transition: "all 300ms ease-out",
              color: ({ palette }) => `${palette.common.white}!important`
            }
          },
          "& .MuiTabs-indicator": {
            height: "100%",
            zIndex: 2,
            ...(tabValue === 0 ? { left: `${0} !important` } : {})
          }
        }}
      >
        <Tab label="EndPoint List" {...a11yProps(0)} />
        <Tab label="Endpoint Flows" {...a11yProps(1)} />
      </Tabs>

      <CustomTabPanel value={tabValue} index={0}>
        <Table<IEndpoint>
          ref={tableRef}
          title="EndPoints"
          url="endpoint"
          defaultPageSize={10}
          columns={[
            { header: "Row", accessorFn: (_, index) => ++index },
            { header: "Name", accessorKey: "name" },
            {
              header: "Type",
              accessorKey: "type",
              cell: ({ cell }) => renderEndPointChip(cell.getValue())
            },
            {
              header: "Value",
              accessorFn: (row) =>
                truncateLongString(row.type === "telegram" ? row.chatId : row.value)
            },
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
          onCreate={() => setModalData("NEW")}
        />
      </CustomTabPanel>

      <CustomTabPanel value={tabValue} index={1}>
        <Flows />
      </CustomTabPanel>

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
