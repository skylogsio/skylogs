"use client";

import { useMemo } from "react";

import {
  Table as MuiTable,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Checkbox,
  Skeleton,
  Box,
  Typography,
  alpha,
  useTheme
} from "@mui/material";
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  flexRender,
  type Row
} from "@tanstack/react-table";

import { useScopedI18n } from "@/locales/client";

import type { DataTableComponentProps } from "../types";

export default function DataTable<T>({
  data,
  columns,
  hasCheckbox,
  isLoading,
  onRowClick
}: DataTableComponentProps<T>) {
  const { palette } = useTheme();
  const t = useScopedI18n("table");

  const tableColumns = useMemo(() => {
    if (hasCheckbox) {
      return [
        {
          id: "select",
          header: ({ table }) => (
            <Checkbox
              checked={table.getIsAllRowsSelected()}
              indeterminate={table.getIsSomeRowsSelected()}
              onChange={table.getToggleAllRowsSelectedHandler()}
              color="default"
            />
          ),
          cell: ({ row }) => (
            <Checkbox
              checked={row.getIsSelected()}
              indeterminate={row.getIsSomeSelected()}
              onChange={row.getToggleSelectedHandler()}
              color="default"
            />
          )
        },
        ...columns
      ];
    }
    return columns;
  }, [columns, hasCheckbox]);

  const table = useReactTable({
    data: data || [],
    columns: tableColumns,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    manualPagination: true
  });

  const handleRowClick = (row: Row<T>) => {
    onRowClick?.(row.original);
  };

  const columnCount = tableColumns.length;
  const hasRows = Boolean(data?.length);
  const skeletonRows = Math.max(data?.length || 10, 5);

  return (
    <Box
      sx={{
        display: "flex",
        flexDirection: "column",
        width: 1,
        minHeight: 1
      }}
    >
      <Box
        sx={{
          width: 1,
          maxHeight: "70vh",
          mt: 1,
          bgcolor: "background.paper",
          borderRadius: 3,
          border: `1px solid ${alpha(palette.text.primary, 0.08)}`,
          overflow: "hidden",
          boxShadow: `0 1px 2px ${alpha(palette.common.black, palette.mode === "dark" ? 0.2 : 0.04)}`
        }}
      >
        <TableContainer sx={{ width: 1, maxHeight: "70vh", overflow: "auto" }}>
          <MuiTable stickyHeader sx={{ width: 1 }}>
            <TableHead>
              {table.getHeaderGroups().map((headerGroup) => (
                <TableRow
                  key={headerGroup.id}
                  sx={{
                    "& th": {
                      backgroundColor:
                        palette.mode === "dark"
                          ? alpha(palette.common.white, 0.04)
                          : alpha(palette.grey[500], 0.06),
                      backdropFilter: "blur(8px)"
                    }
                  }}
                >
                  {headerGroup.headers.map((header) => (
                    <TableCell
                      key={header.id}
                      align="center"
                      sx={({ typography }) => ({
                        ...typography.body2,
                        fontWeight: 700,
                        width: header.id === "select" ? 52 : "auto",
                        py: 1.75,
                        textTransform: "capitalize",
                        borderBottomColor: alpha(palette.text.primary, 0.08),
                        color: palette.text.secondary,
                        letterSpacing: "0.02em",
                        fontSize: 13.5
                      })}
                    >
                      {flexRender(header.column.columnDef.header, header.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableHead>
            <TableBody>
              {isLoading ? (
                Array.from({ length: skeletonRows }).map((_, index) => (
                  <TableRow key={index}>
                    {Array.from({ length: columnCount }).map((_, cellIndex) => (
                      <TableCell
                        key={cellIndex}
                        sx={{
                          width: cellIndex === 0 ? 40 : "auto",
                          borderBottomColor: alpha(palette.text.primary, 0.06),
                          py: 1.5
                        }}
                      >
                        <Skeleton
                          variant="rounded"
                          width={cellIndex === 0 ? 20 : "72%"}
                          height={18}
                          animation="wave"
                          sx={{
                            mx: "auto",
                            bgcolor: alpha(palette.text.primary, 0.06),
                            borderRadius: 1
                          }}
                        />
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              ) : hasRows ? (
                table.getRowModel().rows.map((row) => (
                  <TableRow
                    key={row.id}
                    hover
                    sx={{
                      cursor: onRowClick ? "pointer" : "default",
                      transition: "background-color 160ms ease",
                      backgroundColor: row.getIsSelected()
                        ? alpha(palette.primary.main, 0.06)
                        : "transparent",
                      "&:hover": {
                        backgroundColor: alpha(palette.primary.main, 0.05)
                      },
                      "& td": {
                        borderBottomColor: alpha(palette.text.primary, 0.06)
                      }
                    }}
                    onClick={() => handleRowClick(row)}
                  >
                    {row.getVisibleCells().map((cell) => (
                      <TableCell key={cell.id} align="center" sx={{ py: 1.5 }}>
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              ) : (
                <TableRow>
                  <TableCell colSpan={columnCount} align="center" sx={{ py: 8, border: 0 }}>
                    <Box
                      sx={{
                        display: "flex",
                        flexDirection: "column",
                        alignItems: "center",
                        gap: 0.75
                      }}
                    >
                      <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
                        {t("emptyStateTitle")}
                      </Typography>
                      <Typography variant="body2" sx={{ color: "text.secondary" }}>
                        {t("emptyStateSubtitle")}
                      </Typography>
                    </Box>
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </MuiTable>
        </TableContainer>
      </Box>
    </Box>
  );
}
