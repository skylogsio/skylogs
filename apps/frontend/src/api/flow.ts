"use server";

import type { IEndpoint } from "@/@types/endpoint";
import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

const FLOW_URL = "endpoint";

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
