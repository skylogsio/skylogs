import { useMemo, useState } from "react";

import {
  alpha,
  Avatar,
  Button,
  Chip,
  CircularProgress,
  IconButton,
  Stack,
  Typography,
  useTheme
} from "@mui/material";
import { useInfiniteQuery } from "@tanstack/react-query";
import { HiChevronDoubleDown, HiInformationCircle } from "react-icons/hi";

import type { IAlertRule, IPrometheusAlertHistory } from "@/@types/alertRule";
import { getAlertRuleHistory } from "@/api/alertRule";
import HistoryDetailsModal from "@/components/AlertRule/History/HistoryDetailsModal";
import DataTable from "@/components/Table/DataTable";

export default function PrometheusAlertsHistory({ alertId }: { alertId: IAlertRule["id"] }) {
  const { palette } = useTheme();
  const [details, setDetails] = useState<IPrometheusAlertHistory | null>(null);

  const { data, fetchNextPage, hasNextPage, isFetching, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["alertRuleHistory", alertId],
    queryFn: ({ pageParam }) => getAlertRuleHistory<IPrometheusAlertHistory>(alertId, pageParam),
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

  function renderFiredAndResolvedChip(countFire: number, countResolve: number) {
    const numberOfFired = countFire > 100 ? "+99" : countFire;
    const numberOfResolved = countResolve > 100 ? "+99" : countResolve;
    return (
      <Stack direction="row" spacing={1} justifyContent="center">
        {countFire > 0 && (
          <Chip
            avatar={
              <Avatar
                variant="rounded"
                sx={{
                  bgcolor: ({ palette }) => alpha(palette.error.light, 0.3),
                  color: ({ palette }) => `${palette.error.main}!important`
                }}
              >
                {numberOfFired}
              </Avatar>
            }
            variant="outlined"
            label="Fired"
            color="error"
          />
        )}
        {countResolve > 0 && (
          <Chip
            avatar={
              <Avatar
                variant="rounded"
                sx={{
                  bgcolor: ({ palette }) => alpha(palette.success.light, 0.3),
                  color: ({ palette }) => `${palette.success.main}!important`
                }}
              >
                {numberOfResolved}
              </Avatar>
            }
            variant="outlined"
            label="Resolved"
            color="success"
          />
        )}
      </Stack>
    );
  }

  return (
    <>
      <Stack alignItems="center">
        <DataTable<IPrometheusAlertHistory>
          data={allData}
          isLoading={isFetching && !isFetchingNextPage}
          onRowClick={(row) => setDetails(row)}
          columns={[
            { header: "Row", accessorFn: (_, index) => ++index },
            {
              header: "Fired / Resolved",
              cell: ({ row }) =>
                renderFiredAndResolvedChip(row.original.countFire, row.original.countResolve)
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
