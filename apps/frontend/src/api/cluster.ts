"use server";

import type { ICluster, IClusterStatusResponse } from "@/@types/cluster";
import axios from "@/lib/axios";

const CLUSTER_URL = "skylogs-instance";

export const getAllClusters = async (): Promise<ICluster[]> => {
  const response = await axios.get(`${CLUSTER_URL}/all`);
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

export async function getClusterStatus(id: ICluster["id"]): Promise<IClusterStatusResponse> {
  try {
    const response = await axios.get<IClusterStatusResponse>(`${CLUSTER_URL}/status/${id}`);
    return response.data;
  } catch {
    return {
      isConnected: false,
      enabled: null,
      cluster: null
    };
  }
}
