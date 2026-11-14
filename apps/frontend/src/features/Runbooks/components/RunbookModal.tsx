"use client";

import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Autocomplete,
  Button,
  Chip,
  CircularProgress,
  Grid,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Controller, useFieldArray, useForm } from "react-hook-form";
import { HiPlus, HiTrash } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { CreateUpdateModal } from "@/@types/global";
import { getAllTeams } from "@/api/team";
import GradientSubmitButton from "@/components/GradientSubmitButton";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { getAllAlertRules } from "@/features/Debugging/debugging.api";
import { INCIDENT_SEVERITIES } from "@/features/Incidents/incident.type";
import { useCurrentTheme } from "@/hooks";

import { createRunbook, getRunbookById, updateRunbook } from "../runbook.api";
import {
  RUNBOOK_SOURCE_TYPES,
  RUNBOOK_STATUSES,
  type IRunbook,
  type IRunbookWriteRequest
} from "../runbook.type";
import { RUNBOOK_SOURCE_TYPE_LABELS, RUNBOOK_STATUS_LABELS } from "../runbook.utils";

import RunbookModalBody from "./RunbookModalBody";

const stepSchema = z.object({
  title: z.string(),
  description: z.string(),
  command: z.string(),
  expectedResult: z.string()
});

const runbookSchema = z
  .object({
    name: z.string().trim().min(1, "This field is Required."),
    slug: z.string(),
    description: z.string(),
    teamIds: z.array(z.string()),
    tags: z.array(z.string()),
    status: z.enum(RUNBOOK_STATUSES),
    sourceType: z.enum(RUNBOOK_SOURCE_TYPES),
    steps: z.array(stepSchema),
    content: z.string(),
    externalUrl: z.string(),
    appliesToAlertRuleIds: z.array(z.string()),
    appliesToTags: z.array(z.string()),
    appliesToSeverities: z.array(z.enum(INCIDENT_SEVERITIES)),
    reviewIntervalDays: z.string()
  })
  .superRefine((values, ctx) => {
    if (values.sourceType === "steps") {
      if (values.steps.length === 0) {
        ctx.addIssue({
          code: "custom",
          path: ["steps"],
          message: "Add at least one step."
        });
      }
      values.steps.forEach((step, index) => {
        if (!step.title.trim()) {
          ctx.addIssue({
            code: "custom",
            path: ["steps", index, "title"],
            message: "Step title is required."
          });
        }
      });
    }
    if (values.sourceType === "markdown" && !values.content.trim()) {
      ctx.addIssue({
        code: "custom",
        path: ["content"],
        message: "Markdown content is required."
      });
    }
    if (values.sourceType === "externalUrl") {
      try {
        // eslint-disable-next-line no-new
        new URL(values.externalUrl.trim());
      } catch {
        ctx.addIssue({
          code: "custom",
          path: ["externalUrl"],
          message: "Enter a valid URL."
        });
      }
    }
  });

type RunbookFormType = z.infer<typeof runbookSchema>;

type RunbookModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IRunbook>;
  onSubmit: () => void;
};

const emptyFormValues: RunbookFormType = {
  name: "",
  slug: "",
  description: "",
  teamIds: [],
  tags: [],
  status: "draft",
  sourceType: "steps",
  steps: [{ title: "", description: "", command: "", expectedResult: "" }],
  content: "",
  externalUrl: "",
  appliesToAlertRuleIds: [],
  appliesToTags: [],
  appliesToSeverities: [],
  reviewIntervalDays: "90"
};

function getFormValues(runbook?: IRunbook): RunbookFormType {
  if (!runbook) return emptyFormValues;

  return {
    name: runbook.name,
    slug: runbook.slug ?? "",
    description: runbook.description ?? "",
    teamIds: runbook.teamIds ?? [],
    tags: runbook.tags ?? [],
    status: runbook.status,
    sourceType: runbook.sourceType,
    steps:
      runbook.steps && runbook.steps.length > 0
        ? runbook.steps.map((step) => ({
            title: step.title,
            description: step.description ?? "",
            command: step.command ?? "",
            expectedResult: step.expectedResult ?? ""
          }))
        : [{ title: "", description: "", command: "", expectedResult: "" }],
    content: runbook.content ?? "",
    externalUrl: runbook.externalUrl ?? "",
    appliesToAlertRuleIds: runbook.appliesTo?.alertRuleIds ?? [],
    appliesToTags: runbook.appliesTo?.tags ?? [],
    appliesToSeverities: runbook.appliesTo?.severities ?? [],
    reviewIntervalDays: runbook.reviewIntervalDays != null ? String(runbook.reviewIntervalDays) : ""
  };
}

