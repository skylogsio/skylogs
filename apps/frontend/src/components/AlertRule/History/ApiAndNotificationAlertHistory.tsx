import React, { useMemo } from "react";

import { Button, CircularProgress, Stack, Typography, useTheme } from "@mui/material";
import { purple } from "@mui/material/colors";
import { useInfiniteQuery } from "@tanstack/react-query";
import { FaClockRotateLeft } from "react-icons/fa6";
import { HiChevronDoubleDown } from "react-icons/hi";

import { IAlertRule, IApiAndNotificationAlertRuleHistory } from "@/@types/alertRule";
import { getAlertRuleHistory } from "@/api/alertRule";
import AlertRuleStatus from "@/components/AlertRule/AlertRuleStatus";
import EmptyList from "@/components/EmptyList";
import DataTable from "@/components/Table/DataTable";

export default function ApiAndNotificationAlertHistory({ alertId }: { alertId: IAlertRule["id"] }) {
  const { palette } = useTheme();

  const { data, fetchNextPage, hasNextPage, isFetching, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["api-notification-alert-rule", alertId],
    queryFn: ({ pageParam }) => getAlertRuleHistory<IApiAndNotificationAlertRuleHistory>(alertId, pageParam),
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

  if (isFetching && !isFetchingNextPage) {
    return null;
  }

  if (totalCount === 0) {
    return (
      <EmptyList
        icon={
          <FaClockRotateLeft size="2.5rem" style={{ color: purple[200], marginBottom: "16px" }} />
        }
        title="No history available"
        description="This alert rule hasn't been fired or resolved yet. Each time the alert gets fired or resolved, a new history entry will appear here."
      />
    );
  }

  return (
    <Stack alignItems="center">
      <DataTable<IApiAndNotificationAlertRuleHistory>
        data={allData}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "Instance", accessorKey: "instance" },
          {
            header: "Status",
            cell: ({ row }) => <AlertRuleStatus status={row.original.status} />
          },
          { header: "Description", accessorKey: "description" },
          { header: "Date", accessorKey: "createdAt" }
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
              isFetching ? <CircularProgress color="inherit" size={14} /> : <HiChevronDoubleDown />
            }
            onClick={() => fetchNextPage()}
            disabled={isFetching}
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
  );
}
