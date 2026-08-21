"use client";

import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Autocomplete,
  Button,
  Checkbox,
  Chip,
  CircularProgress,
  FormControlLabel,
  Grid,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Controller, useForm } from "react-hook-form";
import { HiChevronDown, HiPlus, HiTrash } from "react-icons/hi";
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
  DOCUMENT_TYPES,
  INCIDENT_SEVERITIES,
  type IIncident,
  type IncidentDocumentType
} from "../incident.type";
import {
  buildIncidentCreatePayload,
  buildIncidentUpdatePayload,
  type NestedDocumentDraft
} from "../incident.utils";

import IncidentModalBody from "./IncidentModalBody";

const incidentSchema = z
  .object({
    title: z.string().trim().min(1, "This field is Required."),
    description: z.string(),
    severity: z.enum(INCIDENT_SEVERITIES, "This field is Required."),
    teamIds: z.array(z.string()).min(1, "At least one team is required."),
    tags: z.array(z.string()),
    startedAt: z.string().nullable(),
    detectedAt: z.string().nullable(),
    resolvedAt: z.string().nullable(),
    alertRuleIds: z.array(z.string()),
    includePostMortem: z.boolean(),
    postMortemSummary: z.string(),
    postMortemImpact: z.string(),
    postMortemDueAt: z.string().nullable()
  })
  .superRefine((values, ctx) => {
    if (values.includePostMortem && !values.postMortemSummary.trim()) {
      ctx.addIssue({
        code: "custom",
        path: ["postMortemSummary"],
        message: "Summary is required when including a postmortem."
      });
    }
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
  alertRuleIds: [],
  includePostMortem: false,
  postMortemSummary: "",
  postMortemImpact: "",
  postMortemDueAt: null
};

function emptyLinkDoc(): NestedDocumentDraft {
  return {
    mode: "link",
    externalUrl: "",
    name: "",
    type: "other",
    description: "",
    attachableType: "incident"
  };
}

function emptyFileDoc(): NestedDocumentDraft {
  return {
    mode: "file",
    file: null,
    type: "other",
    description: "",
    attachableType: "incident"
  };
}

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
    alertRuleIds: incident.alertRuleIds ?? [],
    includePostMortem: false,
    postMortemSummary: "",
    postMortemImpact: "",
    postMortemDueAt: null
  };
}

