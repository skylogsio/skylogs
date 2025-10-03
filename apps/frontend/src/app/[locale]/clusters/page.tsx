"use client";
import React, { useRef, useState } from "react";

import type { ICluster } from "@/@types/cluster";
import type { CreateUpdateModal } from "@/@types/global";
import ActionColumn from "@/components/ActionColumn";
import ConnectionStatus from "@/components/ConnectionStatus";
import Table from "@/components/Table/SmartTable";
import { type TableComponentRef } from "@/components/Table/types";

import ClusterModal from "./ClusterModal";
import DeleteClusterModal from "./DeleteClusterModal";

export default function Cluster() {
  const tableRef = useRef<TableComponentRef>(null);
  const [modalData, setModalData] = useState<CreateUpdateModal<ICluster>>(null);
  const [deleteModalData, setDeleteModalData] = useState<ICluster | null>(null);

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
      <Table<ICluster>
        ref={tableRef}
        title="Clusters"
        url="skylogs-instance"
        defaultPageSize={10}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Name", accessorKey: "name" },
          { header: "Type", accessorKey: "type" },
          { header: "URL", accessorKey: "url" },
          { header: "Status", cell: ({ row }) => <ConnectionStatus clusterId={row.original.id} /> },
          {
            header: "Action",
            cell: ({ row }) => (
              <ActionColumn
                onEdit={() => setModalData(row.original)}
                onDelete={() => setDeleteModalData(row.original)}
                copyValue={row.original.token}
              />
            )
          }
        ]}
        onCreate={() => setModalData("NEW")}
      />
      {modalData && (
        <ClusterModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {deleteModalData && (
        <DeleteClusterModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleDelete}
        />
      )}
    </>
  );
}
