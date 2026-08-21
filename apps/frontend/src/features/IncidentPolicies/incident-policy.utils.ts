import { format } from "date-fns";

export const INCIDENT_POLICY_DATE_TIME_FORMAT = "yyyy/MM/dd HH:mm";

export function formatIncidentPolicyDateTime(value?: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "—" : format(date, INCIDENT_POLICY_DATE_TIME_FORMAT);
}
