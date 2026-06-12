export type BehaviorRuleType = "template" | "notification" | "silent";

export type BehaviorRuleFilterType = "all" | BehaviorRuleType;

export type TriggerStateType = "resolved" | "critical";

export interface NotificationRuleItem {
  id: string;
  name: string;
  type: "notification";
  filters: Array<{
    key: string;
    value: string;
  }>;
  endpointIds: string[];
  endpoints: Array<{
    id: string;
    name: string;
  }>;
}

export interface SilentRuleItem {
  id: string;
  name: string;
  type: "silent";
  dependsOnAlertRuleIds: string[];
  dependsOnAlertRules: Array<{
    id: string;
    name: string;
  }>;
  triggerState: TriggerStateType;
}

export type BehaviorRuleItem = NotificationRuleItem | SilentRuleItem;
