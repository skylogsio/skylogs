import type { IAlertRule } from "@/@types/alertRule";
import type { AlertRuleType } from "@/utils/alertRuleUtils";

import ApiAndNotificationAlertHistory from "./ApiAndNotificationAlertHistory";
import GrafanaAndPmmAlertHistory from "./GrafanaAndPmmAlertHistory";
import PrometheusAlertsHistory from "./PrometheusAlertHistory";
import SentryAlertHistory from "./SentryAlertHistory";
import ZabbixAlertHistory from "./ZabbixAlertHistory";

export default function AlertRuleHistory({
  alertId,
  type
}: {
  alertId: IAlertRule["id"];
  type: AlertRuleType;
}) {
  switch (type) {
    case "api":
    case "notification":
      return <ApiAndNotificationAlertHistory alertId={alertId} />;
    case "prometheus":
      return <PrometheusAlertsHistory alertId={alertId} />;
    case "grafana":
    case "pmm":
      return <GrafanaAndPmmAlertHistory alertId={alertId} />;
    case "zabbix":
      return <ZabbixAlertHistory alertId={alertId} />;
    case "sentry":
      return <SentryAlertHistory alertId={alertId} />;
    default:
      return null;
  }
}
