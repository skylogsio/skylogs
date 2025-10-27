import { useMemo, useState } from "react";

import { Button, CircularProgress, IconButton, Stack, Typography, useTheme } from "@mui/material";
import { useInfiniteQuery } from "@tanstack/react-query";
import { HiChevronDoubleDown, HiInformationCircle } from "react-icons/hi";

import type { IAlertRule, IGrafanaAndPmmAlertHistory } from "@/@types/alertRule";
import { getAlertRuleHistory } from "@/api/alertRule";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import HistoryDetailsModal from "@/components/AlertRule/History/HistoryDetailsModal";
import DataTable from "@/components/Table/DataTable";

export default function GrafanaAndPmmAlertHistory({ alertId }: { alertId: IAlertRule["id"] }) {
  const { palette } = useTheme();
  const [details, setDetails] = useState<IGrafanaAndPmmAlertHistory | null>(null);

  const { data, fetchNextPage, hasNextPage, isFetching, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["alertRuleHistory", alertId],
    queryFn: ({ pageParam }) => getAlertRuleHistory<IGrafanaAndPmmAlertHistory>(alertId, pageParam),
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
        <DataTable<IGrafanaAndPmmAlertHistory>
          data={allData}
          isLoading={isFetching && !isFetchingNextPage}
          onRowClick={(row) => setDetails(row)}
          columns={[
            { header: "Row", accessorFn: (_, index) => ++index },
            {
              header: "Data Source Name",
              cell: ({ row }) => row.original.dataSourceName
            },
            {
              header: "Status",
              cell: ({ row }) => (
                <AlertRuleStatusIndicator
                  status={row.original.status === "firing" ? "critical" : row.original.status}
                  statusTitle={row.original.status}
                />
              )
            },
            { header: "Date", accessorKey: "createdAt" },
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
      <HistoryDetailsModal alerts={details?.alerts} onClose={() => setDetails(null)} />
    </>
  );
}
