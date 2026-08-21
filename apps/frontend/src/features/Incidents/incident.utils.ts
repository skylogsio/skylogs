import { format } from "date-fns";

import { objectToFormData } from "@/lib/formData";

import type {
  IncidentSeverity,
  IncidentStatus,
  IIncidentCreateRequest,
  IIncidentUpdateRequest,
  IPostMortemWriteRequest
} from "./incident.type";

export const INCIDENT_SEVERITY_COLORS: Record<IncidentSeverity, string> = {
  SEV1: "#C45C4A",
  SEV2: "#E08A3A",
  SEV3: "#C4A07A",
  SEV4: "#A08B74"
};

export const INCIDENT_STATUS_COLORS: Record<IncidentStatus, string> = {
  open: "#E08A3A",
  investigating: "#5B8DEF",
  resolved: "#3FA85A"
};

export const INCIDENT_STATUS_LABELS: Record<IncidentStatus, string> = {
  open: "Open",
  investigating: "Investigating",
  resolved: "Resolved"
};

export const INCIDENT_DATE_TIME_FORMAT = "yyyy/MM/dd HH:mm";

export function formatIncidentDateTime(value?: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "—" : format(date, INCIDENT_DATE_TIME_FORMAT);
}

export type NestedDocumentDraft =
  | {
      mode: "link";
      externalUrl: string;
      name: string;
      type: string;
      description: string;
      attachableType: "incident" | "postMortem";
    }
  | {
      mode: "file";
      file: File | null;
      type: string;
      description: string;
      attachableType: "incident" | "postMortem";
    };

export function documentsHaveFiles(documents: NestedDocumentDraft[]): boolean {
  return documents.some((doc) => doc.mode === "file" && doc.file instanceof File);
}

function serializeDocumentsForJson(documents: NestedDocumentDraft[]) {
  return documents
    .map((doc) => {
      if (doc.mode === "link") {
        if (!doc.externalUrl.trim()) return null;
        return {
          externalUrl: doc.externalUrl.trim(),
          ...(doc.name.trim() ? { name: doc.name.trim() } : {}),
          type: doc.type,
          ...(doc.description.trim() ? { description: doc.description.trim() } : {}),
          attachableType: doc.attachableType
        };
      }
      return null;
    })
    .filter(Boolean);
}

function serializeDocumentsForFormData(documents: NestedDocumentDraft[]) {
  return documents
    .map((doc) => {
      if (doc.mode === "link") {
        if (!doc.externalUrl.trim()) return null;
        return {
          externalUrl: doc.externalUrl.trim(),
          ...(doc.name.trim() ? { name: doc.name.trim() } : {}),
          type: doc.type,
          ...(doc.description.trim() ? { description: doc.description.trim() } : {}),
          attachableType: doc.attachableType
        };
      }
      if (!doc.file) return null;
      return {
        file: doc.file,
        type: doc.type,
        ...(doc.description.trim() ? { description: doc.description.trim() } : {}),
        attachableType: doc.attachableType
      };
    })
    .filter(Boolean);
}

export function buildIncidentCreatePayload(args: {
  title: string;
  severity: IncidentSeverity;
  teamIds: string[];
  description?: string;
  tags?: string[];
  startedAt?: string | null;
  detectedAt?: string | null;
  resolvedAt?: string | null;
  alertRuleIds?: string[];
  includePostMortem?: boolean;
  postMortem?: Partial<IPostMortemWriteRequest> | null;
  documents?: NestedDocumentDraft[];
}): IIncidentCreateRequest | FormData {
  const base: Record<string, unknown> = {
    title: args.title,
    severity: args.severity,
    teamIds: args.teamIds
  };

  if (args.description?.trim()) base.description = args.description.trim();
  if (args.tags && args.tags.length > 0) base.tags = args.tags;
  if (args.startedAt) base.startedAt = args.startedAt;
  if (args.detectedAt) base.detectedAt = args.detectedAt;
  if (args.resolvedAt) base.resolvedAt = args.resolvedAt;
  if (args.alertRuleIds && args.alertRuleIds.length > 0) base.alertRuleIds = args.alertRuleIds;

  if (args.includePostMortem && args.postMortem?.summary?.trim()) {
    base.postMortem = {
      summary: args.postMortem.summary.trim(),
      ...(args.postMortem.impact?.trim() ? { impact: args.postMortem.impact.trim() } : {}),
      ...(args.postMortem.status ? { status: args.postMortem.status } : {}),
      ...(args.postMortem.dueAt ? { dueAt: args.postMortem.dueAt } : {})
    };
  }

  const docs = args.documents ?? [];
  const useFormData = documentsHaveFiles(docs);

  if (useFormData) {
    const serialized = serializeDocumentsForFormData(docs);
    if (serialized.length > 0) base.documents = serialized;
    return objectToFormData(base);
  }

  const linkDocs = serializeDocumentsForJson(docs);
  if (linkDocs.length > 0) base.documents = linkDocs;
  return base as unknown as IIncidentCreateRequest;
}

export function buildIncidentUpdatePayload(args: {
  title: string;
  description: string;
  severity: IncidentSeverity;
  teamIds: string[];
  tags: string[];
  startedAt: string;
  detectedAt: string;
  alertRuleIds: string[];
  includePostMortem?: boolean;
  postMortem?: Partial<IPostMortemWriteRequest> | null;
  documents?: NestedDocumentDraft[];
}): IIncidentUpdateRequest | FormData {
  const base: Record<string, unknown> = {
    title: args.title,
    description: args.description,
    severity: args.severity,
    teamIds: args.teamIds,
    tags: args.tags,
    startedAt: args.startedAt,
    detectedAt: args.detectedAt,
    alertRuleIds: args.alertRuleIds
  };

  if (args.includePostMortem && args.postMortem?.summary?.trim()) {
    base.postMortem = {
      summary: args.postMortem.summary.trim(),
      ...(args.postMortem.impact?.trim() ? { impact: args.postMortem.impact.trim() } : {}),
      ...(args.postMortem.status ? { status: args.postMortem.status } : {}),
      ...(args.postMortem.dueAt ? { dueAt: args.postMortem.dueAt } : {})
    };
  }

  const docs = args.documents ?? [];
  const useFormData = documentsHaveFiles(docs);

  if (useFormData) {
    const serialized = serializeDocumentsForFormData(docs);
    if (serialized.length > 0) base.documents = serialized;
    return objectToFormData(base);
  }

  const linkDocs = serializeDocumentsForJson(docs);
  if (linkDocs.length > 0) base.documents = linkDocs;
  return base as unknown as IIncidentUpdateRequest;
}
