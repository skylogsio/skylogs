export type BehaviorRuleType = "template" | "notification" | "silent";

export type BehaviorRuleFilterType = "all" | BehaviorRuleType;

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
