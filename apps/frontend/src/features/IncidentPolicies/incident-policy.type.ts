import type { IncidentSeverity } from "@/features/Incidents/incident.type";
import type { DataSourceType } from "@/utils/dataSourceUtils";

export const INCIDENT_POLICY_SOURCES = ["api", "yaml"] as const;
export type IncidentPolicySource = (typeof INCIDENT_POLICY_SOURCES)[number];

export const INCIDENT_POLICY_GROUPING_KEYS = [
  "serviceId",
  "alertRuleId",
  "tag",
  "dataSourceType"
] as const;
export type IncidentPolicyGroupingKey = (typeof INCIDENT_POLICY_GROUPING_KEYS)[number];

export interface IIncidentPolicyMatch {
  alertRuleIds: string[];
  tags: string[];
  serviceIds: string[];
  dataSourceTypes: Array<DataSourceType | string>;
}

export interface IIncidentPolicyGrouping {
  key: IncidentPolicyGroupingKey[];
  windowMinutes: number;
}

export interface IIncidentPolicyIncidentDefaults {
  autoCreate: boolean;
  autoResolveOnAlertClear: boolean;
  titleTemplate?: string;
  defaultSeverity: IncidentSeverity;
  severityMap?: Record<string, IncidentSeverity>;
}

export interface IIncidentPolicyEscalation {
  onCallPlanId?: string;
  useLayers?: boolean;
}

export interface IIncidentPolicyCommunication {
  stakeholderUpdateEveryMinutes?: number;
  statusPageUpdateRequired?: boolean;
}

export interface IIncidentPolicyPostmortemRule {
  required?: boolean;
  dueDays?: number;
  reviewRequired?: boolean;
}

export interface IIncidentPolicySeverityRule {
  ackWithinMinutes?: number;
  resolveWithinMinutes?: number;
  requireCommander?: boolean;
  notifyEndpointIds?: string[];
  escalation?: IIncidentPolicyEscalation;
  communication?: IIncidentPolicyCommunication;
  postmortem?: IIncidentPolicyPostmortemRule;
  runbookNames?: string[];
  runbookIds?: string[];
}

export type IncidentPolicyRules = Partial<Record<IncidentSeverity, IIncidentPolicySeverityRule>>;

export interface IIncidentPolicy {
  id: string;
  name: string;
  description?: string | null;
  enabled: boolean;
  source?: IncidentPolicySource | string;
  version?: number;
  ownerId?: string | null;
  owner?: { id: string; name: string } | null;
  teamIds: string[];
  teams?: Array<{ id: string; name: string }>;
  match: IIncidentPolicyMatch;
  grouping?: IIncidentPolicyGrouping;
  incident?: IIncidentPolicyIncidentDefaults;
  rules: IncidentPolicyRules;
  createdAt?: string;
  updatedAt?: string;
}

export interface IIncidentPolicyWriteRequest {
  name: string;
  description?: string;
  enabled: boolean;
  ownerId?: string;
  teamIds: string[];
  match: IIncidentPolicyMatch;
  grouping?: IIncidentPolicyGrouping;
  incident?: IIncidentPolicyIncidentDefaults;
  rules: IncidentPolicyRules;
}

export interface IIncidentPolicyFilters {
  enabled?: "" | "true" | "false";
  teamId?: string;
}

export interface IIncidentPolicyYamlResult {
  valid?: boolean;
  dryRun?: boolean;
  created?: Array<{ name: string; id: string; version: number }>;
  updated?: Array<{ name: string; id: string; version: number }>;
  unchanged?: Array<{ name: string; id: string; version: number }>;
  errors?: Array<{ path?: string; message: string }>;
  message?: string;
  status?: boolean;
}
