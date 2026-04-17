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
  alpha
} from "@mui/material";
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  flexRender,
  type Row
} from "@tanstack/react-table";

import type { DataTableComponentProps } from "../types";

export default function DataTable<T>({
  data,
  columns,
  hasCheckbox,
  isLoading,
  onRowClick
}: DataTableComponentProps<T>) {
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
    if (onRowClick) {
      onRowClick(row.original);
    }
  };

  return (
    <Box display="flex" flexDirection="column" width="100%" minHeight="100%">
      <Box
        width="100%"
        bgcolor="background.paper"
        borderRadius="1rem"
        border="1px solid"
        borderColor={(theme) => theme.palette.divider}
        overflow="hidden"
        marginTop={1}
      >
        <TableContainer sx={{ width: "100%", maxHeight: "70vh", overflow: "auto" }}>
          <MuiTable stickyHeader sx={{ width: "100%" }}>
            <TableHead>
              {table.getHeaderGroups().map((headerGroup) => (
                <TableRow
                  key={headerGroup.id}
                  sx={{
                    "& th": {
                      backgroundColor: (theme) =>
                        theme.palette.mode === "dark"
                          ? theme.palette.grey[900]
                          : theme.palette.grey[50]
                    }
                  }}
                >
                  {headerGroup.headers.map((header) => (
                    <TableCell
                      key={header.id}
                      align="center"
                      sx={({ typography, palette }) => ({
                        ...typography.body1,
                        fontWeight: "bold",
                        width: header.id === "select" ? "50px" : "auto",
                        paddingY: "1rem",
                        textTransform: "capitalize",
                        borderBottomColor: palette.divider,
                        fontSize: "0.9rem"
                      })}
                    >
                      {flexRender(header.column.columnDef.header, header.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableHead>
            <TableBody>
              {isLoading
                ? Array.from({ length: 10 }).map((_, index) => (
                    <TableRow key={index}>
                      {Array.from({ length: tableColumns.length }).map((_, cellIndex) => (
                        <TableCell
                          key={cellIndex}
                          sx={{
                            width: cellIndex === 0 ? "40px" : "auto",
                            borderBottomColor: (theme) => theme.palette.divider
                          }}
                        >
                          <Skeleton
                            variant="text"
                            width={cellIndex === 0 ? "20px" : "100%"}
                            height="30px"
                            className="mx-auto"
                            animation="wave"
                            sx={{ bgcolor: (theme) => theme.palette.action.hover }}
                          />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                : table.getRowModel().rows.map((row) => (
                    <TableRow
                      key={row.id}
                      sx={{
                        width: row.id === "select" ? "50px" : "auto",
                        transition: "background-color 200ms ease",
                        backgroundColor: ({ palette }) =>
                          row.getIsSelected() ? alpha(palette.primary.main, 0.06) : "transparent",
                        "&:hover": {
                          backgroundColor: ({ palette }) => alpha(palette.primary.main, 0.06)
                        }
                      }}
                      onClick={() => handleRowClick(row)}
                    >
                      {row.getVisibleCells().map((cell) => (
                        <TableCell
                          key={cell.id}
                          sx={{ borderBottomColor: (theme) => theme.palette.divider }}
                          align="center"
                        >
                          {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        </TableCell>
                      ))}
                    </TableRow>
                  ))}
            </TableBody>
          </MuiTable>
        </TableContainer>
      </Box>
    </Box>
  );
}
