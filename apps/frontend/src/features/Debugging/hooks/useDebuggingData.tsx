import { useEffect, useState } from "react";

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

  const [debounced, setDebounced] = useState({ fromTime, toTime });

  useEffect(() => {
    const timeout = setTimeout(() => {
      setDebounced({ fromTime, toTime });
    }, 500);

    return () => clearTimeout(timeout);
  }, [fromTime, toTime]);

  const params: GetDebuggingsParams = {
    alertRuleIds,
    bucketCount: 100,
    fromTime: debounced.fromTime,
    toTime: debounced.toTime
  };

  return useQuery({
    queryKey: alertRuleStatusKey(params),
    queryFn: () => getDebuggingBars(params),
    enabled: params.alertRuleIds.length > 0 && !isTimeRangeInvalid,
    placeholderData: keepPreviousData
  });
}