function toWritePayload(values: RunbookFormType): IRunbookWriteRequest {
  const body: IRunbookWriteRequest = {
    name: values.name.trim(),
    teamIds: values.teamIds,
    tags: values.tags,
    status: values.status,
    sourceType: values.sourceType,
    appliesTo: {
      alertRuleIds: values.appliesToAlertRuleIds,
      tags: values.appliesToTags,
      severities: values.appliesToSeverities
    }
  };

  if (values.slug.trim()) body.slug = values.slug.trim();
  if (values.description.trim()) body.description = values.description.trim();

  const reviewDays = values.reviewIntervalDays.trim();
  if (reviewDays) {
    const parsed = Number(reviewDays);
    if (!Number.isNaN(parsed)) body.reviewIntervalDays = parsed;
  }

  if (values.sourceType === "steps") {
    body.steps = values.steps.map((step) => ({
      title: step.title.trim(),
      ...(step.description.trim() ? { description: step.description.trim() } : {}),
      ...(step.command.trim() ? { command: step.command.trim() } : {}),
      ...(step.expectedResult.trim() ? { expectedResult: step.expectedResult.trim() } : {})
    }));
  } else if (values.sourceType === "markdown") {
    body.content = values.content;
  } else {
    body.externalUrl = values.externalUrl.trim();
  }

  return body;
}

