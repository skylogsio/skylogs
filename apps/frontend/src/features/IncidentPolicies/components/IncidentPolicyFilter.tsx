"use client";

import { useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";

import { Autocomplete, Grid, MenuItem, TextField } from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import { getAllTeams } from "@/api/team";
import type { TableFilterComponentProps } from "@/components/Table/types";

import type { IIncidentPolicyFilters } from "../incident-policy.type";

const DEFAULT_FILTERS: IIncidentPolicyFilters = {
  enabled: "",
  teamId: ""
};

export default function IncidentPolicyFilter({ onChange }: TableFilterComponentProps) {
  const searchParams = useSearchParams();
  const [filter, setFilter] = useState<IIncidentPolicyFilters>(DEFAULT_FILTERS);

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
      const parsed = JSON.parse(decodeURIComponent(filterParam)) as IIncidentPolicyFilters;
      setFilter({
        enabled: parsed.enabled ?? "",
        teamId: parsed.teamId ?? ""
      });
    } catch (error) {
      console.error("Error parsing filters from URL:", error);
      setFilter(DEFAULT_FILTERS);
    }
  }, [searchParams]);

  function handleChange(key: keyof IIncidentPolicyFilters, value: string) {
    onChange(key, value);
    setFilter((prev) => ({ ...prev, [key]: value }));
  }

  const selectedTeam = teams?.find((team) => team.id === filter.teamId) ?? null;

  return (
    <Grid container spacing={1}>
      <Grid size={6}>
        <TextField
          select
          size="small"
          label="Enabled"
          variant="filled"
          value={filter.enabled || ""}
          onChange={(event) => handleChange("enabled", event.target.value)}
        >
          <MenuItem value="">All</MenuItem>
          <MenuItem value="true">Enabled</MenuItem>
          <MenuItem value="false">Disabled</MenuItem>
        </TextField>
      </Grid>
      <Grid size={6}>
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
    </Grid>
  );
}
