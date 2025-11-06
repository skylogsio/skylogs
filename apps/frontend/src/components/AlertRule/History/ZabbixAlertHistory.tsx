import { useMemo, useState } from "react";

import { Button, CircularProgress, IconButton, Stack, Typography, useTheme } from "@mui/material";
import { useInfiniteQuery } from "@tanstack/react-query";
import { HiChevronDoubleDown, HiInformationCircle } from "react-icons/hi";

import type { IAlertRule, IZabbixAlertHistory } from "@/@types/alertRule";
import { getAlertRuleHistory } from "@/api/alertRule";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import ModalContainer from "@/components/Modal";
import DataTable from "@/components/Table/DataTable";

export default function ZabbixAlertHistory({ alertId }: { alertId: IAlertRule["id"] }) {
  const { palette } = useTheme();
  const [details, setDetails] = useState<IZabbixAlertHistory["alert_message"] | null>(null);

  const { data, fetchNextPage, hasNextPage, isFetching, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["alertRuleHistory", alertId],
    queryFn: ({ pageParam }) => getAlertRuleHistory<IZabbixAlertHistory>(alertId, pageParam),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.next_page_url ? lastPage.current_page + 1 : undefined,
    refetchOnWindowFocus: false
  });

  const allData = useMemo(() => {
    if (!data?.pages) return [];
    return data.pages.flatMap((page) => page.data);
  }, [data]);

  const totalCount = data?.pages?.[0]?.total || 0;

  return (
    <>
      <Stack alignItems="center">
        <DataTable<IZabbixAlertHistory>
          data={allData}
          isLoading={isFetching && !isFetchingNextPage}
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
        <Stack alignItems="center" position="relative" width="100%" paddingBottom={1}>
          <Typography
            variant="caption"
            color="text.secondary"
            sx={{ position: "absolute", right: 10, top: 6 }}
          >
            Showing {allData.length} of {totalCount}
          </Typography>
          {hasNextPage && (
            <Button
              endIcon={
                isFetchingNextPage ? (
                  <CircularProgress color="inherit" size={15} />
                ) : (
                  <HiChevronDoubleDown />
                )
              }
              onClick={() => fetchNextPage()}
              disabled={isFetchingNextPage}
              sx={{
                marginX: "auto",
                marginTop: 2,
                backgroundColor: palette.background.paper,
                border: "1px solid",
                paddingX: 2,
                borderColor: palette.secondary.light,
                color: palette.secondary.dark
              }}
            >
              Load More
            </Button>
          )}
        </Stack>
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
