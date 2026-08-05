"use client";
import { useSearchParams } from "next/navigation";
import { type ReactNode, useState, useEffect } from "react";

import {
  Autocomplete,
  Box,
  Chip,
  Grid,
  MenuItem,
  Stack,
  Switch,
  TextField,
  Typography,
  alpha,
  useTheme
} from "@mui/material";
import { useQueries } from "@tanstack/react-query";
import { HiOutlineFire, HiOutlineGlobeAlt, HiOutlineUser } from "react-icons/hi";
import { IoNotifications, IoNotificationsOff } from "react-icons/io5";

import { getAlertFilterEndpointList, getAlertRuleTags } from "@/api/alertRule";
import type { TableFilterComponentProps } from "@/components/Table/types";
import { ALERT_RULE_VARIANTS, type AlertRuleType } from "@/utils/alertRuleUtils";

type AlertRuleSilentStatus = "silent" | "not-silent" | "";
type AlertRuleScope = "organization" | "assigned";

interface IAlertRuleFilters {
  alertname?: string;
  status?: string;
  types?: Array<AlertRuleType>;
  endpointId?: string | string[];
  tags?: string | string[];
  silentStatus?: AlertRuleSilentStatus;
  scope?: AlertRuleScope;
}

const DEFAULT_FILTERS: IAlertRuleFilters = {
  scope: "assigned"
};

