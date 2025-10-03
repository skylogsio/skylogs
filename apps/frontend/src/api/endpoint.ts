"use server";

import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

const ENDPOINT_URL = "endpoint";

export async function createEndpoint(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(ENDPOINT_URL, body);
    return response.data;
  } catch (error) {
    throw error;
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
    return response.data;
  } catch (error) {
    throw error;
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
