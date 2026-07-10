import { useQuery } from "@tanstack/react-query";

import { getAllAlertRules } from "../debugging.api";

export const allAlertRulesKey = ["alert-rule", "all"] as const;

export function useAllAlertRules() {
  return useQuery({
    queryKey: allAlertRulesKey,
    queryFn: getAllAlertRules
  });
}
