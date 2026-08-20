export const INCIDENT_STATUSES = ["open", "investigating", "resolved"] as const;
export type IncidentStatus = (typeof INCIDENT_STATUSES)[number];

export const INCIDENT_SEVERITIES = ["SEV1", "SEV2", "SEV3", "SEV4"] as const;
export type IncidentSeverity = (typeof INCIDENT_SEVERITIES)[number];

export const INCIDENT_SOURCES = ["manual"] as const;
export type IncidentSource = (typeof INCIDENT_SOURCES)[number] | (string & {});

export interface IIncidentUserRef {
  id: string;
  name: string;
}

export interface IIncidentAcknowledgement {
  id?: string;
  teamId?: string;
  userId?: string;
  user?: IIncidentUserRef;
  acknowledgedAt?: string;
}

export interface IIncidentTeam {
  id: string;
  name: string;
  onCallPlan?: { id: string; name: string } | null;
  acknowledgement?: IIncidentAcknowledgement | null;
}

export interface IIncidentAlertRule {
  id: string;
  name: string;
}

export interface IIncidentCounts {
  timelineEntries: number;
  documents: number;
  actionItems: number;
  openActionItems: number;
}

export interface IIncident {
  id: string;
  title: string;
  description?: string | null;
  severity: IncidentSeverity;
  status: IncidentStatus;
  source: IncidentSource;
  startedAt: string;
  detectedAt: string;
  resolvedAt: string | null;
  teamIds: string[];
  tags: string[];
  alertRuleIds: string[];
  createdBy: string;
  createdByUser?: IIncidentUserRef | null;
  resolvedBy?: string | null;
  acknowledgements: IIncidentAcknowledgement[];
  teams: IIncidentTeam[];
  alertRules: IIncidentAlertRule[];
  postMortem?: unknown | null;
  counts?: IIncidentCounts | null;
  canEdit: boolean;
  canDelete: boolean;
  canAcknowledge: boolean;
  canResolve: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface IIncidentCreateRequest {
  title: string;
  severity: IncidentSeverity;
  teamIds: string[];
  description?: string;
  tags?: string[];
  startedAt?: string;
  detectedAt?: string;
  resolvedAt?: string;
  alertRuleIds?: string[];
}

export interface IIncidentUpdateRequest {
  title: string;
  description: string;
  severity: IncidentSeverity;
  teamIds: string[];
  tags: string[];
  startedAt: string;
  detectedAt: string;
  alertRuleIds: string[];
}

export interface IIncidentResolveRequest {
  resolvedAt?: string;
}

export interface IIncidentFilters {
  status?: IncidentStatus | "";
  severity?: IncidentSeverity | "";
  teamId?: string;
  tag?: string;
}
