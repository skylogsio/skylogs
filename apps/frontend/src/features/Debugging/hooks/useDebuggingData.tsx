import { useQuery } from "@tanstack/react-query";

import { getDebuggingBars } from "../debugging.api";
import type { GetDebuggingsParams } from "../debugging.type";

export const alertRuleStatusKey = (params: GetDebuggingsParams) =>
  ["alert-rule", "status", params] as const;

export function useDebuggingData(params: GetDebuggingsParams) {
  return useQuery({
    queryKey: alertRuleStatusKey(params),
    queryFn: () => getDebuggingBars(params),
    enabled: params.alertRuleIds.length > 0
  });
}
