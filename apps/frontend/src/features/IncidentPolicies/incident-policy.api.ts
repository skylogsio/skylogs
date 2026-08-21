"use server";

import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";
import { toErrorResponse, toSuccessResponse, unwrapData } from "@/lib/serverResponse";

import type {
  IIncidentPolicy,
  IIncidentPolicyWriteRequest,
  IIncidentPolicyYamlResult
} from "./incident-policy.type";

const INCIDENT_POLICY_URL = "incident-policy";

export async function getIncidentPolicyById(
  policyId: IIncidentPolicy["id"]
): Promise<IIncidentPolicy> {
  try {
    const response = await axios.get<ServerResponse<IIncidentPolicy> | { data: IIncidentPolicy }>(
      `${INCIDENT_POLICY_URL}/${policyId}`
    );
    return unwrapData(response.data, "Failed to load incident policy.");
  } catch (error) {
    throw error;
  }
}

export async function createIncidentPolicy(
  body: IIncidentPolicyWriteRequest
): Promise<ServerResponse<IIncidentPolicy>> {
  try {
    const response = await axios.post<IIncidentPolicy | { data: IIncidentPolicy }>(
      INCIDENT_POLICY_URL,
      body
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to create incident policy.");
  }
}

export async function updateIncidentPolicy(
  policyId: IIncidentPolicy["id"],
  body: IIncidentPolicyWriteRequest
): Promise<ServerResponse<IIncidentPolicy>> {
  try {
    const response = await axios.put<IIncidentPolicy | { data: IIncidentPolicy }>(
      `${INCIDENT_POLICY_URL}/${policyId}`,
      body
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to update incident policy.");
  }
}

export async function deleteIncidentPolicy(
  policyId: IIncidentPolicy["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<unknown>(`${INCIDENT_POLICY_URL}/${policyId}`);
    return toSuccessResponse(response.data ?? null);
  } catch (error) {
    return toErrorResponse(error, "Failed to delete incident policy.");
  }
}

export async function validateIncidentPolicyYaml(
  body: { yaml: string; dryRun?: boolean } | FormData
): Promise<IIncidentPolicyYamlResult> {
  try {
    const response = await axios.post<IIncidentPolicyYamlResult>(
      `${INCIDENT_POLICY_URL}/validate`,
      body,
      {
        headers: { Accept: "application/json" },
        validateStatus: () => true
      }
    );
    return response.data;
  } catch (error) {
    const failed = toErrorResponse(error, "Failed to validate YAML.");
    return { valid: false, errors: [{ message: failed.message }], message: failed.message };
  }
}

export async function importIncidentPolicyYaml(
  body: { yaml: string; dryRun?: boolean } | FormData
): Promise<IIncidentPolicyYamlResult> {
  try {
    const response = await axios.post<IIncidentPolicyYamlResult>(
      `${INCIDENT_POLICY_URL}/import`,
      body,
      {
        headers: { Accept: "application/json" },
        validateStatus: () => true
      }
    );
    return response.data;
  } catch (error) {
    const failed = toErrorResponse(error, "Failed to import YAML.");
    return {
      valid: false,
      status: false,
      errors: [{ message: failed.message }],
      message: failed.message
    };
  }
}

export async function exportIncidentPolicyYaml(
  policyId: IIncidentPolicy["id"]
): Promise<{ base64: string; filename: string }> {
  try {
    const response = await axios.get<ArrayBuffer>(`${INCIDENT_POLICY_URL}/${policyId}/export`, {
      responseType: "arraybuffer",
      headers: { Accept: "application/x-yaml" }
    });

    const disposition = response.headers["content-disposition"] as string | undefined;
    const match = disposition?.match(/filename="?([^"]+)"?/i);
    const base64 = Buffer.from(response.data).toString("base64");

    return {
      base64,
      filename: match?.[1] ?? `incident-policy-${policyId}.yaml`
    };
  } catch (error) {
    throw error;
  }
}
