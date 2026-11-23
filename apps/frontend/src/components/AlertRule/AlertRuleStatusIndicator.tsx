import { useMemo } from "react";

import { Chip, type ChipProps, CircularProgress, IconButton, Stack, Tooltip } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import { type AlertRuleStatus, IAlertRule } from "@/@types/alertRule";
import { acknowledgeFiredAlertRule, resolveFiredAlertRule } from "@/api/alertRule";
import { actionButtonSx } from "@/components/ActionColumn";
import AlertRuleState from "@/components/AlertRule/AlertRuleState";

interface AlertRuleStatusIndicatorProps extends Pick<ChipProps, "size"> {
  status: AlertRuleStatus;
  statusTitle?: string;
  isSilent?: boolean;
  acknowledgedBy?: string | null;
  acknowledgedByName?: string | null;
  id?: IAlertRule["id"];
  type?: IAlertRule["type"];
  isReadonly?: boolean;
  onAfterResolve?: () => void;
}

export default function AlertRuleStatusIndicator({
  status,
  statusTitle,
  size = "medium",
  isSilent,
  acknowledgedBy,
  acknowledgedByName,
  id,
  type,
  isReadonly = false,
  onAfterResolve
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

  const rawStatus = typeof status === "string" ? status.toLowerCase() : "";
  const isFired = rawStatus === "critical" || rawStatus === "triggered";
  const isAcked = Boolean(acknowledgedBy || acknowledgedByName);
  const canResolve = !isReadonly && isFired && type === "api" && Boolean(id);
  const canAcknowledge =
    !isReadonly && isFired && type !== "notification" && !isAcked && Boolean(id);

  const renderChip = () => {
    if (!status || status === "unknown") {
      if (!statusTitle) {
        return (
          <AlertRuleState
            isSilent={isSilent}
            status={status}
            acknowledgedBy={acknowledgedBy}
            acknowledgedByName={acknowledgedByName}
          />
        );
      }
      return (
        <Chip
          label={statusTitle}
          size={size}
          sx={{
            textTransform: "capitalize",
            color: "text.secondary",
            backgroundColor: "color-mix(in srgb, var(--mui-palette-text-secondary) 7%, transparent)"
          }}
        />
      );
    }

    if (statusTitle) {
      return (
        <Chip
          label={statusTitle}
          size={size}
          sx={{
            textTransform: "capitalize",
            color: color[status as Exclude<AlertRuleStatus, "unknown">] ?? "text.secondary",
            backgroundColor: `color-mix(in srgb, ${color[status as Exclude<AlertRuleStatus, "unknown">] ?? "var(--mui-palette-text-secondary)"} 7%, transparent)`
          }}
        />
      );
    }

    return (
      <AlertRuleState
        isSilent={isSilent}
        status={status}
        acknowledgedBy={acknowledgedBy}
        acknowledgedByName={acknowledgedByName}
      />
    );
  };

  if (!canResolve && !canAcknowledge) {
    return renderChip();
  }

  return (
    <Stack direction="row" spacing={0.75} sx={{ alignItems: "center" }}>
      {renderChip()}
      {canResolve && (
        <Tooltip title="Resolve" placement="bottom" arrow>
          <span>
            <IconButton
              aria-label="Resolve"
              disabled={isPending}
              sx={actionButtonSx("#22C55E", (theme) => ({
                height: 25,
                minWidth: "auto",
                width: "auto",
                px: 0.9,
                py: 0,
                fontSize: "0.6875rem",
                fontWeight: 700,
                letterSpacing: "0.02em",
                borderRadius: "7px",
                ...theme.applyStyles("dark", { color: "#4ADE80" })
              }))}
              onClick={() => resolveAlertRule()}
            >
              {isPendingResolve ? <CircularProgress size={13} color="inherit" /> : "Res"}
            </IconButton>
          </span>
        </Tooltip>
      )}
      {canAcknowledge && (
        <Tooltip title="Acknowledge" placement="bottom" arrow>
          <span>
            <IconButton
              aria-label="Acknowledge"
              disabled={isPending}
              sx={actionButtonSx("#3B82F6", (theme) => ({
                height: 25,
                minWidth: "auto",
                width: "auto",
                px: 0.9,
                py: 0,
                fontSize: "0.6875rem",
                fontWeight: 700,
                letterSpacing: "0.02em",
                borderRadius: "7px",
                ...theme.applyStyles("dark", { color: "#60A5FA" })
              }))}
              onClick={() => acknowledgeAlertRule()}
            >
              {isPendingAcknowledge ? <CircularProgress size={13} color="inherit" /> : "Ack"}
            </IconButton>
          </span>
        </Tooltip>
      )}
    </Stack>
  );
}
