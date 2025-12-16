import { useState } from "react";

import { IconButton, Stack, Typography, useTheme } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { HiInformationCircle } from "react-icons/hi";

import type { IAlertRule, IZabbixHistoryInstance } from "@/@types/alertRule";
import { getFiredInstances } from "@/api/alertRule";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import ModalContainer from "@/components/Modal";
import DataTable from "@/components/Table/DataTable";

export default function ZabbixFiredInstance({ alertId }: { alertId: IAlertRule["id"] }) {
  const { palette } = useTheme();
  const [details, setDetails] = useState<IZabbixHistoryInstance["alert_message"] | null>(null);

  const { data } = useQuery({
    queryKey: ["fired-instances", alertId],
    queryFn: () => getFiredInstances(alertId)
  });

  if (!data) return null;

  return (
    <>
      <Stack alignItems="center">
        <DataTable<IZabbixHistoryInstance>
          data={data}
          onRowClick={(row) => setDetails(row.alert_message)}
          columns={[
            { header: "Row", accessorFn: (_, index) => ++index },
            {
              header: "Alert Subject",
              cell: ({ row }) => row.original.alert_subject
            },
            {
              header: "Status",
              cell: ({ row }) => (
                <AlertRuleStatusIndicator
                  status={row.original.event_status === "PROBLEM" ? "critical" : "resolved"}
                  statusTitle={row.original.event_status}
                />
              )
            },
            { header: "Date", accessorKey: "createdAt" },
            {
              header: "Actions",
              cell: ({ row }) => (
                <IconButton onClick={() => setDetails(row.original.alert_message)}>
                  <HiInformationCircle color={palette.primary.light} />
                </IconButton>
              )
            }
          ]}
        />
      </Stack>
      <ModalContainer
        title="Alert Message"
        maxWidth="50vw"
        open={Boolean(details)}
        onClose={() => setDetails(null)}
      >
        <Stack maxHeight="70vh" overflow="auto" paddingRight={1} spacing={2} marginTop={2}>
          <Stack
            width="100%"
            padding={2}
            borderRadius={2}
            sx={{ backgroundColor: ({ palette }) => palette.grey[200] }}
          >
            <Typography variant="subtitle2" component="pre">
              {details}
            </Typography>
          </Stack>
        </Stack>
      </ModalContainer>
    </>
  );
}
