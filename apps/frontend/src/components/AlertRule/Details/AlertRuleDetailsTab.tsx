"use client";

import { type ReactNode, useMemo } from "react";

import { Box, Card, Chip, Grid, Stack, Typography, useTheme } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { format } from "date-fns";
import {
  HiCalendar,
  HiClock,
  HiDatabase,
  HiDocumentText,
  HiGlobeAlt,
  HiInformationCircle,
  HiLockClosed,
  HiTag,
  HiUser,
  HiUserGroup
} from "react-icons/hi";
import { IoCheckmarkCircle, IoNotifications } from "react-icons/io5";

import type { IAlertRule } from "@/@types/alertRule";
import {
  getAlertRuleAccessList,
  getAlertRuleDataSourcesByAlertType,
  getAlertRuleEndpointsList
} from "@/api/alertRule";
import type { DataSourceType } from "@/features/DataSource/dataSource.utils";

import { DETAIL_FIELD_LABELS, getDetailSections } from "./alertDetailConfig";
import {
  DetailBooleanBadge,
  DetailEmpty,
  DetailFieldValue,
  DetailValueChips,
  EMPTY_VALUE,
  QueryCodeBlock
} from "./DetailFieldValue";

interface AlertRuleDetailsTabProps {
  alert: IAlertRule;
  onAfterAction?: () => void;
}

interface DetailSectionCardProps {
  title: string;
  icon: ReactNode;
  badge?: ReactNode;
  subtitle?: string;
  children: ReactNode;
}

function DetailSectionCard({ title, icon, badge, subtitle, children }: DetailSectionCardProps) {
  return (
    <Card
      elevation={0}
      sx={{
        width: "100%",
        borderRadius: 3,
        border: "1px solid",
        borderColor: "color-mix(in srgb, var(--mui-palette-divider) 80%, transparent)",
        bgcolor: "background.paper",
        overflow: "hidden",
        transition: "border-color 0.2s ease, box-shadow 0.2s ease",
        "&:hover": {
          borderColor: "color-mix(in srgb, var(--mui-palette-primary-main) 30%, transparent)"
        }
      }}
    >
      <Stack
        direction="row"
        sx={{
          justifyContent: "space-between",
          alignItems: "center",
          px: 2.5,
          py: 1.75,
          borderBottom: "1px solid",
          borderColor: "color-mix(in srgb, var(--mui-palette-divider) 60%, transparent)",
          bgcolor: (theme) =>
            theme.palette.mode === "dark"
              ? "rgba(255, 255, 255, 0.02)"
              : "color-mix(in srgb, var(--mui-palette-primary-main) 3%, #FAF8F5)"
        }}
      >
        <Stack direction="row" spacing={1.25} sx={{ alignItems: "center", minWidth: 0 }}>
          <Box
            sx={{
              width: 32,
              height: 32,
              borderRadius: 2,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              bgcolor: "color-mix(in srgb, var(--mui-palette-primary-main) 12%, transparent)",
              color: "primary.main",
              fontSize: "1.1rem",
              flexShrink: 0
            }}
          >
            {icon}
          </Box>
          <Box sx={{ minWidth: 0 }}>
            <Typography
              variant="subtitle1"
              sx={{
                fontWeight: 700,
                fontSize: "0.9375rem",
                color: "text.primary",
                lineHeight: 1.2
              }}
            >
              {title}
            </Typography>
            {subtitle && (
              <Typography
                variant="caption"
                sx={{ color: "text.secondary", fontSize: "0.75rem", display: "block" }}
              >
                {subtitle}
              </Typography>
            )}
          </Box>
        </Stack>
        {badge}
      </Stack>
      <Box sx={{ p: 2.5 }}>{children}</Box>
    </Card>
  );
}