export default function AlertRuleFilter({ onChange }: TableFilterComponentProps) {
  const { palette } = useTheme();
  const searchParams = useSearchParams();

  const [silentStatus, setSilentStatus] = useState<AlertRuleSilentStatus>("");
  const [filter, setFilter] = useState<IAlertRuleFilters>(DEFAULT_FILTERS);

  const showAllAlerts = filter.scope === "organization";
  const onlyFiredAlerts = filter.status === "critical";

  const [{ data: tagsList }, { data: endpointList }] = useQueries({
    queries: [
      {
        queryKey: ["all-alert-rule-tags"],
        queryFn: () => getAlertRuleTags()
      },
      {
        queryKey: ["alert-rule-filter-endpoint-list"],
        queryFn: () => getAlertFilterEndpointList()
      }
    ]
  });

  useEffect(() => {
    const filterParam = searchParams.get("filters");
    if (filterParam) {
      try {
        const parsedFilters = JSON.parse(decodeURIComponent(filterParam)) as IAlertRuleFilters;
        setFilter({
          ...parsedFilters,
          scope: parsedFilters.scope === "organization" ? "organization" : "assigned"
        });

        if (parsedFilters.silentStatus) {
          setSilentStatus(parsedFilters.silentStatus);
        } else {
          setSilentStatus("");
        }
      } catch (error) {
        console.error("Error parsing filters from URL:", error);
      }
    } else {
      setFilter(DEFAULT_FILTERS);
      setSilentStatus("");
    }
  }, [searchParams]);

  function handleChange(
    key: keyof IAlertRuleFilters,
    event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement> | string | string[]
  ) {
    let value: string | string[];

    if (typeof event === "string" || Array.isArray(event)) {
      value = event;
    } else {
      value = event.target.value;
    }

    const scope = filter.scope ?? "assigned";
    onChange(key, value);
    onChange("scope", scope);
    setFilter((prev) => ({ ...prev, [key]: value, scope }));
  }

  function handleSilentFilter(event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) {
    const value = event.target.value as AlertRuleSilentStatus;
    const scope = filter.scope ?? "assigned";
    onChange("silentStatus", value);
    onChange("scope", scope);
    setSilentStatus(value);
    setFilter((prev) => ({ ...prev, silentStatus: value, scope }));
  }

  function handleShowAllAlertsChange(checked: boolean) {
    const scope: AlertRuleScope = checked ? "organization" : "assigned";
    onChange("scope", scope);
    setFilter((prev) => ({ ...prev, scope }));
  }

  function handleFiredAlertsChange(checked: boolean) {
    const status = checked ? "critical" : "";
    const scope = filter.scope ?? "assigned";
    onChange("status", status);
    onChange("scope", scope);
    setFilter((prev) => ({ ...prev, status, scope }));
  }

  function renderAlertRuleList() {
    return Object.entries(ALERT_RULE_VARIANTS).map(([key, value]) => (
      <MenuItem key={key} value={key}>
        <Stack
          direction="row"
          spacing={1}
          sx={{
            alignItems: "center"
          }}
        >
          <value.Icon size={value.defaultSize} color={value.defaultColor} />
          <Typography component="span">{value.label}</Typography>
        </Stack>
      </MenuItem>
    ));
  }

  function renderEndpointsChip(selectedEndpointIds: unknown): ReactNode {
    const selectedEndpoints = filter.types?.filter((item) =>
      (selectedEndpointIds as string[]).includes(item)
    );
    if (selectedEndpoints && selectedEndpoints.length > 0) {
      return (
        <Box sx={{ display: "flex", flexWrap: "wrap", gap: 0.5 }}>
          {selectedEndpoints.map((value, index) => (
            <Chip size="small" key={index} label={value} sx={{ textTransform: "capitalize" }} />
          ))}
        </Box>
      );
    }
    return <></>;
  }

  function getSelectedEndpoints() {
    if (!filter.endpointId || !endpointList) return [];
    const endpointIds = Array.isArray(filter.endpointId) ? filter.endpointId : [filter.endpointId];
    return endpointList.filter((endpoint) => endpointIds.includes(endpoint.id));
  }

  return (
    <Grid container spacing={1}>
      <Grid size={3}>
        <TextField
          size="small"
          label="Name"
          value={filter.alertname || ""}
          variant="filled"
          onChange={(event) => handleChange("alertname", event)}
        />
      </Grid>
      <Grid size={3}>
        <TextField
          label="Type Of Data Source"
          variant="filled"
          select
          value={filter.types || []}
          slotProps={{ select: { multiple: true, renderValue: renderEndpointsChip } }}
          size="small"
          onChange={(event) => handleChange("types", event)}
        >
          {renderAlertRuleList()}
        </TextField>
      </Grid>
      <Grid size={3}>
        <Autocomplete
          multiple
          id="endpoints-filter"
          size="small"
          options={endpointList || []}
          value={getSelectedEndpoints()}
          getOptionLabel={(option) => option.name}
          onChange={(_, value) =>
            handleChange(
              "endpointId",
              value.map((item) => item.id)
            )
          }
          renderInput={(params) => (
            <TextField
              {...params}
              slotProps={{
                ...params.slotProps,
                input: params.slotProps.input,
                inputLabel: params.slotProps.inputLabel,
                htmlInput: params.slotProps.htmlInput
              }}
              variant="filled"
              label="Endpoints"
            />
          )}
        />
      </Grid>
      <Grid size={3}>
        <TextField
          label="Silent Status"
          variant="filled"
          select
          size="small"
          value={silentStatus}
          onChange={handleSilentFilter}
        >
          <MenuItem value="">
            <Stack
              direction="row"
              spacing={1}
              sx={{
                alignItems: "center"
              }}
            >
              <Typography component="span">All</Typography>
            </Stack>
          </MenuItem>
          <MenuItem value="silent">
            <Stack
              direction="row"
              spacing={1}
              sx={{
                alignItems: "center"
              }}
            >
              <IoNotificationsOff color={palette.warning.main} size="1.4rem" />
              <Typography component="span">Silent</Typography>
            </Stack>
          </MenuItem>
          <MenuItem value="unsilent">
            <Stack
              direction="row"
              spacing={1}
              sx={{
                alignItems: "center"
              }}
            >
              <IoNotifications color={palette.warning.main} size="1.4rem" />
              <Typography component="span">Not Silent</Typography>
            </Stack>
          </MenuItem>
        </TextField>
      </Grid>
      <Grid size={6}>
        <Autocomplete
          multiple
          id="alert-tags-filter"
          size="small"
          options={tagsList || []}
          value={Array.isArray(filter.tags) ? filter.tags : filter.tags ? [filter.tags] : []}
          freeSolo
          onChange={(_, value) => handleChange("tags", value)}
          renderValue={(value: readonly string[], getItemProps) =>
            value.map((option: string, index: number) => {
              const { key, ...itemProps } = getItemProps({ index });
              return <Chip key={key} variant="filled" label={option} {...itemProps} />;
            })
          }
          renderInput={(params) => (
            <TextField
              {...params}
              slotProps={{
                ...params.slotProps,
                input: params.slotProps.input,
                inputLabel: params.slotProps.inputLabel,
                htmlInput: params.slotProps.htmlInput
              }}
              variant="filled"
              label="Tags"
            />
          )}
        />
      </Grid>
      <Grid size={3}>
        <Box
          onClick={() => handleShowAllAlertsChange(!showAllAlerts)}
          sx={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: 1,
            height: 1,
            minHeight: 48,
            px: 1.5,
            borderRadius: 2,
            cursor: "pointer",
            bgcolor: showAllAlerts
              ? alpha(palette.primary.main, 0.1)
              : alpha(palette.secondary.main, 0.06),
            border: 1,
            borderColor: showAllAlerts ? alpha(palette.primary.main, 0.35) : palette.divider,
            transition: "background-color 0.2s ease, border-color 0.2s ease"
          }}
        >
          <Stack direction="row" spacing={1} sx={{ alignItems: "center", minWidth: 0 }}>
            <Box
              sx={{
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                width: 28,
                height: 28,
                borderRadius: 1.5,
                flexShrink: 0,
                bgcolor: showAllAlerts
                  ? alpha(palette.primary.main, 0.16)
                  : alpha(palette.secondary.main, 0.12),
                color: showAllAlerts ? palette.primary.main : palette.text.secondary
              }}
            >
              {showAllAlerts ? <HiOutlineGlobeAlt size="1rem" /> : <HiOutlineUser size="1rem" />}
            </Box>
            <Stack spacing={0} sx={{ minWidth: 0 }}>
              <Typography variant="body2" sx={{ fontWeight: 600, lineHeight: 1.2 }}>
                Show All Alerts
              </Typography>
              <Typography
                variant="caption"
                sx={{ color: "text.secondary", lineHeight: 1.2 }}
                noWrap
              >
                {showAllAlerts ? "Organization-wide" : "Assigned to you"}
              </Typography>
            </Stack>
          </Stack>
          <Switch
            size="small"
            checked={showAllAlerts}
            onChange={(_, checked) => handleShowAllAlertsChange(checked)}
            onClick={(event) => event.stopPropagation()}
            slotProps={{ input: { "aria-label": "Show all alerts" } }}
          />
        </Box>
      </Grid>
      <Grid size={3}>
        <Box
          onClick={() => handleFiredAlertsChange(!onlyFiredAlerts)}
          sx={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: 1,
            height: 1,
            minHeight: 48,
            px: 1.5,
            borderRadius: 2,
            cursor: "pointer",
            bgcolor: onlyFiredAlerts
              ? alpha(palette.error.main, 0.1)
              : alpha(palette.secondary.main, 0.06),
            border: 1,
            borderColor: onlyFiredAlerts ? alpha(palette.error.main, 0.35) : palette.divider,
            transition: "background-color 0.2s ease, border-color 0.2s ease"
          }}
        >
          <Stack direction="row" spacing={1} sx={{ alignItems: "center", minWidth: 0 }}>
            <Box
              sx={{
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                width: 28,
                height: 28,
                borderRadius: 1.5,
                flexShrink: 0,
                bgcolor: onlyFiredAlerts
                  ? alpha(palette.error.main, 0.16)
                  : alpha(palette.secondary.main, 0.12),
                color: onlyFiredAlerts ? palette.error.main : palette.text.secondary
              }}
            >
              <HiOutlineFire size="1rem" />
            </Box>
            <Stack spacing={0} sx={{ minWidth: 0 }}>
              <Typography variant="body2" sx={{ fontWeight: 600, lineHeight: 1.2 }}>
                Fired Alerts
              </Typography>
              <Typography
                variant="caption"
                sx={{ color: "text.secondary", lineHeight: 1.2 }}
                noWrap
              >
                {onlyFiredAlerts ? "Critical only" : "All statuses"}
              </Typography>
            </Stack>
          </Stack>
          <Switch
            size="small"
            checked={onlyFiredAlerts}
            onChange={(_, checked) => handleFiredAlertsChange(checked)}
            onClick={(event) => event.stopPropagation()}
            color="error"
            slotProps={{ input: { "aria-label": "Only show fired alerts" } }}
          />
        </Box>
      </Grid>
    </Grid>
  );
}
