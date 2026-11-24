"use client";

import { type ReactNode, useState } from "react";

import {
  Box,
  Chip,
  IconButton,
  Skeleton,
  Stack,
  Tooltip,
  Typography
} from "@mui/material";
import { BsCheck2, BsCopy } from "react-icons/bs";
import { FaCheck } from "react-icons/fa6";
import { HiDatabase } from "react-icons/hi";

import type { IAlertRule } from "@/@types/alertRule";

export const EMPTY_VALUE = "—";

export function DetailEmpty({ text = EMPTY_VALUE }: { text?: string }) {
  return (
    <Typography
      variant="body2"
      sx={{
        color: "text.disabled",
        fontSize: "0.8125rem",
        fontStyle: "italic",
        letterSpacing: "0.02em"
      }}
    >
      {text}
    </Typography>
  );
}

export function DetailFieldText({
  text,
  mono = false
}: {
  text: string;
  mono?: boolean;
}) {
  const isEmpty = !text || text === EMPTY_VALUE;
  return (
    <Typography
      variant="body2"
      sx={{
        fontWeight: 600,
        fontSize: "0.875rem",
        lineHeight: 1.4,
        wordBreak: "break-word",
        color: isEmpty ? "text.disabled" : "text.primary",
        fontFamily: mono ? "var(--font-mono, monospace)" : "inherit"
      }}
    >
      {isEmpty ? EMPTY_VALUE : text}
    </Typography>
  );
}

export function DetailBooleanBadge({
  value,
  trueLabel = "Enabled",
  falseLabel = "Disabled"
}: {
  value: boolean;
  trueLabel?: string;
  falseLabel?: string;
}) {
  return (
    <Chip
      size="small"
      icon={
        value ? (
          <FaCheck style={{ fontSize: "0.65rem" }} />
        ) : undefined
      }
      label={value ? trueLabel : falseLabel}
      sx={{
        height: 24,
        borderRadius: "7px",
        fontWeight: 600,
        fontSize: "0.75rem",
        bgcolor: value
          ? "color-mix(in srgb, var(--mui-palette-success-main) 12%, transparent)"
          : "color-mix(in srgb, var(--mui-palette-text-disabled) 12%, transparent)",
        color: value ? "success.main" : "text.secondary",
        border: "1px solid",
        borderColor: value
          ? "color-mix(in srgb, var(--mui-palette-success-main) 25%, transparent)"
          : "color-mix(in srgb, var(--mui-palette-text-disabled) 25%, transparent)",
        "& .MuiChip-icon": {
          color: "success.main"
        }
      }}
    />
  );
}

export function DetailValueChips({
  values,
  icon,
  emptyText = EMPTY_VALUE
}: {
  values: string[];
  icon?: ReactNode;
  emptyText?: string;
}) {
  if (!values || values.length === 0) {
    return <DetailEmpty text={emptyText} />;
  }

  return (
    <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
      {values.map((value, index) => (
        <Chip
          key={`${value}-${index}`}
          icon={icon as React.ReactElement}
          label={value}
          size="small"
          sx={{
            height: 26,
            borderRadius: "7px",
            fontSize: "0.8125rem",
            fontWeight: 500,
            bgcolor:
              "color-mix(in srgb, var(--mui-palette-primary-main) 9%, transparent)",
            color: "text.primary",
            border: "1px solid",
            borderColor:
              "color-mix(in srgb, var(--mui-palette-primary-main) 18%, transparent)",
            transition: "all 0.15s ease",
            "&:hover": {
              bgcolor:
                "color-mix(in srgb, var(--mui-palette-primary-main) 16%, transparent)",
              borderColor:
                "color-mix(in srgb, var(--mui-palette-primary-main) 30%, transparent)"
            },
            "& .MuiChip-icon": {
              color: "primary.main",
              fontSize: "0.875rem"
            }
          }}
        />
      ))}
    </Stack>
  );
}

