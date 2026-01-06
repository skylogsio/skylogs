"use server";

import type { ISmsConfig } from "@/@types/admin-area/smsConfig";
import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

const SMS_CONFIG_URL = "config/sms";

export async function getAllSmsConfigs(): Promise<ISmsConfig[]> {
  try {
    const response = await axios.get<ISmsConfig[]>(`${SMS_CONFIG_URL}?page=1&perPage=100`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function createSmsConfig(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(SMS_CONFIG_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function updateSmsConfig(
  proxyId: ISmsConfig["id"],
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(`${SMS_CONFIG_URL}/${proxyId}`, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteSmsConfig(id: ISmsConfig["id"]): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${SMS_CONFIG_URL}/${id}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}
