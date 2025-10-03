import { useState } from "react";

import { alpha, IconButton, Stack, Typography, useTheme } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { HiInformationCircle } from "react-icons/hi";

import type { IAlertRule, IApiAlertRuleInstance } from "@/@types/alertRule";
import { getFiredInstances } from "@/api/alertRule";
import ModalContainer from "@/components/Modal";
import DataTable from "@/components/Table/DataTable";
import { truncateLongString } from "@/utils/general";

export default function ApiFiredInstances({ alertId }: { alertId: IAlertRule["id"] }) {
  const { palette } = useTheme();
  const [details, setDetails] = useState<IApiAlertRuleInstance | null>(null);

  const { data } = useQuery({
    queryKey: ["fired-instances", alertId],
    queryFn: () => getFiredInstances(alertId)
  });

  if (!data) return null;

  return (
    <>
      <DataTable<IApiAlertRuleInstance>
        data={data}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Instance", accessorKey: "instance" },
          {
            header: "Description",
            accessorFn: (item) => truncateLongString(item.description)
          },
          { header: "Date", accessorKey: "updatedAt" },
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
          title={details.instance}
          open={Boolean(details)}
          onClose={() => setDetails(null)}
        >
          <Stack>
            <Typography variant="body2" color="textSecondary">
              {details.updatedAt}
            </Typography>
            <Stack
              padding={2}
              marginTop={2}
              borderRadius={2}
              maxHeight="60vh"
              bgcolor={alpha(palette.secondary.light, 0.3)}
            >
              <Typography sx={{ wordBreak: "break-word" }}>{details.description}</Typography>
            </Stack>
          </Stack>
        </ModalContainer>
      )}
    </>
  );
}