export default function RunbookModal({ open, onClose, data, onSubmit }: RunbookModalProps) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();
  const isCreate = data === "NEW";
  const runbookId = data && data !== "NEW" ? data.id : null;

  const {
    register,
    handleSubmit,
    reset,
    control,
    watch,
    formState: { errors }
  } = useForm<RunbookFormType>({
    resolver: zodResolver(runbookSchema),
    defaultValues: emptyFormValues,
    mode: "onSubmit"
  });

  const { fields, append, remove } = useFieldArray({ control, name: "steps" });
  const sourceType = watch("sourceType");

  const { data: teams } = useQuery({
    queryKey: ["all-teams"],
    queryFn: () => getAllTeams()
  });

  const { data: alertRules } = useQuery({
    queryKey: ["all-alert-rules"],
    queryFn: () => getAllAlertRules()
  });

  const {
    data: runbook,
    isError: isRunbookError,
    error: runbookError
  } = useQuery({
    queryKey: ["runbook", runbookId],
    queryFn: () => getRunbookById(runbookId!),
    enabled: open && Boolean(runbookId)
  });

  const { mutate: createMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: IRunbookWriteRequest) => createRunbook(body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Runbook Created Successfully.");
        void queryClient.invalidateQueries({ queryKey: ["runbook"] });
        void queryClient.invalidateQueries({ queryKey: ["published-runbooks"] });
        onSubmit();
        onClose?.();
        return;
      }
      toast.error(response.message);
    }
  });

  const { mutate: updateMutation, isPending: isUpdating } = useMutation({
    mutationFn: (body: IRunbookWriteRequest) => updateRunbook(runbookId!, body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Runbook Updated Successfully.");
        void queryClient.invalidateQueries({ queryKey: ["runbook"] });
        void queryClient.invalidateQueries({ queryKey: ["published-runbooks"] });
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
    if (runbook) reset(getFormValues(runbook));
  }, [open, isCreate, runbook, reset]);

  function handleSubmitForm(values: RunbookFormType) {
    const payload = toWritePayload(values);
    if (isCreate) {
      createMutation(payload);
      return;
    }
    updateMutation(payload);
  }

  const isPending = isCreating || isUpdating;

  return (
    <ModalContainer
      title={isCreate ? "Create Runbook" : "Edit Runbook"}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
      maxWidth={820}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <RunbookModalBody>
        {isRunbookError ? (
          <Typography color="error" sx={{ py: 2 }}>
            {runbookError instanceof Error ? runbookError.message : "Failed to load runbook."}
          </Typography>
        ) : runbookId && !runbook ? (
          <Stack sx={{ alignItems: "center", py: 6 }}>
            <CircularProgress size={32} />
          </Stack>
        ) : (
          <Grid
            component="form"
            onSubmit={handleSubmit(handleSubmitForm, (error) => console.log(error))}
            container
            spacing={2}
            sx={{ width: 1, maxHeight: "min(75vh, 720px)", overflowY: "auto", pr: 0.5 }}
          >
            <Grid size={8}>
              <TextField
                label="Name"
                variant="filled"
                error={!!errors.name}
                helperText={errors.name?.message}
                {...register("name")}
                fullWidth
              />
            </Grid>
            <Grid size={4}>
              <TextField label="Slug (optional)" variant="filled" {...register("slug")} fullWidth />
            </Grid>
            <Grid size={12}>
              <TextField
                label="Description"
                variant="filled"
                multiline
                minRows={2}
                fullWidth
                {...register("description")}
              />
            </Grid>
            <Grid size={4}>
              <Controller
                control={control}
                name="status"
                render={({ field }) => (
                  <TextField {...field} select label="Status" variant="filled" fullWidth>
                    {RUNBOOK_STATUSES.map((status) => (
                      <MenuItem key={status} value={status}>
                        {RUNBOOK_STATUS_LABELS[status]}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
            </Grid>
            <Grid size={4}>
              <Controller
                control={control}
                name="sourceType"
                render={({ field }) => (
                  <TextField {...field} select label="Source Type" variant="filled" fullWidth>
                    {RUNBOOK_SOURCE_TYPES.map((type) => (
                      <MenuItem key={type} value={type}>
                        {RUNBOOK_SOURCE_TYPE_LABELS[type]}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
            </Grid>
            <Grid size={4}>
              <TextField
                label="Review Interval (days)"
                variant="filled"
                type="number"
                fullWidth
                {...register("reviewIntervalDays")}
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
                      <TextField {...params} variant="filled" label="Teams" />
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

            {sourceType === "steps" && (
              <Grid size={12}>
                <Stack spacing={1.5}>
                  <Stack
                    direction="row"
                    sx={{ justifyContent: "space-between", alignItems: "center" }}
                  >
                    <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                      Steps
                    </Typography>
                    <Button
                      size="small"
                      startIcon={<HiPlus />}
                      onClick={() =>
                        append({ title: "", description: "", command: "", expectedResult: "" })
                      }
                      sx={{ textTransform: "none" }}
                    >
                      Add step
                    </Button>
                  </Stack>
                  {typeof errors.steps?.message === "string" && (
                    <Typography variant="caption" color="error">
                      {errors.steps.message}
                    </Typography>
                  )}
                  {fields.map((field, index) => (
                    <Stack
                      key={field.id}
                      spacing={1}
                      sx={{
                        p: 1.5,
                        borderRadius: 2,
                        border: ({ palette }) => `1px solid ${palette.divider}`
                      }}
                    >
                      <Stack direction="row" spacing={1} sx={{ alignItems: "flex-start" }}>
                        <TextField
                          label={`Step ${index + 1} title`}
                          variant="filled"
                          fullWidth
                          error={!!errors.steps?.[index]?.title}
                          helperText={errors.steps?.[index]?.title?.message}
                          {...register(`steps.${index}.title`)}
                        />
                        <IconButton
                          aria-label="Remove step"
                          onClick={() => remove(index)}
                          disabled={fields.length === 1}
                        >
                          <HiTrash />
                        </IconButton>
                      </Stack>
                      <TextField
                        label="Description"
                        variant="filled"
                        fullWidth
                        {...register(`steps.${index}.description`)}
                      />
                      <TextField
                        label="Command"
                        variant="filled"
                        fullWidth
                        {...register(`steps.${index}.command`)}
                      />
                      <TextField
                        label="Expected Result"
                        variant="filled"
                        fullWidth
                        {...register(`steps.${index}.expectedResult`)}
                      />
                    </Stack>
                  ))}
                </Stack>
              </Grid>
            )}

            {sourceType === "markdown" && (
              <Grid size={12}>
                <TextField
                  label="Markdown Content"
                  variant="filled"
                  multiline
                  minRows={8}
                  fullWidth
                  error={!!errors.content}
                  helperText={errors.content?.message}
                  {...register("content")}
                />
              </Grid>
            )}

            {sourceType === "externalUrl" && (
              <Grid size={12}>
                <TextField
                  label="External URL"
                  variant="filled"
                  fullWidth
                  error={!!errors.externalUrl}
                  helperText={errors.externalUrl?.message}
                  {...register("externalUrl")}
                />
              </Grid>
            )}

            <Grid size={12}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>
                Applies To
              </Typography>
            </Grid>
            <Grid size={12}>
              <Controller
                control={control}
                name="appliesToAlertRuleIds"
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
                name="appliesToSeverities"
                render={({ field }) => (
                  <Autocomplete
                    multiple
                    options={[...INCIDENT_SEVERITIES]}
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
                      <TextField {...params} variant="filled" label="Severities" />
                    )}
                  />
                )}
              />
            </Grid>
            <Grid size={12}>
              <Controller
                control={control}
                name="appliesToTags"
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
                        label="Applies-to Tags"
                        placeholder="Type and press Enter"
                      />
                    )}
                  />
                )}
              />
            </Grid>

            <Grid size={12}>
              <GradientSubmitButton type="submit" fullWidth loading={isPending}>
                {isCreate ? "Create" : "Save"}
              </GradientSubmitButton>
            </Grid>
          </Grid>
        )}
      </RunbookModalBody>
    </ModalContainer>
  );
}
