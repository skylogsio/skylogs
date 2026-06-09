import { type ReactNode } from "react";

import {
  Autocomplete,
  Chip,
  FormControlLabel,
  Grid,
  Stack,
  TextField,
  Checkbox,
  Tooltip,
  Box,
  Typography
} from "@mui/material";
import { useQueries } from "@tanstack/react-query";
import {
  Controller,
  type Path,
  type PathValue,
  type UseFormReturn,
  type FormState
} from "react-hook-form";
import { MdInfoOutline } from "react-icons/md";

import { getAlertRuleCreateData, getAlertRuleTags } from "@/api/alertRule";
import AccessUsersAndTeams from "@/components/AccessUsersAndTeams";

type MustHaveFields = {
  endpointIds: string[];
  userIds: string[];
  teamIds: string[];
  tags: string[];
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
  const { control, setValue, watch } = methods;

  const [{ data }, { data: tagsList }] = useQueries({
    queries: [
      {
        queryKey: ["alert-rule-create-data"],
        queryFn: () => getAlertRuleCreateData()
      },
      {
        queryKey: ["all-alert-rule-tags"],
        queryFn: () => getAlertRuleTags()
      }
    ]
  });

  const endpoints = data?.endpoints ?? [];

  return (
    <>
      <Stack
        direction="row"
        spacing={2}
        sx={{
          width: "100%"
        }}
      >
        <Box
          sx={{
            width: "50%"
          }}
        >
          <Controller
            control={control}
            name={"endpointIds" as Path<T>}
            render={({ field }) => {
              const selectedEndpoints = endpoints.filter((ep) =>
                (field.value as string[])?.includes(ep.id)
              );

              return (
                <Autocomplete
                  multiple
                  options={endpoints}
                  getOptionLabel={(option) => option.name}
                  value={selectedEndpoints}
                  onChange={(_, newValue) => {
                    field.onChange(newValue.map((ep) => ep.id) as PathValue<T, Path<T>>);
                  }}
                  isOptionEqualToValue={(option, value) => option.id === value.id}
                  renderValue={(value, getItemProps) =>
                    value.map((option, index) => {
                      const { key, ...itemProps } = getItemProps({ index });
                      return <Chip key={key} label={option.name} size="small" {...itemProps} />;
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
                      label="Endpoints"
                      error={!!errors.endpointIds}
                      helperText={errors.endpointIds?.message as string}
                    />
                  )}
                />
              );
            }}
          />
        </Box>
        <Box
          sx={{
            width: "50%"
          }}
        >
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
      <Grid size={12}>
        <Autocomplete
          multiple
          id="alert-rule-tags"
          options={tagsList ?? []}
          freeSolo
          value={(watch("tags" as Path<T>) as string[]) ?? []}
          onChange={(_, value) => setValue("tags" as Path<T>, value as PathValue<T, Path<T>>)}
          renderValue={(value: readonly string[], getItemProps) =>
            value.map((option: string, index: number) => {
              const { key, ...itemProps } = getItemProps({ index });
              return <Chip key={key} variant="filled" label={option} size="small" {...itemProps} />;
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
      <Controller
        control={control}
        name={"showAcknowledgeBtn" as Path<T>}
        render={({ field }) => (
          <Stack
            direction="row"
            spacing={1}
            sx={{
              alignItems: "center",
              width: "100%"
            }}
          >
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
            value={field.value ?? ""}
            multiline
            minRows={3}
            maxRows={8}
          />
        )}
      />
    </>
  );
}
