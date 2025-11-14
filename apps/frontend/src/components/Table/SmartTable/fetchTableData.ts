"use server";

import type { IServerResponseTabularData } from "@/@types/global";
import type { FetchTableDataArgs } from "@/components/Table/types";
import axios from "@/lib/axios";

export async function fetchTableData<T>({
  url,
  pageIndex,
  pageSize,
  filterSearchParams,
  searchKey,
  searchValue
}: FetchTableDataArgs): Promise<IServerResponseTabularData<T>> {
  try {
    const searchParams = new URLSearchParams({
      perPage: pageSize,
      page: pageIndex + 1,
      [searchKey]: searchValue
    } as Record<string, string>);
    const urlSearchParams = searchParams.toString();
    const endpoint = `${process.env.BASE_URL}${url}?${urlSearchParams}&sortBy=_id&sortType=asc&${filterSearchParams}`;
    const response = await axios.get<IServerResponseTabularData<T>>(endpoint);
    return response.data;
  } catch (error) {
    throw error;
  }
}
