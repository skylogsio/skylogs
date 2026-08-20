"use server";

import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

import type {
  IIncident,
  IIncidentCreateRequest,
  IIncidentResolveRequest,
  IIncidentUpdateRequest
} from "./incident.type";

const INCIDENT_URL = "incident";

export async function getIncidentById(incidentId: IIncident["id"]): Promise<IIncident> {
  const response = await axios.get<ServerResponse<IIncident> | { data: IIncident }>(
    `${INCIDENT_URL}/${incidentId}`
  );
  const payload = response.data;

  if ("status" in payload && payload.status === false) {
    throw new Error(payload.message);
  }
  if ("data" in payload && payload.data) {
    return payload.data;
  }

  throw new Error("Failed to load incident.");
}

export async function createIncident(
  body: IIncidentCreateRequest
): Promise<ServerResponse<IIncident>> {
  const response = await axios.post<ServerResponse<IIncident>>(INCIDENT_URL, body);
  console.log("🚀 ~ createIncident ~ response:", body);
  return response.data;
}

export async function updateIncident(
  incidentId: IIncident["id"],
  body: IIncidentUpdateRequest
): Promise<ServerResponse<IIncident>> {
  const response = await axios.put<ServerResponse<IIncident>>(
    `${INCIDENT_URL}/${incidentId}`,
    body
  );
  return response.data;
}

export async function deleteIncident(
  incidentId: IIncident["id"]
): Promise<ServerResponse<unknown>> {
  const response = await axios.delete<ServerResponse<unknown>>(`${INCIDENT_URL}/${incidentId}`);
  return response.data;
}

export async function resolveIncident(
  incidentId: IIncident["id"],
  body: IIncidentResolveRequest = {}
): Promise<ServerResponse<IIncident>> {
  const response = await axios.post<ServerResponse<IIncident>>(
    `${INCIDENT_URL}/${incidentId}/resolve`,
    body
  );
  return response.data;
}