export default function IncidentModal({ open, onClose, data, onSubmit }: IncidentModalProps) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();
  const isCreate = data === "NEW";
  const incidentId = data && data !== "NEW" ? data.id : null;
  const [documents, setDocuments] = useState<NestedDocumentDraft[]>([]);

  const {
    register,
    handleSubmit,
    reset,
    control,
    watch,
    formState: { errors }
  } = useForm<IncidentFormType>({
    resolver: zodResolver(incidentSchema),
    defaultValues: emptyFormValues,
    mode: "onSubmit"
  });

  const includePostMortem = watch("includePostMortem");

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

  const allowNested = isCreate || incident?.source === "manual";

  const { mutate: createIncidentMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: ReturnType<typeof buildIncidentCreatePayload>) => createIncident(body),
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
    mutationFn: (body: ReturnType<typeof buildIncidentUpdatePayload>) =>
      updateIncident(incidentId!, body),
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
    setDocuments([]);
    if (isCreate) {
      reset(emptyFormValues);
      return;
    }
    if (incident) {
      reset(getFormValues(incident));
    }
  }, [open, isCreate, incident, reset]);

  function handleSubmitForm(values: IncidentFormType) {
    const nestedDocs = allowNested ? documents : [];
    const nestedPm =
      allowNested && values.includePostMortem
        ? {
            summary: values.postMortemSummary,
            impact: values.postMortemImpact,
            dueAt: values.postMortemDueAt ?? undefined,
            status: "draft" as const
          }
        : null;

    if (isCreate) {
      createIncidentMutation(
        buildIncidentCreatePayload({
          title: values.title,
          severity: values.severity,
          teamIds: values.teamIds,
          description: values.description,
          tags: values.tags,
          startedAt: values.startedAt,
          detectedAt: values.detectedAt,
          resolvedAt: values.resolvedAt,
          alertRuleIds: values.alertRuleIds,
          includePostMortem: values.includePostMortem,
          postMortem: nestedPm,
          documents: nestedDocs
        })
      );
      return;
    }

    updateIncidentMutation(
      buildIncidentUpdatePayload({
        title: values.title,
        description: values.description,
        severity: values.severity,
        teamIds: values.teamIds,
        tags: values.tags,
        startedAt: values.startedAt ?? new Date().toISOString(),
        detectedAt: values.detectedAt ?? new Date().toISOString(),
        alertRuleIds: values.alertRuleIds,
        includePostMortem: values.includePostMortem,
        postMortem: nestedPm,
        documents: nestedDocs
      })
    );
  }

  const isPending = isCreating || isUpdating;

  function updateDocument(index: number, patch: Partial<NestedDocumentDraft>) {
    setDocuments((prev) =>
      prev.map((doc, docIndex) =>
        docIndex === index ? ({ ...doc, ...patch } as NestedDocumentDraft) : doc
      )
    );
  }

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
            sx={{ width: 1, maxHeight: "min(78vh, 760px)", overflowY: "auto", pr: 0.5 }}
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

            {allowNested && (
              <Grid size={12}>
                <Accordion disableGutters>
                  <AccordionSummary expandIcon={<HiChevronDown />}>
                    <Typography sx={{ fontWeight: 700 }}>
                      {isCreate ? "Optional postmortem & documents" : "Add postmortem / documents"}
                    </Typography>
                  </AccordionSummary>
                  <AccordionDetails>
                    <Stack spacing={2}>
                      {!isCreate && (
                        <Typography variant="caption" sx={{ color: "text.secondary" }}>
                          Nested documents only add attachments. Omit postmortem to leave the
                          existing one unchanged. Delete documents from Incident Details.
                        </Typography>
                      )}
                      <Controller
                        control={control}
                        name="includePostMortem"
                        render={({ field }) => (
                          <FormControlLabel
                            control={
                              <Checkbox
                                checked={field.value}
                                onChange={(_, checked) => field.onChange(checked)}
                              />
                            }
                            label="Include postmortem"
                          />
                        )}
                      />
                      {includePostMortem && (
                        <Grid container spacing={2}>
                          <Grid size={12}>
                            <TextField
                              label="Postmortem summary"
                              variant="filled"
                              fullWidth
                              multiline
                              minRows={2}
                              error={!!errors.postMortemSummary}
                              helperText={errors.postMortemSummary?.message}
                              {...register("postMortemSummary")}
                            />
                          </Grid>
                          <Grid size={12}>
                            <TextField
                              label="Impact (optional)"
                              variant="filled"
                              fullWidth
                              {...register("postMortemImpact")}
                            />
                          </Grid>
                          <Grid size={12}>
                            <Controller
                              control={control}
                              name="postMortemDueAt"
                              render={({ field }) => (
                                <DateTimeInput
                                  calendar="gregorian"
                                  type="date-time"
                                  label="Due At (optional)"
                                  value={field.value}
                                  onChange={(payload) => field.onChange(payload.iso)}
                                />
                              )}
                            />
                          </Grid>
                        </Grid>
                      )}

                      <Stack
                        direction="row"
                        spacing={1}
                        sx={{ justifyContent: "space-between", alignItems: "center" }}
                      >
                        <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                          Documents to add
                        </Typography>
                        <Stack direction="row" spacing={1}>
                          <Button
                            size="small"
                            startIcon={<HiPlus />}
                            onClick={() => setDocuments((prev) => [...prev, emptyLinkDoc()])}
                            sx={{ textTransform: "none" }}
                          >
                            Link
                          </Button>
                          <Button
                            size="small"
                            startIcon={<HiPlus />}
                            onClick={() => setDocuments((prev) => [...prev, emptyFileDoc()])}
                            sx={{ textTransform: "none" }}
                          >
                            File
                          </Button>
                        </Stack>
                      </Stack>

                      {documents.map((doc, index) => (
                        <Stack
                          key={index}
                          spacing={1}
                          sx={{
                            p: 1.5,
                            borderRadius: 2,
                            border: ({ palette }) => `1px solid ${palette.divider}`
                          }}
                        >
                          <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                            <Typography variant="caption" sx={{ fontWeight: 700 }}>
                              {doc.mode === "link" ? "External link" : "File upload"}
                            </Typography>
                            <IconButton
                              size="small"
                              aria-label="Remove document"
                              onClick={() =>
                                setDocuments((prev) => prev.filter((_, i) => i !== index))
                              }
                            >
                              <HiTrash />
                            </IconButton>
                          </Stack>
                          {doc.mode === "link" ? (
                            <>
                              <TextField
                                label="URL"
                                variant="filled"
                                fullWidth
                                value={doc.externalUrl}
                                onChange={(event) =>
                                  updateDocument(index, { externalUrl: event.target.value })
                                }
                              />
                              <TextField
                                label="Name"
                                variant="filled"
                                fullWidth
                                value={doc.name}
                                onChange={(event) =>
                                  updateDocument(index, { name: event.target.value })
                                }
                              />
                            </>
                          ) : (
                            <Button
                              variant="outlined"
                              component="label"
                              sx={{ textTransform: "none" }}
                            >
                              {doc.file ? doc.file.name : "Choose file"}
                              <input
                                hidden
                                type="file"
                                onChange={(event) =>
                                  updateDocument(index, {
                                    file: event.target.files?.[0] ?? null
                                  })
                                }
                              />
                            </Button>
                          )}
                          <TextField
                            select
                            label="Type"
                            variant="filled"
                            fullWidth
                            value={doc.type}
                            onChange={(event) =>
                              updateDocument(index, {
                                type: event.target.value as IncidentDocumentType
                              })
                            }
                          >
                            {DOCUMENT_TYPES.map((docType) => (
                              <MenuItem key={docType} value={docType}>
                                {docType}
                              </MenuItem>
                            ))}
                          </TextField>
                          <TextField
                            select
                            label="Attach to"
                            variant="filled"
                            fullWidth
                            value={doc.attachableType}
                            onChange={(event) =>
                              updateDocument(index, {
                                attachableType: event.target.value as "incident" | "postMortem"
                              })
                            }
                            helperText={
                              doc.attachableType === "postMortem" && !includePostMortem
                                ? "Enable Include postmortem when attaching to postmortem on create."
                                : undefined
                            }
                          >
                            <MenuItem value="incident">Incident</MenuItem>
                            <MenuItem value="postMortem">Postmortem</MenuItem>
                          </TextField>
                          <TextField
                            label="Description"
                            variant="filled"
                            fullWidth
                            value={doc.description}
                            onChange={(event) =>
                              updateDocument(index, { description: event.target.value })
                            }
                          />
                        </Stack>
                      ))}
                    </Stack>
                  </AccordionDetails>
                </Accordion>
              </Grid>
            )}

            {!isCreate && incident && incident.source !== "manual" && (
              <Grid size={12}>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  Nested postmortem and documents are only available for manual incidents. Use
                  Incident Details tabs instead.
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
