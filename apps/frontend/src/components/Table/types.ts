import { type MouseEventHandler, ReactNode } from "react";

import { type ColumnDef } from "@tanstack/react-table";

export interface SearchBoxProps {
  title?: string;
  onSearch?: (searchText: string) => void;
}

export interface TableComponentRef {
  refreshData: () => void;
}

export interface TableFilterComponentProps {
  onChange: (key: string, value: unknown) => void;
}

export interface SmartTableComponentProps<T> extends Pick<SearchBoxProps, "title"> {
  url: string;
  columns: ColumnDef<T>[];
  hasCheckbox?: boolean;
  defaultPage?: number;
  defaultPageSize?: number;
  rowsPerPageOptions?: Array<number>;
  onCreate?: MouseEventHandler<HTMLButtonElement> | undefined;
  refetchInterval?: number;
  filterComponent?: (props: TableFilterComponentProps) => ReactNode;
  searchKey?: (string & {}) | keyof T;
  onRowClick?: (row: T) => void;
  onGroupActionClick?: () => void;
}

export interface DataTableComponentProps<T> {
  data: Array<T>;
  columns: ColumnDef<T>[];
  hasCheckbox?: boolean;
  isLoading?: boolean;
  onRowClick?: (row: T) => void;
}

export interface FetchTableDataArgs {
  url: string;
  pageSize: number;
  pageIndex: number;
  filterSearchParams: string;
  searchKey: string;
  searchValue: string;
}
