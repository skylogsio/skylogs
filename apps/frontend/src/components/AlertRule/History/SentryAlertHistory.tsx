import { useInfiniteQuery } from "@tanstack/react-query";

import type { IAlertRule } from "@/@types/alertRule";
import { getAlertRuleHistory } from "@/api/alertRule";

export default function SentryAlertHistory({ alertId }: { alertId: IAlertRule["id"] }) {
  const { data, fetchNextPage, hasNextPage, isFetching, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["alertRuleHistory", alertId],
    queryFn: ({ pageParam }) => getAlertRuleHistory<unknown>(alertId, pageParam),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.next_page_url ? lastPage.current_page + 1 : undefined,
    refetchOnWindowFocus: false
  });
  console.log("🚀 ~ SentryAlertHistory ~ data:", data);
  return <></>;
}
