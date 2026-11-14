import { useMemo } from "react";

import type { SxProps, Theme } from "@mui/material";
import { Button, Chip, type ChipProps, CircularProgress, Stack, Tooltip } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import { type AlertRuleStatus, IAlertRule } from "@/@types/alertRule";
import { acknowledgeFiredAlertRule, resolveFiredAlertRule } from "@/api/alertRule";
import { mergeSx, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";

interface AlertRuleStatusIndicatorProps extends Pick<ChipProps, "size"> {
  status: AlertRuleStatus;
  id?: IAlertRule["id"];
  onAfterResolve?: () => void;
  statusTitle?: string;
  showAcknowledge?: boolean;
}

export default function AlertRuleStatusIndicator({
  status,
  onAfterResolve,
  id,
  statusTitle,
  size = "medium",
  showAcknowledge
}: AlertRuleStatusIndicatorProps) {
  const { mutate: resolveAlertRule, isPending: isPendingResolve } = useMutation({
    mutationFn: () => resolveFiredAlertRule(id!),
    onSuccess: (data) => {
      if (data.status) {
        onAfterResolve?.();
        toast.success("Alert Rule Resolved Successfully.");
      }
    }
  });

  const { mutate: acknowledgeAlertRule, isPending: isPendingAcknowledge } = useMutation({
    mutationFn: () => acknowledgeFiredAlertRule(id!),
    onSuccess: (data) => {
      if (data.status) {
        onAfterResolve?.();
        toast.success("Alert Rule Acknowledged Successfully.");
      }
    }
  });

  const isPending = isPendingAcknowledge || isPendingResolve;

  const resButtonSx: SxProps<Theme> = useMemo(
    () =>
      mergeSx(
        pillSurfaceSx(),
        {
          minWidth: "auto",
          height: 26,
          px: 1,
          py: 0,
          fontSize: "0.65rem",
          fontWeight: 700,
          textTransform: "none",
          borderRadius: "8px",
          color: "#4ADE80",
          borderColor: "color-mix(in srgb, #4ADE80 20%, transparent)",
          backgroundColor: "color-mix(in srgb, #4ADE80 5%, #FAF7F2)",
          transition:
            "background-color 150ms ease, border-color 150ms ease, transform 150ms ease, color 150ms ease",
          "&:hover": {
            backgroundColor: "color-mix(in srgb, #4ADE80 10%, #EFE9E0)",
            borderColor: "color-mix(in srgb, #4ADE80 35%, transparent)",
            color: "#22C55E"
          },
          "&:active": {
            transform: "scale(0.95)"
          }
        },
        (muiTheme: Theme) =>
          muiTheme.applyStyles("dark", {
            color: "#16A34A",
            borderColor: "color-mix(in srgb, #16A34A 25%, transparent)",
            backgroundColor: "color-mix(in srgb, #16A34A 12%, rgba(0, 0, 0, 0.6))",
            "&:hover": {
              backgroundColor: "color-mix(in srgb, #16A34A 20%, rgba(0, 0, 0, 0.7))",
              borderColor: "color-mix(in srgb, #16A34A 45%, transparent)",
              color: "#22C55E"
            }
          })
      ),
    []
  );

  const ackButtonSx: SxProps<Theme> = useMemo(
    () =>
      mergeSx(
        pillSurfaceSx(),
        {
          minWidth: "auto",
          height: 26,
          px: 1,
          py: 0,
          fontSize: "0.65rem",
          fontWeight: 700,
          textTransform: "none",
          borderRadius: "8px",
          color: "#60A5FA",
          borderColor: "color-mix(in srgb, #60A5FA 20%, transparent)",
          backgroundColor: "color-mix(in srgb, #60A5FA 5%, #FAF7F2)",
          transition:
            "background-color 150ms ease, border-color 150ms ease, transform 150ms ease, color 150ms ease",
          "&:hover": {
            backgroundColor: "color-mix(in srgb, #60A5FA 10%, #EFE9E0)",
            borderColor: "color-mix(in srgb, #60A5FA 35%, transparent)",
            color: "#3B82F6"
          },
          "&:active": {
            transform: "scale(0.95)"
          }
        },
        (muiTheme: Theme) =>
          muiTheme.applyStyles("dark", {
            color: "#2563EB",
            borderColor: "color-mix(in srgb, #2563EB 25%, transparent)",
            backgroundColor: "color-mix(in srgb, #2563EB 12%, rgba(0, 0, 0, 0.6))",
            "&:hover": {
              backgroundColor: "color-mix(in srgb, #2563EB 20%, rgba(0, 0, 0, 0.7))",
              borderColor: "color-mix(in srgb, #2563EB 45%, transparent)",
              color: "#3B82F6"
            }
          })
      ),
    []
  );

  const color: Record<Exclude<AlertRuleStatus, "unknown">, string> = useMemo(
    () => ({
      resolved: "var(--mui-palette-success-main)",
      critical: "var(--mui-palette-error-main)",
      warning: "var(--mui-palette-warning-main)",
      triggered: "var(--mui-palette-text-secondary)"
    }),
    []
  );

  if (!status || status === "unknown") {
    return (
      <Chip
        label="Unknown"
        size={size}
        sx={{
          textTransform: "capitalize",
          color: "text.secondary",
          backgroundColor: "color-mix(in srgb, var(--mui-palette-text-secondary) 7%, transparent)"
        }}
      />
    );
  }

  return (
    <Stack
      direction="row"
      spacing={1}
      sx={{
        justifyContent: "center"
      }}
    >
      <Chip
        label={statusTitle ?? status}
        size={size}
        sx={{
          textTransform: "capitalize",
          color: color[status],
          backgroundColor: `color-mix(in srgb, ${color[status]} 7%, transparent)`
        }}
      />
      {status === "critical" && id && (
        <Stack direction="row" spacing={0.75} sx={{ alignItems: "center" }}>
          <Tooltip title="Resolve" placement="bottom" arrow>
            <Button
              aria-label="Resolve"
              disabled={isPending}
              size="small"
              sx={resButtonSx}
              onClick={() => resolveAlertRule()}
            >
              {isPendingResolve ? <CircularProgress size={16} color="inherit" /> : "Res"}
            </Button>
          </Tooltip>
          {showAcknowledge && (
            <Tooltip title="Acknowledge" placement="bottom" arrow>
              <Button
                aria-label="Acknowledge"
                disabled={isPending}
                size="small"
                sx={ackButtonSx}
                onClick={() => acknowledgeAlertRule()}
              >
                {isPendingAcknowledge ? <CircularProgress size={16} color="inherit" /> : "Ack"}
              </Button>
            </Tooltip>
          )}
        </Stack>
      )}
    </Stack>
  );
}
