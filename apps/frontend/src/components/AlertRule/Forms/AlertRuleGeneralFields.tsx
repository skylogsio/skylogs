import { type ReactNode } from "react";

import {
  Chip,
  FormControlLabel,
  MenuItem,
  Stack,
  TextField,
  Checkbox,
  Tooltip,
  Box,
  Typography
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import {
  Controller,
  type Path,
  type PathValue,
  type UseFormReturn,
  type FormState
} from "react-hook-form";
import { MdInfoOutline } from "react-icons/md";

import { getAlertRuleCreateData } from "@/api/alertRule";
import AccessUsersAndTeams from "@/components/AccessUsersAndTeams";

type MustHaveFields = {
  endpointIds: string[];
  userIds: string[];
  teamIds: string[];
  description: string;
  showAcknowledgeBtn?: boolean;
};

type AlertRuleEndpointUserSelectorProps<T extends MustHaveFields> = {
  methods: Pick<UseFormReturn<T>, "control" | "watch" | "setValue" | "getValues">;
  errors: FormState<T>["errors"];
  children?: ReactNode;
};

export default function AlertRuleGeneralFields<T extends MustHaveFields>({
  methods,
  errors,
  children
}: AlertRuleEndpointUserSelectorProps<T>) {
  const { control, setValue, getValues, watch } = methods;

  const { data } = useQuery({
    queryKey: ["alert-rule-create-data"],
    queryFn: () => getAlertRuleCreateData()
  });

  const handleRemoveEndpointChip = (endpointId: string) => {
    const selected = getValues("endpointIds" as Path<T>) as string[];
    setValue(
      "endpointIds" as Path<T>,
      selected.filter((id) => id !== endpointId) as PathValue<T, Path<T>>
    );
  };

  const renderEndpointChips = (selectedIds: unknown) => {
    const selected =
      data?.endpoints.filter((endpoint) => (selectedIds as string[]).includes(endpoint.id)) ?? [];
    return (
      <Stack
        gap={1}
        direction="row"
        flexWrap="wrap"
        justifyContent="flex-start"
        sx={{ float: "left" }}
        onMouseDown={(event) => event.stopPropagation()}
      >
        {selected.map((endpoint) => (
          <Chip
            key={endpoint.id}
            label={endpoint.name}
            size="small"
            onDelete={() => handleRemoveEndpointChip(endpoint.id)}
          />
        ))}
      </Stack>
    );
  };

  return (
    <>
      <Stack direction="row" spacing={2} width="100%">
        <Box width="50%">
          <Controller
            control={control}
            name={"endpointIds" as Path<T>}
            render={({ field }) => (
              <TextField
                {...field}
                select
                label="Endpoints"
                variant="filled"
                error={!!errors.endpointIds}
                helperText={errors.endpointIds?.message as string}
                value={field.value ?? []}
                slotProps={{
                  select: {
                    multiple: true,
                    renderValue: renderEndpointChips
                  }
                }}
              >
                {data?.endpoints.map((endpoint) => (
                  <MenuItem key={endpoint.id} value={endpoint.id}>
                    {endpoint.name}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />
        </Box>
        <Box width="50%">
          <AccessUsersAndTeams
            selectedTeamIds={watch("teamIds" as Path<T>) as string[]}
            selectedUserIds={watch("userIds" as Path<T>) as string[]}
            onTeamIdsChange={(teamIds) =>
              setValue("teamIds" as Path<T>, teamIds as PathValue<T, Path<T>>)
            }
            onUserIdsChange={(userIds) =>
              setValue("userIds" as Path<T>, userIds as PathValue<T, Path<T>>)
            }
          />
        </Box>
      </Stack>
      {children}
      <Controller
        control={control}
        name={"showAcknowledgeBtn" as Path<T>}
        render={({ field }) => (
          <Stack direction="row" alignItems="center" spacing={1} width="100%">
            <FormControlLabel
              control={
                <Checkbox
                  checked={Boolean(field.value ?? false)}
                  onChange={(e) => field.onChange(e.target.checked)}
                />
              }
              label="Show Acknowledge Button in Telegram"
              sx={{ margin: 0 }}
            />
            <Tooltip
              title={
                <Typography variant="caption">
                  When enabled, Telegram alert messages will include an Acknowledge button that
                  allows users to mark the alert as seen and handled directly from Telegram.
                </Typography>
              }
              arrow
              placement="top"
            >
              <Box sx={{ color: ({ palette }) => palette.primary.light, cursor: "pointer" }}>
                <MdInfoOutline size={20} />
              </Box>
            </Tooltip>
          </Stack>
        )}
      />
      <Controller
        control={control}
        name={"description" as Path<T>}
        render={({ field }) => (
          <TextField
            {...field}
            label="Description"
            variant="filled"
            error={!!errors.description}
            helperText={errors.description?.message as string}
            value={field.value ?? []}
            multiline
            minRows={3}
            maxRows={8}
          />
        )}
      />
    </>
  );
}
