import type { IAlertRule } from "@/@types/alertRule";
import type { AlertRuleType } from "@/utils/alertRuleUtils";
import { clientApiSchema } from "../Forms/ClientAPIForm";
import { elasticAlertRuleSchema } from "../Forms/ElasticForm";
import { generalAlertRuleSchema } from "../Forms/GeneralAlertRuleForm";
import { notificationSchema } from "../Forms/NotificationForm";
import { sentryAlertRuleSchema } from "../Forms/SentryForm";
import { splunkAlertRuleSchema } from "../Forms/SplunkForm";
import { victoriaLogsSchema } from "../Forms/VictoriaLogsForm";
import { zabbixAlertRuleSchema } from "../Forms/ZabbixForm";

interface DetailSchema {
  shape: Record<string, unknown>;
}

const SCHEMA_BY_TYPE: Record<AlertRuleType, DetailSchema> = {
  api: clientApiSchema,
  notification: notificationSchema,
  health: notificationSchema,
  prometheus: generalAlertRuleSchema,
  pmm: generalAlertRuleSchema,
  grafana: generalAlertRuleSchema,
  metabase: generalAlertRuleSchema,
  victoriametrics: generalAlertRuleSchema,
  sentry: sentryAlertRuleSchema,
  zabbix: zabbixAlertRuleSchema,
  splunk: splunkAlertRuleSchema,
  elastic: elasticAlertRuleSchema,
  victoria_logs: victoriaLogsSchema
};

export function getDetailFieldKeys(type: IAlertRule["type"]): string[] {
  const schema = SCHEMA_BY_TYPE[type];
  if (!schema) {
    return [];
  }
  return Object.keys(schema.shape).filter((key) => key !== "tags");
}

export const DETAIL_FIELD_LABELS: Record<string, string> = {
  name: "Name",
  type: "Type",
  endpointIds: "Endpoints",
  userIds: "Users",
  teamIds: "Teams",
  extraField: "Extra Fields",
  dataSourceIds: "Data Sources",
  dataSourceId: "Data Source",
  queryType: "Query Type",
  dataSourceAlertName: "DataSource Alert Name",
  description: "Description",
  showAcknowledgeBtn: "Show Acknowledge Button in Telegram",
  isPrivate: "Private",
  actions: "Actions",
  hosts: "Hosts",
  severities: "Severity",
  queryString: "Query String",
  dataviewName: "Dataview Name",
  dataviewTitle: "Dataview Title",
  conditionType: "Condition Type",
  countDocument: "Count Document",
  minutes: "Minutes",
  enableAutoResolve: "Auto Resolve",
  autoResolveMinutes: "Auto Resolve After (Minutes)"
};
