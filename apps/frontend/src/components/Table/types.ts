import { type ColumnDef } from "@tanstack/react-table";

// Define the props for the TableComponent
export interface SearchBoxProps {
  title?: string;
}
export interface TableComponentProps<T> extends SearchBoxProps {
  url: string;
  columns: ColumnDef<T>[];
  hasCheckbox?: boolean;
  defaultPage: number;
  defaultPageSize: number;
  rowsPerPageOptions?: Array<number>;
}
