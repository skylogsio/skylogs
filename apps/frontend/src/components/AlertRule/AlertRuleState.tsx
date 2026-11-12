"use client";

import { Box, keyframes, Stack } from "@mui/material";
import { HiCheck } from "react-icons/hi";
import { IoNotificationsOff } from "react-icons/io5";

import type { AlertRuleStatus } from "@/@types/alertRule";

const blinkAnimation = keyframes`
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.25;
  }
`;

interface AlertRuleStateProps {
  isSilent?: boolean;
  status?: AlertRuleStatus;
  acknowledgedBy?: string | null;
  acknowledgedByName?: string | null;
}

export default function AlertRuleState({
  isSilent,
  status,
  acknowledgedBy,
  acknowledgedByName
}: AlertRuleStateProps) {
  const ackUser = acknowledgedBy ?? acknowledgedByName;

  if (isSilent) {
    return (
      <Box
        sx={(theme) => ({
          display: "inline-flex",
          alignItems: "center",
          gap: 0.6,
          height: 25,
          px: 1.25,
          borderRadius: "999px",
          fontSize: "0.75rem",
          fontWeight: 600,
          whiteSpace: "nowrap",
          color: theme.palette.text.secondary,
          backgroundColor: `color-mix(in srgb, ${theme.palette.text.secondary} 5%, transparent)`,
          border: `1px solid color-mix(in srgb, ${theme.palette.text.secondary} 18%, transparent)`,
          ...theme.applyStyles("dark", {
            color: theme.palette.text.secondary,
            backgroundColor: `color-mix(in srgb, ${theme.palette.text.secondary} 8%, transparent)`,
            borderColor: `color-mix(in srgb, ${theme.palette.text.secondary} 20%, transparent)`
          })
        })}
      >
        <IoNotificationsOff size={13} style={{ flexShrink: 0 }} />
        <span>Silenced</span>
      </Box>
    );
  }

  if ((status === "critical" || status === "warning" || status === "triggered") && ackUser) {
    return (
      <Box
        sx={(theme) => ({
          display: "inline-flex",
          alignItems: "center",
          gap: 0.6,
          height: 25,
          px: 1.25,
          borderRadius: "999px",
          fontSize: "0.75rem",
          fontWeight: 600,
          whiteSpace: "nowrap",
          color: theme.palette.info.main,
          backgroundColor: `color-mix(in srgb, ${theme.palette.info.main} 10%, transparent)`,
          border: `1px solid color-mix(in srgb, ${theme.palette.info.main} 28%, transparent)`,
          ...theme.applyStyles("dark", {
            backgroundColor: `color-mix(in srgb, ${theme.palette.info.main} 16%, transparent)`,
            borderColor: `color-mix(in srgb, ${theme.palette.info.main} 35%, transparent)`
          })
        })}
      >
        <HiCheck size={14} style={{ flexShrink: 0 }} />
        <Stack direction="row" spacing={0.5} sx={{ alignItems: "center" }}>
          <span>Acked by</span>
          <Box component="span" sx={{ fontWeight: 700 }}>
            {ackUser}
          </Box>
        </Stack>
      </Box>
    );
  }

  if (status === "critical" || status === "triggered") {
    return (
      <Box
        sx={(theme) => ({
          display: "inline-flex",
          alignItems: "center",
          gap: 0.6,
          height: 25,
          px: 1.25,
          borderRadius: "999px",
          fontSize: "0.75rem",
          fontWeight: 600,
          whiteSpace: "nowrap",
          color: theme.palette.error.dark,
          backgroundColor: `color-mix(in srgb, ${theme.palette.error.main} 10%, transparent)`,
          border: `1px solid color-mix(in srgb, ${theme.palette.error.main} 28%, transparent)`,
          ...theme.applyStyles("dark", {
            backgroundColor: `color-mix(in srgb, ${theme.palette.error.main} 16%, transparent)`,
            borderColor: `color-mix(in srgb, ${theme.palette.error.main} 35%, transparent)`
          })
        })}
      >
        <Box
          component="span"
          sx={{
            width: 6,
            height: 6,
            borderRadius: "50%",
            backgroundColor: "currentColor",
            flexShrink: 0,
            animation: `${blinkAnimation} 1.2s ease-in-out infinite`
          }}
        />
        <span>Firing</span>
      </Box>
    );
  }

  if (status === "warning") {
    return (
      <Box
        sx={(theme) => ({
          display: "inline-flex",
          alignItems: "center",
          gap: 0.6,
          height: 25,
          px: 1.25,
          borderRadius: "999px",
          fontSize: "0.75rem",
          fontWeight: 600,
          whiteSpace: "nowrap",
          color: theme.palette.warning.dark,
          backgroundColor: `color-mix(in srgb, ${theme.palette.warning.main} 10%, transparent)`,
          border: `1px solid color-mix(in srgb, ${theme.palette.warning.main} 28%, transparent)`,
          ...theme.applyStyles("dark", {
            backgroundColor: `color-mix(in srgb, ${theme.palette.warning.main} 16%, transparent)`,
            borderColor: `color-mix(in srgb, ${theme.palette.warning.main} 35%, transparent)`
          })
        })}
      >
        <Box
          component="span"
          sx={{
            width: 6,
            height: 6,
            borderRadius: "50%",
            backgroundColor: "currentColor",
            flexShrink: 0
          }}
        />
        <span>Warning</span>
      </Box>
    );
  }

  if (status === "resolved") {
    return (
      <Box
        sx={(theme) => ({
          display: "inline-flex",
          alignItems: "center",
          gap: 0.6,
          height: 25,
          px: 1.25,
          borderRadius: "999px",
          fontSize: "0.75rem",
          fontWeight: 600,
          whiteSpace: "nowrap",
          color: theme.palette.success.dark,
          backgroundColor: `color-mix(in srgb, ${theme.palette.success.main} 10%, transparent)`,
          border: `1px solid color-mix(in srgb, ${theme.palette.success.main} 28%, transparent)`,
          ...theme.applyStyles("dark", {
            backgroundColor: `color-mix(in srgb, ${theme.palette.success.main} 16%, transparent)`,
            borderColor: `color-mix(in srgb, ${theme.palette.success.main} 35%, transparent)`
          })
        })}
      >
        <HiCheck size={14} style={{ flexShrink: 0 }} />
        <span>Resolved</span>
      </Box>
    );
  }

  return (
    <Box
      sx={(theme) => ({
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        height: 25,
        px: 1.25,
        borderRadius: "999px",
        fontSize: "0.75rem",
        fontWeight: 500,
        whiteSpace: "nowrap",
        color: theme.palette.text.disabled,
        backgroundColor: `color-mix(in srgb, ${theme.palette.text.disabled} 5%, transparent)`,
        border: `1px dashed color-mix(in srgb, ${theme.palette.text.disabled} 30%, transparent)`,
        ...theme.applyStyles("dark", {
          backgroundColor: `color-mix(in srgb, ${theme.palette.text.disabled} 8%, transparent)`,
          borderColor: `color-mix(in srgb, ${theme.palette.text.disabled} 35%, transparent)`
        })
      })}
    >
      <span>No data yet</span>
    </Box>
  );
}
