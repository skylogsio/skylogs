import { format } from "date-fns";

import type { IncidentSeverity, IncidentStatus } from "./incident.type";

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
