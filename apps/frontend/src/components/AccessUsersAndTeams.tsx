import React from "react";

import {
  Autocomplete,
  Box,
  Chip,
  ListSubheader,
  Stack,
  TextField,
  Typography
} from "@mui/material";
import { useQueries } from "@tanstack/react-query";
import { FaUsers, FaUser } from "react-icons/fa";

import { getAllTeams } from "@/api/team";
import { getAllUsers } from "@/api/user";

interface AccessOption {
  type: "team" | "user";
  id: string;
  label: string;
}

interface AccessUsersAndTeamsProps {
  selectedTeamIds: string[];
  selectedUserIds: string[];
  onTeamIdsChange: (teamIds: string[]) => void;
  onUserIdsChange: (userIds: string[]) => void;
  label?: string;
  error?: boolean;
  helperText?: string;
  disabled?: boolean;
}

export default function AccessUsersAndTeams({
  selectedTeamIds,
  selectedUserIds,
  onTeamIdsChange,
  onUserIdsChange,
  label = "Access Control (Teams & Users)",
  error = false,
  helperText,
  disabled = false
}: AccessUsersAndTeamsProps) {
  const [
    { data: teamsData, isLoading: isLoadingTeams },
    { data: usersData, isLoading: isLoadingUsers }
  ] = useQueries({
    queries: [
      {
        queryKey: ["all-teams"],
        queryFn: () => getAllTeams()
      },
      {
        queryKey: ["all-users"],
        queryFn: () => getAllUsers()
      }
    ]
  });

  const accessOptions: AccessOption[] = React.useMemo(
    () => [
      ...(teamsData?.map((team) => ({
        type: "team" as const,
        id: team.id,
        label: team.name
      })) || []),
      ...(usersData?.map((user) => ({
        type: "user" as const,
        id: user.id,
        label: user.name
      })) || [])
    ],
    [teamsData, usersData]
  );

  const selectedValues: AccessOption[] = React.useMemo(() => {
    return [
      ...selectedTeamIds.map((id) => ({
        type: "team" as const,
        id,
        label: teamsData?.find((t) => t.id === id)?.name || id
      })),
      ...selectedUserIds.map((id) => ({
        type: "user" as const,
        id,
        label: usersData?.find((u) => u.id === id)?.name || id
      }))
    ];
  }, [selectedTeamIds, selectedUserIds, teamsData, usersData]);

  const handleChange = (_: React.SyntheticEvent, newValue: AccessOption[]) => {
    const teamIds = newValue.filter((item) => item.type === "team").map((item) => item.id);
    const userIds = newValue.filter((item) => item.type === "user").map((item) => item.id);

    onTeamIdsChange(teamIds);
    onUserIdsChange(userIds);
  };

  const isLoading = isLoadingTeams || isLoadingUsers;

  return (
    <Autocomplete
      multiple
      options={accessOptions}
      loading={isLoading}
      disabled={disabled}
      groupBy={(option) => (option.type === "team" ? "Teams" : "Users")}
      getOptionLabel={(option) => option.label}
      value={selectedValues}
      onChange={handleChange}
      isOptionEqualToValue={(option, value) => option.id === value.id && option.type === value.type}
      renderGroup={(params) => (
        <li key={params.key}>
          <ListSubheader
            component="div"
            sx={{
              backgroundColor: "grey.100",
              fontWeight: "bold",
              display: "flex",
              alignItems: "center",
              gap: 1
            }}
          >
            {params.group === "Teams" ? (
              <>
                <FaUsers size={16} /> Teams
              </>
            ) : (
              <>
                <FaUser size={16} /> Users
              </>
            )}
          </ListSubheader>
          <ul style={{ padding: 0 }}>{params.children}</ul>
        </li>
      )}
      renderOption={(props, option) => {
        const { key, ...otherProps } = props;
        return (
          <Box component="li" key={key} {...otherProps}>
            <Stack direction="row" alignItems="center" spacing={1}>
              <Typography>{option.label}</Typography>
            </Stack>
          </Box>
        );
      }}
      renderTags={(value, getTagProps) =>
        value.map((option, index) => {
          const { key, ...tagProps } = getTagProps({ index });
          return (
            <Chip
              key={key}
              label={option.label}
              size="small"
              icon={option.type === "team" ? <FaUsers size={12} /> : <FaUser size={12} />}
              sx={{
                "& .MuiChip-icon": {
                  color: ({ palette }) =>
                    option.type === "team" ? palette.primary.main : palette.success.dark
                }
              }}
              {...tagProps}
            />
          );
        })
      }
      renderInput={(params) => (
        <TextField
          {...params}
          slotProps={{
            input: params.InputProps,
            inputLabel: params.InputLabelProps,
            htmlInput: params.inputProps
          }}
          variant="filled"
          label={label}
          error={error}
          helperText={helperText}
        />
      )}
    />
  );
}
