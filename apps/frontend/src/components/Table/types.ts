import { type ColumnDef } from "@tanstack/react-table";

export interface TableComponentRef {
  refreshData: () => void;
}

export interface TableFilterComponentProps {
  onChange: (key: string, value: unknown) => void;
  values?: Record<string, unknown>;
  setValues?: (values: Record<string, unknown>) => void;
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
