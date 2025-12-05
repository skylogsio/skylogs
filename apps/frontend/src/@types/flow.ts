export type TimeUnit = "s" | "m" | "h";

export interface IFlowStep {
  type: "wait" | "endpoint";
  duration?: number;
  timeUnit?: TimeUnit;
  endpointIds?: string[];
}

export interface IFlow {
  id: string;
  user_id: string;
  name: string;
  hasActionAccess: boolean;
  type: "flow";
  accessTeamIds: string[];
  accessUserIds: string[];
  steps: IFlowStep[];
  isPublic: boolean;
  updatedAt: Date;
  createdAt: Date;
}
