import React, { useState } from "react";

import {
  alpha,
  Autocomplete,
  Button,
  Stack,
  TextField,
  IconButton,
  useTheme,
  ListSubheader,
  Box,
  Typography,
  Chip
} from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { FaUser, FaUsers } from "react-icons/fa";
import { HiOutlinePlusSm, HiTrash } from "react-icons/hi";
import { toast } from "react-toastify";

import type { IAccessOption, IAlertRule } from "@/@types/alertRule";
import type { ITeam } from "@/@types/team";
import type { IUser } from "@/@types/user";
import {
  addAccessToAlertRule,
  getAlertRuleAccessList,
  removeAccessFromAlertRule
} from "@/api/alertRule";
import EmptyList from "@/components/EmptyList";
import DataTable from "@/components/Table/DataTable";

interface AlertRuleAccessManagerProps {
  alertId: IAlertRule["id"];
  onClose?: () => void;
}

export default function AlertRuleAccessManager({ alertId }: AlertRuleAccessManagerProps) {
  const { palette } = useTheme();
  const [selectedUserIds, setSelectedUserIds] = useState<Array<IUser["id"]>>([]);
  const [selectedTeamIds, setSelectedTeamIds] = useState<Array<ITeam["id"]>>([]);

  const {
    data: AccessList,
    isPending: isPendingAccessList,
    refetch
  } = useQuery({
    queryKey: ["alert-rule-access", alertId],
    queryFn: () => getAlertRuleAccessList(alertId)
  });

  const { mutate: addAccess, isPending: isAddingAccess } = useMutation({
    mutationFn: (body: { userIds: Array<IUser["id"]>; teamIds: Array<ITeam["id"]> }) =>
      addAccessToAlertRule(alertId, body),
    onSuccess: (data) => {
      if (data.status) {
        setSelectedUserIds([]);
        setSelectedTeamIds([]);
        toast.success("Access successfully added!");
        refetch();
      }
    }
  });

  const { mutate: removeAccess, isPending: isRemovingAccess } = useMutation({
    mutationFn: (accessId: IUser["id"] | ITeam["id"]) =>
      removeAccessFromAlertRule(alertId, accessId),
    onSuccess: (data) => {
      if (data.status) {
        refetch();
      }
    }
  });

  function handleAddAccess() {
    if (selectedTeamIds.length > 0 || selectedUserIds.length > 0) {
      addAccess({ userIds: selectedUserIds, teamIds: selectedTeamIds });
    } else {
      toast.error("Select at least one Team or User.");
    }
  }

  const accessOptions: IAccessOption[] = React.useMemo(
    () => [
      ...(AccessList?.selectableTeams?.map((team) => ({
        type: "team" as const,
        id: team.id,
        label: team.name
      })) || []),
      ...(AccessList?.selectableUsers?.map((user) => ({
        type: "user" as const,
        id: user.id,
        label: user.name
      })) || [])
    ],
    [AccessList]
  );

  const selectedValues: IAccessOption[] = React.useMemo(() => {
    return [
      ...selectedTeamIds.map((id) => ({
        type: "team" as const,
        id,
        label: AccessList?.selectableTeams?.find((t) => t.id === id)?.name || id
      })),
      ...selectedUserIds.map((id) => ({
        type: "user" as const,
        id,
        label: AccessList?.selectableUsers?.find((u) => u.id === id)?.name || id
      }))
    ];
  }, [selectedTeamIds, selectedUserIds, AccessList]);

  const handleChange = (_: React.SyntheticEvent, newValue: IAccessOption[]) => {
    setSelectedTeamIds(newValue.filter((item) => item.type === "team").map((item) => item.id));
    setSelectedUserIds(newValue.filter((item) => item.type === "user").map((item) => item.id));
  };

  const tableData = [
    ...(AccessList?.alertTeams ?? []).map((item) => ({ ...item, type: "Team" })),
    ...(AccessList?.alertUsers ?? []).map((item) => ({ ...item, type: "User" }))
  ];

  return (
    <Stack spacing={2} marginTop={1}>
      <Stack direction="row" spacing={1} alignItems="center">
        <Autocomplete
          multiple
          options={accessOptions}
          loading={isPendingAccessList}
          groupBy={(option) => (option.type === "team" ? "Teams" : "Users")}
          getOptionLabel={(option) => option.label}
          value={selectedValues}
          onChange={handleChange}
          isOptionEqualToValue={(option, value) =>
            option.id === value.id && option.type === value.type
          }
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
                        option.type === "team" ? palette.primary.main : palette.success.main
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
              label="Access Control (Teams & Users)"
              fullWidth
            />
          )}
          sx={{
            width: "100%"
          }}
        />
        <Button
          onClick={() => handleAddAccess()}
          disabled={isAddingAccess}
          variant="contained"
          color="primary"
          size="large"
          startIcon={<HiOutlinePlusSm size="1.3rem" />}
        >
          Add
        </Button>
      </Stack>
      {tableData.length > 0 ? (
        <DataTable
          data={tableData}
          columns={[
            { header: "Row", size: 50, accessorFn: (_, index) => ++index },
            { header: "Name", accessorKey: "name" },
            {
              header: "Type",
              size: 100,
              cell: ({ row }) => (
                <Chip
                  label={row.original.type}
                  // size="small"
                  color={row.original.type === "Team" ? "primary" : "success"}
                  icon={row.original.type === "Team" ? <FaUsers size={16} /> : <FaUser size={12} />}
                  variant="outlined"
                  sx={{
                    border: "none",
                    paddingLeft: 1,
                    paddingY: 2,
                    backgroundColor: ({ palette }) =>
                      alpha(
                        row.original.type === "Team" ? palette.primary.main : palette.success.main,
                        0.1
                      )
                  }}
                />
              )
            },
            {
              header: "Actions",
              cell: ({ row }) => (
                <IconButton
                  disabled={isRemovingAccess}
                  onClick={() => removeAccess(row.original.id)}
                  sx={({ palette }) => ({
                    color: palette.error.light,
                    backgroundColor: alpha(palette.error.light, 0.05)
                  })}
                >
                  <HiTrash size="1.4rem" />
                </IconButton>
              )
            }
          ]}
        />
      ) : (
        <EmptyList
          minimal
          icon={<FaUsers size="2rem" color={palette.common.white} />}
          title="No Teams Or Users Assigned"
          description="This alert rule doesn't have any teams or users assigned yet. Use the form above to add users or teams who should receive notifications for this alert."
          gradientColors={[palette.primary.dark, palette.primary.light]}
        />
      )}
    </Stack>
  );
}
