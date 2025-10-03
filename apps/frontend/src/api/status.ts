"use server";
import { ServerResponse } from "@/@types/global";
import type { IStatusCard } from "@/@types/status";
import axios from "@/lib/axios";

const STATUS_URL = "status";

export async function getAllStatus(): Promise<IStatusCard[]> {
  try {
    const response = await axios.get(`${STATUS_URL}/all`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function createStatusCard(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(STATUS_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function udpateStatusCard(
  id: IStatusCard["id"],
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(`${STATUS_URL}/${id}`, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteStatusCard(id: IStatusCard["id"]): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${STATUS_URL}/${id}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}
