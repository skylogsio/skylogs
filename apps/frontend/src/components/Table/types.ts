import type React from "react";
import { type ColumnDef, type RowData } from "@tanstack/react-table";

declare module "@tanstack/react-table" {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData extends RowData, TValue> {
    align?: "left" | "center" | "right" | "inherit" | "justify";
    width?: string | number;
    minWidth?: string | number;
    skeleton?: React.ReactNode | (() => React.ReactNode);
  }
}

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
