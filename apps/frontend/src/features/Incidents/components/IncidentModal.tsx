"use client";

import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Autocomplete,
  Chip,
  CircularProgress,
  Grid,
  MenuItem,
  Stack,
  TextField,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Controller, useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { CreateUpdateModal } from "@/@types/global";
import { getAllTeams } from "@/api/team";
import DateTimeInput from "@/components/DateTimeInput";
import GradientSubmitButton from "@/components/GradientSubmitButton";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { getAllAlertRules } from "@/features/Debugging/debugging.api";
import { useCurrentTheme } from "@/hooks";

import { createIncident, getIncidentById, updateIncident } from "../incident.api";
import {
  INCIDENT_SEVERITIES,
  type IIncident,
  type IIncidentCreateRequest,
  type IIncidentUpdateRequest
} from "../incident.type";

import IncidentModalBody from "./IncidentModalBody";

const incidentSchema = z.object({
  title: z.string().trim().min(1, "This field is Required."),
  description: z.string(),
  severity: z.enum(INCIDENT_SEVERITIES, "This field is Required."),
  teamIds: z.array(z.string()).min(1, "At least one team is required."),
  tags: z.array(z.string()),
  startedAt: z.string().nullable(),
  detectedAt: z.string().nullable(),
  resolvedAt: z.string().nullable(),
  alertRuleIds: z.array(z.string())
});

type IncidentFormType = z.infer<typeof incidentSchema>;

type IncidentModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IIncident>;
  onSubmit: () => void;
};

const emptyFormValues: IncidentFormType = {
  title: "",
  description: "",
  severity: "SEV2",
  teamIds: [],
  tags: [],
  startedAt: null,
  detectedAt: null,
  resolvedAt: null,
  alertRuleIds: []
};

function getFormValues(incident?: IIncident): IncidentFormType {
  if (!incident) {
    return emptyFormValues;
  }

  return {
    title: incident.title,
    description: incident.description ?? "",
    severity: incident.severity,
    teamIds: incident.teamIds ?? [],
    tags: incident.tags ?? [],
    startedAt: incident.startedAt ?? null,
    detectedAt: incident.detectedAt ?? null,
    resolvedAt: incident.resolvedAt ?? null,
    alertRuleIds: incident.alertRuleIds ?? []
  };
}

function toCreatePayload(values: IncidentFormType): IIncidentCreateRequest {
  const body: IIncidentCreateRequest = {
    title: values.title,
    severity: values.severity,
    teamIds: values.teamIds
  };

  if (values.description.trim()) body.description = values.description.trim();
  if (values.tags.length > 0) body.tags = values.tags;
  if (values.startedAt) body.startedAt = values.startedAt;
  if (values.detectedAt) body.detectedAt = values.detectedAt;
  if (values.resolvedAt) body.resolvedAt = values.resolvedAt;
  if (values.alertRuleIds.length > 0) body.alertRuleIds = values.alertRuleIds;

  return body;
}

function toUpdatePayload(values: IncidentFormType): IIncidentUpdateRequest {
  return {
    title: values.title,
    description: values.description,
    severity: values.severity,
    teamIds: values.teamIds,
    tags: values.tags,
    startedAt: values.startedAt ?? new Date().toISOString(),
    detectedAt: values.detectedAt ?? new Date().toISOString(),
    alertRuleIds: values.alertRuleIds
  };
}

