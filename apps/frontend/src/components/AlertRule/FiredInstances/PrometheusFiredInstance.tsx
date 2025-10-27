import React, { useState } from "react";

import { IconButton, Stack, Typography, useTheme } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import JsonView from "@uiw/react-json-view";
import { githubLightTheme } from "@uiw/react-json-view/githubLight";
import { HiInformationCircle } from "react-icons/hi";

import type { IAlertRule,IAlertRuleHistoryInstance } from "@/@types/alertRule";
import { getFiredInstances } from "@/api/alertRule";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import ModalContainer from "@/components/Modal";
import DataTable from "@/components/Table/DataTable";
import { truncateLongString } from "@/utils/general";

export default function PrometheusFiredInstance({ alertId }: { alertId: IAlertRule["id"] }) {
  const { palette } = useTheme();
  const [details, setDetails] = useState<IAlertRuleHistoryInstance | null>(null);

  const { data } = useQuery({
    queryKey: ["fired-instances", alertId],
    queryFn: () => getFiredInstances(alertId)
  });

  if (!data) return null;

  return (
    <>
      <DataTable<IAlertRuleHistoryInstance>
        data={data}
        onRowClick={(row) => setDetails(row)}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "DataSource", accessorKey: "dataSourceName" },
          {
            header: "Status",
            cell: ({ row }) => (
              <AlertRuleStatusIndicator
                status={row.original.skylogsStatus === 2 ? "critical" : "resolved"}
              />
            )
          },
          {
            header: "DataSource Alert Name",
            accessorFn: (item) => truncateLongString(item.dataSourceAlertName)
          },
          {
            header: "Actions",
            cell: ({ row }) => (
              <IconButton onClick={() => setDetails(row.original)}>
                <HiInformationCircle color={palette.primary.light} />
              </IconButton>
            )
          }
        ]}
      />
      {details && (
        <ModalContainer
          title="Details"
          maxWidth="70vw"
          open={Boolean(details)}
          onClose={() => setDetails(null)}
        >
          <Stack spacing={1} marginTop={3}>
            <Stack direction="row" spacing={2}>
              <Typography variant="body1" fontWeight="bold">
                {details.alertRuleName}
              </Typography>
              <AlertRuleStatusIndicator
                size="small"
                status={details.skylogsStatus === 2 ? "critical" : "resolved"}
              />
            </Stack>
            <Stack direction="row" width="100%" spacing={2}>
              <Stack
                direction="row"
                padding={1}
                bgcolor={palette.background.default}
                borderRadius={2}
                spacing={1}
                flexWrap="wrap"
                width="50%"
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Data Source:
                </Typography>
                <Typography variant="body2">{details.dataSourceName}</Typography>
              </Stack>
              <Stack
                direction="row"
                padding={1}
                bgcolor={palette.background.default}
                borderRadius={2}
                spacing={1}
                flexWrap="wrap"
                width="50%"
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Data Source Alert Name:
                </Typography>
                <Typography variant="body2">{details.dataSourceAlertName}</Typography>
              </Stack>
            </Stack>
            <Stack
              direction="row-reverse"
              width="100%"
              spacing={2}
              sx={{ "& .w-json-view-container": { backgroundColor: "transparent !important" } }}
            >
              <Stack
                direction="row"
                width="50%"
                padding={1}
                bgcolor={palette.background.default}
                borderRadius={2}
                spacing={1}
                flexWrap="wrap"
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Annotations:
                </Typography>
                <JsonView
                  style={githubLightTheme}
                  value={details.annotations}
                  enableClipboard={false}
                  displayDataTypes={false}
                />
              </Stack>
              <Stack
                direction="row"
                width="50%"
                padding={1}
                bgcolor={palette.background.default}
                borderRadius={2}
                spacing={1}
                flexWrap="wrap"
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Labels:
                </Typography>
                <JsonView
                  style={githubLightTheme}
                  value={details.labels}
                  enableClipboard={false}
                  displayDataTypes={false}
                />
              </Stack>
            </Stack>
          </Stack>
        </ModalContainer>
      )}
    </>
  );
}
