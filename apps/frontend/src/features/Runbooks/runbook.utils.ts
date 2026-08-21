import { format } from "date-fns";

import type { RunbookSourceType, RunbookStatus } from "./runbook.type";

export const RUNBOOK_STATUS_LABELS: Record<RunbookStatus, string> = {
  draft: "Draft",
  published: "Published",
  archived: "Archived"
};

export const RUNBOOK_STATUS_COLORS: Record<RunbookStatus, string> = {
  draft: "#E08A3A",
  published: "#3FA85A",
  archived: "#A08B74"
};

export const RUNBOOK_SOURCE_TYPE_LABELS: Record<RunbookSourceType, string> = {
  steps: "Steps",
  markdown: "Markdown",
  externalUrl: "External URL"
};

export const RUNBOOK_SOURCE_TYPE_COLORS: Record<RunbookSourceType, string> = {
  steps: "#5B8DEF",
  markdown: "#8B6BC4",
  externalUrl: "#C4A07A"
};

export const RUNBOOK_DATE_TIME_FORMAT = "yyyy/MM/dd HH:mm";

export function formatRunbookDateTime(value?: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "—" : format(date, RUNBOOK_DATE_TIME_FORMAT);
}
