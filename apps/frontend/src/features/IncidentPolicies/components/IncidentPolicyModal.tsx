"use client";

import { useEffect, useMemo } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Autocomplete,
  Checkbox,
  Chip,
  CircularProgress,
  FormControlLabel,
  Grid,
  MenuItem,
  Stack,
  Switch,
  TextField,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Controller, useForm } from "react-hook-form";
import { HiChevronDown } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { CreateUpdateModal } from "@/@types/global";
import { getAllEndpoints } from "@/api/flow";
import { getAllTeams } from "@/api/team";
import { getAllUsers } from "@/api/user";
import GradientSubmitButton from "@/components/GradientSubmitButton";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { getAllAlertRules } from "@/features/Debugging/debugging.api";
import { INCIDENT_SEVERITIES } from "@/features/Incidents/incident.type";
import { getPublishedRunbooks } from "@/features/Runbooks/runbook.api";
import { useCurrentTheme } from "@/hooks";
import { DATA_SOURCE_VARIANTS, type DataSourceType } from "@/utils/dataSourceUtils";

import {
  createIncidentPolicy,
  getIncidentPolicyById,
  updateIncidentPolicy
} from "../incident-policy.api";
import {
  INCIDENT_POLICY_GROUPING_KEYS,
  type IIncidentPolicy,
  type IIncidentPolicyWriteRequest,
  type IncidentPolicyRules
} from "../incident-policy.type";

import IncidentPolicyModalBody from "./IncidentPolicyModalBody";

const severityRuleSchema = z.object({
  enabled: z.boolean(),
  ackWithinMinutes: z.string(),
  resolveWithinMinutes: z.string(),
  requireCommander: z.boolean(),
  notifyEndpointIds: z.array(z.string()),
  onCallPlanId: z.string(),
  useLayers: z.boolean(),
  stakeholderUpdateEveryMinutes: z.string(),
  statusPageUpdateRequired: z.boolean(),
  postmortemRequired: z.boolean(),
  postmortemDueDays: z.string(),
  postmortemReviewRequired: z.boolean(),
  runbookNames: z.array(z.string())
});

const policySchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(1, "This field is Required.")
      .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, "Use a lowercase slug (letters, numbers, hyphens)."),
    description: z.string(),
    enabled: z.boolean(),
    ownerId: z.string(),
    teamIds: z.array(z.string()).min(1, "At least one team is required."),
    alertRuleIds: z.array(z.string()),
    tags: z.array(z.string()),
    serviceIds: z.array(z.string()),
    dataSourceTypes: z.array(z.string()),
    groupingKeys: z.array(z.enum(INCIDENT_POLICY_GROUPING_KEYS)),
    windowMinutes: z.string(),
    autoCreate: z.boolean(),
    autoResolveOnAlertClear: z.boolean(),
    titleTemplate: z.string(),
    defaultSeverity: z.enum(INCIDENT_SEVERITIES),
    severityMapCritical: z.string(),
    severityMapWarning: z.string(),
    rules: z.record(z.enum(INCIDENT_SEVERITIES), severityRuleSchema)
  })
  .superRefine((values, ctx) => {
    const hasMatcher =
      values.alertRuleIds.length > 0 ||
      values.tags.length > 0 ||
      values.serviceIds.length > 0 ||
      values.dataSourceTypes.length > 0;
    if (!hasMatcher) {
      ctx.addIssue({
        code: "custom",
        path: ["tags"],
        message: "Provide at least one match condition."
      });
    }

    const enabledRules = INCIDENT_SEVERITIES.filter((sev) => values.rules[sev]?.enabled);
    if (enabledRules.length === 0) {
      ctx.addIssue({
        code: "custom",
        path: ["rules"],
        message: "Enable at least one severity rule."
      });
    }
  });

type PolicyFormType = z.infer<typeof policySchema>;

type IncidentPolicyModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IIncidentPolicy>;
  onSubmit: () => void;
};

