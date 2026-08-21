"use client";

import type { ReactNode } from "react";

import {
  Box,
  Chip,
  CircularProgress,
  Divider,
  Grid,
  Stack,
  Typography,
  alpha,
  useTheme
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { INCIDENT_SEVERITIES } from "@/features/Incidents/incident.type";
import { useCurrentTheme } from "@/hooks";

import { getIncidentPolicyById } from "../incident-policy.api";
import type { IIncidentPolicy } from "../incident-policy.type";
import { formatIncidentPolicyDateTime } from "../incident-policy.utils";

import IncidentPolicyModalBody from "./IncidentPolicyModalBody";

type IncidentPolicyDetailsModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  policyId: IIncidentPolicy["id"];
};

function GroupHeading({ children }: { children: ReactNode }) {
  return (
    <Typography
      variant="overline"
      sx={{
        display: "block",
        color: "text.secondary",
        fontWeight: 700,
        fontSize: "0.7rem",
        letterSpacing: "0.1em"
      }}
    >
      {children}
    </Typography>
  );
}

function FieldLabel({ children }: { children: ReactNode }) {
  return (
    <Typography
      variant="caption"
      sx={{ color: "text.disabled", fontWeight: 500, fontSize: "0.7rem" }}
    >
      {children}
    </Typography>
  );
}

function MetaItem({ label, value }: { label: string; value: string }) {
  return (
    <Stack spacing={0.35}>
      <FieldLabel>{label}</FieldLabel>
      <Typography variant="body1" sx={{ fontWeight: 600, fontSize: "0.9375rem" }}>
        {value || "—"}
      </Typography>
    </Stack>
  );
}

function ChipGroup({ label, items }: { label: string; items: string[] }) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  return (
    <Stack spacing={0.75}>
      <FieldLabel>{label}</FieldLabel>
      {items.length === 0 ? (
        <Typography variant="body2" sx={{ color: "text.disabled" }}>
          —
        </Typography>
      ) : (
        <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
          {items.map((item) => (
            <Chip
              key={item}
              label={item}
              size="small"
              sx={{
                height: 28,
                fontWeight: 600,
                backgroundColor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
                border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
                borderRadius: "10px"
              }}
            />
          ))}
        </Stack>
      )}
    </Stack>
  );
}

export default function IncidentPolicyDetailsModal({
  open,
  onClose,
  policyId
}: IncidentPolicyDetailsModalProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  const { data: policy, isPending, isError, error } = useQuery({
    queryKey: ["incident-policy", policyId],
    queryFn: () => getIncidentPolicyById(policyId),
    enabled: open && Boolean(policyId)
  });

  return (
    <ModalContainer
      title={policy?.name ?? "Incident Policy Details"}
      open={open}
      onClose={onClose}
      maxWidth={720}
      paperSx={{
        ...getGlassCardSx(theme, isDark),
        maxHeight: "90vh",
        display: "flex",
        flexDirection: "column"
      }}
    >
      <IncidentPolicyModalBody>
        {isError ? (
          <Typography color="error" sx={{ py: 2 }}>
            {error instanceof Error ? error.message : "Failed to load incident policy."}
          </Typography>
        ) : isPending || !policy ? (
          <Stack sx={{ alignItems: "center", py: 6 }}>
            <CircularProgress size={32} />
          </Stack>
        ) : (
          <Box sx={{ maxHeight: "min(70vh, 640px)", overflowY: "auto", pr: 0.5 }}>
            <Stack
              spacing={2}
              divider={
                <Divider sx={{ borderColor: alpha(palette.primary.main, isDark ? 0.12 : 0.16) }} />
              }
            >
              <Stack spacing={1.5}>
                <GroupHeading>Details</GroupHeading>
                <Grid container spacing={2}>
                  <Grid size={6}>
                    <MetaItem label="Enabled" value={policy.enabled ? "Yes" : "No"} />
                  </Grid>
                  <Grid size={6}>
                    <MetaItem label="Source" value={String(policy.source ?? "—")} />
                  </Grid>
                  <Grid size={6}>
                    <MetaItem
                      label="Version"
                      value={policy.version != null ? String(policy.version) : "—"}
                    />
                  </Grid>
                  <Grid size={6}>
                    <MetaItem label="Owner" value={policy.owner?.name ?? policy.ownerId ?? "—"} />
                  </Grid>
                  <Grid size={6}>
                    <MetaItem
                      label="Updated At"
                      value={formatIncidentPolicyDateTime(policy.updatedAt)}
                    />
                  </Grid>
                </Grid>
                <Stack spacing={0.35}>
                  <FieldLabel>Description</FieldLabel>
                  <Typography
                    sx={{
                      whiteSpace: "pre-wrap",
                      color: policy.description ? "text.primary" : "text.disabled"
                    }}
                  >
                    {policy.description?.trim() || "—"}
                  </Typography>
                </Stack>
              </Stack>

              <Stack spacing={1.5}>
                <GroupHeading>Match</GroupHeading>
                <ChipGroup label="Tags" items={policy.match?.tags ?? []} />
                <ChipGroup label="Alert Rule IDs" items={policy.match?.alertRuleIds ?? []} />
                <ChipGroup label="Service IDs" items={policy.match?.serviceIds ?? []} />
                <ChipGroup
                  label="Data Source Types"
                  items={(policy.match?.dataSourceTypes ?? []).map(String)}
                />
              </Stack>

              <Stack spacing={1.5}>
                <GroupHeading>Teams</GroupHeading>
                <ChipGroup
                  label="Teams"
                  items={policy.teams?.map((team) => team.name) ?? policy.teamIds ?? []}
                />
              </Stack>

              <Stack spacing={1.5}>
                <GroupHeading>Rules</GroupHeading>
                {INCIDENT_SEVERITIES.filter((severity) => policy.rules?.[severity]).map(
                  (severity) => {
                    const rule = policy.rules[severity]!;
                    return (
                      <Box
                        key={severity}
                        sx={{
                          p: 1.5,
                          borderRadius: 2,
                          border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`
                        }}
                      >
                        <Typography sx={{ fontWeight: 700, mb: 0.75 }}>{severity}</Typography>
                        <Typography variant="body2" sx={{ color: "text.secondary" }}>
                          Ack {rule.ackWithinMinutes ?? "—"} min · Resolve{" "}
                          {rule.resolveWithinMinutes ?? "—"} min
                          {rule.requireCommander ? " · Commander required" : ""}
                        </Typography>
                        {(rule.runbookNames?.length ?? 0) > 0 && (
                          <Typography variant="body2" sx={{ mt: 0.5 }}>
                            Runbooks: {rule.runbookNames?.join(", ")}
                          </Typography>
                        )}
                      </Box>
                    );
                  }
                )}
              </Stack>
            </Stack>
          </Box>
        )}
      </IncidentPolicyModalBody>
    </ModalContainer>
  );
}