export default function IncidentModal({ open, onClose, data, onSubmit }: IncidentModalProps) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();
  const isCreate = data === "NEW";
  const incidentId = data && data !== "NEW" ? data.id : null;

  const {
    register,
    handleSubmit,
    reset,
    control,
    formState: { errors }
  } = useForm<IncidentFormType>({
    resolver: zodResolver(incidentSchema),
    defaultValues: emptyFormValues,
    mode: "onSubmit"
  });

  const { data: teams } = useQuery({
    queryKey: ["all-teams"],
    queryFn: () => getAllTeams()
  });

  const { data: alertRules } = useQuery({
    queryKey: ["all-alert-rules"],
    queryFn: () => getAllAlertRules()
  });

  const {
    data: incident,
    isError: isIncidentError,
    error: incidentError
  } = useQuery({
    queryKey: ["incident", incidentId],
    queryFn: () => getIncidentById(incidentId!),
    enabled: open && Boolean(incidentId)
  });

  const { mutate: createIncidentMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: IIncidentCreateRequest) => createIncident(body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Incident Created Successfully.");
        void queryClient.invalidateQueries({ queryKey: ["incident"] });
        onSubmit();
        onClose?.();
        return;
      }
      toast.error(response.message);
    }
  });

  const { mutate: updateIncidentMutation, isPending: isUpdating } = useMutation({
    mutationFn: (body: IIncidentUpdateRequest) => updateIncident(incidentId!, body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Incident Updated Successfully.");
        void queryClient.invalidateQueries({ queryKey: ["incident"] });
        onSubmit();
        onClose?.();
        return;
      }
      toast.error(response.message);
    }
  });

  useEffect(() => {
    if (!open) return;
    if (isCreate) {
      reset(emptyFormValues);
      return;
    }
    if (incident) {
      reset(getFormValues(incident));
    }
  }, [open, isCreate, incident, reset]);

  function handleSubmitForm(values: IncidentFormType) {
    if (isCreate) {
      createIncidentMutation(toCreatePayload(values));
      return;
    }
    updateIncidentMutation(toUpdatePayload(values));
  }

  const isPending = isCreating || isUpdating;

  return (
    <ModalContainer
      title={isCreate ? "Create New Incident" : "Edit Incident"}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
      maxWidth={720}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <IncidentModalBody>
        {isIncidentError ? (
          <Typography color="error" sx={{ py: 2 }}>
            {incidentError instanceof Error ? incidentError.message : "Failed to load incident."}
          </Typography>
        ) : incidentId && !incident ? (
          <Stack sx={{ alignItems: "center", py: 6 }}>
            <CircularProgress size={32} />
          </Stack>
        ) : (
          <Grid
            component="form"
            onSubmit={handleSubmit(handleSubmitForm)}
            container
            spacing={2}
            sx={{ width: 1 }}
          >
            <Grid size={8}>
              <TextField
                label="Title"
                variant="filled"
                error={!!errors.title}
                helperText={errors.title?.message}
                {...register("title")}
                fullWidth
              />
            </Grid>
            <Grid size={4}>
              <Controller
                control={control}
                name="severity"
                render={({ field }) => (
                  <TextField
                    {...field}
                    select
                    label="Severity"
                    variant="filled"
                    error={!!errors.severity}
                    helperText={errors.severity?.message}
                  >
                    {INCIDENT_SEVERITIES.map((severity) => (
                      <MenuItem key={severity} value={severity}>
                        {severity}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
            </Grid>
            <Grid size={12}>
              <TextField
                label="Description"
                variant="filled"
                error={!!errors.description}
                helperText={errors.description?.message}
                multiline
                minRows={3}
                maxRows={8}
                fullWidth
                {...register("description")}
              />
            </Grid>
            <Grid size={12}>
              <Controller
                control={control}
                name="teamIds"
                render={({ field }) => (
                  <Autocomplete
                    multiple
                    options={teams ?? []}
                    value={(teams ?? []).filter((team) => field.value.includes(team.id))}
                    getOptionLabel={(option) => option.name}
                    isOptionEqualToValue={(option, value) => option.id === value.id}
                    onChange={(_, value) => field.onChange(value.map((item) => item.id))}
                    renderValue={(value, getItemProps) =>
                      value.map((option, index) => {
                        const { key, ...itemProps } = getItemProps({ index });
                        return (
                          <Chip
                            key={key}
                            size="small"
                            variant="filled"
                            label={option.name}
                            {...itemProps}
                          />
                        );
                      })
                    }
                    renderInput={(params) => (
                      <TextField
                        {...params}
                        variant="filled"
                        label="Teams"
                        error={!!errors.teamIds}
                        helperText={errors.teamIds?.message}
                      />
                    )}
                  />
                )}
              />
            </Grid>
            <Grid size={12}>
              <Controller
                control={control}
                name="alertRuleIds"
                render={({ field }) => (
                  <Autocomplete
                    multiple
                    options={alertRules ?? []}
                    value={(alertRules ?? []).filter((rule) => field.value.includes(rule.id))}
                    getOptionLabel={(option) => option.name}
                    isOptionEqualToValue={(option, value) => option.id === value.id}
                    onChange={(_, value) => field.onChange(value.map((item) => item.id))}
                    renderValue={(value, getItemProps) =>
                      value.map((option, index) => {
                        const { key, ...itemProps } = getItemProps({ index });
                        return (
                          <Chip
                            key={key}
                            size="small"
                            variant="filled"
                            label={option.name}
                            {...itemProps}
                          />
                        );
                      })
                    }
                    renderInput={(params) => (
                      <TextField {...params} variant="filled" label="Alert Rules" />
                    )}
                  />
                )}
              />
            </Grid>
            <Grid size={12}>
              <Controller
                control={control}
                name="tags"
                render={({ field }) => (
                  <Autocomplete
                    multiple
                    freeSolo
                    options={[]}
                    value={field.value}
                    onChange={(_, value) => field.onChange(value)}
                    renderValue={(value, getItemProps) =>
                      value.map((option, index) => {
                        const { key, ...itemProps } = getItemProps({ index });
                        return (
                          <Chip
                            key={key}
                            size="small"
                            variant="filled"
                            label={option}
                            {...itemProps}
                          />
                        );
                      })
                    }
                    renderInput={(params) => (
                      <TextField
                        {...params}
                        variant="filled"
                        label="Tags"
                        placeholder="Type and press Enter"
                      />
                    )}
                  />
                )}
              />
            </Grid>
            <Grid size={isCreate ? 4 : 6}>
              <Controller
                control={control}
                name="startedAt"
                render={({ field }) => (
                  <DateTimeInput
                    calendar="gregorian"
                    type="date-time"
                    label="Started At"
                    value={field.value}
                    onChange={(payload) => field.onChange(payload.iso)}
                  />
                )}
              />
            </Grid>
            <Grid size={isCreate ? 4 : 6}>
              <Controller
                control={control}
                name="detectedAt"
                render={({ field }) => (
                  <DateTimeInput
                    calendar="gregorian"
                    type="date-time"
                    label="Detected At"
                    value={field.value}
                    onChange={(payload) => field.onChange(payload.iso)}
                  />
                )}
              />
            </Grid>
            {isCreate && (
              <Grid size={4}>
                <Controller
                  control={control}
                  name="resolvedAt"
                  render={({ field }) => (
                    <DateTimeInput
                      calendar="gregorian"
                      type="date-time"
                      label="Resolved At"
                      value={field.value}
                      onChange={(payload) => field.onChange(payload.iso)}
                    />
                  )}
                />
              </Grid>
            )}
            {isCreate && (
              <Grid size={12}>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  Leave Resolved At empty unless you are logging a past, already-closed incident.
                </Typography>
              </Grid>
            )}
            <Grid size={12}>
              <GradientSubmitButton type="submit" fullWidth loading={isPending}>
                {isCreate ? "Create" : "Save"}
              </GradientSubmitButton>
            </Grid>
          </Grid>
        )}
      </IncidentModalBody>
    </ModalContainer>
  );
}
