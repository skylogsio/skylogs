"use server";

import type { IDataSource } from "@/@types/dataSource";
import type { IConnectionStatus, ServerResponse } from "@/@types/global";
import axios from "@/lib/axios";

const DATA_SOURCE_URL = "data-source";

export async function createDataSource(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(DATA_SOURCE_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function updateDataSource(
  dataSourceId: IDataSource["id"],
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${DATA_SOURCE_URL}/${dataSourceId}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteDataSource(
  dataSourceId: IDataSource["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete(`${DATA_SOURCE_URL}/${dataSourceId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getDataSourceStatus(
  dataSourceId: IDataSource["id"]
): Promise<IConnectionStatus> {
  try {
    const response = await axios.get<IConnectionStatus>(
      `${DATA_SOURCE_URL}/status/${dataSourceId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}
