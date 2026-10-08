"use client";

import { Chip, Stack, Typography, useTheme } from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import type { IAlertRule } from "@/@types/alertRule";
import { getAlertRuleAccessList, getAlertRuleEndpointsList } from "@/api/alertRule";
import { getAlertRuleDataSourcesByAlertType } from "@/api/alertRule";
import { useZone } from "@/context/ZoneContext";
import type { DataSourceType } from "@/utils/dataSourceUtils";

import { DETAIL_FIELD_LABELS, getDetailFieldKeys } from "./alertDetailConfig";
import { DetailFieldValue } from "./DetailFieldValue";

const EMPTY_VALUE = "—";

interface AlertRuleDetailsTabProps {
  alert: IAlertRule;
}

export default function AlertRuleDetailsTab({ alert }: AlertRuleDetailsTabProps) {
  const { palette } = useTheme();
  const { selectedZone } = useZone();

  const { data: accessList } = useQuery({
    queryKey: ["alert-rule-access", alert.id],
    queryFn: () => getAlertRuleAccessList(alert.id),
    enabled: !alert.userIds || alert.userIds.length === 0
  });

  const { data: endpointsList } = useQuery({
    queryKey: ["alert-rule-endpoint-list", alert.id],
    queryFn: () => getAlertRuleEndpointsList(alert.id),
    enabled: !alert.endpointIds || alert.endpointIds.length === 0
  });

  const hasDataSources = !["api", "notification", "health"].includes(alert.type);
  const needsDataSourceNames =
    hasDataSources &&
    (!alert.dataSourceLabels || alert.dataSourceLabels.length === 0) &&
    ((alert.dataSourceIds?.length ?? 0) > 0 || Boolean(alert.dataSourceId));

  const { data: dataSourceOptions } = useQuery({
    queryKey: ["alert-rule-data-source", alert.type],
    queryFn: () => getAlertRuleDataSourcesByAlertType(alert.type as DataSourceType),
    enabled: needsDataSourceNames
  });

  function resolveNames(fieldKey: string, ids: string[]): string[] {
    if (fieldKey === "endpointIds") {
      const known = endpointsList?.alertEndpoints ?? [];
      return ids.map((id) => known.find((endpoint) => endpoint.id === id)?.name ?? id);
    }
    if (fieldKey === "userIds") {
      const known = accessList?.alertUsers ?? [];
      return ids.map((id) => known.find((user) => user.id === id)?.name ?? id);
    }
    if (fieldKey === "teamIds") {
      const known = accessList?.alertTeams ?? [];
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

  const fieldKeys = getDetailFieldKeys(alert.type);

  return (
    <Stack spacing={3}>
      <Stack
        direction="row"
        spacing={2}
        sx={{ width: "100%", alignItems: "flex-start", flexWrap: "wrap", rowGap: 2 }}
      >
        <Stack direction="row" spacing={1} sx={{ width: "50%", alignItems: "center" }}>
          <Typography variant="subtitle1" sx={{ fontWeight: "bold" }}>
            Owner:
          </Typography>
          <Typography>{alert.ownerName || EMPTY_VALUE}</Typography>
        </Stack>
        <Stack direction="row" spacing={1} sx={{ width: "50%", alignItems: "center" }}>
          <Typography variant="subtitle1" sx={{ fontWeight: "bold" }}>
            Zone:
          </Typography>
          <Typography>{selectedZone || EMPTY_VALUE}</Typography>
        </Stack>
        <Stack direction="row" spacing={1} sx={{ width: "100%", alignItems: "center" }}>
          <Typography variant="subtitle1" sx={{ fontWeight: "bold" }}>
            Tags:
          </Typography>
          <Stack direction="row" sx={{ padding: 1, flexWrap: "wrap", gap: 1 }}>
            {alert.tags && alert.tags.length > 0 ? (
              alert.tags.map((tag, index) => (
                <Chip key={index} variant="filled" label={tag} size="small" />
              ))
            ) : (
              <Typography>{EMPTY_VALUE}</Typography>
            )}
          </Stack>
        </Stack>
      </Stack>
      <Stack direction="row" sx={{ width: "100%", flexWrap: "wrap", rowGap: 2, columnGap: 0 }}>
        {fieldKeys.map((fieldKey) => (
          <Stack
            key={fieldKey}
            direction="row"
            spacing={1}
            sx={{
              width: { xs: "100%", md: "50%" },
              alignItems: "flex-start",
              paddingRight: { xs: 0, md: 2 }
            }}
          >
            <Typography
              variant="subtitle1"
              sx={{
                fontWeight: "bold",
                minWidth: 140,
                color: palette.text.primary
              }}
            >
              {DETAIL_FIELD_LABELS[fieldKey] ?? fieldKey}:
            </Typography>
            <DetailFieldValue
              fieldKey={fieldKey}
              alert={alert}
              resolveNames={resolveNames}
              formatValue={formatValue}
            />
          </Stack>
        ))}
      </Stack>
    </Stack>
  );
}
