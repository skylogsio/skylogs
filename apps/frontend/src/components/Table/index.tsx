"use client";

import { useMemo, useState } from "react";

import {
  Table as MuiTable,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Checkbox,
  TablePagination,
  Skeleton,
  Box,
  alpha,
  Typography,
  Button,
  useTheme,
  Stack
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  flexRender,
  type PaginationState
} from "@tanstack/react-table";
import { HiOutlinePlusSm, HiFilter } from "react-icons/hi";

import { useCurrentDirection } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

import SearchBox from "./SearchBox";
import type { TableComponentProps } from "./types";

export default function Table<T>({
  title,
  url,
  columns,
  hasCheckbox,
  defaultPage = 0,
  defaultPageSize,
  rowsPerPageOptions = [10, 25, 50, 100]
}: TableComponentProps<T>) {
  const [{ pageIndex, pageSize }, setPagination] = useState<PaginationState>({
    pageIndex: defaultPage,
    pageSize: defaultPageSize
  });

  const { palette } = useTheme();
  const direction = useCurrentDirection();
  const t = useScopedI18n("table");

  const { data, isLoading, isError } = useQuery<T[]>({
    queryKey: ["tableData", url, pageIndex, pageSize],
    queryFn: () =>
      fetch(`${url}?offset=${pageIndex * pageSize}&limit=${pageSize}`).then((res) => res.json())
  });

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

  //TODO:این قسمت باید متناسب با سرور تکمیل شود
  const table = useReactTable({
    data: data || [],
    columns: tableColumns,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onPaginationChange: setPagination,
    pageCount: 50,
    state: {
      pagination: { pageIndex, pageSize }
    },
    manualPagination: true
  });

  if (isError)
    return (
      <Typography variant="h5" color="error" mt="2rem">
        {t("errorOnGettingData")}
      </Typography>
    );

  return (
    <Box display="flex" flexDirection="column" width="100%" height="100%">
      <Box
        width="100%"
        display="flex"
        justifyContent="space-between"
        alignItems="flex-end"
        marginBottom="1rem"
        paddingX="1rem"
      >
        {title && (
          <Typography variant="h5" fontSize="1.8rem" fontWeight="700" component="div">
            {title}
          </Typography>
        )}
        <Stack direction="row" spacing={1}>
          <SearchBox title={title} />
          <Button
            startIcon={<HiFilter />}
            size="small"
            variant="outlined"
            sx={{
              backgroundColor: palette.background.paper,
              borderColor: palette.secondary.light,
              color: palette.secondary.dark,
              paddingY: "0.19rem",
              paddingRight: "0.7rem",
              textTransform: "none"
            }}
          >
            {t("filterButton")}
          </Button>
          <Button
            startIcon={<HiOutlinePlusSm size="1.3rem" />}
            size="small"
            variant="contained"
            sx={{ paddingRight: "1rem" }}
          >
            {t("createButton")}
          </Button>
        </Stack>
      </Box>
      <Box
        width="100%"
        height="100%"
        maxHeight="100%"
        bgcolor="background.paper"
        borderRadius="1rem"
        border="1px solid"
        borderColor="grey.200"
        overflow="hidden"
      >
        <TableContainer sx={{ width: "100%", maxHeight: "100%", overflow: "auto" }}>
          <MuiTable stickyHeader sx={{ width: "100%" }}>
            <TableHead>
              {table.getHeaderGroups().map((headerGroup) => (
                <TableRow key={headerGroup.id} sx={{ "& th": { backgroundColor: "grey.50" } }}>
                  {headerGroup.headers.map((header) => (
                    <TableCell
                      key={header.id}
                      sx={({ typography, palette }) => ({
                        ...typography.body1,
                        fontWeight: "bold",
                        width: header.id === "select" ? "50px" : "auto",
                        paddingY: "0.4rem",
                        textTransform: "capitalize",
                        borderBottomColor: palette.grey[200]
                      })}
                    >
                      {flexRender(header.column.columnDef.header, header.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableHead>
            <TableBody>
              {!data || isLoading
                ? Array.from({ length: pageSize }).map((_, index) => (
                    <TableRow key={index}>
                      {Array.from({ length: tableColumns.length }).map((_, cellIndex) => (
                        <TableCell
                          key={cellIndex}
                          sx={{
                            width: cellIndex === 0 ? "40px" : "auto",
                            borderBottomColor: "grey.200"
                          }}
                        >
                          <Skeleton
                            variant="text"
                            width={cellIndex === 0 ? "20px" : "100%"}
                            height="30px"
                            className="mx-auto"
                            animation="wave"
                            sx={{ bgcolor: "grey.200" }}
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
                    >
                      {row.getVisibleCells().map((cell) => (
                        <TableCell key={cell.id} sx={{ borderBottomColor: "grey.200" }}>
                          {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        </TableCell>
                      ))}
                    </TableRow>
                  ))}
            </TableBody>
          </MuiTable>
        </TableContainer>
      </Box>
      <TablePagination
        component="div"
        count={table.getPageCount()}
        page={table.getState().pagination.pageIndex}
        onPageChange={(_, page) => table.setPageIndex(page)}
        rowsPerPage={table.getState().pagination.pageSize}
        onRowsPerPageChange={(e) => table.setPageSize(Number(e.target.value))}
        labelRowsPerPage={t("row.perPage")}
        rowsPerPageOptions={rowsPerPageOptions}
        labelDisplayedRows={({ from, to, count }) =>
          count !== -1
            ? t("row.labelWithCount", { from, to, count })
            : t("row.labelWithoutCount", { from, to })
        }
        sx={{
          overflow: "hidden",
          width: "100%",
          "& .MuiTablePagination-displayedRows": {
            marginLeft: "auto",
            color: "grey.600"
          },
          "& .MuiTablePagination-spacer": {
            display: "none"
          },
          "& .MuiTablePagination-selectLabel": {
            color: "grey.600"
          },
          "& .MuiTablePagination-input": {
            color: "grey.600"
          },
          "& .MuiTablePagination-actions": {
            "& button": {
              transform: `rotateY(${direction === "ltr" ? 0 : "180deg"})`,
              "svg path": {
                color: "grey.600"
              },
              "&.Mui-disabled svg path": {
                color: "grey.400"
              }
            }
          }
        }}
      />
    </Box>
  );
}
