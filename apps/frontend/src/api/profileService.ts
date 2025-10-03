"use server";

import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

const PROFILE_SERVICE_URL = "profile/asset";

export async function createProfileService(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(PROFILE_SERVICE_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function updateProfileService(
  profileServiceId: string,
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${PROFILE_SERVICE_URL}/${profileServiceId}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteProfileService(profileServiceId: string): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${PROFILE_SERVICE_URL}/${profileServiceId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}
