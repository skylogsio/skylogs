"use server";

import type { ServerResponse } from "@/@types/global";
import type { ITelegramProxy } from "@/@types/settings/telegram";
import axios from "@/lib/axios";

const TELEGRAM_SETTINGS_URL = "config/telegram";

export async function getAllTelegramProxies(): Promise<ITelegramProxy[]> {
  try {
    const response = await axios.get<ITelegramProxy[]>(TELEGRAM_SETTINGS_URL);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function createTelegramProxy(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(TELEGRAM_SETTINGS_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function updateTelegramProxy(
  proxyId: ITelegramProxy["id"],
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${TELEGRAM_SETTINGS_URL}/${proxyId}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteTelegramProxy(
  proxyId: ITelegramProxy["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(
      `${TELEGRAM_SETTINGS_URL}/${proxyId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function activateTelegramProxy(
  proxyId: ITelegramProxy["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${TELEGRAM_SETTINGS_URL}/activate/${proxyId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deactivateTelegramProxy(): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${TELEGRAM_SETTINGS_URL}/deactivate`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}
