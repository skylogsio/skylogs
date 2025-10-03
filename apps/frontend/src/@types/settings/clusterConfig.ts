export interface IClusterConfig {
  id: string;
  type: "main" | "agent";
  sourceUrl?: string;
  sourceToken?: string;
  createdAt: string;
  updatedAt: string;
}

export interface IClusterConfigRequest {
  type: "main" | "agent";
  sourceUrl?: string;
  sourceToken?: string;
}