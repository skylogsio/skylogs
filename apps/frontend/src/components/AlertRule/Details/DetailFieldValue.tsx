import { Chip, Stack, Typography } from "@mui/material";

import type { IAlertRule } from "@/@types/alertRule";

const EMPTY_VALUE = "—";

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
    return <Typography>{EMPTY_VALUE}</Typography>;
  }
  return (
    <Stack direction="row" sx={{ flexWrap: "wrap", gap: 1 }}>
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
}

export function DetailFieldValue({
  fieldKey,
  alert,
  resolveNames,
  formatValue
}: DetailFieldValueProps) {
  const value = alert[fieldKey as keyof IAlertRule] as unknown;

  if (Array.isArray(value)) {
    if (fieldKey === "extraField") {
      const pairs = (value as Array<{ key: string; value: string }>).filter(
        (pair) => pair.key || pair.value
      );
      if (pairs.length === 0) {
        return <Typography>{EMPTY_VALUE}</Typography>;
      }
      return (
        <Stack direction="row" sx={{ flexWrap: "wrap", gap: 1 }}>
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
    const resolved =
      fieldKey === "endpointIds" ||
      fieldKey === "userIds" ||
      fieldKey === "teamIds" ||
      fieldKey === "dataSourceIds" ||
      fieldKey === "dataSourceId"
        ? resolveNames(fieldKey, items)
        : items;
    return <DetailValueChips values={resolved} />;
  }

  if (fieldKey === "dataSourceId" && typeof value === "string" && value) {
    const resolved = resolveNames(fieldKey, [value]);
    return <Typography>{resolved[0] ?? value}</Typography>;
  }

  const formatted = formatValue ? formatValue(fieldKey, value) : formatScalar(value);
  return <Typography>{formatted}</Typography>;
}
