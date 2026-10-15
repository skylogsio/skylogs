import { useMemo } from "react";

import {
  Chip,
  type ChipProps,
  CircularProgress,
  IconButton,
  Stack,
  Tooltip
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { FaHandsHelping, FaTools } from "react-icons/fa";
import { toast } from "react-toastify";

import { type AlertRuleStatus, IAlertRule } from "@/@types/alertRule";
import { acknowledgeFiredAlertRule, resolveFiredAlertRule } from "@/api/alertRule";

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
          backgroundColor:
            "color-mix(in srgb, var(--mui-palette-text-secondary) 7%, transparent)"
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
        <Stack direction="row" spacing={1}>
          <Tooltip title="Resolve" placement="bottom" arrow>
            <IconButton
              disabled={isPending}
              size="small"
              sx={{
                paddingX: 1,
                color: color[status],
                backgroundColor: `color-mix(in srgb, ${isPending ? "var(--mui-palette-grey-700)" : color[status]} 7%, transparent) !important`
              }}
              onClick={() => resolveAlertRule()}
            >
              {isPending ? <CircularProgress size={17} color="inherit" /> : <FaTools />}
            </IconButton>
          </Tooltip>
          {showAcknowledge && (
            <Tooltip title="Acknowledge" placement="bottom" arrow>
              <IconButton
                disabled={isPending}
                size="small"
                sx={{
                  paddingX: 1,
                  color: color[status],
                  backgroundColor: `color-mix(in srgb, ${isPending ? "var(--mui-palette-grey-700)" : color[status]} 7%, transparent) !important`
                }}
                onClick={() => acknowledgeAlertRule()}
              >
                {isPending ? <CircularProgress size={17} color="inherit" /> : <FaHandsHelping />}
              </IconButton>
            </Tooltip>
          )}
        </Stack>
      )}
    </Stack>
  );
}
