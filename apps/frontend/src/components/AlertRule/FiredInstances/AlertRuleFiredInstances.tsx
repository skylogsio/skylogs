import type { IAlertRule } from "@/@types/alertRule";
import ApiFiredInstances from "@/components/AlertRule/FiredInstances/ApiFiredInstances";
import PrometheusFiredInstance from "@/components/AlertRule/FiredInstances/PrometheusFiredInstance";
import type { AlertRuleType } from "@/utils/alertRuleUtils";

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
    default:
      return null;
  }
}
