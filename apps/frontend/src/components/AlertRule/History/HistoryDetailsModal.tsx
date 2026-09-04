"use client";

import { Stack, Typography, useColorScheme, useTheme } from "@mui/material";
import { JsonView } from "@uiw/react-json-view";
import { githubDarkTheme } from "@uiw/react-json-view/githubDark";
import { githubLightTheme } from "@uiw/react-json-view/githubLight";

import type { IAlertRuleHistoryInstance, AlertRuleStatus } from "@/@types/alertRule";
import AlertRuleStatusIndicator from "@/components/AlertRule/AlertRuleStatusIndicator";
import ModalContainer from "@/components/Modal";
import { ModalContainerProps } from "@/components/Modal/types";

interface HistoryDetailsModalProps extends Pick<ModalContainerProps, "onClose"> {
  alerts: IAlertRuleHistoryInstance[] | undefined;
  status?: AlertRuleStatus;
}

export default function HistoryDetailsModal({ alerts, status, onClose }: HistoryDetailsModalProps) {
  const { colorScheme } = useColorScheme();
  const { palette } = useTheme();

  if (!alerts) return null;

  return (
    <ModalContainer
      title="History Details"
      maxWidth="70vw"
      open={Boolean(alerts)}
      onClose={onClose}
    >
      <Stack
        spacing={2}
        sx={{
          height: "70vh",
          overflow: "auto",
          paddingRight: 1,
          marginTop: 2
        }}
      >
        {alerts.map((alert, index) => (
          <Stack
            key={index}
            sx={{
              borderRadius: 2,
              border: 1,
              borderColor: palette.grey[100],
              padding: 2,
              paddingTop: 1
            }}
            spacing={1}
          >
            <Stack direction="row" spacing={2}>
              <Typography
                variant="body1"
                sx={{
                  fontWeight: "bold"
                }}
              >
                {alert.alertRuleName}
              </Typography>
              <AlertRuleStatusIndicator
                size="small"
                status={status ?? (alert.skylogsStatus === 2 ? "critical" : "resolved")}
              />
            </Stack>
            <Stack
              direction="row"
              spacing={2}
              sx={{
                width: "100%"
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
                <Typography variant="body2">{alert.dataSourceName}</Typography>
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
                  Data Source Alert Name:
                </Typography>
                <Typography variant="body2">{alert.dataSourceAlertName}</Typography>
              </Stack>
            </Stack>
            <Stack
              direction="row-reverse"
              spacing={2}
              sx={{
                width: "100%",
                "& .w-json-view-container": { backgroundColor: "transparent !important" }
              }}
            >
              <Stack
                direction="row"
                spacing={1}
                sx={{
                  width: "50%",
                  padding: 1,
                  bgcolor: palette.background.default,
                  borderRadius: 2,
                  flexWrap: "wrap"
                }}
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Annotations:
                </Typography>
                <JsonView
                  collapsed={0}
                  style={colorScheme === "dark" ? githubDarkTheme : githubLightTheme}
                  value={alert.annotations}
                  enableClipboard={false}
                />
              </Stack>
              <Stack
                direction="row"
                spacing={1}
                sx={{
                  width: "50%",
                  padding: 1,
                  bgcolor: palette.background.default,
                  borderRadius: 2,
                  flexWrap: "wrap"
                }}
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Labels:
                </Typography>
                <JsonView
                  collapsed={0}
                  style={colorScheme === "dark" ? githubDarkTheme : githubLightTheme}
                  value={alert.labels}
                  enableClipboard={false}
                />
              </Stack>
            </Stack>
          </Stack>
        ))}
      </Stack>
    </ModalContainer>
  );
}
