"use client";

import { useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";

import { Autocomplete, Grid, MenuItem, TextField } from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import { getAllTeams } from "@/api/team";
import type { TableFilterComponentProps } from "@/components/Table/types";

import {
  INCIDENT_SEVERITIES,
  INCIDENT_STATUSES,
  type IIncidentFilters,
  type IncidentStatus
} from "../incident.type";
import { INCIDENT_STATUS_LABELS } from "../incident.utils";

const DEFAULT_FILTERS: IIncidentFilters = {
  status: "",
  severity: "",
  teamId: "",
  tag: ""
};

export default function IncidentFilter({ onChange }: TableFilterComponentProps) {
  const searchParams = useSearchParams();
  const [filter, setFilter] = useState<IIncidentFilters>(DEFAULT_FILTERS);

  const { data: teams } = useQuery({
    queryKey: ["all-teams"],
    queryFn: () => getAllTeams()
  });

  useEffect(() => {
    const filterParam = searchParams.get("filters");
    if (!filterParam) {
      setFilter(DEFAULT_FILTERS);
      return;
    }

    try {
      const parsedFilters = JSON.parse(decodeURIComponent(filterParam)) as IIncidentFilters;
      setFilter({
        status: parsedFilters.status ?? "",
        severity: parsedFilters.severity ?? "",
        teamId: parsedFilters.teamId ?? "",
        tag: parsedFilters.tag ?? ""
      });
    } catch (error) {
      console.error("Error parsing filters from URL:", error);
      setFilter(DEFAULT_FILTERS);
    }
  }, [searchParams]);

  function handleChange(key: keyof IIncidentFilters, value: string) {
    onChange(key, value);
    setFilter((prev) => ({ ...prev, [key]: value }));
  }

  const selectedTeam = teams?.find((team) => team.id === filter.teamId) ?? null;

  return (
    <Grid container spacing={1}>
      <Grid size={3}>
        <TextField
          select
          size="small"
          label="Status"
          variant="filled"
          value={filter.status || ""}
          onChange={(event) => handleChange("status", event.target.value)}
        >
          <MenuItem value="">All</MenuItem>
          {INCIDENT_STATUSES.map((status) => (
            <MenuItem key={status} value={status}>
              {INCIDENT_STATUS_LABELS[status as IncidentStatus]}
            </MenuItem>
          ))}
        </TextField>
      </Grid>
      <Grid size={3}>
        <TextField
          select
          size="small"
          label="Severity"
          variant="filled"
          value={filter.severity || ""}
          onChange={(event) => handleChange("severity", event.target.value)}
        >
          <MenuItem value="">All</MenuItem>
          {INCIDENT_SEVERITIES.map((severity) => (
            <MenuItem key={severity} value={severity}>
              {severity}
            </MenuItem>
          ))}
        </TextField>
      </Grid>
      <Grid size={3}>
        <Autocomplete
          size="small"
          options={teams ?? []}
          value={selectedTeam}
          getOptionLabel={(option) => option.name}
          isOptionEqualToValue={(option, value) => option.id === value.id}
          onChange={(_, value) => handleChange("teamId", value?.id ?? "")}
          renderInput={(params) => <TextField {...params} variant="filled" label="Team" />}
        />
      </Grid>
      <Grid size={3}>
        <TextField
          size="small"
          label="Tag"
          variant="filled"
          value={filter.tag || ""}
          onChange={(event) => handleChange("tag", event.target.value)}
        />
      </Grid>
    </Grid>
  );
}