function emptySeverityRule() {
  return {
    enabled: false,
    ackWithinMinutes: "",
    resolveWithinMinutes: "",
    requireCommander: false,
    notifyEndpointIds: [] as string[],
    onCallPlanId: "",
    useLayers: true,
    stakeholderUpdateEveryMinutes: "",
    statusPageUpdateRequired: false,
    postmortemRequired: false,
    postmortemDueDays: "",
    postmortemReviewRequired: false,
    runbookNames: [] as string[]
  };
}

function buildEmptyRules(): PolicyFormType["rules"] {
  return Object.fromEntries(
    INCIDENT_SEVERITIES.map((severity) => [severity, emptySeverityRule()])
  ) as PolicyFormType["rules"];
}

const emptyFormValues: PolicyFormType = {
  name: "",
  description: "",
  enabled: true,
  ownerId: "",
  teamIds: [],
  alertRuleIds: [],
  tags: [],
  serviceIds: [],
  dataSourceTypes: [],
  groupingKeys: ["serviceId", "alertRuleId"],
  windowMinutes: "15",
  autoCreate: true,
  autoResolveOnAlertClear: false,
  titleTemplate: "{{ alert.name }} on {{ service.name }}",
  defaultSeverity: "SEV3",
  severityMapCritical: "SEV1",
  severityMapWarning: "SEV3",
  rules: {
    ...buildEmptyRules(),
    SEV1: {
      ...emptySeverityRule(),
      enabled: true,
      ackWithinMinutes: "5",
      resolveWithinMinutes: "60",
      requireCommander: true
    }
  }
};

function getFormValues(policy?: IIncidentPolicy): PolicyFormType {
  if (!policy) return emptyFormValues;

  const rules = buildEmptyRules();
  INCIDENT_SEVERITIES.forEach((severity) => {
    const rule = policy.rules?.[severity];
    if (!rule) return;
    rules[severity] = {
      enabled: true,
      ackWithinMinutes: rule.ackWithinMinutes != null ? String(rule.ackWithinMinutes) : "",
      resolveWithinMinutes:
        rule.resolveWithinMinutes != null ? String(rule.resolveWithinMinutes) : "",
      requireCommander: Boolean(rule.requireCommander),
      notifyEndpointIds: rule.notifyEndpointIds ?? [],
      onCallPlanId: rule.escalation?.onCallPlanId ?? "",
      useLayers: rule.escalation?.useLayers ?? true,
      stakeholderUpdateEveryMinutes:
        rule.communication?.stakeholderUpdateEveryMinutes != null
          ? String(rule.communication.stakeholderUpdateEveryMinutes)
          : "",
      statusPageUpdateRequired: Boolean(rule.communication?.statusPageUpdateRequired),
      postmortemRequired: Boolean(rule.postmortem?.required),
      postmortemDueDays: rule.postmortem?.dueDays != null ? String(rule.postmortem.dueDays) : "",
      postmortemReviewRequired: Boolean(rule.postmortem?.reviewRequired),
      runbookNames: rule.runbookNames ?? []
    };
  });

  return {
    name: policy.name,
    description: policy.description ?? "",
    enabled: policy.enabled,
    ownerId: policy.ownerId ?? "",
    teamIds: policy.teamIds ?? [],
    alertRuleIds: policy.match?.alertRuleIds ?? [],
    tags: policy.match?.tags ?? [],
    serviceIds: policy.match?.serviceIds ?? [],
    dataSourceTypes: (policy.match?.dataSourceTypes ?? []).map(String),
    groupingKeys: (policy.grouping?.key ?? []) as PolicyFormType["groupingKeys"],
    windowMinutes:
      policy.grouping?.windowMinutes != null ? String(policy.grouping.windowMinutes) : "15",
    autoCreate: policy.incident?.autoCreate ?? true,
    autoResolveOnAlertClear: policy.incident?.autoResolveOnAlertClear ?? false,
    titleTemplate: policy.incident?.titleTemplate ?? "",
    defaultSeverity: policy.incident?.defaultSeverity ?? "SEV3",
    severityMapCritical: policy.incident?.severityMap?.critical ?? "",
    severityMapWarning: policy.incident?.severityMap?.warning ?? "",
    rules
  };
}

