import { useMemo, useState } from "react";

import {
  Button,
  CircularProgress,
  IconButton,
  Stack,
  Tooltip,
  Typography,
  useTheme
} from "@mui/material";
import { useInfiniteQuery } from "@tanstack/react-query";
import { HiChevronDoubleDown, HiInformationCircle, HiOutlineLink } from "react-icons/hi";

import type { IAlertRule, ISentryAlertHistory } from "@/@types/alertRule";
import { getAlertRuleHistory } from "@/api/alertRule";
import DataTable from "@/components/Table/DataTable";

import SentryAlertHistoryDetail from "./SentryAlertHistoryDetail";

export default function SentryAlertHistory({ alertId }: { alertId: IAlertRule["id"] }) {
  const [details, setDetails] = useState<ISentryAlertHistory | null>(null);
  const { palette } = useTheme();
  const { data, fetchNextPage, hasNextPage, isFetching, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["alertRuleHistory", alertId],
    queryFn: ({ pageParam }) => getAlertRuleHistory<ISentryAlertHistory>(alertId, pageParam),
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
        <DataTable<ISentryAlertHistory>
          data={allData}
          isLoading={isFetching && !isFetchingNextPage}
          onRowClick={(row) => setDetails(row)}
          columns={[
            {
              header: "Row",
              accessorFn: (_, index) => index + 1,
              size: 40
            },
            {
              header: "Title",
              accessorFn: (row) =>
                row.action === "triggered" ? row.title : row.data.description_title,
              cell: ({ getValue }) => (
                <Typography variant="body2" sx={{ maxWidth: 300 }}>
                  {getValue<string>()}
                </Typography>
              )
            },

            {
              header: "Message",
              accessorFn: (row) => row.message,
              cell: ({ getValue }) => {
                const text = getValue<string>() || "-";

                return (
                  <Tooltip title={text}>
                    <Typography
                      variant="body2"
                      color="text.secondary"
                      noWrap
                      sx={{
                        maxWidth: 350,
                        overflow: "hidden",
                        textOverflow: "ellipsis"
                      }}
                    >
                      {text}
                    </Typography>
                  </Tooltip>
                );
              }
            },

            {
              header: "URL",
              accessorFn: (row) => row.url,
              cell: ({ getValue }) => {
                const url = getValue<string>();

                return (
                  <Tooltip title={url}>
                    <Stack direction="row" alignItems="center" spacing={0.5} color="primary.main">
                      <HiOutlineLink size={14} />

                      <Typography
                        variant="body2"
                        noWrap
                        component="a"
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer"
                        sx={{
                          maxWidth: 200,
                          display: "inline-block",
                          overflow: "hidden",
                          textOverflow: "ellipsis",
                          textDecoration: "none",
                          "&:hover": { textDecoration: "underline" }
                        }}
                      >
                        {url}
                      </Typography>
                    </Stack>
                  </Tooltip>
                );
              }
            },

            {
              header: "Date",
              accessorFn: (row) => row.createdAt,
              cell: ({ getValue }) => (
                <Typography variant="body2" textAlign="center" sx={{ maxWidth: 100 }}>
                  {getValue<string>()}
                </Typography>
              )
            },
            {
              header: "Actions",
              cell: ({ row }) => (
                <Stack
                  width="100%"
                  minWidth="100%"
                  justifyContent="center"
                  alignItems="center"
                  maxWidth={40}
                >
                  <IconButton onClick={() => setDetails(row.original)}>
                    <HiInformationCircle color={palette.primary.light} />
                  </IconButton>
                </Stack>
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
      <SentryAlertHistoryDetail onClose={() => setDetails(null)} data={details} />
    </>
  );
}