function FieldTile({
  label,
  children,
  icon
}: {
  label: string;
  children: ReactNode;
  icon?: ReactNode;
}) {
  return (
    <Stack spacing={0.65} sx={{ minWidth: 0, width: "100%", alignItems: "flex-start" }}>
      <Stack direction="row" spacing={0.6} sx={{ alignItems: "center" }}>
        {icon && (
          <Box
            sx={{
              color: "text.disabled",
              display: "inline-flex",
              alignItems: "center",
              fontSize: "0.85rem"
            }}
          >
            {icon}
          </Box>
        )}
        <Typography
          variant="caption"
          sx={{
            color: "text.secondary",
            fontWeight: 600,
            fontSize: "0.75rem",
            letterSpacing: "0.02em"
          }}
        >
          {label}
        </Typography>
      </Stack>
      <Box sx={{ width: "100%", minWidth: 0 }}>{children}</Box>
    </Stack>
  );
}

function formatDateSafe(val: unknown, pattern: string): string | null {
  if (!val) return null;
  const d = val instanceof Date ? val : new Date(String(val));
  return Number.isNaN(d.getTime()) ? null : format(d, pattern);
}

export default function AlertRuleDetailsTab({ alert }: AlertRuleDetailsTabProps) {
  const theme = useTheme();

  const { data: endpointsList, isLoading: isLoadingEndpoints } = useQuery({
    queryKey: ["alert-rule-endpoint-list", alert.id],
    queryFn: () => getAlertRuleEndpointsList(alert.id)
  });

  const { data: accessList, isLoading: isLoadingAccess } = useQuery({
    queryKey: ["alert-rule-access", alert.id],
    queryFn: () => getAlertRuleAccessList(alert.id)
  });

  const hasDataSources = !["api", "notification", "health"].includes(alert.type);
  const needsDataSourceNames =
    hasDataSources &&
    (!alert.dataSourceLabels || alert.dataSourceLabels.length === 0) &&
    ((alert.dataSourceIds?.length ?? 0) > 0 || Boolean(alert.dataSourceId));

  const { data: dataSourceOptions, isLoading: isLoadingDataSources } = useQuery({
    queryKey: ["alert-rule-data-source", alert.type],
    queryFn: () => getAlertRuleDataSourcesByAlertType(alert.type as DataSourceType),
    enabled: needsDataSourceNames
  });

  const isLoadingNames = isLoadingEndpoints || isLoadingAccess || isLoadingDataSources;

  function resolveNames(fieldKey: string, ids: string[]): string[] {
    if (fieldKey === "endpointIds") {
      const known = [
        ...(endpointsList?.alertEndpoints ?? []),
        ...(endpointsList?.selectableEndpoints ?? [])
      ];
      return ids.map((id) => known.find((endpoint) => endpoint.id === id)?.name ?? id);
    }
    if (fieldKey === "userIds") {
      const known = [...(accessList?.alertUsers ?? []), ...(accessList?.selectableUsers ?? [])];
      return ids.map((id) => known.find((user) => user.id === id)?.name ?? id);
    }
    if (fieldKey === "teamIds") {
      const known = [...(accessList?.alertTeams ?? []), ...(accessList?.selectableTeams ?? [])];
      return ids.map((id) => known.find((team) => team.id === id)?.name ?? id);
    }
    if (fieldKey === "dataSourceIds") {
      if (alert.dataSourceLabels && alert.dataSourceLabels.length > 0) {
        return alert.dataSourceLabels;
      }
      const known = dataSourceOptions ?? [];
      return ids.map((id) => known.find((option) => option.id === id)?.name ?? id);
    }
    if (fieldKey === "dataSourceId") {
      const known = dataSourceOptions ?? [];
      return ids.map((id) => known.find((option) => option.id === id)?.name ?? id);
    }
    return ids;
  }

  const isNotification = alert.type === "notification";
  const ackUser = alert.acknowledgedByName || alert.acknowledgedBy;
  const isAcked = Boolean(ackUser);
  const formattedAckAt = formatDateSafe(alert.acknowledgedAt, "EEEE, yyyy/MM/dd HH:mm");
  const formattedCreatedAt = formatDateSafe(alert.created_at, "yyyy/MM/dd HH:mm");
  const formattedUpdatedAt = formatDateSafe(alert.updated_at, "yyyy/MM/dd HH:mm");

  const recipientCounts = useMemo(() => {
    const endpoints = alert.endpointIds?.length ?? 0;
    const users = alert.userIds?.length ?? 0;
    const teams = alert.teamIds?.length ?? 0;
    return {
      endpoints,
      users,
      teams,
      total: endpoints + users + teams
    };
  }, [alert.endpointIds, alert.userIds, alert.teamIds]);

  // Keys already featured in dedicated hero/overview cards
  const EXCLUDED_FROM_SECTIONS = new Set([
    "name",
    "type",
    "description",
    "isPrivate",
    "showAcknowledgeBtn",
    "endpointIds",
    "userIds",
    "teamIds",
    "tags"
  ]);

  const allSections = getDetailSections(alert.type);
  const remainingSections = allSections
    .map((s) => ({
      title: s.title,
      keys: s.keys.filter((k) => !EXCLUDED_FROM_SECTIONS.has(k))
    }))
    .filter((s) => s.keys.length > 0);

  return (
    <Stack spacing={2.5} sx={{ minWidth: 0, width: "100%" }}>
      {/* 1. Acknowledged Highlight Banner */}
      {!isNotification && isAcked && (
        <Card
          elevation={0}
          sx={{
            borderRadius: "14px",
            border: "1px solid",
            borderColor: "color-mix(in srgb, #3B82F6 26%, transparent)",
            bgcolor: (t) =>
              t.palette.mode === "dark"
                ? "color-mix(in srgb, #3B82F6 12%, var(--mui-palette-background-paper))"
                : "color-mix(in srgb, #3B82F6 8%, #FAF8F5)",
            p: 2.25
          }}
        >
          <Stack
            direction={{ xs: "column", sm: "row" }}
            spacing={2}
            sx={{
              justifyContent: "space-between",
              alignItems: { xs: "flex-start", sm: "center" }
            }}
          >
            <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
              <Box
                sx={{
                  width: 36,
                  height: 36,
                  borderRadius: "10px",
                  bgcolor: "color-mix(in srgb, #3B82F6 18%, transparent)",
                  color: "#3B82F6",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  fontSize: "1.3rem",
                  flexShrink: 0
                }}
              >
                <IoCheckmarkCircle />
              </Box>
              <Box>
                <Typography
                  variant="subtitle2"
                  sx={{
                    fontWeight: 700,
                    fontSize: "0.9375rem",
                    color: (t) => (t.palette.mode === "dark" ? "#60A5FA" : "#1D4ED8"),
                    lineHeight: 1.3
                  }}
                >
                  Alert Rule Acknowledged
                </Typography>
                <Typography
                  variant="body2"
                  sx={{ color: "text.primary", fontWeight: 500, fontSize: "0.85rem" }}
                >
                  Acknowledged by{" "}
                  <Box
                    component="span"
                    sx={{
                      fontWeight: 700,
                      color: (t) => (t.palette.mode === "dark" ? "#93C5FD" : "#1E40AF")
                    }}
                  >
                    {ackUser}
                  </Box>
                </Typography>
              </Box>
            </Stack>

            {formattedAckAt && (
              <Stack
                direction="row"
                spacing={0.75}
                sx={{
                  alignItems: "center",
                  bgcolor: "color-mix(in srgb, #3B82F6 12%, transparent)",
                  px: 1.5,
                  py: 0.6,
                  borderRadius: "8px",
                  border: "1px solid color-mix(in srgb, #3B82F6 20%, transparent)"
                }}
              >
                <HiClock color="#3B82F6" size="0.95rem" />
                <Typography
                  variant="caption"
                  sx={{
                    fontWeight: 600,
                    fontSize: "0.78125rem",
                    color: "text.primary"
                  }}
                >
                  {formattedAckAt}
                </Typography>
              </Stack>
            )}
          </Stack>
        </Card>
      )}

      {/* 2. Overview Card */}
      <DetailSectionCard
        title="Overview & Metadata"
        icon={<HiInformationCircle />}
        badge={
          <Stack direction="row" spacing={0.75} sx={{ alignItems: "center" }}>
            <Chip
              label={alert.isPrivate ? "Private Rule" : "Shared Rule"}
              size="small"
              icon={
                alert.isPrivate ? (
                  <HiLockClosed style={{ fontSize: "0.8rem" }} />
                ) : (
                  <HiGlobeAlt style={{ fontSize: "0.8rem" }} />
                )
              }
              sx={{
                height: 24,
                borderRadius: "7px",
                fontWeight: 600,
                fontSize: "0.75rem",
                bgcolor: alert.isPrivate
                  ? "color-mix(in srgb, var(--mui-palette-warning-main) 12%, transparent)"
                  : "color-mix(in srgb, var(--mui-palette-success-main) 12%, transparent)",
                color: alert.isPrivate ? "warning.main" : "success.main",
                border: "1px solid",
                borderColor: alert.isPrivate
                  ? "color-mix(in srgb, var(--mui-palette-warning-main) 25%, transparent)"
                  : "color-mix(in srgb, var(--mui-palette-success-main) 25%, transparent)",
                "& .MuiChip-icon": {
                  color: "inherit"
                }
              }}
            />
            {alert.accessLevel && (
              <Chip
                label={alert.accessLevel}
                size="small"
                sx={{
                  height: 24,
                  borderRadius: "7px",
                  fontWeight: 600,
                  fontSize: "0.75rem",
                  textTransform: "capitalize",
                  bgcolor: "color-mix(in srgb, var(--mui-palette-primary-main) 12%, transparent)",
                  color: "primary.main",
                  border:
                    "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 25%, transparent)"
                }}
              />
            )}
          </Stack>
        }
      >
        <Grid container spacing={2.5}>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <FieldTile label="Owner" icon={<HiUser />}>
              <Typography
                variant="body2"
                sx={{
                  fontWeight: 700,
                  fontSize: "0.9375rem",
                  color: alert.ownerName ? "text.primary" : "text.disabled"
                }}
              >
                {alert.ownerName || EMPTY_VALUE}
              </Typography>
            </FieldTile>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <FieldTile label="Created At" icon={<HiCalendar />}>
              <Typography variant="body2" sx={{ fontWeight: 600, fontSize: "0.875rem" }}>
                {formattedCreatedAt || EMPTY_VALUE}
              </Typography>
            </FieldTile>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <FieldTile label="Last Updated" icon={<HiClock />}>
              <Typography variant="body2" sx={{ fontWeight: 600, fontSize: "0.875rem" }}>
                {formattedUpdatedAt || EMPTY_VALUE}
              </Typography>
            </FieldTile>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <FieldTile label="Telegram Acknowledge Button">
              <DetailBooleanBadge
                value={Boolean(alert.showAcknowledgeBtn)}
                trueLabel="Active in Telegram"
                falseLabel="Disabled"
              />
            </FieldTile>
          </Grid>

          {alert.enableAutoResolve !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
              <FieldTile label="Auto Resolve">
                <DetailBooleanBadge
                  value={Boolean(alert.enableAutoResolve)}
                  trueLabel={
                    alert.autoResolveMinutes ? `After ${alert.autoResolveMinutes}m` : "Enabled"
                  }
                  falseLabel="Disabled"
                />
              </FieldTile>
            </Grid>
          )}

          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <FieldTile label="Tags" icon={<HiTag />}>
              {alert.tags && alert.tags.length > 0 ? (
                <DetailValueChips values={alert.tags} />
              ) : (
                <DetailEmpty text="No tags assigned" />
              )}
            </FieldTile>
          </Grid>
        </Grid>
      </DetailSectionCard>

      {/* 3. Description Card (if provided) */}
      {alert.description && alert.description.trim() && (
        <DetailSectionCard title="Description & Notes" icon={<HiDocumentText />}>
          <Box
            sx={{
              pl: 2,
              borderLeft: "3px solid",
              borderColor: "primary.main",
              bgcolor: (t) =>
                t.palette.mode === "dark"
                  ? "rgba(255, 255, 255, 0.02)"
                  : "color-mix(in srgb, var(--mui-palette-primary-main) 4%, transparent)",
              py: 1.5,
              pr: 2,
              borderRadius: "0 10px 10px 0"
            }}
          >
            <Typography
              variant="body2"
              sx={{
                color: "text.primary",
                fontSize: "0.875rem",
                lineHeight: 1.65,
                whiteSpace: "pre-wrap"
              }}
            >
              {alert.description}
            </Typography>
          </Box>
        </DetailSectionCard>
      )}

      {/* 4. Notification Targets Card */}
      <DetailSectionCard
        title="Notification Targets & Access"
        icon={<IoNotifications />}
        subtitle="Endpoints, teams, and users that receive alerts"
        badge={
          <Chip
            label={`${recipientCounts.total} configured`}
            size="small"
            sx={{
              height: 24,
              borderRadius: "7px",
              fontWeight: 600,
              fontSize: "0.75rem",
              bgcolor: "color-mix(in srgb, var(--mui-palette-primary-main) 12%, transparent)",
              color: "primary.main"
            }}
          />
        }
      >
        <Grid container spacing={3}>
          <Grid size={{ xs: 12, md: 4 }}>
            <FieldTile
              label={`Notification Endpoints (${recipientCounts.endpoints})`}
              icon={<IoNotifications />}
            >
              <DetailFieldValue
                fieldKey="endpointIds"
                alert={alert}
                resolveNames={resolveNames}
                isLoadingNames={isLoadingNames}
              />
            </FieldTile>
          </Grid>

          <Grid size={{ xs: 12, md: 4 }}>
            <FieldTile label={`Assigned Users (${recipientCounts.users})`} icon={<HiUser />}>
              <DetailFieldValue
                fieldKey="userIds"
                alert={alert}
                resolveNames={resolveNames}
                isLoadingNames={isLoadingNames}
              />
            </FieldTile>
          </Grid>

          <Grid size={{ xs: 12, md: 4 }}>
            <FieldTile label={`Assigned Teams (${recipientCounts.teams})`} icon={<HiUserGroup />}>
              <DetailFieldValue
                fieldKey="teamIds"
                alert={alert}
                resolveNames={resolveNames}
                isLoadingNames={isLoadingNames}
              />
            </FieldTile>
          </Grid>
        </Grid>
      </DetailSectionCard>

      {/* 5. Remaining Specialized Sections (Source & Conditions, etc.) */}
      {remainingSections.map((section) => {
        const isSourceSection = section.title.toLowerCase().includes("source");
        const icon = isSourceSection ? <HiDatabase /> : <HiInformationCircle />;

        const hasQueryString = section.keys.includes("queryString") && alert.queryString;
        const nonQueryKeys = section.keys.filter((k) => k !== "queryString");

        return (
          <DetailSectionCard key={section.title} title={section.title} icon={icon}>
            <Stack spacing={2.5}>
              {hasQueryString && (
                <FieldTile label="Query Expression">
                  <QueryCodeBlock query={String(alert.queryString)} />
                </FieldTile>
              )}

              {nonQueryKeys.length > 0 && (
                <Grid container spacing={2.5}>
                  {nonQueryKeys.map((fieldKey) => {
                    const isWide =
                      fieldKey === "extraField" ||
                      fieldKey === "actions" ||
                      fieldKey === "hosts" ||
                      fieldKey === "severities";

                    return (
                      <Grid
                        key={fieldKey}
                        size={{ xs: 12, sm: isWide ? 12 : 6, md: isWide ? 12 : 4 }}
                      >
                        <FieldTile label={DETAIL_FIELD_LABELS[fieldKey] ?? fieldKey}>
                          <DetailFieldValue
                            fieldKey={fieldKey}
                            alert={alert}
                            resolveNames={resolveNames}
                            isLoadingNames={isLoadingNames}
                          />
                        </FieldTile>
                      </Grid>
                    );
                  })}
                </Grid>
              )}
            </Stack>
          </DetailSectionCard>
        );
      })}
    </Stack>
  );
}
