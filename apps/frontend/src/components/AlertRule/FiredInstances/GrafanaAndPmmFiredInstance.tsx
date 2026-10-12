import { useMemo, useState } from "react";

import { IconButton, Stack, Typography, useColorScheme, useTheme } from "@mui/material";
import { useInfiniteQuery } from "@tanstack/react-query";
import { JsonView } from "@uiw/react-json-view";
import { githubDarkTheme } from "@uiw/react-json-view/githubDark";
import { githubLightTheme } from "@uiw/react-json-view/githubLight";
import { HiInformationCircle } from "react-icons/hi";

import type { IAlertRule, IGrafanaAndPmmAlertHistory } from "@/@types/alertRule";
import { getAlertRuleHistory } from "@/api/alertRule";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import ModalContainer from "@/components/Modal";
import DataTable from "@/components/Table/DataTable";
import { truncateLongString } from "@/utils/general";

export default function GrafanaAndPmmFiredInstance({ alertId }: { alertId: IAlertRule["id"] }) {
  const { colorScheme } = useColorScheme();
  const { palette } = useTheme();
  const [details, setDetails] = useState<IGrafanaAndPmmAlertHistory | null>(null);

  const { data, isFetching } = useInfiniteQuery({
    queryKey: ["alertRuleHistory", alertId],
    queryFn: ({ pageParam }) => getAlertRuleHistory<IGrafanaAndPmmAlertHistory>(alertId, pageParam),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.next_page_url ? lastPage.current_page + 1 : undefined,
    refetchOnWindowFocus: false
  });

  const firingData = useMemo(() => {
    if (!data?.pages) return [];
    return data.pages.flatMap((page) => page.data).filter((row) => row.status === "firing");
  }, [data]);

  return (
    <>
      <DataTable<IGrafanaAndPmmAlertHistory>
        data={firingData}
        isLoading={isFetching}
        onRowClick={(row) => setDetails(row)}
        columns={[
          { header: "Row", accessorFn: (_, index) => ++index },
          { header: "DataSource", accessorKey: "dataSourceName" },
          {
            header: "Status",
            cell: ({ row }) => (
              <AlertRuleStatusIndicator status="critical" statusTitle={row.original.status} />
            )
          },
          {
            header: "Title",
            accessorFn: (item) => truncateLongString(item.title)
          },
          { header: "Date", accessorKey: "createdAt" },
          {
            header: "More Info",
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
          <Stack
            spacing={1}
            sx={{
              marginTop: 3
            }}
          >
            <Stack direction="row" spacing={2}>
              <Typography
                variant="body1"
                sx={{
                  fontWeight: "bold"
                }}
              >
                {details.title}
              </Typography>
              <AlertRuleStatusIndicator
                size="small"
                status="critical"
                statusTitle={details.status}
              />
            </Stack>
            <Stack
              direction="row"
              spacing={2}
              sx={{
                width: 1
              }}
            >
              <Stack
                direction="row"
                spacing={1}
                sx={{
                  padding: 1,
                  bgcolor: palette.background.default,
                  borderRadius: 2,
                  flexWrap: "wrap",
                  width: "50%"
                }}
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Data Source:
                </Typography>
                <Typography variant="body2">{details.dataSourceName}</Typography>
              </Stack>
              <Stack
                direction="row"
                spacing={1}
                sx={{
                  padding: 1,
                  bgcolor: palette.background.default,
                  borderRadius: 2,
                  flexWrap: "wrap",
                  width: "50%"
                }}
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Group Key:
                </Typography>
                <Typography variant="body2">{details.groupKey}</Typography>
              </Stack>
            </Stack>
            <Stack
              spacing={2}
              sx={{
                width: "100%"
              }}
            >
              <Stack
                spacing={1}
                sx={{
                  padding: 1,
                  bgcolor: palette.background.default,
                  borderRadius: 2
                }}
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Message:
                </Typography>
                <Typography variant="body2" sx={{ wordBreak: "break-word" }}>
                  {details.message}
                </Typography>
              </Stack>
              <Stack
                spacing={1}
                sx={{
                  padding: 1,
                  bgcolor: palette.background.default,
                  borderRadius: 2
                }}
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Common Labels:
                </Typography>
                <JsonView
                  style={
                    {
                      ...(colorScheme === "dark" ? githubDarkTheme : githubLightTheme),
                      "--w-rjv-background-color": "transparent"
                    } as React.CSSProperties
                  }
                  value={details.commonLabels}
                  enableClipboard={false}
                  displayDataTypes={false}
                />
              </Stack>
              <Stack
                spacing={1}
                sx={{
                  padding: 1,
                  bgcolor: palette.background.default,
                  borderRadius: 2
                }}
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Common Annotations:
                </Typography>
                <JsonView
                  style={
                    {
                      ...(colorScheme === "dark" ? githubDarkTheme : githubLightTheme),
                      "--w-rjv-background-color": "transparent"
                    } as React.CSSProperties
                  }
                  value={details.commonAnnotations}
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