function parseOptionalNumber(value: string): number | undefined {
  const trimmed = value.trim();
  if (!trimmed) return undefined;
  const parsed = Number(trimmed);
  return Number.isNaN(parsed) ? undefined : parsed;
}

function toWritePayload(values: PolicyFormType): IIncidentPolicyWriteRequest {
  const rules: IncidentPolicyRules = {};

  INCIDENT_SEVERITIES.forEach((severity) => {
    const rule = values.rules[severity];
    if (!rule?.enabled) return;

    rules[severity] = {
      ...(parseOptionalNumber(rule.ackWithinMinutes) != null
        ? { ackWithinMinutes: parseOptionalNumber(rule.ackWithinMinutes) }
        : {}),
      ...(parseOptionalNumber(rule.resolveWithinMinutes) != null
        ? { resolveWithinMinutes: parseOptionalNumber(rule.resolveWithinMinutes) }
        : {}),
      requireCommander: rule.requireCommander,
      notifyEndpointIds: rule.notifyEndpointIds,
      escalation: {
        ...(rule.onCallPlanId.trim() ? { onCallPlanId: rule.onCallPlanId.trim() } : {}),
        useLayers: rule.useLayers
      },
      communication: {
        ...(parseOptionalNumber(rule.stakeholderUpdateEveryMinutes) != null
          ? {
              stakeholderUpdateEveryMinutes: parseOptionalNumber(
                rule.stakeholderUpdateEveryMinutes
              )
            }
          : {}),
        statusPageUpdateRequired: rule.statusPageUpdateRequired
      },
      postmortem: {
        required: rule.postmortemRequired,
        ...(parseOptionalNumber(rule.postmortemDueDays) != null
          ? { dueDays: parseOptionalNumber(rule.postmortemDueDays) }
          : {}),
        reviewRequired: rule.postmortemReviewRequired
      },
      runbookNames: rule.runbookNames
    };
  });

  const severityMap: Record<string, (typeof INCIDENT_SEVERITIES)[number]> = {};
  if (
    values.severityMapCritical &&
    INCIDENT_SEVERITIES.includes(values.severityMapCritical as (typeof INCIDENT_SEVERITIES)[number])
  ) {
    severityMap.critical = values.severityMapCritical as (typeof INCIDENT_SEVERITIES)[number];
  }
  if (
    values.severityMapWarning &&
    INCIDENT_SEVERITIES.includes(values.severityMapWarning as (typeof INCIDENT_SEVERITIES)[number])
  ) {
    severityMap.warning = values.severityMapWarning as (typeof INCIDENT_SEVERITIES)[number];
  }

  return {
    name: values.name.trim(),
    description: values.description.trim() || undefined,
    enabled: values.enabled,
    ownerId: values.ownerId || undefined,
    teamIds: values.teamIds,
    match: {
      alertRuleIds: values.alertRuleIds,
      tags: values.tags,
      serviceIds: values.serviceIds,
      dataSourceTypes: values.dataSourceTypes
    },
    grouping: {
      key: values.groupingKeys,
      windowMinutes: parseOptionalNumber(values.windowMinutes) ?? 15
    },
    incident: {
      autoCreate: values.autoCreate,
      autoResolveOnAlertClear: values.autoResolveOnAlertClear,
      titleTemplate: values.titleTemplate.trim() || undefined,
      defaultSeverity: values.defaultSeverity,
      ...(Object.keys(severityMap).length > 0 ? { severityMap } : {})
    },
    rules
  };
}

const DATA_SOURCE_OPTIONS = Object.keys(DATA_SOURCE_VARIANTS) as DataSourceType[];

