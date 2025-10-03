"use server";

import type { IEndpoint } from "@/@types/endpoint";
import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

const FLOW_URL = "endpoint";

export async function createFlow(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(FLOW_URL, body);
    return response.data;
  } catch (error) {
    console.log("🚀 ~ createFlow ~ error:", error);
    throw error;
  }
}

export async function updateFlow(flowId: string, body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(`${FLOW_URL}/${flowId}`, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteFlow(flowId: string): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${FLOW_URL}/${flowId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAllEndpoints(): Promise<IEndpoint[]> {
  try {
    const response = await axios.get<IEndpoint[]>("endpoint/createFlowEndpoints");
    return response.data;
  } catch (error) {
    throw error;
  }
}
