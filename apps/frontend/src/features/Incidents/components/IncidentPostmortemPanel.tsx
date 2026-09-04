"use client";

import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Autocomplete,
  Button,
  Chip,
  CircularProgress,
  Grid,
  MenuItem,
  Stack,
  TextField,
  Typography
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Controller, useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import { getAllUsers } from "@/api/user";
import DateTimeInput from "@/components/DateTimeInput";
import GradientSubmitButton from "@/components/GradientSubmitButton";

import {
  getIncidentPostmortem,
  publishIncidentPostmortem,
  upsertIncidentPostmortem
} from "../incident.api";
import {
  ROOT_CAUSE_METHODS,
  type IIncident,
  type IPostMortemWriteRequest
} from "../incident.type";
import { formatIncidentDateTime } from "../incident.utils";

type IncidentPostmortemPanelProps = {
  incident: IIncident;
};

const postmortemSchema = z.object({
  summary: z.string().trim().min(1, "Summary is required."),
  impact: z.string(),
  detection: z.string(),
  resolution: z.string(),
  rootCauseMethod: z.enum(["", ...ROOT_CAUSE_METHODS] as [string, ...string[]]),
  rootCauseStatement: z.string(),
  whys: z.array(z.string()),
  contributingFactors: z.array(z.string()),
  whatWentWell: z.array(z.string()),
  whatWentWrong: z.array(z.string()),
  lessonsLearned: z.array(z.string()),
  authorId: z.string(),
  reviewerIds: z.array(z.string()),
  dueAt: z.string().nullable()
});

type PostmortemFormType = z.infer<typeof postmortemSchema>;

const emptyValues: PostmortemFormType = {
  summary: "",
  impact: "",
  detection: "",
  resolution: "",
  rootCauseMethod: "",
  rootCauseStatement: "",
  whys: [],
  contributingFactors: [],
  whatWentWell: [],
  whatWentWrong: [],
  lessonsLearned: [],
  authorId: "",
  reviewerIds: [],
  dueAt: null
};

function toWritePayload(values: PostmortemFormType): IPostMortemWriteRequest {
  const body: IPostMortemWriteRequest = {
    status: "draft",
    summary: values.summary.trim()
  };

  if (values.impact.trim()) body.impact = values.impact.trim();
  if (values.detection.trim()) body.detection = values.detection.trim();
  if (values.resolution.trim()) body.resolution = values.resolution.trim();
  if (values.authorId) body.authorId = values.authorId;
  if (values.reviewerIds.length > 0) body.reviewerIds = values.reviewerIds;
  if (values.dueAt) body.dueAt = values.dueAt;
  if (values.whatWentWell.length > 0) body.whatWentWell = values.whatWentWell;
  if (values.whatWentWrong.length > 0) body.whatWentWrong = values.whatWentWrong;
  if (values.lessonsLearned.length > 0) body.lessonsLearned = values.lessonsLearned;

  if (
    values.rootCauseMethod ||
    values.rootCauseStatement.trim() ||
    values.whys.length > 0 ||
    values.contributingFactors.length > 0
  ) {
    body.rootCause = {
      ...(values.rootCauseMethod
        ? { method: values.rootCauseMethod as (typeof ROOT_CAUSE_METHODS)[number] }
        : {}),
      ...(values.whys.length > 0 ? { whys: values.whys } : {}),
      ...(values.contributingFactors.length > 0
        ? { contributingFactors: values.contributingFactors }
        : {}),
      ...(values.rootCauseStatement.trim()
        ? { statement: values.rootCauseStatement.trim() }
        : {})
    };
  }

  return body;
}

function ChipArrayField({
  label,
  value,
  onChange,
  disabled
}: {
  label: string;
  value: string[];
  onChange: (value: string[]) => void;
  disabled?: boolean;
}) {
  return (
    <Autocomplete
      multiple
      freeSolo
      options={[]}
      value={value}
      disabled={disabled}
      onChange={(_, next) => onChange(next)}
      renderValue={(selected, getItemProps) =>
        selected.map((option, index) => {
          const { key, ...itemProps } = getItemProps({ index });
          return <Chip key={key} size="small" variant="filled" label={option} {...itemProps} />;
        })
      }
      renderInput={(params) => (
        <TextField
          {...params}
          variant="filled"
          label={label}
          placeholder="Type and press Enter"
        />
      )}
    />
  );
}

