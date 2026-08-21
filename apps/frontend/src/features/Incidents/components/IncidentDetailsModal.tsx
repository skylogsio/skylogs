"use client";

import type { ReactNode } from "react";
import { useState } from "react";

import {
  Box,
  Chip,
  CircularProgress,
  Divider,
  Grid,
  Stack,
  Tab,
  Tabs,
  Typography,
  alpha,
  useTheme
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import { getIncidentById } from "../incident.api";
import type { IIncident } from "../incident.type";
import { formatIncidentDateTime } from "../incident.utils";

import IncidentDocumentsPanel from "./IncidentDocumentsPanel";
import IncidentModalBody from "./IncidentModalBody";
import IncidentPostmortemPanel from "./IncidentPostmortemPanel";
import IncidentSeverityChip from "./IncidentSeverityChip";
import IncidentStatusChip from "./IncidentStatusChip";

type IncidentDetailsModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  incidentId: IIncident["id"];
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
        letterSpacing: "0.1em",
        lineHeight: 1.3
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
      sx={{
        color: "text.disabled",
        fontWeight: 500,
        fontSize: "0.7rem",
        letterSpacing: "0.04em"
      }}
    >
      {children}
    </Typography>
  );
}

function MetaItem({ label, value }: { label: string; value: string }) {
  const isEmpty = value === "—";

  return (
    <Stack spacing={0.35} sx={{ minWidth: 0 }}>
      <FieldLabel>{label}</FieldLabel>
      <Typography
        variant="body1"
        sx={{
          fontWeight: 600,
          fontSize: "0.9375rem",
          lineHeight: 1.35,
          wordBreak: "break-word",
          color: isEmpty ? "text.disabled" : "text.primary"
        }}
      >
        {value}
      </Typography>
    </Stack>
  );
}

function DetailChip({ label }: { label: string }) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  return (
    <Chip
      label={label}
      size="small"
      sx={{
        height: 28,
        fontWeight: 600,
        letterSpacing: "0.02em",
        backgroundColor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
        border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
        borderRadius: "10px",
        "& .MuiChip-label": {
          px: 1.25
        }
      }}
    />
  );
}

function ChipGroup({ label, items }: { label: string; items: string[] }) {
  return (
    <Stack spacing={0.75} sx={{ alignItems: "flex-start" }}>
      <FieldLabel>{label}</FieldLabel>
      {items.length === 0 ? (
        <Typography variant="body2" sx={{ color: "text.disabled", fontSize: "0.875rem" }}>
          —
        </Typography>
      ) : (
        <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
          {items.map((item) => (
            <DetailChip key={item} label={item} />
          ))}
        </Stack>
      )}
    </Stack>
  );
}

