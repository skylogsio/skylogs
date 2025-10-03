"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useRef, useState } from "react";

import { Stack } from "@mui/material";
import { grey } from "@mui/material/colors";
import { FaThumbtack } from "react-icons/fa6";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import AlertRuleActionColumn from "@/app/[locale]/alert-rule/AlertRuleActionColumn";
import TagsCell from "@/app/[locale]/alert-rule/TagsCell";
import AlertRuleStatus from "@/components/AlertRule/AlertRuleStatus";
import AlertRuleType from "@/components/AlertRule/AlertRuleType";
import GroupActionModal from "@/components/AlertRule/GroupActionModal";
import AlertRuleNotifyModal from "@/components/AlertRule/Notify/AlertRuleNotifyModal";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";

import AlertRuleFilter from "./AlertRuleFilter";
import AlertRuleModal from "./AlertRuleModal";
import DeleteAlertRuleModal from "./DeleteAlertRuleModal";

export default function AlertRule() {
  const tableRef = useRef<TableComponentRef>(null);
  const pathname = usePathname();
  const [modalData, setModalData] = useState<CreateUpdateModal<IAlertRule>>(null);
  const [deleteModalData, setDeleteModalData] = useState<IAlertRule | null>(null);
  const [openGroupActionModal, setOpenGroupActionModal] = useState<boolean>(false);
  const [currentFilters, setCurrentFilters] = useState<Record<string, unknown>>({});

  function handleRefreshData() {
    if (tableRef.current) {
      tableRef.current.refreshData();
    }
  }

  function handleAfterDelete() {
    handleRefreshData();
    setDeleteModalData(null);
  }

  function handleAfterGroupAction() {
    handleRefreshData();
    setOpenGroupActionModal(false);
  }

  function handleFilterChange(key: string, value: unknown) {
    setCurrentFilters((prev) => ({
      ...prev,
      [key]: value
    }));
  }

  return (
    <>
      <Table<IAlertRule>
        ref={tableRef}
        title="Alert Rule"
        url="alert-rule"
        onGroupActionClick={() => setOpenGroupActionModal(true)}
        searchKey="alertname"
        filterComponent={({ onChange }) => (
          <AlertRuleFilter
            onChange={(key, value) => {
              onChange(key, value);
              handleFilterChange(key, value);
            }}
          />
        )}
        defaultPageSize={10}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          {
            header: "Name",
            cell: ({ row }) => (
              <Stack
                spacing={1}
                direction="row"
                alignItems="center"
                justifyContent="center"
                component={Link}
                href={`${pathname}/${row.original.id}`}
              >
                {row.original.isPinned && (
                  <FaThumbtack
                    color={grey[500]}
                    style={{ transform: "rotate(-30deg)" }}
                    size="0.9rem"
                  />
                )}
                <span>{row.original.name}</span>
              </Stack>
            )
          },
          {
            header: "Type",
            cell: ({ row }) => <AlertRuleType type={row.original.type} />
          },
          {
            header: "Notify",
            cell: ({ row }) => (
              <AlertRuleNotifyModal
                alertId={row.original.id}
                numberOfEndpoints={row.original.count_endpoints}
                onClose={handleRefreshData}
              />
            )
          },
          {
            header: "Status",
            cell: ({ row }) => (
              <AlertRuleStatus
                id={row.original.id}
                status={row.original.status_label}
                onAfterResolve={handleRefreshData}
                showAcknowledge={!row.original.acknowledgedBy}
              />
            )
          },
          {
            header: "Tags",
            cell: ({ row }) => <TagsCell tags={row.original.tags} />
          },
          {
            header: "Action",
            cell: ({ row }) => (
              <AlertRuleActionColumn
                refreshData={handleRefreshData}
                isSilent={row.original.is_silent}
                rowId={row.original.id}
                isPinned={row.original.isPinned}
                onEdit={() => setModalData(row.original)}
                onDelete={() => setDeleteModalData(row.original)}
              />
            )
          }
        ]}
        onCreate={() => setModalData("NEW")}
      />
      {modalData && (
        <AlertRuleModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {deleteModalData && (
        <DeleteAlertRuleModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          onAfterDelete={handleAfterDelete}
          data={deleteModalData}
        />
      )}
      {openGroupActionModal && (
        <GroupActionModal
          open={openGroupActionModal}
          onClose={handleAfterGroupAction}
          currentFilters={currentFilters}
        />
      )}
    </>
  );
}
