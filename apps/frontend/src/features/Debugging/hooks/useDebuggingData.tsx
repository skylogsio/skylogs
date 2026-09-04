import { useEffect, useMemo, useState } from "react";

import { useQuery, keepPreviousData } from "@tanstack/react-query";

import { useDebuggingTimeRange } from "../context/DebuggingTimeRange.context";
import { getDebuggingBars } from "../debugging.api";
import type { GetDebuggingsParams } from "../debugging.type";

export const alertRuleStatusKey = (params: GetDebuggingsParams) =>
  ["alert-rule", "status", params] as const;

export function useDebuggingData({ alertRuleIds }: Pick<GetDebuggingsParams, "alertRuleIds">) {
  const { start, end, isTimeRangeInvalid } = useDebuggingTimeRange();
  const fromTime = start.dateTime.getTime();
  const toTime = end.dateTime.getTime();
  const alertRuleIdsKey = alertRuleIds.join(",");
  const stableAlertRuleIds = useMemo(
    () => (alertRuleIdsKey ? alertRuleIdsKey.split(",") : []),
    [alertRuleIdsKey]
  );

  const [debounced, setDebounced] = useState({
    fromTime,
    toTime,
    alertRuleIds: stableAlertRuleIds
  });

  useEffect(() => {
    const timeout = setTimeout(() => {
      setDebounced((prev) => {
        const prevAlertRuleIdsKey = prev.alertRuleIds.join(",");

        if (
          prev.fromTime === fromTime &&
          prev.toTime === toTime &&
          prevAlertRuleIdsKey === alertRuleIdsKey
        ) {
          return prev;
        }

        return { fromTime, toTime, alertRuleIds: stableAlertRuleIds };
      });
    }, 500);

    return () => clearTimeout(timeout);
  }, [fromTime, toTime, alertRuleIdsKey, stableAlertRuleIds]);

  const params: GetDebuggingsParams = {
    alertRuleIds: debounced.alertRuleIds,
    bucketCount: 100,
    fromTime: debounced.fromTime,
    toTime: debounced.toTime
  };

  const isDebouncing =
    fromTime !== debounced.fromTime ||
    toTime !== debounced.toTime ||
    alertRuleIdsKey !== debounced.alertRuleIds.join(",");

  const query = useQuery({
    queryKey: alertRuleStatusKey(params),
    queryFn: () => getDebuggingBars(params),
    enabled: params.alertRuleIds.length > 0 && !isTimeRangeInvalid && !isDebouncing,
    placeholderData: keepPreviousData
  });

  const hasNoAlertRules = params.alertRuleIds.length === 0;

  return {
    data: hasNoAlertRules ? [] : query.data,
    error: query.error,
    isFetching: hasNoAlertRules ? false : query.isFetching,
    isDebouncing
  };
}
