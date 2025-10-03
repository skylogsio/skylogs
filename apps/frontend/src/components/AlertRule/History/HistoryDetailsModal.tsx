"use client";

import { Stack, Typography, useTheme } from "@mui/material";
import JsonView from "@uiw/react-json-view";
import { githubLightTheme } from "@uiw/react-json-view/githubLight";

import type { IAlertRuleHistoryInstance } from "@/@types/alertRule";
import AlertRuleStatus from "@/components/AlertRule/AlertRuleStatus";
import ModalContainer from "@/components/Modal";
import { ModalContainerProps } from "@/components/Modal/types";

export default function HistoryDetailsModal({
  alerts,
  onClose
}: { alerts: IAlertRuleHistoryInstance[] | undefined } & Pick<ModalContainerProps, "onClose">) {
  const { palette } = useTheme();

  if (!alerts) return null;

  return (
    <ModalContainer
      title="History Details"
      maxWidth="70vw"
      open={Boolean(alerts)}
      onClose={onClose}
    >
      <Stack height="70vh" overflow="auto" paddingRight={1} spacing={2} marginTop={2}>
        {alerts.map((alert, index) => (
          <Stack
            key={index}
            sx={{
              borderRadius: 2,
              border: "1px solid",
              borderColor: palette.grey[100],
              padding: 2,
              paddingTop: 1
            }}
            spacing={1}
          >
            <Stack direction="row" spacing={2}>
              <Typography variant="body1" fontWeight="bold">
                {alert.alertRuleName}
              </Typography>
              <AlertRuleStatus
                size="small"
                status={alert.skylogsStatus === 2 ? "critical" : "resolved"}
              />
            </Stack>
            <Stack direction="row" width="100%" spacing={2}>
              <Stack
                direction="row"
                padding={1}
                bgcolor={palette.background.default}
                borderRadius={2}
                spacing={1}
                flexWrap="wrap"
                width="50%"
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Data Source:
                </Typography>
                <Typography variant="body2">{alert.dataSourceName}</Typography>
              </Stack>
              <Stack
                direction="row"
                padding={1}
                bgcolor={palette.background.default}
                borderRadius={2}
                spacing={1}
                flexWrap="wrap"
                width="50%"
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Data Source Alert Name:
                </Typography>
                <Typography variant="body2">{alert.dataSourceAlertName}</Typography>
              </Stack>
            </Stack>
            <Stack
              direction="row-reverse"
              width="100%"
              spacing={2}
              sx={{ "& .w-json-view-container": { backgroundColor: "transparent !important" } }}
            >
              <Stack
                direction="row"
                width="50%"
                padding={1}
                bgcolor={palette.background.default}
                borderRadius={2}
                spacing={1}
                flexWrap="wrap"
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Annotations:
                </Typography>
                <JsonView
                  collapsed={0}
                  style={githubLightTheme}
                  value={alert.annotations}
                  enableClipboard={false}
                />
              </Stack>
              <Stack
                direction="row"
                width="50%"
                padding={1}
                bgcolor={palette.background.default}
                borderRadius={2}
                spacing={1}
                flexWrap="wrap"
              >
                <Typography variant="body2" sx={{ opacity: 0.6 }}>
                  Labels:
                </Typography>
                <JsonView
                  collapsed={0}
                  style={githubLightTheme}
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
