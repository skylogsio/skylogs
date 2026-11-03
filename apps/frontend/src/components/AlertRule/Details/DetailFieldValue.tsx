import { Chip, Skeleton, Stack, Typography } from "@mui/material";

import type { IAlertRule } from "@/@types/alertRule";

const EMPTY_VALUE = "—";

export function DetailEmpty() {
  return (
    <Typography variant="body2" sx={{ color: "text.disabled", fontSize: "0.875rem" }}>
      {EMPTY_VALUE}
    </Typography>
  );
}

function formatScalar(value: unknown): string {
  if (value === null || value === undefined || value === "") {
    return EMPTY_VALUE;
  }
  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? EMPTY_VALUE : value.toLocaleString();
  }
  return String(value);
}

export function DetailValueChips({ values }: { values: string[] }) {
  if (values.length === 0) {
    return <DetailEmpty />;
  }
  return (
    <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
      {values.map((value, index) => (
        <Chip key={`${value}-${index}`} variant="filled" label={value} size="small" />
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

export function DetailFieldText({ text }: { text: string }) {
  const isEmpty = text === EMPTY_VALUE;
  return (
    <Typography
      variant="body1"
      sx={{
        fontWeight: 600,
        fontSize: "0.9375rem",
        lineHeight: 1.35,
        wordBreak: "break-word",
        color: isEmpty ? "text.disabled" : "text.primary"
      }}
    >
      {text}
    </Typography>
  );
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
        <Skeleton variant="rounded" width={90} height={24} />
        <Skeleton variant="rounded" width={70} height={24} />
      </Stack>
    );
  }

  if (Array.isArray(value)) {
    if (fieldKey === "extraField") {
      const pairs = (value as Array<{ key: string; value: string }>).filter(
        (pair) => pair.key || pair.value
      );
      if (pairs.length === 0) {
        return <DetailEmpty />;
      }
      return (
        <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
          {pairs.map((pair, index) => (
            <Chip
              key={`${pair.key}-${index}`}
              variant="filled"
              label={`${pair.key}: ${pair.value}`}
              size="small"
            />
          ))}
        </Stack>
      );
    }
    const items = value.map((item) => String(item));
    const resolved = needsNames ? resolveNames(fieldKey, items) : items;
    return <DetailValueChips values={resolved} />;
  }

  if (fieldKey === "dataSourceId" && typeof value === "string" && value) {
    const resolved = resolveNames(fieldKey, [value]);
    return <DetailFieldText text={resolved[0] ?? value} />;
  }

  const formatted = formatValue ? formatValue(fieldKey, value) : formatScalar(value);
  return <DetailFieldText text={formatted} />;
}
