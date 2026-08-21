import { AxiosError } from "axios";

import type { ServerResponse } from "@/@types/global";

/** Unwrap `{ data }` / `ServerResponse` payloads, or throw on failure. */
export function unwrapData<T>(
  payload: ServerResponse<T> | { data: T },
  fallbackMessage: string
): T {
  if ("status" in payload && payload.status === false) {
    throw new Error(payload.message);
  }
  if ("data" in payload && payload.data !== undefined && payload.data !== null) {
    return payload.data;
  }
  throw new Error(fallbackMessage);
}

/** Map an Axios failure into a consistent `{ status: false, message }` response. */
export function toErrorResponse<T>(error: unknown, fallbackMessage: string): ServerResponse<T> {
  const tempError = error as AxiosError<ServerResponse<T> | { message?: string }>;
  const payload = tempError.response?.data;

  if (payload && typeof payload === "object" && "status" in payload && payload.status === false) {
    return payload;
  }

  const message =
    payload && typeof payload === "object" && "message" in payload && payload.message
      ? String(payload.message)
      : fallbackMessage;

  return { status: false, message };
}

/**
 * Normalize success payloads that may be:
 * - `ServerResponse<T>` (`{ status: true, data }`)
 * - `{ data: T }`
 * - bare `T` (e.g. create returning the entity with no wrapper)
 */
export function toSuccessResponse<T>(
  payload: T | { data: T } | ServerResponse<T>
): ServerResponse<T> {
  if (payload && typeof payload === "object" && "status" in payload) {
    if (payload.status === true && "data" in payload) {
      return payload;
    }
    if (payload.status === false) {
      return payload;
    }
  }

  if (payload && typeof payload === "object" && "data" in payload && payload.data !== undefined) {
    return { status: true, data: (payload as { data: T }).data };
  }

  return { status: true, data: payload as T };
}
