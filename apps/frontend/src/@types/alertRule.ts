import type { IEndpoint } from "@/@types/endpoint";
import type { ITeam } from "@/@types/team";
import type { IUser } from "@/@types/user";
import { type AlertRuleType } from "@/utils/alertRuleUtils";

export interface IAlertRuleCreateData {
  endpoints: IEndpoint[];
  users: IUser[];
}

export interface IZabbixCreateData {
  actions: string[];
  hosts: string[];
  severities: string[];
}

export type AlertRuleStatus = "resolved" | "warning" | "critical" | "triggered" | "unknown";

export interface IAlertRule {
  apiToken?: string;
  name: string;
  type: AlertRuleType;
  user_id: string;
  acknowledgedBy: string | null;
  enableAutoResolve: boolean;
  autoResolveMinutes: number;
  updated_at: Date;
  created_at: Date;
  endpoint_ids: string[];
  user_ids: string[];
  id: string;
  ownerName: string;
  hasActionAccess: boolean;
  status_label: AlertRuleStatus;
  is_silent: boolean;
  isPinned: boolean;
  count_endpoints: number;
  tags: string[];
  dataSourceAlertName?: string;
  dataSourceIds?: string[];
  dataSourceLabels?: string[];
}

export interface IZabbixAlertRule extends IAlertRule {
  actions?: string[];
  hosts?: string[];
  severities?: string[];
}

export interface IAlertRuleEndpoints {
  alertEndpoints: Array<IEndpoint>;
  selectableEndpoints: Array<IEndpoint>;
}

export interface IAlertRuleAccess {
  alertUsers: Array<IUser>;
  selectableUsers: Array<IUser>;
  alertTeams: Array<ITeam>;
  selectableTeams: Array<ITeam>;
}

export interface IAccessOption {
  type: "team" | "user";
  id: string;
  label: string;
}

export interface IApiAndNotificationAlertRuleHistory {
  alertRuleId: string;
  alertRuleName: string;
  instance: string;
  description: string;
  summary: string;
  state: number;
  status: AlertRuleStatus;
  updatedAt: string;
  createdAt: string;
  id: string;
}

export interface IApiAlertRuleInstance {
  alertRuleId: string;
  alertRuleName: string;
  instance: string;
  job: string;
  state: number;
  description: string;
  summary: string;
  updated_at: string;
  created_at: string;
  historyId: string;
  name: string | null;
  file: string;
  fileName: string;
  updatedAt: string;
  id: string;
  status: AlertRuleStatus;
}

export interface IAlertRuleHistoryInstance {
  dataSourceId: string;
  dataSourceName: string;
  alertRuleName: string;
  dataSourceAlertName: string;
  labels: Record<string, string>;
  annotations: Record<string, string>;
  alertRuleId: string;
  skylogsStatus: number;
}

export interface IPrometheusAlertHistory {
  alertRuleId: string;
  alerts: IAlertRuleHistoryInstance[];
  state: number;
  countResolve: number;
  countFire: number;
  updatedAt: string;
  createdAt: string;
  id: string;
}

export interface IGrafanaAndPmmAlertHistory {
  alerts: IAlertRuleHistoryInstance[];
  dataSourceId: string;
  dataSourceName: string;
  alertRuleId: string;
  status: "firing" | "resolved";
  groupLabels: Record<string, string>;
  commonLabels: Record<string, string>;
  commonAnnotations: Record<string, string>;
  externalURL: string;
  groupKey: string;
  truncatedAlerts: number;
  orgId: number;
  title: string;
  message: string;
  updatedAt: string;
  createdAt: string;
  id: string;
}
export interface IZabbixAlertHistory {
  dataSourceId: string;
  dataSourceName: string;
  alert_message: string;
  alert_subject: string;
  event_date: string;
  event_id: string;
  event_name: string;
  event_nseverity: string;
  event_opdata: string;
  event_recovery_date: string;
  event_recovery_time: string;
  event_severity: string;
  event_source: string;
  event_tags: string;
  event_time: string;
  event_update_action: string;
  event_update_date: string;
  event_update_message: string;
  event_update_status: string;
  event_update_time: string;
  event_update_user: string;
  event_value: string;
  host_ip: string;
  host_name: string;
  trigger_description: string;
  trigger_id: string;
  use_default_message: string;
  zabbix_url: string;
  skylogs_endpoint: string;
  host_host: string;
  action_name: string;
  timestamp: string;
  event_duration: string;
  trigger_name: string;
  host_id: string;
  trigger_url: string | null;
  event_status: "PROBLEM" | "RESOLVE";
  event: string;

  generatedFields: {
    color: number;
    url: string;
    title: string;
    footer: {
      text: string;
    };
    fields: {
      name: string;
      value: string;
      inline?: string;
    }[];
  };

  alertRuleId: string;
  alertRuleName: string;
  updatedAt: string;
  createdAt: string;
  id: string;
}

export interface IZabbixHistoryInstance extends IZabbixAlertHistory {}
