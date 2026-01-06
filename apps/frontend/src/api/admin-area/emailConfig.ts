"use server";

import type { IEmailConfig } from "@/@types/admin-area/emailConfig";
import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

const EMAIL_CONFIG_URL = "config/email";

export async function getAllEmailConfigs(): Promise<IEmailConfig[]> {
  try {
    const response = await axios.get<IEmailConfig[]>(`${EMAIL_CONFIG_URL}?page=1&perPage=100`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function createEmailConfig(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(EMAIL_CONFIG_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function updateEmailConfig(
  proxyId: IEmailConfig["id"],
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${EMAIL_CONFIG_URL}/${proxyId}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteEmailConfig(id: IEmailConfig["id"]): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${EMAIL_CONFIG_URL}/${id}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}
