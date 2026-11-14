"use client";

import type { ReactNode } from "react";

import {
  Box,
  Chip,
  CircularProgress,
  Divider,
  Grid,
  Link,
  Stack,
  Typography,
  alpha,
  useTheme
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import { getRunbookById } from "../runbook.api";
import type { IRunbook } from "../runbook.type";
import { formatRunbookDateTime } from "../runbook.utils";

import RunbookModalBody from "./RunbookModalBody";
import RunbookSourceTypeChip from "./RunbookSourceTypeChip";
import RunbookStatusChip from "./RunbookStatusChip";

type RunbookDetailsModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  runbookId: IRunbook["id"];
};

function GroupHeading({ children }: { children: ReactNode }) {
  return (
    <Typography
      variant="overline"
      sx={{
        display: "block",
        color: "text.secondary",
        fontWeight: 700,
        fontSize: "0.7rem",
        letterSpacing: "0.1em",
        lineHeight: 1.3
      }}
    >
      {children}
    </Typography>
  );
}

function FieldLabel({ children }: { children: ReactNode }) {
  return (
    <Typography
      variant="caption"
      sx={{
        color: "text.disabled",
        fontWeight: 500,
        fontSize: "0.7rem",
        letterSpacing: "0.04em"
      }}
    >
      {children}
    </Typography>
  );
}

function MetaItem({ label, value }: { label: string; value: ReactNode }) {
  return (
    <Stack spacing={0.35} sx={{ minWidth: 0 }}>
      <FieldLabel>{label}</FieldLabel>
      <Typography
        variant="body1"
        sx={{
          fontWeight: 600,
          fontSize: "0.9375rem",
          lineHeight: 1.35,
          wordBreak: "break-word",
          color: "text.primary"
        }}
      >
        {value || "—"}
      </Typography>
    </Stack>
  );
}

function ChipGroup({ label, items }: { label: string; items: string[] }) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  return (
    <Stack spacing={0.75} sx={{ alignItems: "flex-start" }}>
      <FieldLabel>{label}</FieldLabel>
      {items.length === 0 ? (
        <Typography variant="body2" sx={{ color: "text.disabled", fontSize: "0.875rem" }}>
          —
        </Typography>
      ) : (
        <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
          {items.map((item) => (
            <Chip
              key={item}
              label={item}
              size="small"
              sx={{
                height: 28,
                fontWeight: 600,
                backgroundColor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
                border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
                borderRadius: "10px"
              }}
            />
          ))}
        </Stack>
      )}
    </Stack>
  );
}

