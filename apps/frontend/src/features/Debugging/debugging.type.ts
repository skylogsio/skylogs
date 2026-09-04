import type { AlertRuleStatus } from "@/@types/alertRule";
import type { AlertRuleType } from "@/utils/alertRuleUtils";

export interface DeguggingSegment {
  status: Exclude<AlertRuleStatus, "triggered">;
  count: number;
  fromTime: number;
  toTime: number;
  summary?: string;
}

export interface DebuggingBarType {
  alertRuleId: string;
  type: string;
  name: string;
  bucketSeconds: number;
  segments: DeguggingSegment[];
}

export interface GetDebuggingsParams {
  alertRuleIds: string[];
  fromTime: number;
  toTime: number;
  bucketCount: number;
}

export interface AlertRuleOption {
  id: string;
  name: string;
  type: AlertRuleType;
}