function CountTile({ label, value }: { label: string; value: number }) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  return (
    <Box
      sx={{
        px: 1.25,
        py: 1.25,
        borderRadius: 2,
        textAlign: "center",
        bgcolor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
        border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`
      }}
    >
      <Typography
        variant="h5"
        sx={{ fontWeight: 700, lineHeight: 1.2, letterSpacing: "-0.02em", color: "text.primary" }}
      >
        {value}
      </Typography>
      <Typography
        variant="caption"
        sx={{
          color: "text.disabled",
          fontWeight: 500,
          fontSize: "0.65rem",
          letterSpacing: "0.06em",
          textTransform: "uppercase"
        }}
      >
        {label}
      </Typography>
    </Box>
  );
}

export default function IncidentDetailsModal({
  open,
  onClose,
  incidentId
}: IncidentDetailsModalProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();
  const [tab, setTab] = useState(0);

  const {
    data: incident,
    isPending,
    isError,
    error
  } = useQuery({
    queryKey: ["incident", incidentId],
    queryFn: () => getIncidentById(incidentId),
    enabled: open && Boolean(incidentId)
  });

  const description = incident?.description?.trim();

  return (
    <ModalContainer
      title={incident?.title ?? "Incident Details"}
      open={open}
      onClose={onClose}
      maxWidth={760}
      paperSx={{
        ...getGlassCardSx(theme, isDark),
        maxHeight: "90vh",
        display: "flex",
        flexDirection: "column"
      }}
    >
      <IncidentModalBody>
        {isError ? (
          <Typography color="error" sx={{ py: 2 }}>
            {error instanceof Error ? error.message : "Failed to load incident."}
          </Typography>
        ) : isPending || !incident ? (
          <Stack sx={{ alignItems: "center", py: 6 }}>
            <CircularProgress size={32} />
          </Stack>
        ) : (
          <Box sx={{ minWidth: 0 }}>
            <Tabs
              value={tab}
              onChange={(_, value: number) => setTab(value)}
              sx={{ mb: 2, borderBottom: 1, borderColor: "divider" }}
            >
              <Tab label="Overview" sx={{ textTransform: "none", fontWeight: 600 }} />
              <Tab
                label={`Documents${incident.counts ? ` (${incident.counts.documents})` : ""}`}
                sx={{ textTransform: "none", fontWeight: 600 }}
              />
              <Tab
                label={`Postmortem${incident.postMortem ? ` · ${incident.postMortem.status}` : ""}`}
                sx={{ textTransform: "none", fontWeight: 600 }}
              />
            </Tabs>

            <Box sx={{ maxHeight: "min(65vh, 600px)", overflowY: "auto", minWidth: 0, pr: 0.5 }}>
              {tab === 0 && (
                <Stack
                  spacing={2}
                  divider={
                    <Divider
                      sx={{ borderColor: alpha(palette.primary.main, isDark ? 0.12 : 0.16) }}
                    />
                  }
                  sx={{ minWidth: 0 }}
                >
                  <Stack spacing={1.5}>
                    <GroupHeading>Details</GroupHeading>
                    <Grid container spacing={2}>
                      <Grid size={6}>
                        <Stack spacing={0.35} sx={{ minWidth: 0, alignItems: "flex-start" }}>
                          <FieldLabel>Severity</FieldLabel>
                          <IncidentSeverityChip severity={incident.severity} />
                        </Stack>
                      </Grid>
                      <Grid size={6}>
                        <Stack spacing={0.35} sx={{ minWidth: 0, alignItems: "flex-start" }}>
                          <FieldLabel>Status</FieldLabel>
                          <IncidentStatusChip status={incident.status} />
                        </Stack>
                      </Grid>
                      <Grid size={6}>
                        <MetaItem
                          label="Started At"
                          value={formatIncidentDateTime(incident.startedAt)}
                        />
                      </Grid>
                      <Grid size={6}>
                        <MetaItem
                          label="Detected At"
                          value={formatIncidentDateTime(incident.detectedAt)}
                        />
                      </Grid>
                      <Grid size={6}>
                        <MetaItem
                          label="Resolved At"
                          value={formatIncidentDateTime(incident.resolvedAt)}
                        />
                      </Grid>
                      <Grid size={6}>
                        <MetaItem
                          label="Created By"
                          value={incident.createdByUser?.name ?? "—"}
                        />
                      </Grid>
                      <Grid size={6}>
                        <MetaItem
                          label="Created At"
                          value={formatIncidentDateTime(incident.createdAt)}
                        />
                      </Grid>
                      <Grid size={6}>
                        <MetaItem label="Source" value={incident.source || "—"} />
                      </Grid>
                    </Grid>
                    <Stack spacing={0.35} sx={{ minWidth: 0 }}>
                      <FieldLabel>Description</FieldLabel>
                      <Box
                        sx={{
                          px: 1.75,
                          py: 1.5,
                          borderRadius: 2,
                          minWidth: 0,
                          maxWidth: "100%",
                          overflow: "hidden",
                          bgcolor: isDark
                            ? alpha(palette.primary.main, 0.08)
                            : alpha(palette.secondary.light, 0.55),
                          border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`
                        }}
                      >
                        <Typography
                          variant="body1"
                          sx={{
                            fontWeight: 400,
                            fontSize: "0.9375rem",
                            lineHeight: 1.5,
                            whiteSpace: "pre-wrap",
                            overflowWrap: "anywhere",
                            wordBreak: "break-word",
                            color: description ? "text.primary" : "text.disabled"
                          }}
                        >
                          {description || "—"}
                        </Typography>
                      </Box>
                    </Stack>
                  </Stack>

                  <Stack spacing={1.5}>
                    <GroupHeading>Related</GroupHeading>
                    <ChipGroup
                      label="Teams"
                      items={incident.teams?.map((team) => team.name) ?? []}
                    />
                    <ChipGroup
                      label="Alert Rules"
                      items={incident.alertRules?.map((rule) => rule.name) ?? []}
                    />
                    <ChipGroup label="Tags" items={incident.tags ?? []} />
                  </Stack>

                  {incident.postMortem && (
                    <Stack spacing={0.75} sx={{ alignItems: "flex-start" }}>
                      <GroupHeading>Postmortem</GroupHeading>
                      <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: "wrap" }}>
                        <DetailChip label={String(incident.postMortem.status)} />
                        {incident.postMortem.dueAt && (
                          <DetailChip
                            label={`Due ${formatIncidentDateTime(incident.postMortem.dueAt)}`}
                          />
                        )}
                        {incident.postMortem.publishedAt && (
                          <DetailChip
                            label={`Published ${formatIncidentDateTime(incident.postMortem.publishedAt)}`}
                          />
                        )}
                      </Stack>
                    </Stack>
                  )}

                  {incident.counts && (
                    <Stack spacing={0.75}>
                      <GroupHeading>Activity</GroupHeading>
                      <Grid container spacing={1}>
                        <Grid size={3}>
                          <CountTile label="Timeline" value={incident.counts.timelineEntries} />
                        </Grid>
                        <Grid size={3}>
                          <CountTile label="Documents" value={incident.counts.documents} />
                        </Grid>
                        <Grid size={3}>
                          <CountTile label="Actions" value={incident.counts.actionItems} />
                        </Grid>
                        <Grid size={3}>
                          <CountTile label="Open" value={incident.counts.openActionItems} />
                        </Grid>
                      </Grid>
                    </Stack>
                  )}

                  {incident.acknowledgements?.length > 0 && (
                    <Stack spacing={0.75} sx={{ alignItems: "flex-start" }}>
                      <GroupHeading>Acknowledgements</GroupHeading>
                      <Stack spacing={0.5}>
                        {incident.acknowledgements.map((acknowledgement, index) => (
                          <Typography
                            key={acknowledgement.id ?? index}
                            variant="body1"
                            sx={{ fontSize: "0.9375rem" }}
                          >
                            <Box component="span" sx={{ fontWeight: 600, color: "text.primary" }}>
                              {acknowledgement.user?.name ??
                                acknowledgement.userId ??
                                acknowledgement.acknowledgedBy ??
                                "Unknown"}
                            </Box>
                            {acknowledgement.acknowledgedAt ? (
                              <Box
                                component="span"
                                sx={{ color: "text.disabled", fontWeight: 400 }}
                              >
                                {` · ${formatIncidentDateTime(acknowledgement.acknowledgedAt)}`}
                              </Box>
                            ) : null}
                          </Typography>
                        ))}
                      </Stack>
                    </Stack>
                  )}
                </Stack>
              )}

              {tab === 1 && <IncidentDocumentsPanel incident={incident} />}
              {tab === 2 && <IncidentPostmortemPanel incident={incident} />}
            </Box>
          </Box>
        )}
      </IncidentModalBody>
    </ModalContainer>
  );
}
