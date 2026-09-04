import { type MouseEventHandler, type ReactNode } from "react";

import type { Theme } from "@mui/material";
import type { SystemStyleObject } from "@mui/system";
import { type ColumnDef } from "@tanstack/react-table";

import type { TableComponentRef, TableFilterComponentProps } from "../types";

export type { TableComponentRef, TableFilterComponentProps };

export type BuiltInToolbarAction = "search" | "filter" | "create";

export interface ToolbarActionContext {
  openFilter: boolean;
  toggleFilter: () => void;
  hasActiveFilters: boolean;
  activeFilterCount: number;
  isFilterDirty: boolean;
  searchValue: string;
  onSearch: (value: string) => void;
}

export interface CustomToolbarAction {
  id: string;
  render: ReactNode | ((ctx: ToolbarActionContext) => ReactNode);
}

export type ToolbarAction = BuiltInToolbarAction | CustomToolbarAction;

export interface SmartTableToolbarSlots {
  title: ReactNode;
  search: ReactNode;
  filter: ReactNode | null;
  create: ReactNode | null;
  actions: ReactNode;
}

export interface SmartTableProps<T> {
  title?: string;
  url: string;
  columns: ColumnDef<T>[];
  hasCheckbox?: boolean;
  defaultPage?: number;
  defaultPageSize?: number;
  rowsPerPageOptions?: Array<number>;
  onCreate?: MouseEventHandler<HTMLButtonElement>;
  createLabel?: string;
  refetchInterval?: number;
  filterComponent?: (props: TableFilterComponentProps) => ReactNode;
  defaultFilters?: Record<string, unknown>;
  searchKey?: (string & {}) | keyof T;
  syncSearchToUrl?: boolean;
  onRowClick?: (row: T) => void;
  onGroupActionClick?: () => void;
  toolbarActions?: ToolbarAction[];
  toolbarStart?: ReactNode;
  toolbarEnd?: ReactNode;
  renderToolbar?: (slots: SmartTableToolbarSlots) => ReactNode;
  forceShowFilter?: boolean;
  hideSearch?: boolean;
  excludeFilterKeys?: string[];
  tablePaperSx?: SystemStyleObject<Theme>;
}
