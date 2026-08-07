"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useRef, useState } from "react";

import { Box } from "@mui/material";
import { grey } from "@mui/material/colors";
import { BsFillPinFill } from "react-icons/bs";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import AlertRuleAccessBadge from "@/components/AlertRule/AlertRuleAccessBadge";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import AlertRuleType from "@/components/AlertRule/AlertRuleType";
import GroupActionModal from "@/components/AlertRule/GroupActionModal";
import AlertRuleNotifyModal from "@/components/AlertRule/Notify/AlertRuleNotifyModal";
import Table, { type TableComponentRef } from "@/components/Table/SmartTable";

import AlertRuleActionColumn from "./AlertRuleActionColumn";
import AlertRuleFilter from "./AlertRuleFilter";
import AlertRuleModal from "./AlertRuleModal";
import DeleteAlertRuleModal from "./DeleteAlertRuleModal";
import ShowAllAlertsToggle from "./ShowAllAlertsToggle";
import TagsCell from "./TagsCell";

export default function AlertRule() {
  const tableRef = useRef<TableComponentRef>(null);
  const pathname = usePathname();
  const [modalData, setModalData] = useState<CreateUpdateModal<IAlertRule>>(null);
  const [deleteModalData, setDeleteModalData] = useState<IAlertRule | null>(null);
  const [openGroupActionModal, setOpenGroupActionModal] = useState<boolean>(false);

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

  return (
    <>
      <Table<IAlertRule>
        ref={tableRef}
        title="Alert Rule"
        url="alert-rule"
        searchKey="alertname"
        defaultPageSize={10}
        defaultFilters={{ scope: "assigned" }}
        excludeFilterKeys={["scope"]}
        onGroupActionClick={() => setOpenGroupActionModal(true)}
        onCreate={() => setModalData("NEW")}
        filterComponent={({ onChange }) => (
          <AlertRuleFilter onChange={onChange} showScopeToggle={false} />
        )}
        toolbarActions={[
          "search",
          { id: "show-all-alerts", render: <ShowAllAlertsToggle /> },
          "filter",
          "create"
        ]}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          {
            header: "Name",
            cell: ({ row }) => (
              <Box
                component={Link}
                href={`${pathname}/${row.original.id}`}
                sx={{
                  alignItems: "center",
                  justifyContent: "center",
                  color: ({ palette }) => palette.text.primary,
                  textDecoration: "none"
                }}
              >
                {row.original.isPinned && (
                  <BsFillPinFill
                    color={grey[500]}
                    style={{ transform: "rotate(-30deg)", marginRight: "0.4rem" }}
                    size={16}
                  />
                )}
                {row.original.name} <AlertRuleAccessBadge accessLevel={row.original.accessLevel} />
              </Box>
            )
          },
          {
            header: "Type",
            cell: ({ row }) => <AlertRuleType type={row.original.type} />
          },
          {
            header: "Notify",
            cell: ({ row }) =>
              row.original.accessLevel === "readonly" ? (
                "-"
              ) : (
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
              <AlertRuleStatusIndicator
                id={row.original.accessLevel === "readonly" ? undefined : row.original.id}
                status={row.original.status_label}
                onAfterResolve={handleRefreshData}
                showAcknowledge={
                  row.original.accessLevel !== "readonly" && !row.original.acknowledgedBy
                }
              />
            )
          },
          {
            header: "Tags",
            cell: ({ row }) => <TagsCell tags={row.original.tags} />
          },
          {
            header: "Action",
            cell: ({ row }) =>
              row.original.accessLevel === "readonly" ? (
                "-"
              ) : (
                <AlertRuleActionColumn
                  hasActionAccess={row.original.hasActionAccess}
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
        <GroupActionModal open={openGroupActionModal} onClose={handleAfterGroupAction} />
      )}
    </>
  );
}