export default function RunbookDetailsModal({
  open,
  onClose,
  runbookId
}: RunbookDetailsModalProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  const {
    data: runbook,
    isPending,
    isError,
    error
  } = useQuery({
    queryKey: ["runbook", runbookId],
    queryFn: () => getRunbookById(runbookId),
    enabled: open && Boolean(runbookId)
  });

  return (
    <ModalContainer
      title={runbook?.name ?? "Runbook Details"}
      open={open}
      onClose={onClose}
      maxWidth={720}
      paperSx={{
        ...getGlassCardSx(theme, isDark),
        maxHeight: "90vh",
        display: "flex",
        flexDirection: "column"
      }}
    >
      <RunbookModalBody>
        {isError ? (
          <Typography color="error" sx={{ py: 2 }}>
            {error instanceof Error ? error.message : "Failed to load runbook."}
          </Typography>
        ) : isPending || !runbook ? (
          <Stack sx={{ alignItems: "center", py: 6 }}>
            <CircularProgress size={32} />
          </Stack>
        ) : (
          <Box sx={{ maxHeight: "min(70vh, 640px)", overflowY: "auto", minWidth: 0, pr: 0.5 }}>
            <Stack
              spacing={2}
              divider={
                <Divider sx={{ borderColor: alpha(palette.primary.main, isDark ? 0.12 : 0.16) }} />
              }
            >
              <Stack spacing={1.5}>
                <GroupHeading>Details</GroupHeading>
                <Grid container spacing={2}>
                  <Grid size={6}>
                    <Stack spacing={0.35} sx={{ minWidth: 0, alignItems: "flex-start" }}>
                      <FieldLabel>Status</FieldLabel>
                      <RunbookStatusChip status={runbook.status} />
                    </Stack>
                  </Grid>
                  <Grid size={6}>
                    <Stack spacing={0.35} sx={{ minWidth: 0, alignItems: "flex-start" }}>
                      <FieldLabel>Source Type</FieldLabel>
                      <RunbookSourceTypeChip sourceType={runbook.sourceType} />
                    </Stack>
                  </Grid>
                  <Grid size={6}>
                    <MetaItem label="Slug" value={runbook.slug} />
                  </Grid>
                  <Grid size={6}>
                    <MetaItem
                      label="Review Interval"
                      value={
                        runbook.reviewIntervalDays != null
                          ? `${runbook.reviewIntervalDays} days`
                          : "—"
                      }
                    />
                  </Grid>
                  <Grid size={6}>
                    <MetaItem label="Version" value={runbook.version != null ? String(runbook.version) : "—"} />
                  </Grid>
                  <Grid size={6}>
                    <MetaItem
                      label="Updated At"
                      value={formatRunbookDateTime(runbook.updatedAt)}
                    />
                  </Grid>
                </Grid>
                <Stack spacing={0.35}>
                  <FieldLabel>Description</FieldLabel>
                  <Typography
                    variant="body1"
                    sx={{
                      whiteSpace: "pre-wrap",
                      color: runbook.description ? "text.primary" : "text.disabled"
                    }}
                  >
                    {runbook.description?.trim() || "—"}
                  </Typography>
                </Stack>
              </Stack>

              <Stack spacing={1.5}>
                <GroupHeading>Related</GroupHeading>
                <ChipGroup
                  label="Teams"
                  items={
                    runbook.teams?.map((team) => team.name) ??
                    runbook.teamIds ??
                    []
                  }
                />
                <ChipGroup label="Tags" items={runbook.tags ?? []} />
                <ChipGroup label="Applies Tags" items={runbook.appliesTo?.tags ?? []} />
                <ChipGroup
                  label="Applies Severities"
                  items={runbook.appliesTo?.severities ?? []}
                />
              </Stack>

              <Stack spacing={1.5}>
                <GroupHeading>Content</GroupHeading>
                {runbook.sourceType === "steps" && (
                  <Stack spacing={1}>
                    {(runbook.steps ?? []).map((step, index) => (
                      <Box
                        key={`${step.title}-${index}`}
                        sx={{
                          p: 1.5,
                          borderRadius: 2,
                          border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`
                        }}
                      >
                        <Typography sx={{ fontWeight: 700 }}>
                          {index + 1}. {step.title}
                        </Typography>
                        {step.description && (
                          <Typography variant="body2" sx={{ mt: 0.5, color: "text.secondary" }}>
                            {step.description}
                          </Typography>
                        )}
                        {step.command && (
                          <Typography
                            variant="body2"
                            component="pre"
                            sx={{ mt: 1, fontFamily: "monospace", whiteSpace: "pre-wrap" }}
                          >
                            {step.command}
                          </Typography>
                        )}
                        {step.expectedResult && (
                          <Typography variant="body2" sx={{ mt: 0.75, color: "text.secondary" }}>
                            Expected: {step.expectedResult}
                          </Typography>
                        )}
                      </Box>
                    ))}
                  </Stack>
                )}
                {runbook.sourceType === "markdown" && (
                  <Typography
                    variant="body2"
                    component="pre"
                    sx={{ whiteSpace: "pre-wrap", fontFamily: "inherit" }}
                  >
                    {runbook.content || "—"}
                  </Typography>
                )}
                {runbook.sourceType === "externalUrl" &&
                  (runbook.externalUrl ? (
                    <Link href={runbook.externalUrl} target="_blank" rel="noopener noreferrer">
                      {runbook.externalUrl}
                    </Link>
                  ) : (
                    "—"
                  ))}
              </Stack>
            </Stack>
          </Box>
        )}
      </RunbookModalBody>
    </ModalContainer>
  );
}
