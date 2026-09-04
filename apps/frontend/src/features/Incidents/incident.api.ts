"use server";

import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";
import { toErrorResponse, toSuccessResponse, unwrapData } from "@/lib/serverResponse";

import type {
  IIncident,
  IIncidentCreateRequest,
  IIncidentDocument,
  IIncidentDocumentDownloadUrl,
  IIncidentDocumentLinkRequest,
  IIncidentResolveRequest,
  IIncidentUpdateRequest,
  IPostMortem,
  IPostMortemWriteRequest
} from "./incident.type";

const INCIDENT_URL = "incident";

export async function getIncidentById(incidentId: IIncident["id"]): Promise<IIncident> {
  try {
    const response = await axios.get<ServerResponse<IIncident> | { data: IIncident }>(
      `${INCIDENT_URL}/${incidentId}`
    );
    return unwrapData(response.data, "Failed to load incident.");
  } catch (error) {
    throw error;
  }
}

export async function createIncident(
  body: IIncidentCreateRequest | FormData
): Promise<ServerResponse<IIncident>> {
  try {
    const response = await axios.post<IIncident | { data: IIncident }>(INCIDENT_URL, body, {
      headers: { Accept: "application/json" }
    });
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to create incident.");
  }
}

export async function updateIncident(
  incidentId: IIncident["id"],
  body: IIncidentUpdateRequest | FormData
): Promise<ServerResponse<IIncident>> {
  try {
    const response = await axios.put<IIncident | { data: IIncident }>(
      `${INCIDENT_URL}/${incidentId}`,
      body,
      { headers: { Accept: "application/json" } }
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to update incident.");
  }
}

export async function deleteIncident(
  incidentId: IIncident["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<unknown>(`${INCIDENT_URL}/${incidentId}`);
    return toSuccessResponse(response.data ?? null);
  } catch (error) {
    return toErrorResponse(error, "Failed to delete incident.");
  }
}

export async function resolveIncident(
  incidentId: IIncident["id"],
  body: IIncidentResolveRequest = {}
): Promise<ServerResponse<IIncident>> {
  try {
    const response = await axios.post<IIncident | { data: IIncident }>(
      `${INCIDENT_URL}/${incidentId}/resolve`,
      body
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to resolve incident.");
  }
}

export async function listIncidentDocuments(
  incidentId: IIncident["id"]
): Promise<IIncidentDocument[]> {
  try {
    const response = await axios.get<
      ServerResponse<IIncidentDocument[]> | { data: IIncidentDocument[] } | IIncidentDocument[]
    >(`${INCIDENT_URL}/${incidentId}/document`);
    const payload = response.data;
    if (Array.isArray(payload)) return payload;
    if ("data" in payload && Array.isArray(payload.data)) return payload.data;
    return [];
  } catch (error) {
    throw error;
  }
}

export async function createIncidentDocumentLink(
  incidentId: IIncident["id"],
  body: IIncidentDocumentLinkRequest
): Promise<ServerResponse<IIncidentDocument>> {
  try {
    const response = await axios.post<IIncidentDocument | { data: IIncidentDocument }>(
      `${INCIDENT_URL}/${incidentId}/document`,
      body,
      { headers: { Accept: "application/json" } }
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to add document.");
  }
}

export async function createIncidentDocumentFile(
  incidentId: IIncident["id"],
  formData: FormData
): Promise<ServerResponse<IIncidentDocument>> {
  try {
    const response = await axios.post<IIncidentDocument | { data: IIncidentDocument }>(
      `${INCIDENT_URL}/${incidentId}/document`,
      formData,
      { headers: { Accept: "application/json" } }
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to upload document.");
  }
}

export async function getIncidentDocumentDownloadUrl(
  incidentId: IIncident["id"],
  documentId: IIncidentDocument["id"]
): Promise<IIncidentDocumentDownloadUrl> {
  try {
    const response = await axios.get<
      | ServerResponse<IIncidentDocumentDownloadUrl>
      | { data: IIncidentDocumentDownloadUrl }
      | IIncidentDocumentDownloadUrl
    >(`${INCIDENT_URL}/${incidentId}/document/${documentId}/download-url`);
    const payload = response.data;
    if ("url" in payload && typeof payload.url === "string") {
      return payload;
    }
    return unwrapData(
      payload as
        | ServerResponse<IIncidentDocumentDownloadUrl>
        | { data: IIncidentDocumentDownloadUrl },
      "Failed to mint download URL."
    );
  } catch (error) {
    throw error;
  }
}

export async function deleteIncidentDocument(
  incidentId: IIncident["id"],
  documentId: IIncidentDocument["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<unknown>(
      `${INCIDENT_URL}/${incidentId}/document/${documentId}`
    );
    return toSuccessResponse(response.data ?? null);
  } catch (error) {
    return toErrorResponse(error, "Failed to delete document.");
  }
}

export async function getIncidentPostmortem(
  incidentId: IIncident["id"]
): Promise<IPostMortem | null> {
  try {
    const response = await axios.get<
      ServerResponse<IPostMortem | null> | { data: IPostMortem | null }
    >(`${INCIDENT_URL}/${incidentId}/postmortem`);
    const payload = response.data;
    if ("status" in payload && payload.status === false) {
      throw new Error(payload.message);
    }
    if ("data" in payload) {
      return payload.data ?? null;
    }
    return null;
  } catch (error) {
    throw error;
  }
}

export async function upsertIncidentPostmortem(
  incidentId: IIncident["id"],
  body: IPostMortemWriteRequest
): Promise<ServerResponse<IPostMortem>> {
  try {
    const response = await axios.put<IPostMortem | { data: IPostMortem }>(
      `${INCIDENT_URL}/${incidentId}/postmortem`,
      body
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to save postmortem.");
  }
}

export async function publishIncidentPostmortem(
  incidentId: IIncident["id"]
): Promise<ServerResponse<IPostMortem>> {
  try {
    const response = await axios.post<IPostMortem | { data: IPostMortem }>(
      `${INCIDENT_URL}/${incidentId}/postmortem/publish`,
      {}
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to publish postmortem.");
  }
}
