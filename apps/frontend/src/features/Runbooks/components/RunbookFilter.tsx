"use client";

import { useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";

import { Autocomplete, Grid, MenuItem, TextField } from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import { getAllTeams } from "@/api/team";
import type { TableFilterComponentProps } from "@/components/Table/types";

import { RUNBOOK_STATUSES, type IRunbookFilters, type RunbookStatus } from "../runbook.type";
import { RUNBOOK_STATUS_LABELS } from "../runbook.utils";

const DEFAULT_FILTERS: IRunbookFilters = {
  status: "",
  teamId: "",
  tag: ""
};

export default function RunbookFilter({ onChange }: TableFilterComponentProps) {
  const searchParams = useSearchParams();
  const [filter, setFilter] = useState<IRunbookFilters>(DEFAULT_FILTERS);

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
      const parsedFilters = JSON.parse(decodeURIComponent(filterParam)) as IRunbookFilters;
      setFilter({
        status: parsedFilters.status ?? "",
        teamId: parsedFilters.teamId ?? "",
        tag: parsedFilters.tag ?? ""
      });
    } catch (error) {
      console.error("Error parsing filters from URL:", error);
      setFilter(DEFAULT_FILTERS);
    }
  }, [searchParams]);

  function handleChange(key: keyof IRunbookFilters, value: string) {
    onChange(key, value);
    setFilter((prev) => ({ ...prev, [key]: value }));
  }

  const selectedTeam = teams?.find((team) => team.id === filter.teamId) ?? null;

  return (
    <Grid container spacing={1}>
      <Grid size={4}>
        <TextField
          select
          size="small"
          label="Status"
          variant="filled"
          value={filter.status || ""}
          onChange={(event) => handleChange("status", event.target.value)}
        >
          <MenuItem value="">All</MenuItem>
          {RUNBOOK_STATUSES.map((status) => (
            <MenuItem key={status} value={status}>
              {RUNBOOK_STATUS_LABELS[status as RunbookStatus]}
            </MenuItem>
          ))}
        </TextField>
      </Grid>
      <Grid size={4}>
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
      <Grid size={4}>
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
