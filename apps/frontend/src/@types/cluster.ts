import type { IRaftCluster } from "./ha";

export interface ICluster {
  id: string;
  name: string;
  type: "main" | "agent";
  url: string;
  token?: string;
  createdAt?: string;
  updatedAt?: string;
}

export interface IClusterStatusResponse {
  isConnected: boolean;
  enabled?: boolean | null;
  cluster?: IRaftCluster | null;
}
