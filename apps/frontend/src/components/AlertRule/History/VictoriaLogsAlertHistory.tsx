import { useMemo } from "react";

import { Button, CircularProgress, Stack, Typography, useTheme } from "@mui/material";
import { purple } from "@mui/material/colors";
import { useInfiniteQuery } from "@tanstack/react-query";
import { FaClockRotateLeft } from "react-icons/fa6";
import { HiChevronDoubleDown } from "react-icons/hi";

import type { AlertRuleStatus, IAlertRule, IVictoriaLogsAlertHistory } from "@/@types/alertRule";
import { getAlertRuleHistory } from "@/api/alertRule";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import EmptyList from "@/components/EmptyList";
import DataTable from "@/components/Table/DataTable";

function getStateStatus(state: number): { status: AlertRuleStatus; statusTitle: string } {
  if (state === 1) {
    return { status: "resolved", statusTitle: "Resolved" };
  }
  if (state === 2) {
    return { status: "critical", statusTitle: "Fired" };
  }
  return { status: "unknown", statusTitle: String(state) };
}

export default function VictoriaLogsAlertHistory({ alertId }: { alertId: IAlertRule["id"] }) {
  const { palette } = useTheme();

  const { data, fetchNextPage, hasNextPage, isFetching, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["victoria-logs-alert-rule-history", alertId],
    queryFn: ({ pageParam }) => getAlertRuleHistory<IVictoriaLogsAlertHistory>(alertId, pageParam),
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
        minimal
        icon={<FaClockRotateLeft size="2rem" color={palette.common.white} />}
        title="No history available"
        description="This alert rule hasn't been fired or resolved yet. Each time the alert gets fired or resolved, a new history entry will appear here."
        gradientColors={[purple[300], purple[200]]}
      />
    );
  }

  return (
    <Stack
      sx={{
        alignItems: "center"
      }}
    >
      <DataTable<IVictoriaLogsAlertHistory>
        data={allData}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "No. of Document", accessorKey: "currentCountDocument" },
          {
            header: "State",
            cell: ({ row }) => {
              const { status, statusTitle } = getStateStatus(row.original.state);
              return (
                <AlertRuleStatusIndicator status={status} statusTitle={statusTitle} size="small" />
              );
            }
          },
          { header: "Date", accessorKey: "updatedAt" }
        ]}
      />
      <Stack
        sx={{
          alignItems: "center",
          position: "relative",
          width: "100%",
          paddingBottom: 1
        }}
      >
        <Typography
          variant="caption"
          sx={{
            color: "text.secondary",
            position: "absolute",
            right: 10,
            top: 6
          }}
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
              border: 1,
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
