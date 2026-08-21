import type { IncidentSeverity } from "@/features/Incidents/incident.type";

export const RUNBOOK_STATUSES = ["draft", "published", "archived"] as const;
export type RunbookStatus = (typeof RUNBOOK_STATUSES)[number];

export const RUNBOOK_SOURCE_TYPES = ["steps", "markdown", "externalUrl"] as const;
export type RunbookSourceType = (typeof RUNBOOK_SOURCE_TYPES)[number];

export interface IRunbookStep {
  title: string;
  description?: string;
  command?: string;
  expectedResult?: string;
}

export interface IRunbookAppliesTo {
  alertRuleIds: string[];
  tags: string[];
  severities: IncidentSeverity[];
}

export interface IRunbook {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  teamIds: string[];
  tags: string[];
  status: RunbookStatus;
  sourceType: RunbookSourceType;
  steps?: IRunbookStep[];
  content?: string | null;
  externalUrl?: string | null;
  appliesTo?: IRunbookAppliesTo;
  reviewIntervalDays?: number | null;
  version?: number;
  createdAt?: string;
  updatedAt?: string;
  teams?: Array<{ id: string; name: string }>;
}

export interface IRunbookWriteRequest {
  name: string;
  slug?: string;
  description?: string;
  teamIds: string[];
  tags?: string[];
  status: RunbookStatus;
  sourceType: RunbookSourceType;
  steps?: IRunbookStep[];
  content?: string;
  externalUrl?: string;
  appliesTo?: IRunbookAppliesTo;
  reviewIntervalDays?: number | null;
}

export interface IRunbookFilters {
  status?: RunbookStatus | "";
  teamId?: string;
  tag?: string;
}

export interface IRunbookOption {
  id: string;
  name: string;
  slug: string;
}
