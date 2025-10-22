import { type ReactNode } from "react";

import { Chip, MenuItem, Stack, TextField } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import {
  Controller,
  type Path,
  type PathValue,
  type UseFormReturn,
  type FormState
} from "react-hook-form";

import { getAlertRuleCreateData } from "@/api/alertRule";

type MustHaveFields = {
  endpointIds: string[];
  userIds: string[];
  description: string;
};

type AlertRuleEndpointUserSelectorProps<T extends MustHaveFields> = {
  methods: Pick<UseFormReturn<T>, "control" | "setValue" | "getValues">;
  errors: FormState<T>["errors"];
  children?: ReactNode;
};

export default function AlertRuleGeneralFields<T extends MustHaveFields>({
  methods,
  errors,
  children
}: AlertRuleEndpointUserSelectorProps<T>) {
  const { control, setValue, getValues } = methods;

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

  const handleRemoveUserChip = (userId: string) => {
    const selected = getValues("userIds" as Path<T>) as string[];
    setValue("userIds" as Path<T>, selected.filter((id) => id !== userId) as PathValue<T, Path<T>>);
  };

  const renderUserChips = (selectedIds: unknown) => {
    const selected =
      data?.users.filter((user) => (selectedIds as string[]).includes(user.id)) ?? [];
    return (
      <Stack
        gap={1}
        direction="row"
        flexWrap="wrap"
        justifyContent="flex-start"
        sx={{ float: "left" }}
        onMouseDown={(event) => event.stopPropagation()}
      >
        {selected.map((user) => (
          <Chip
            key={user.id}
            label={user.name}
            size="small"
            onDelete={() => handleRemoveUserChip(user.id)}
          />
        ))}
      </Stack>
    );
  };

  return (
    <>
      <Stack direction="row" spacing={2} width="100%">
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
        <Controller
          control={control}
          name={"userIds" as Path<T>}
          render={({ field }) => (
            <TextField
              {...field}
              select
              label="Users"
              variant="filled"
              error={!!errors.userIds}
              helperText={errors.userIds?.message as string}
              value={field.value ?? []}
              slotProps={{
                select: {
                  multiple: true,
                  renderValue: renderUserChips
                }
              }}
            >
              {data?.users.map((user) => (
                <MenuItem key={user.id} value={user.id}>
                  {user.name}
                </MenuItem>
              ))}
            </TextField>
          )}
        />
      </Stack>
      {children}
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
          ></TextField>
        )}
      />
    </>
  );
}