export function QueryCodeBlock({ query }: { query: string }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    try {
      await window.navigator.clipboard.writeText(query);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error("Failed to copy query to clipboard", err);
    }
  }

  return (
    <Box
      sx={{
        width: "100%",
        borderRadius: 2,
        overflow: "hidden",
        border: "1px solid",
        borderColor:
          "color-mix(in srgb, var(--mui-palette-divider) 80%, transparent)",
        bgcolor: (theme) =>
          theme.palette.mode === "dark"
            ? "color-mix(in srgb, var(--mui-palette-common-black) 40%, var(--mui-palette-background-paper))"
            : "#FAF7F2"
      }}
    >
      <Stack
        direction="row"
        sx={{
          justifyContent: "space-between",
          alignItems: "center",
          px: 1.5,
          py: 0.75,
          borderBottom: "1px solid",
          borderColor:
            "color-mix(in srgb, var(--mui-palette-divider) 60%, transparent)",
          bgcolor: (theme) =>
            theme.palette.mode === "dark"
              ? "rgba(0,0,0,0.2)"
              : "color-mix(in srgb, var(--mui-palette-primary-main) 4%, #F3EEE6)"
        }}
      >
        <Typography
          variant="caption"
          sx={{
            fontWeight: 700,
            fontSize: "0.6875rem",
            letterSpacing: "0.06em",
            textTransform: "uppercase",
            color: "text.secondary"
          }}
        >
          Query Expression
        </Typography>
        <Tooltip title={copied ? "Copied!" : "Copy Query"} placement="left" arrow>
          <span>
            <IconButton
              size="small"
              onClick={handleCopy}
              aria-label="Copy query"
              sx={{
                width: 26,
                height: 26,
                borderRadius: "6px",
                color: copied ? "success.main" : "text.secondary",
                "&:hover": {
                  color: "primary.main",
                  bgcolor:
                    "color-mix(in srgb, var(--mui-palette-primary-main) 12%, transparent)"
                }
              }}
            >
              {copied ? <BsCheck2 size="0.95rem" /> : <BsCopy size="0.8rem" />}
            </IconButton>
          </span>
        </Tooltip>
      </Stack>
      <Box
        component="pre"
        sx={{
          m: 0,
          p: 1.75,
          fontSize: "0.8125rem",
          fontFamily: "var(--font-mono, 'Roboto Mono', 'Fira Code', monospace)",
          lineHeight: 1.55,
          overflowX: "auto",
          maxHeight: 280,
          color: "text.primary",
          whiteSpace: "pre-wrap",
          wordBreak: "break-all"
        }}
      >
        <code>{query}</code>
      </Box>
    </Box>
  );
}

export function ExtraFieldsChips({
  pairs
}: {
  pairs: Array<{ key: string; value: string }>;
}) {
  const validPairs = pairs.filter((p) => p.key || p.value);
  if (validPairs.length === 0) {
    return <DetailEmpty text="No extra fields" />;
  }

  return (
    <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
      {validPairs.map((pair, index) => (
        <Chip
          key={`${pair.key}-${index}`}
          label={
            <Box component="span" sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}>
              <Box component="span" sx={{ fontWeight: 600, color: "text.secondary" }}>
                {pair.key}:
              </Box>
              <Box component="span" sx={{ fontWeight: 700, color: "text.primary" }}>
                {pair.value || EMPTY_VALUE}
              </Box>
            </Box>
          }
          size="small"
          sx={{
            height: 26,
            borderRadius: "7px",
            fontSize: "0.78125rem",
            bgcolor:
              "color-mix(in srgb, var(--mui-palette-secondary-main) 10%, transparent)",
            border: "1px solid",
            borderColor:
              "color-mix(in srgb, var(--mui-palette-secondary-main) 20%, transparent)"
          }}
        />
      ))}
    </Stack>
  );
}

interface DetailFieldValueProps {
  fieldKey: string;
  alert: IAlertRule;
  resolveNames: (fieldKey: string, ids: string[]) => string[];
  formatValue?: (fieldKey: string, value: unknown) => string;
  isLoadingNames?: boolean;
}

export function DetailFieldValue({
  fieldKey,
  alert,
  resolveNames,
  formatValue,
  isLoadingNames = false
}: DetailFieldValueProps) {
  const value = alert[fieldKey as keyof IAlertRule] as unknown;
  const needsNames =
    fieldKey === "endpointIds" ||
    fieldKey === "userIds" ||
    fieldKey === "teamIds" ||
    fieldKey === "dataSourceIds" ||
    fieldKey === "dataSourceId";

  if (isLoadingNames && needsNames) {
    return (
      <Stack direction="row" spacing={0.75} sx={{ flexWrap: "wrap" }}>
        <Skeleton variant="rounded" width={90} height={26} />
        <Skeleton variant="rounded" width={70} height={26} />
      </Stack>
    );
  }

  if (fieldKey === "queryString" && typeof value === "string" && value.trim()) {
    return <QueryCodeBlock query={value} />;
  }

  if (fieldKey === "description" && typeof value === "string") {
    if (!value.trim()) return <DetailEmpty text="No description provided" />;
    return (
      <Typography
        variant="body2"
        sx={{
          color: "text.primary",
          lineHeight: 1.55,
          fontSize: "0.875rem",
          whiteSpace: "pre-wrap"
        }}
      >
        {value}
      </Typography>
    );
  }

  if (typeof value === "boolean") {
    return <DetailBooleanBadge value={value} />;
  }

  if (fieldKey === "extraField" && Array.isArray(value)) {
    return (
      <ExtraFieldsChips
        pairs={value as Array<{ key: string; value: string }>}
      />
    );
  }

  if (Array.isArray(value)) {
    const items = value.map((item) => String(item));
    const resolved = needsNames ? resolveNames(fieldKey, items) : items;

    let icon: ReactNode = undefined;
    if (fieldKey === "dataSourceIds") {
      icon = <HiDatabase />;
    }

    return <DetailValueChips values={resolved} icon={icon} />;
  }

  if (fieldKey === "dataSourceId" && typeof value === "string" && value) {
    const resolved = resolveNames(fieldKey, [value]);
    return (
      <DetailValueChips
        values={[resolved[0] ?? value]}
        icon={<HiDatabase />}
      />
    );
  }

  const formatted = formatValue ? formatValue(fieldKey, value) : String(value ?? EMPTY_VALUE);
  return <DetailFieldText text={formatted} />;
}
