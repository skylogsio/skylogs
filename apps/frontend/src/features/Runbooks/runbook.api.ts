"use server";

import type { ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";
import { toErrorResponse, toSuccessResponse, unwrapData } from "@/lib/serverResponse";

import type { IRunbook, IRunbookOption, IRunbookWriteRequest } from "./runbook.type";

const RUNBOOK_URL = "runbook";

export async function getRunbookById(runbookId: IRunbook["id"]): Promise<IRunbook> {
  try {
    const response = await axios.get<ServerResponse<IRunbook> | { data: IRunbook }>(
      `${RUNBOOK_URL}/${runbookId}`
    );
    return unwrapData(response.data, "Failed to load runbook.");
  } catch (error) {
    throw error;
  }
}

export async function getPublishedRunbooks(): Promise<IRunbookOption[]> {
  try {
    const response = await axios.get<
      ServerResponse<IRunbook[]> | { data: IRunbook[] } | IRunbook[]
    >(RUNBOOK_URL, {
      params: { status: "published", perPage: 100, page: 1 }
    });

    const payload = response.data;
    const rows = Array.isArray(payload)
      ? payload
      : "data" in payload && Array.isArray(payload.data)
        ? payload.data
        : [];

    return rows.map((row) => ({
      id: row.id,
      name: row.name,
      slug: row.slug
    }));
  } catch (error) {
    throw error;
  }
}

export async function createRunbook(
  body: IRunbookWriteRequest
): Promise<ServerResponse<IRunbook>> {
  try {
    const response = await axios.post<IRunbook | { data: IRunbook }>(RUNBOOK_URL, body);
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to create runbook.");
  }
}

export async function updateRunbook(
  runbookId: IRunbook["id"],
  body: IRunbookWriteRequest
): Promise<ServerResponse<IRunbook>> {
  try {
    const response = await axios.put<IRunbook | { data: IRunbook }>(
      `${RUNBOOK_URL}/${runbookId}`,
      body
    );
    return toSuccessResponse(response.data);
  } catch (error) {
    return toErrorResponse(error, "Failed to update runbook.");
  }
}

export async function deleteRunbook(
  runbookId: IRunbook["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<unknown>(`${RUNBOOK_URL}/${runbookId}`);
    return toSuccessResponse(response.data ?? null);
  } catch (error) {
    return toErrorResponse(error, "Failed to delete runbook.");
  }
}
