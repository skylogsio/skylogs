"use server";

import type { IOTPResponse } from "@/@types/endpoint";
import type { ErrorResponse, ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";
import { toErrorResponse } from "@/lib/serverResponse";

const ENDPOINT_URL = "endpoint";

/** Endpoint validation failures come back as `{ status: false, errors }` without a message. */
function withErrorMessage<T>(payload: ServerResponse<T>, fallbackMessage: string): ServerResponse<T> {
  if (payload.status === false && !payload.message) {
    const firstFieldError = Object.values(payload.errors ?? {})[0]?.[0];
    return { ...payload, message: firstFieldError ?? fallbackMessage };
  }
  return payload;
}

export async function createEndpoint(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(ENDPOINT_URL, body);
    return withErrorMessage(response.data, "Failed to create endpoint.");
  } catch (error) {
    return toErrorResponse(error, "Failed to create endpoint.");
  }
}

export async function updateEndpoint(
  endpointId: string,
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${ENDPOINT_URL}/${endpointId}`,
      body
    );
    return withErrorMessage(response.data, "Failed to update endpoint.");
  } catch (error) {
    return toErrorResponse(error, "Failed to update endpoint.");
  }
}

export async function deleteEndpoint(endpointId: string): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${ENDPOINT_URL}/${endpointId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function sendOTP(body: unknown): Promise<IOTPResponse | ErrorResponse> {
  try {
    const response = await axios.post<IOTPResponse>(`${ENDPOINT_URL}/sendOTP`, body);
    return response.data;
  } catch (error) {
    return toErrorResponse(error, "Failed to send the OTP code.");
  }
}
