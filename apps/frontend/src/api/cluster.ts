"use server";

import { ICluster } from "@/@types/cluster";
import type { IConnectionStatus } from "@/@types/global";
import axios from "@/lib/axios";

const CLUSTER_URL = "skylogs-instance";

export const getClusters = async (): Promise<ICluster[]> => {
  const response = await axios.get(CLUSTER_URL);
  return response.data;
};

export const getCluster = async (id: string): Promise<ICluster> => {
  const response = await axios.get(`${CLUSTER_URL}/${id}`);
  return response.data;
};

export const createCluster = async (data: unknown): Promise<ICluster> => {
  const response = await axios.post(`${CLUSTER_URL}`, data);
  return response.data;
};

export const updateCluster = async (id: string, data: unknown): Promise<ICluster> => {
  const response = await axios.put(`${CLUSTER_URL}/${id}`, data);
  return response.data;
};

export const deleteCluster = async (id: string): Promise<void> => {
  await axios.delete(`${CLUSTER_URL}/${id}`);
};

export async function getClusterStatus(id: ICluster["id"]): Promise<IConnectionStatus> {
  try {
    const response = await axios.get<IConnectionStatus>(`${CLUSTER_URL}/status/${id}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}
