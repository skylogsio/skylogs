"use client";

import type { ReactNode } from "react";

import { Chip, Divider, Grid, Stack, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import type { IAlertRule } from "@/@types/alertRule";
import { getAlertRuleAccessList, getAlertRuleEndpointsList } from "@/api/alertRule";
import { getAlertRuleDataSourcesByAlertType } from "@/api/alertRule";
import type { DataSourceType } from "@/features/DataSource/dataSource.utils";

import { DETAIL_FIELD_LABELS, getDetailSections } from "./alertDetailConfig";
import { DetailEmpty, DetailFieldValue } from "./DetailFieldValue";

const EMPTY_VALUE = "—";

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

interface AlertRuleDetailsTabProps {
  alert: IAlertRule;
}

export default function AlertRuleDetailsTab({ alert }: AlertRuleDetailsTabProps) {

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

  function formatValue(fieldKey: string, value: unknown): string {
    if (value === null || value === undefined || value === "") {
      return EMPTY_VALUE;
    }
    if (typeof value === "boolean") {
      return value ? "Yes" : "No";
    }
    return String(value);
  }

  function renderField(fieldKey: string, wide: boolean) {
    return (
      <Grid key={fieldKey} size={wide ? 12 : 6}>
        <Stack spacing={0.35} sx={{ minWidth: 0, alignItems: "flex-start" }}>
          <FieldLabel>{DETAIL_FIELD_LABELS[fieldKey] ?? fieldKey}</FieldLabel>
          <DetailFieldValue
            fieldKey={fieldKey}
            alert={alert}
            resolveNames={resolveNames}
            formatValue={formatValue}
            isLoadingNames={isLoadingNames}
          />
        </Stack>
      </Grid>
    );
  }

  function isWideField(fieldKey: string): boolean {
    return (
      fieldKey === "description" ||
      fieldKey === "queryString" ||
      fieldKey === "extraField" ||
      fieldKey === "showAcknowledgeBtn"
    );
  }

  const sections = getDetailSections(alert.type);

  return (
    <Stack
      spacing={2}
      divider={
        <Divider
          sx={{
            borderColor:
              "color-mix(in srgb, var(--mui-palette-primary-main) 14%, transparent)"
          }}
        />
      }
      sx={{ minWidth: 0 }}
    >
      <Stack spacing={1.5}>
        <GroupHeading>Overview</GroupHeading>
        <Grid container spacing={2}>
          <Grid size={6}>
            <Stack spacing={0.35} sx={{ minWidth: 0, alignItems: "flex-start" }}>
              <FieldLabel>Owner</FieldLabel>
              <Typography
                variant="body1"
                sx={{
                  fontWeight: 600,
                  fontSize: "0.9375rem",
                  lineHeight: 1.35,
                  wordBreak: "break-word",
                  color: alert.ownerName ? "text.primary" : "text.disabled"
                }}
              >
                {alert.ownerName || EMPTY_VALUE}
              </Typography>
            </Stack>
          </Grid>
          <Grid size={6}>
            <Stack spacing={0.75} sx={{ minWidth: 0, alignItems: "flex-start" }}>
              <FieldLabel>Tags</FieldLabel>
              {alert.tags && alert.tags.length > 0 ? (
                <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
                  {alert.tags.map((tag, index) => (
                    <Chip key={index} variant="filled" label={tag} size="small" />
                  ))}
                </Stack>
              ) : (
                <DetailEmpty />
              )}
            </Stack>
          </Grid>
        </Grid>
      </Stack>
      {sections.map((section) => (
        <Stack key={section.title} spacing={1.5}>
          <GroupHeading>{section.title}</GroupHeading>
          <Grid container spacing={2}>
            {section.keys.map((fieldKey) => renderField(fieldKey, isWideField(fieldKey)))}
          </Grid>
        </Stack>
      ))}
    </Stack>
  );
}
