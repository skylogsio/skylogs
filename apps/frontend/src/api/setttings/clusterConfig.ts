"use server";

import type { IClusterConfig, IClusterConfigRequest } from "@/@types/settings/clusterConfig";
import axios from "@/lib/axios";

const CLUSTER_CONFIG_URL = "config/skylogs/cluster";

export async function getClusterConfig(): Promise<IClusterConfig> {
  const response = await axios.get(CLUSTER_CONFIG_URL);
  return response.data;
}

export async function createUpdateClusterConfig(
  data: IClusterConfigRequest
): Promise<IClusterConfig> {
  const response = await axios.post(CLUSTER_CONFIG_URL, data);
  return response.data;
}
