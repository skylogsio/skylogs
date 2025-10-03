import type { IAlertRule } from "@/@types/alertRule";
import ApiAndNotificationAlertHistory from "@/components/AlertRule/History/ApiAndNotificationAlertHistory";
import GrafanaAndPmmAlertHistory from "@/components/AlertRule/History/GrafanaAndPmmAlertHistory";
import PrometheusAlertsHistory from "@/components/AlertRule/History/PrometheusAlertHistory";
import type { AlertRuleType } from "@/utils/alertRuleUtils";

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
    default:
      return null;
  }
}
