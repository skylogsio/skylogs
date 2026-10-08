import type { IAlertRule } from "@/@types/alertRule";
import type { AlertRuleType } from "@/utils/alertRuleUtils";

import ApiFiredInstances from "./ApiFiredInstances";
import GrafanaAndPmmFiredInstance from "./GrafanaAndPmmFiredInstance";
import PrometheusFiredInstance from "./PrometheusFiredInstance";
import ZabbixFiredInstance from "./ZabbixFiredInstance";

export default function AlertRuleFiredInstances({
  alertId,
  type
}: {
  alertId: IAlertRule["id"];
  type: AlertRuleType;
}) {
  switch (type) {
    case "api":
      return <ApiFiredInstances alertId={alertId} />;
    case "prometheus":
      return <PrometheusFiredInstance alertId={alertId} />;
    case "grafana":
    case "pmm":
      return <GrafanaAndPmmFiredInstance alertId={alertId} />;
    case "zabbix":
      return <ZabbixFiredInstance alertId={alertId} />;
    default:
      return null;
  }
}
