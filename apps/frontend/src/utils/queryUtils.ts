import { type QueryClient, type QueryKey } from "@tanstack/react-query";

export const refetchAllExcept = async (queryClient: QueryClient, excludeKey: QueryKey) => {
  const queries = queryClient
    .getQueryCache()
    .getAll()
    .filter((q) => q.queryKey[0] !== excludeKey[0]);

  for (const query of queries) {
    await queryClient.invalidateQueries({ queryKey: query.queryKey });
  }
};
