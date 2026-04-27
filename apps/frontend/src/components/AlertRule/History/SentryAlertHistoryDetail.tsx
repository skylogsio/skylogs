import { Typography, Box, Link, Stack, useTheme, useColorScheme } from "@mui/material";
import JsonView from "@uiw/react-json-view";
import { githubDarkTheme } from "@uiw/react-json-view/githubDark";
import { githubLightTheme } from "@uiw/react-json-view/githubLight";

import { ISentryAlertHistory } from "@/@types/alertRule";
import ModalContainer from "@/components/Modal";

import AlertRuleStatusIndicator from "../AlertRuleStatusIndicator";

interface SentryAlertHistoryDetailProps {
  onClose: () => void;
  data: ISentryAlertHistory | null;
}

export default function SentryAlertHistoryDetail({ onClose, data }: SentryAlertHistoryDetailProps) {
  const { colorScheme } = useColorScheme();
  const { palette } = useTheme();
  if (!data) return null;

  const isTriggered = data.action === "triggered";
  const title = isTriggered ? data.title : data.data.description_title;

  return (
    <ModalContainer title="History Details" maxWidth="70vw" open={Boolean(data)} onClose={onClose}>
      <Stack height="70vh" overflow="auto" paddingRight={1} spacing={2} marginTop={2}>
        <Box
          sx={{
            display: "flex",
            alignItems: "center",
            gap: 2,
            mb: 1
          }}
        >
          <Typography variant="h6">{data.alertRuleName}</Typography>
          <AlertRuleStatusIndicator size="small" status={data.action} />
        </Box>

        <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
          {data.createdAt}
        </Typography>

        <Stack
          padding={1}
          bgcolor={palette.background.default}
          borderRadius={2}
          spacing={1}
          sx={{ mb: 2 }}
        >
          <Typography variant="caption" color="text.secondary" sx={{ display: "block", mb: 0.5 }}>
            Title
          </Typography>
          <Typography variant="body2">{title}</Typography>
        </Stack>

        <Stack
          padding={1}
          bgcolor={palette.background.default}
          borderRadius={2}
          spacing={1}
          sx={{ mb: 2 }}
        >
          <Typography variant="caption" color="text.secondary" sx={{ display: "block", mb: 0.5 }}>
            Message
          </Typography>
          <Typography variant="body2">{data.message}</Typography>
        </Stack>

        <Stack
          padding={1}
          bgcolor={palette.background.default}
          borderRadius={2}
          spacing={1}
          sx={{ mb: 2 }}
        >
          <Typography variant="caption" color="text.secondary" sx={{ display: "block", mb: 0.5 }}>
            URL
          </Typography>
          <Link
            href={data.url}
            target="_blank"
            rel="noopener noreferrer"
            variant="body2"
            sx={{ wordBreak: "break-all" }}
          >
            {data.url}
          </Link>
        </Stack>

        <Stack
          padding={1}
          bgcolor={palette.background.default}
          borderRadius={2}
          spacing={1}
          sx={{ "& .w-json-view-container": { backgroundColor: "transparent !important" } }}
        >
          <Typography variant="caption" color="text.secondary" sx={{ display: "block" }}>
            {isTriggered ? "Event Data" : "Metric Alert Data"}
          </Typography>
          <JsonView
            value={isTriggered ? data.data.event : data.data.metric_alert}
            collapsed={0}
            style={colorScheme === "dark" ? githubDarkTheme : githubLightTheme}
            enableClipboard={false}
          />
        </Stack>
      </Stack>
    </ModalContainer>
  );
}