export default function IncidentPostmortemPanel({ incident }: IncidentPostmortemPanelProps) {
  const queryClient = useQueryClient();
  const canEdit = incident.canEdit;

  const {
    data: postmortem,
    isPending,
    isError,
    error,
    refetch
  } = useQuery({
    queryKey: ["incident-postmortem", incident.id],
    queryFn: () => getIncidentPostmortem(incident.id)
  });

  const { data: users } = useQuery({
    queryKey: ["all-users"],
    queryFn: () => getAllUsers(),
    enabled: canEdit
  });

  const {
    register,
    handleSubmit,
    reset,
    control,
    formState: { errors }
  } = useForm<PostmortemFormType>({
    resolver: zodResolver(postmortemSchema),
    defaultValues: emptyValues
  });

  useEffect(() => {
    if (!postmortem) {
      reset(emptyValues);
      return;
    }
    reset({
      summary: postmortem.summary ?? "",
      impact: postmortem.impact ?? "",
      detection: postmortem.detection ?? "",
      resolution: postmortem.resolution ?? "",
      rootCauseMethod: (postmortem.rootCause?.method as string) ?? "",
      rootCauseStatement: postmortem.rootCause?.statement ?? "",
      whys: postmortem.rootCause?.whys ?? [],
      contributingFactors: postmortem.rootCause?.contributingFactors ?? [],
      whatWentWell: postmortem.whatWentWell ?? [],
      whatWentWrong: postmortem.whatWentWrong ?? [],
      lessonsLearned: postmortem.lessonsLearned ?? [],
      authorId: postmortem.authorId ?? "",
      reviewerIds: postmortem.reviewerIds ?? [],
      dueAt: postmortem.dueAt ?? null
    });
  }, [postmortem, reset]);

  function invalidate() {
    void queryClient.invalidateQueries({ queryKey: ["incident-postmortem", incident.id] });
    void queryClient.invalidateQueries({ queryKey: ["incident", incident.id] });
  }

  const { mutate: saveMutation, isPending: isSaving } = useMutation({
    mutationFn: (body: IPostMortemWriteRequest) => upsertIncidentPostmortem(incident.id, body),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Postmortem saved.");
        invalidate();
        return;
      }
      toast.error(response.message);
    }
  });

  const { mutate: publishMutation, isPending: isPublishing } = useMutation({
    mutationFn: () => publishIncidentPostmortem(incident.id),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Postmortem published.");
        invalidate();
        return;
      }
      toast.error(response.message);
    }
  });

  if (isPending) {
    return (
      <Stack sx={{ alignItems: "center", py: 4 }}>
        <CircularProgress size={28} />
      </Stack>
    );
  }

  if (isError) {
    return (
      <Typography color="error">
        {error instanceof Error ? error.message : "Failed to load postmortem."}
      </Typography>
    );
  }

  if (!postmortem && !canEdit) {
    return (
      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        No postmortem yet.
      </Typography>
    );
  }

  if (!postmortem && canEdit) {
    return (
      <Stack spacing={1.5} sx={{ alignItems: "flex-start" }}>
        <Typography variant="body2" sx={{ color: "text.secondary" }}>
          No postmortem yet. Create a draft to start documenting the RCA.
        </Typography>
        <Button
          variant="outlined"
          sx={{ textTransform: "none", fontWeight: 600 }}
          onClick={() =>
            saveMutation({
              status: "draft",
              summary: `${incident.title} — postmortem draft`
            })
          }
          disabled={isSaving}
        >
          Create draft
        </Button>
      </Stack>
    );
  }

  const isPublished = postmortem?.status === "published";

  return (
    <Stack spacing={2}>
      <Stack direction="row" spacing={1} sx={{ alignItems: "center", flexWrap: "wrap" }}>
        <Chip
          size="small"
          label={String(postmortem?.status ?? "draft")}
          color={isPublished ? "success" : "default"}
          variant="outlined"
        />
        {postmortem?.publishedAt && (
          <Typography variant="caption" sx={{ color: "text.secondary" }}>
            Published {formatIncidentDateTime(postmortem.publishedAt)}
          </Typography>
        )}
        {postmortem?.dueAt && (
          <Typography variant="caption" sx={{ color: "text.secondary" }}>
            Due {formatIncidentDateTime(postmortem.dueAt)}
          </Typography>
        )}
      </Stack>

      <Grid
        component="form"
        onSubmit={handleSubmit((values) => saveMutation(toWritePayload(values)))}
        container
        spacing={2}
      >
        <Grid size={12}>
          <TextField
            label="Summary"
            variant="filled"
            fullWidth
            multiline
            minRows={2}
            disabled={!canEdit || isPublished}
            error={!!errors.summary}
            helperText={errors.summary?.message}
            {...register("summary")}
          />
        </Grid>
        <Grid size={12}>
          <TextField
            label="Impact"
            variant="filled"
            fullWidth
            multiline
            minRows={2}
            disabled={!canEdit || isPublished}
            {...register("impact")}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Detection"
            variant="filled"
            fullWidth
            multiline
            minRows={2}
            disabled={!canEdit || isPublished}
            {...register("detection")}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Resolution"
            variant="filled"
            fullWidth
            multiline
            minRows={2}
            disabled={!canEdit || isPublished}
            {...register("resolution")}
          />
        </Grid>
        <Grid size={6}>
          <Controller
            control={control}
            name="rootCauseMethod"
            render={({ field }) => (
              <TextField
                {...field}
                select
                label="Root Cause Method"
                variant="filled"
                fullWidth
                disabled={!canEdit || isPublished}
              >
                <MenuItem value="">—</MenuItem>
                {ROOT_CAUSE_METHODS.map((method) => (
                  <MenuItem key={method} value={method}>
                    {method}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />
        </Grid>
        <Grid size={6}>
          <Controller
            control={control}
            name="dueAt"
            render={({ field }) => (
              <DateTimeInput
                calendar="gregorian"
                type="date-time"
                label="Due At"
                value={field.value}
                onChange={(payload) => field.onChange(payload.iso)}
                disabled={!canEdit || isPublished}
              />
            )}
          />
        </Grid>
        <Grid size={12}>
          <TextField
            label="Root Cause Statement"
            variant="filled"
            fullWidth
            multiline
            minRows={2}
            disabled={!canEdit || isPublished}
            {...register("rootCauseStatement")}
          />
        </Grid>
        <Grid size={12}>
          <Controller
            control={control}
            name="whys"
            render={({ field }) => (
              <ChipArrayField
                label="Five Whys"
                value={field.value}
                onChange={field.onChange}
                disabled={!canEdit || isPublished}
              />
            )}
          />
        </Grid>
        <Grid size={12}>
          <Controller
            control={control}
            name="contributingFactors"
            render={({ field }) => (
              <ChipArrayField
                label="Contributing Factors"
                value={field.value}
                onChange={field.onChange}
                disabled={!canEdit || isPublished}
              />
            )}
          />
        </Grid>
        <Grid size={12}>
          <Controller
            control={control}
            name="whatWentWell"
            render={({ field }) => (
              <ChipArrayField
                label="What Went Well"
                value={field.value}
                onChange={field.onChange}
                disabled={!canEdit || isPublished}
              />
            )}
          />
        </Grid>
        <Grid size={12}>
          <Controller
            control={control}
            name="whatWentWrong"
            render={({ field }) => (
              <ChipArrayField
                label="What Went Wrong"
                value={field.value}
                onChange={field.onChange}
                disabled={!canEdit || isPublished}
              />
            )}
          />
        </Grid>
        <Grid size={12}>
          <Controller
            control={control}
            name="lessonsLearned"
            render={({ field }) => (
              <ChipArrayField
                label="Lessons Learned"
                value={field.value}
                onChange={field.onChange}
                disabled={!canEdit || isPublished}
              />
            )}
          />
        </Grid>
        <Grid size={6}>
          <Controller
            control={control}
            name="authorId"
            render={({ field }) => (
              <Autocomplete
                options={users ?? []}
                value={(users ?? []).find((user) => user.id === field.value) ?? null}
                getOptionLabel={(option) => option.name || option.username}
                isOptionEqualToValue={(option, value) => option.id === value.id}
                onChange={(_, value) => field.onChange(value?.id ?? "")}
                disabled={!canEdit || isPublished}
                renderInput={(params) => (
                  <TextField {...params} variant="filled" label="Author" />
                )}
              />
            )}
          />
        </Grid>
        <Grid size={6}>
          <Controller
            control={control}
            name="reviewerIds"
            render={({ field }) => (
              <Autocomplete
                multiple
                options={users ?? []}
                value={(users ?? []).filter((user) => field.value.includes(user.id))}
                getOptionLabel={(option) => option.name || option.username}
                isOptionEqualToValue={(option, value) => option.id === value.id}
                onChange={(_, value) => field.onChange(value.map((item) => item.id))}
                disabled={!canEdit || isPublished}
                renderValue={(value, getItemProps) =>
                  value.map((option, index) => {
                    const { key, ...itemProps } = getItemProps({ index });
                    return (
                      <Chip
                        key={key}
                        size="small"
                        variant="filled"
                        label={option.name || option.username}
                        {...itemProps}
                      />
                    );
                  })
                }
                renderInput={(params) => (
                  <TextField {...params} variant="filled" label="Reviewers" />
                )}
              />
            )}
          />
        </Grid>

        {canEdit && !isPublished && (
          <Grid size={12}>
            <Stack direction="row" spacing={1.5}>
              <GradientSubmitButton type="submit" fullWidth loading={isSaving}>
                Save draft
              </GradientSubmitButton>
              <Button
                fullWidth
                variant="outlined"
                disabled={isSaving || isPublishing}
                onClick={() => publishMutation()}
                sx={{ textTransform: "none", fontWeight: 600 }}
              >
                Publish
              </Button>
            </Stack>
          </Grid>
        )}

        {canEdit && isPublished && (
          <Grid size={12}>
            <Button
              variant="text"
              onClick={() => void refetch()}
              sx={{ textTransform: "none" }}
            >
              Refresh
            </Button>
          </Grid>
        )}
      </Grid>
    </Stack>
  );
}
