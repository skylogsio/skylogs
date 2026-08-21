export const INCIDENT_STATUSES = ["open", "investigating", "resolved"] as const;
export type IncidentStatus = (typeof INCIDENT_STATUSES)[number];

export const INCIDENT_SEVERITIES = ["SEV1", "SEV2", "SEV3", "SEV4"] as const;
export type IncidentSeverity = (typeof INCIDENT_SEVERITIES)[number];

export const INCIDENT_SOURCES = ["manual"] as const;
export type IncidentSource = (typeof INCIDENT_SOURCES)[number] | (string & {});

export const DOCUMENT_TYPES = [
  "screenshot",
  "log",
  "metric",
  "diagram",
  "report",
  "other"
] as const;
export type IncidentDocumentType = (typeof DOCUMENT_TYPES)[number];

export const DOCUMENT_ATTACHABLE_TYPES = ["incident", "postMortem"] as const;
export type DocumentAttachableType = (typeof DOCUMENT_ATTACHABLE_TYPES)[number];

export const POSTMORTEM_STATUSES = ["draft", "published"] as const;
export type PostMortemStatus = (typeof POSTMORTEM_STATUSES)[number];

export const ROOT_CAUSE_METHODS = ["fiveWhys", "fishbone", "timeline", "other"] as const;
export type RootCauseMethod = (typeof ROOT_CAUSE_METHODS)[number];

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
  acknowledgedBy?: string;
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

export interface IIncidentPostMortemSummary {
  id: string;
  status: PostMortemStatus | string;
  authorId?: string | null;
  dueAt?: string | null;
  publishedAt?: string | null;
}

export interface IPostMortemRootCause {
  method?: RootCauseMethod | string;
  whys?: string[];
  contributingFactors?: string[];
  statement?: string;
}

export interface IPostMortem {
  id?: string;
  status: PostMortemStatus | string;
  summary: string;
  impact?: string | null;
  detection?: string | null;
  resolution?: string | null;
  rootCause?: IPostMortemRootCause | null;
  whatWentWell?: string[];
  whatWentWrong?: string[];
  lessonsLearned?: string[];
  authorId?: string | null;
  reviewerIds?: string[];
  dueAt?: string | null;
  publishedAt?: string | null;
  createdAt?: string;
  updatedAt?: string;
}

export interface IPostMortemWriteRequest {
  status?: PostMortemStatus;
  summary: string;
  impact?: string;
  detection?: string;
  resolution?: string;
  rootCause?: IPostMortemRootCause;
  whatWentWell?: string[];
  whatWentWrong?: string[];
  lessonsLearned?: string[];
  authorId?: string;
  reviewerIds?: string[];
  dueAt?: string;
}

export interface IIncidentDocument {
  id: string;
  name?: string | null;
  type: IncidentDocumentType | string;
  description?: string | null;
  externalUrl?: string | null;
  attachableType?: DocumentAttachableType | string;
  createdAt?: string;
  updatedAt?: string;
}

export interface IIncidentDocumentLinkRequest {
  externalUrl: string;
  name?: string;
  type: IncidentDocumentType;
  description?: string;
  attachableType?: DocumentAttachableType;
}

export interface IIncidentDocumentDownloadUrl {
  url: string;
  expiresAt?: string | null;
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
  postMortem?: IIncidentPostMortemSummary | null;
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
  postMortem?: IPostMortemWriteRequest;
  documents?: Array<
    IIncidentDocumentLinkRequest | { type: IncidentDocumentType; description?: string; attachableType?: DocumentAttachableType; file?: File; externalUrl?: string; name?: string }
  >;
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
  postMortem?: IPostMortemWriteRequest;
  documents?: Array<
    IIncidentDocumentLinkRequest | { type: IncidentDocumentType; description?: string; attachableType?: DocumentAttachableType; file?: File; externalUrl?: string; name?: string }
  >;
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