export default function IncidentPolicyModal({
  open,
  onClose,
  data,
  onSubmit
}: IncidentPolicyModalProps) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();
  const isCreate = data === "NEW";
  const policyId = data && data !== "NEW" ? data.id : null;

  const {
    register,
    handleSubmit,
    reset,
    control,
    watch,
    formState: { errors }
  } = useForm<PolicyFormType>({
    resolver: zodResolver(policySchema),
    defaultValues: emptyFormValues,
    mode: "onSubmit"
  });

  const rulesWatch = watch("rules");

  const { data: teams } = useQuery({ queryKey: ["all-teams"], queryFn: () => getAllTeams() });
  const { data: users } = useQuery({ queryKey: ["all-users"], queryFn: () => getAllUsers() });
  const { data: alertRules } = useQuery({
    queryKey: ["all-alert-rules"],
    queryFn: () => getAllAlertRules()
  });
  const { data: endpoints } = useQuery({
    queryKey: ["all-endpoints"],
    queryFn: () => getAllEndpoints()
  });
  const { data: publishedRunbooks } = useQuery({
    queryKey: ["published-runbooks"],
    queryFn: () => getPublishedRunbooks()
  });

  const runbookNameOptions = useMemo(
    () =>
      (publishedRunbooks ?? []).map((runbook) => runbook.slug || runbook.name).filter(Boolean),
    [publishedRunbooks]
  );

  const {
    data: policy,
    isError,
    error
  } = useQuery({
    queryKey: ["incident-policy", policyId],
    queryFn: () => getIncidentPolicyById(policyId!),
    enabled: open && Boolean(policyId)
  });

  const { mutate: createMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: IIncidentPolicyWriteRequest) => createIncidentPolicy(body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Incident Policy Created Successfully.");
        void queryClient.invalidateQueries({ queryKey: ["incident-policy"] });
        onSubmit();
        onClose?.();
        return;
      }
      toast.error(response.message);
    }
  });

  const { mutate: updateMutation, isPending: isUpdating } = useMutation({
    mutationFn: (body: IIncidentPolicyWriteRequest) => updateIncidentPolicy(policyId!, body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Incident Policy Updated Successfully.");
        void queryClient.invalidateQueries({ queryKey: ["incident-policy"] });
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
    if (policy) reset(getFormValues(policy));
  }, [open, isCreate, policy, reset]);

  function handleSubmitForm(values: PolicyFormType) {
    const payload = toWritePayload(values);
    if (isCreate) createMutation(payload);
    else updateMutation(payload);
  }

  return (
    <ModalContainer
      title={isCreate ? "Create Incident Policy" : "Edit Incident Policy"}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
      maxWidth={900}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <IncidentPolicyModalBody>
        {isError ? (
          <Typography color="error" sx={{ py: 2 }}>
            {error instanceof Error ? error.message : "Failed to load incident policy."}
          </Typography>
        ) : policyId && !policy ? (
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
                label="Name (slug)"
                variant="filled"
                error={!!errors.name}
                helperText={errors.name?.message}
                fullWidth
                {...register("name")}
              />
            </Grid>
            <Grid size={4} sx={{ display: "flex", alignItems: "center" }}>
              <Controller
                control={control}
                name="enabled"
                render={({ field }) => (
                  <FormControlLabel
                    control={
                      <Switch checked={field.value} onChange={(_, checked) => field.onChange(checked)} />
                    }
                    label="Enabled"
                  />
                )}
              />
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
            <Grid size={6}>
              <Controller
                control={control}
                name="ownerId"
                render={({ field }) => (
                  <Autocomplete
                    options={users ?? []}
                    value={(users ?? []).find((user) => user.id === field.value) ?? null}
                    getOptionLabel={(option) => option.name || option.username}
                    isOptionEqualToValue={(option, value) => option.id === value.id}
                    onChange={(_, value) => field.onChange(value?.id ?? "")}
                    renderInput={(params) => (
                      <TextField {...params} variant="filled" label="Owner" />
                    )}
                  />
                )}
              />
            </Grid>
            <Grid size={6}>
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
              <Accordion defaultExpanded disableGutters>
                <AccordionSummary expandIcon={<HiChevronDown />}>
                  <Typography sx={{ fontWeight: 700 }}>Match</Typography>
                </AccordionSummary>
                <AccordionDetails>
                  <Grid container spacing={2}>
                    <Grid size={12}>
                      <Controller
                        control={control}
                        name="alertRuleIds"
                        render={({ field }) => (
                          <Autocomplete
                            multiple
                            options={alertRules ?? []}
                            value={(alertRules ?? []).filter((rule) =>
                              field.value.includes(rule.id)
                            )}
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
                    <Grid size={6}>
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
                                error={!!errors.tags}
                                helperText={errors.tags?.message}
                              />
                            )}
                          />
                        )}
                      />
                    </Grid>
                    <Grid size={6}>
                      <Controller
                        control={control}
                        name="serviceIds"
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
                                label="Service IDs"
                                placeholder="Paste id and press Enter"
                              />
                            )}
                          />
                        )}
                      />
                    </Grid>
                    <Grid size={12}>
                      <Controller
                        control={control}
                        name="dataSourceTypes"
                        render={({ field }) => (
                          <Autocomplete
                            multiple
                            options={DATA_SOURCE_OPTIONS}
                            value={field.value as DataSourceType[]}
                            getOptionLabel={(option) =>
                              DATA_SOURCE_VARIANTS[option as DataSourceType]?.label ?? option
                            }
                            onChange={(_, value) => field.onChange(value)}
                            renderValue={(value, getItemProps) =>
                              value.map((option, index) => {
                                const { key, ...itemProps } = getItemProps({ index });
                                return (
                                  <Chip
                                    key={key}
                                    size="small"
                                    variant="filled"
                                    label={
                                      DATA_SOURCE_VARIANTS[option as DataSourceType]?.label ??
                                      option
                                    }
                                    {...itemProps}
                                  />
                                );
                              })
                            }
                            renderInput={(params) => (
                              <TextField {...params} variant="filled" label="Data Source Types" />
                            )}
                          />
                        )}
                      />
                    </Grid>
                  </Grid>
                </AccordionDetails>
              </Accordion>
            </Grid>

            <Grid size={12}>
              <Accordion defaultExpanded disableGutters>
                <AccordionSummary expandIcon={<HiChevronDown />}>
                  <Typography sx={{ fontWeight: 700 }}>Grouping & Incident Defaults</Typography>
                </AccordionSummary>
                <AccordionDetails>
                  <Grid container spacing={2}>
                    <Grid size={8}>
                      <Controller
                        control={control}
                        name="groupingKeys"
                        render={({ field }) => (
                          <Autocomplete
                            multiple
                            options={[...INCIDENT_POLICY_GROUPING_KEYS]}
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
                              <TextField {...params} variant="filled" label="Grouping Keys" />
                            )}
                          />
                        )}
                      />
                    </Grid>
                    <Grid size={4}>
                      <TextField
                        label="Window (minutes)"
                        variant="filled"
                        type="number"
                        fullWidth
                        {...register("windowMinutes")}
                      />
                    </Grid>
                    <Grid size={6}>
                      <Controller
                        control={control}
                        name="autoCreate"
                        render={({ field }) => (
                          <FormControlLabel
                            control={
                              <Checkbox
                                checked={field.value}
                                onChange={(_, checked) => field.onChange(checked)}
                              />
                            }
                            label="Auto-create incidents"
                          />
                        )}
                      />
                    </Grid>
                    <Grid size={6}>
                      <Controller
                        control={control}
                        name="autoResolveOnAlertClear"
                        render={({ field }) => (
                          <FormControlLabel
                            control={
                              <Checkbox
                                checked={field.value}
                                onChange={(_, checked) => field.onChange(checked)}
                              />
                            }
                            label="Auto-resolve on alert clear"
                          />
                        )}
                      />
                    </Grid>
                    <Grid size={8}>
                      <TextField
                        label="Title Template"
                        variant="filled"
                        fullWidth
                        {...register("titleTemplate")}
                      />
                    </Grid>
                    <Grid size={4}>
                      <Controller
                        control={control}
                        name="defaultSeverity"
                        render={({ field }) => (
                          <TextField {...field} select label="Default Severity" variant="filled" fullWidth>
                            {INCIDENT_SEVERITIES.map((severity) => (
                              <MenuItem key={severity} value={severity}>
                                {severity}
                              </MenuItem>
                            ))}
                          </TextField>
                        )}
                      />
                    </Grid>
                    <Grid size={6}>
                      <Controller
                        control={control}
                        name="severityMapCritical"
                        render={({ field }) => (
                          <TextField {...field} select label="Map critical →" variant="filled" fullWidth>
                            <MenuItem value="">—</MenuItem>
                            {INCIDENT_SEVERITIES.map((severity) => (
                              <MenuItem key={severity} value={severity}>
                                {severity}
                              </MenuItem>
                            ))}
                          </TextField>
                        )}
                      />
                    </Grid>
                    <Grid size={6}>
                      <Controller
                        control={control}
                        name="severityMapWarning"
                        render={({ field }) => (
                          <TextField {...field} select label="Map warning →" variant="filled" fullWidth>
                            <MenuItem value="">—</MenuItem>
                            {INCIDENT_SEVERITIES.map((severity) => (
                              <MenuItem key={severity} value={severity}>
                                {severity}
                              </MenuItem>
                            ))}
                          </TextField>
                        )}
                      />
                    </Grid>
                  </Grid>
                </AccordionDetails>
              </Accordion>
            </Grid>

            <Grid size={12}>
              <Accordion defaultExpanded disableGutters>
                <AccordionSummary expandIcon={<HiChevronDown />}>
                  <Typography sx={{ fontWeight: 700 }}>Severity Rules</Typography>
                </AccordionSummary>
                <AccordionDetails>
                  {typeof errors.rules?.message === "string" && (
                    <Typography variant="caption" color="error" sx={{ mb: 1, display: "block" }}>
                      {errors.rules.message}
                    </Typography>
                  )}
                  <Stack spacing={1.5}>
                    {INCIDENT_SEVERITIES.map((severity) => (
                      <Accordion key={severity} disableGutters>
                        <AccordionSummary expandIcon={<HiChevronDown />}>
                          <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
                            <Controller
                              control={control}
                              name={`rules.${severity}.enabled`}
                              render={({ field }) => (
                                <Checkbox
                                  checked={field.value}
                                  onChange={(_, checked) => field.onChange(checked)}
                                  onClick={(event) => event.stopPropagation()}
                                />
                              )}
                            />
                            <Typography sx={{ fontWeight: 700 }}>{severity}</Typography>
                            {!rulesWatch?.[severity]?.enabled && (
                              <Typography variant="caption" sx={{ color: "text.disabled" }}>
                                Disabled
                              </Typography>
                            )}
                          </Stack>
                        </AccordionSummary>
                        <AccordionDetails>
                          <Grid container spacing={2}>
                            <Grid size={4}>
                              <TextField
                                label="Ack within (min)"
                                variant="filled"
                                type="number"
                                fullWidth
                                {...register(`rules.${severity}.ackWithinMinutes`)}
                              />
                            </Grid>
                            <Grid size={4}>
                              <TextField
                                label="Resolve within (min)"
                                variant="filled"
                                type="number"
                                fullWidth
                                {...register(`rules.${severity}.resolveWithinMinutes`)}
                              />
                            </Grid>
                            <Grid size={4} sx={{ display: "flex", alignItems: "center" }}>
                              <Controller
                                control={control}
                                name={`rules.${severity}.requireCommander`}
                                render={({ field }) => (
                                  <FormControlLabel
                                    control={
                                      <Checkbox
                                        checked={field.value}
                                        onChange={(_, checked) => field.onChange(checked)}
                                      />
                                    }
                                    label="Require commander"
                                  />
                                )}
                              />
                            </Grid>
                            <Grid size={12}>
                              <Controller
                                control={control}
                                name={`rules.${severity}.notifyEndpointIds`}
                                render={({ field }) => (
                                  <Autocomplete
                                    multiple
                                    options={endpoints ?? []}
                                    value={(endpoints ?? []).filter((endpoint) =>
                                      field.value.includes(endpoint.id)
                                    )}
                                    getOptionLabel={(option) => option.name}
                                    isOptionEqualToValue={(option, value) => option.id === value.id}
                                    onChange={(_, value) =>
                                      field.onChange(value.map((item) => item.id))
                                    }
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
                                        label="Notify Endpoints"
                                      />
                                    )}
                                  />
                                )}
                              />
                            </Grid>
                            <Grid size={8}>
                              <TextField
                                label="On-call Plan ID"
                                variant="filled"
                                fullWidth
                                {...register(`rules.${severity}.onCallPlanId`)}
                              />
                            </Grid>
                            <Grid size={4} sx={{ display: "flex", alignItems: "center" }}>
                              <Controller
                                control={control}
                                name={`rules.${severity}.useLayers`}
                                render={({ field }) => (
                                  <FormControlLabel
                                    control={
                                      <Checkbox
                                        checked={field.value}
                                        onChange={(_, checked) => field.onChange(checked)}
                                      />
                                    }
                                    label="Use layers"
                                  />
                                )}
                              />
                            </Grid>
                            <Grid size={6}>
                              <TextField
                                label="Stakeholder update every (min)"
                                variant="filled"
                                type="number"
                                fullWidth
                                {...register(`rules.${severity}.stakeholderUpdateEveryMinutes`)}
                              />
                            </Grid>
                            <Grid size={6} sx={{ display: "flex", alignItems: "center" }}>
                              <Controller
                                control={control}
                                name={`rules.${severity}.statusPageUpdateRequired`}
                                render={({ field }) => (
                                  <FormControlLabel
                                    control={
                                      <Checkbox
                                        checked={field.value}
                                        onChange={(_, checked) => field.onChange(checked)}
                                      />
                                    }
                                    label="Status page update required"
                                  />
                                )}
                              />
                            </Grid>
                            <Grid size={4} sx={{ display: "flex", alignItems: "center" }}>
                              <Controller
                                control={control}
                                name={`rules.${severity}.postmortemRequired`}
                                render={({ field }) => (
                                  <FormControlLabel
                                    control={
                                      <Checkbox
                                        checked={field.value}
                                        onChange={(_, checked) => field.onChange(checked)}
                                      />
                                    }
                                    label="Postmortem required"
                                  />
                                )}
                              />
                            </Grid>
                            <Grid size={4}>
                              <TextField
                                label="Postmortem due (days)"
                                variant="filled"
                                type="number"
                                fullWidth
                                {...register(`rules.${severity}.postmortemDueDays`)}
                              />
                            </Grid>
                            <Grid size={4} sx={{ display: "flex", alignItems: "center" }}>
                              <Controller
                                control={control}
                                name={`rules.${severity}.postmortemReviewRequired`}
                                render={({ field }) => (
                                  <FormControlLabel
                                    control={
                                      <Checkbox
                                        checked={field.value}
                                        onChange={(_, checked) => field.onChange(checked)}
                                      />
                                    }
                                    label="Review required"
                                  />
                                )}
                              />
                            </Grid>
                            <Grid size={12}>
                              <Controller
                                control={control}
                                name={`rules.${severity}.runbookNames`}
                                render={({ field }) => (
                                  <Autocomplete
                                    multiple
                                    freeSolo
                                    options={runbookNameOptions}
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
                                        label="Runbook Names"
                                        helperText="Select published runbooks or type a name"
                                      />
                                    )}
                                  />
                                )}
                              />
                            </Grid>
                          </Grid>
                        </AccordionDetails>
                      </Accordion>
                    ))}
                  </Stack>
                </AccordionDetails>
              </Accordion>
            </Grid>

            <Grid size={12}>
              <GradientSubmitButton type="submit" fullWidth loading={isCreating || isUpdating}>
                {isCreate ? "Create" : "Save"}
              </GradientSubmitButton>
            </Grid>
          </Grid>
        )}
      </IncidentPolicyModalBody>
    </ModalContainer>
  );
}
