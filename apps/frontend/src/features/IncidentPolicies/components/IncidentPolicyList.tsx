"use client";

import { useRef, useState } from "react";

import { Box, Button, Chip, Typography, useTheme } from "@mui/material";
import { toast } from "react-toastify";

import type { CreateUpdateModal } from "@/@types/global";
import TagsCell from "@/app/[locale]/alert-rule/TagsCell";
import Table from "@/components/Table/SmartTable";
import type { TableComponentRef } from "@/components/Table/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme, useRole } from "@/hooks";

import { exportIncidentPolicyYaml } from "../incident-policy.api";
import type { IIncidentPolicy } from "../incident-policy.type";
import { formatIncidentPolicyDateTime } from "../incident-policy.utils";

import DeleteIncidentPolicyModal from "./DeleteIncidentPolicyModal";
import IncidentPolicyActionButtons from "./IncidentPolicyActionButtons";
import IncidentPolicyDetailsModal from "./IncidentPolicyDetailsModal";
import IncidentPolicyFilter from "./IncidentPolicyFilter";
import IncidentPolicyModal from "./IncidentPolicyModal";
import IncidentPolicyYamlModal from "./IncidentPolicyYamlModal";

function downloadBase64File(base64: string, filename: string) {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i += 1) {
    bytes[i] = binary.charCodeAt(i);
  }
  const blob = new Blob([bytes], { type: "application/x-yaml" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

export default function IncidentPolicyList() {
  const tableRef = useRef<TableComponentRef>(null);
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const { hasRole } = useRole();
  const canWrite = hasRole(["owner", "manager"]);

  const [modalData, setModalData] = useState<CreateUpdateModal<IIncidentPolicy>>(null);
  const [detailsPolicyId, setDetailsPolicyId] = useState<string | null>(null);
  const [deleteModalData, setDeleteModalData] = useState<IIncidentPolicy | null>(null);
  const [yamlModalOpen, setYamlModalOpen] = useState(false);
  const [exportingId, setExportingId] = useState<string | null>(null);

  function handleRefreshData() {
    tableRef.current?.refreshData();
  }

  async function handleExport(policy: IIncidentPolicy) {
    try {
      setExportingId(policy.id);
      const result = await exportIncidentPolicyYaml(policy.id);
      downloadBase64File(result.base64, result.filename || `${policy.name}.yaml`);
      toast.success("YAML exported.");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Failed to export YAML.");
    } finally {
      setExportingId(null);
    }
  }

  return (
    <>
      <Table<IIncidentPolicy>
        ref={tableRef}
        title="Incident Policies"
        url="incident-policy"
        searchKey="name"
        defaultPageSize={10}
        tablePaperSx={getGlassCardSx(theme, isDark)}
        onCreate={canWrite ? () => setModalData("NEW") : undefined}
        onRowClick={(row) => setDetailsPolicyId(row.id)}
        filterComponent={({ onChange }) => <IncidentPolicyFilter onChange={onChange} />}
        toolbarActions={
          canWrite
            ? [
                "search",
                "filter",
                {
                  id: "import-yaml",
                  render: (
                    <Button
                      size="small"
                      variant="outlined"
                      onClick={() => setYamlModalOpen(true)}
                      sx={{
                        height: 33,
                        minHeight: 33,
                        borderRadius: 2,
                        px: 1.35,
                        fontSize: 12.5,
                        textTransform: "none",
                        fontWeight: 400,
                        whiteSpace: "nowrap"
                      }}
                    >
                      Import YAML
                    </Button>
                  )
                },
                "create"
              ]
            : undefined
        }
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          {
            header: "Name",
            cell: ({ row }) => (
              <Typography
                component="span"
                sx={{
                  fontWeight: 600,
                  color: "text.primary",
                  "&:hover": { textDecoration: "underline" }
                }}
              >
                {row.original.name}
              </Typography>
            )
          },
          {
            header: "Enabled",
            cell: ({ row }) => (
              <Chip
                size="small"
                label={row.original.enabled ? "Enabled" : "Disabled"}
                color={row.original.enabled ? "success" : "default"}
                variant="outlined"
              />
            )
          },
          {
            header: "Source",
            accessorFn: (row) => String(row.source ?? "—")
          },
          {
            header: "Version",
            accessorFn: (row) => (row.version != null ? String(row.version) : "—")
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
            header: "Updated At",
            accessorFn: (row) => formatIncidentPolicyDateTime(row.updatedAt)
          },
          {
            header: "Action",
            cell: ({ row }) => (
              <Box onClick={(event) => event.stopPropagation()}>
                <IncidentPolicyActionButtons
                  onEdit={canWrite ? () => setModalData(row.original) : undefined}
                  onExport={
                    exportingId === row.original.id
                      ? undefined
                      : () => void handleExport(row.original)
                  }
                  onDelete={canWrite ? () => setDeleteModalData(row.original) : undefined}
                />
              </Box>
            )
          }
        ]}
      />

      {modalData && (
        <IncidentPolicyModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {detailsPolicyId && (
        <IncidentPolicyDetailsModal
          open={!!detailsPolicyId}
          onClose={() => setDetailsPolicyId(null)}
          policyId={detailsPolicyId}
        />
      )}
      {deleteModalData && (
        <DeleteIncidentPolicyModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={() => {
            setDeleteModalData(null);
            handleRefreshData();
          }}
        />
      )}
      {yamlModalOpen && (
        <IncidentPolicyYamlModal
          open={yamlModalOpen}
          onClose={() => setYamlModalOpen(false)}
          onSubmit={handleRefreshData}
        />
      )}
    </>
  );
}
