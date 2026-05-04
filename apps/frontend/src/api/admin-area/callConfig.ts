"use server";

import type { ICallConfig } from "@/@types/admin-area/callConfig";
import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

const CALL_CONFIG_URL = "config/call";

export async function getAllCallConfigs(): Promise<ICallConfig[]> {
  try {
    const response = await axios.get<ICallConfig[]>(`${CALL_CONFIG_URL}?page=1&perPage=100`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function createCallConfig(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(CALL_CONFIG_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function updateCallConfig(
  proxyId: ICallConfig["id"],
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${CALL_CONFIG_URL}/${proxyId}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteCallConfig(id: ICallConfig["id"]): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${CALL_CONFIG_URL}/${id}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function setDefaultCallConfig(
  id: ICallConfig["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${CALL_CONFIG_URL}/make-default/${id}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}
