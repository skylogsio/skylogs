"use client";

import { useRouter, useSearchParams, usePathname } from "next/navigation";
import React, { forwardRef, useImperativeHandle, useMemo, useState, useEffect } from "react";

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
  Stack,
  Collapse
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  flexRender,
  type PaginationState,
  type Row
} from "@tanstack/react-table";
import { HiOutlinePlusSm, HiFilter } from "react-icons/hi";

import { fetchTableData } from "@/components/Table/SmartTable/fetchTableData";
import { useCurrentDirection } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

import SearchBox from "../SearchBox";
import { SmartTableComponentProps, TableComponentRef } from "../types";

function Table<T>(
  {
    title,
    url,
    columns,
    hasCheckbox,
    defaultPage = 0,
    defaultPageSize,
    rowsPerPageOptions = [10, 25, 50, 100],
    onCreate,
    refetchInterval,
    filterComponent,
    searchKey = "name",
    onRowClick,
    onGroupActionClick
  }: SmartTableComponentProps<T>,
  ref: React.Ref<TableComponentRef>
) {
  const { palette } = useTheme();
  const direction = useCurrentDirection();
  const t = useScopedI18n("table");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const getInitialPagination = (): PaginationState => {
    const pageFromUrl = searchParams.get("page");
    const pageSizeFromUrl = searchParams.get("perPage");

    return {
      pageIndex: pageFromUrl ? parseInt(pageFromUrl) - 1 : defaultPage,
      pageSize: pageSizeFromUrl ? parseInt(pageSizeFromUrl) : (defaultPageSize ?? 10)
    };
  };

  const [{ pageIndex, pageSize }, setPagination] = useState<PaginationState>(getInitialPagination);
  const [openFilterBox, setOpenFilterBox] = useState(false);
  const [filter, setFilter] = useState<Record<string, unknown>>({});
  const [searchValue, setSearchValue] = useState("");

  useEffect(() => {
    const filterParam = searchParams.get("filters");
    if (filterParam) {
      try {
        const parsedFilters = JSON.parse(decodeURIComponent(filterParam));
        setFilter(parsedFilters);
      } catch (error) {
        console.error("Error parsing filters from URL:", error);
      }
    }
  }, [searchParams]);

  const filterSearchParams = useMemo(() => {
    const filterParam = searchParams.get("filters");
    if (filterParam) {
      try {
        const parsedFilters = JSON.parse(decodeURIComponent(filterParam));
        const params = new URLSearchParams();
        Object.entries(parsedFilters).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== "") {
            if (Array.isArray(value)) {
              params.append(key, value.join(","));
            } else {
              params.append(key, String(value));
            }
          }
        });
        return params.toString();
      } catch (error) {
        console.error("Error parsing filters from URL:", error);
        return "";
      }
    }
    return "";
  }, [searchParams]);

  const { data, isPending, isError, refetch } = useQuery({
    queryKey: ["tableData", url, pageIndex, pageSize, filterSearchParams, searchValue, searchKey],
    queryFn: () =>
      fetchTableData<T>({
        url,
        pageIndex,
        pageSize,
        filterSearchParams,
        searchKey: searchKey as string,
        searchValue
      }),
    refetchInterval
  });

  useImperativeHandle(ref, () => ({
    refreshData: refetch
  }));

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

  const updateUrlWithPagination = (newPageIndex: number, newPageSize: number) => {
    const params = new URLSearchParams(searchParams.toString());
    params.set("page", String(newPageIndex + 1));
    params.set("perPage", String(newPageSize));
    router.push(`${pathname}?${params.toString()}`);
  };

  const table = useReactTable({
    data: data?.data || [],
    columns: tableColumns,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onPaginationChange: setPagination,
    pageCount: data?.last_page,
    state: {
      pagination: { pageIndex, pageSize }
    },
    manualPagination: true
  });

  const handleSearch = (search: string) => {
    setSearchValue(search);
    table.setPageIndex(defaultPage);
    updateUrlWithPagination(defaultPage, pageSize);
  };

  function handleChangeFilter(key: string, value: unknown) {
    setFilter((prev) => ({ ...prev, [key]: value }));
  }

  function handleSetFilter() {
    const cleanedFilter = Object.entries(filter).reduce(
      (acc, [key, value]) => {
        if (
          value !== undefined &&
          value !== null &&
          value !== "" &&
          !(Array.isArray(value) && value.length === 0)
        ) {
          acc[key] = value;
        }
        return acc;
      },
      {} as Record<string, unknown>
    );

    const params = new URLSearchParams(searchParams.toString());

    if (Object.keys(cleanedFilter).length > 0) {
      params.set("filters", encodeURIComponent(JSON.stringify(cleanedFilter)));
    } else {
      params.delete("filters");
    }

    table.setPageIndex(defaultPage);
    params.set("page", String(defaultPage + 1));
    params.set("perPage", String(pageSize));

    router.push(`${pathname}?${params.toString()}`);
  }

  function handleClearFilters() {
    setFilter({});
    const params = new URLSearchParams(searchParams.toString());
    params.delete("filters");

    table.setPageIndex(defaultPage);
    params.set("page", String(defaultPage + 1));
    params.set("perPage", String(pageSize));

    router.push(`${pathname}?${params.toString()}`);
  }

  if (isError)
    return (
      <Typography variant="h5" color="error" mt="2rem">
        {t("errorOnGettingData")}
      </Typography>
    );

  const handleRowClick = (row: Row<T>) => {
    if (onRowClick) {
      onRowClick(row.original);
    }
  };

  const hasActiveFilters = Object.keys(filter).some((key) => {
    const value = filter[key];
    return (
      value !== undefined &&
      value !== null &&
      value !== "" &&
      !(Array.isArray(value) && value.length === 0)
    );
  });

  return (
    <Box display="flex" flexDirection="column" width="100%" minHeight="100%">
      <Box
        width="100%"
        display="flex"
        justifyContent="space-between"
        alignItems="flex-end"
        paddingX={1}
      >
        {title && (
          <Typography variant="h5" fontSize="1.8rem" fontWeight="700" component="div">
            {title}
          </Typography>
        )}
        <Stack direction="row" spacing={1}>
          <SearchBox title={title} onSearch={handleSearch} />
          <Button
            startIcon={<HiFilter />}
            size="small"
            variant="outlined"
            onClick={() => setOpenFilterBox((prev) => !prev)}
            sx={{
              backgroundColor:
                openFilterBox || hasActiveFilters
                  ? palette.secondary.dark
                  : palette.background.paper,
              borderColor: hasActiveFilters ? palette.secondary.dark : palette.secondary.light,
              paddingY: "0.19rem",
              color:
                openFilterBox || hasActiveFilters
                  ? palette.background.paper
                  : palette.secondary.dark,
              paddingRight: "0.7rem",
              textTransform: "none"
            }}
          >
            {t("filterButton")}{" "}
            {hasActiveFilters &&
              `(${
                Object.keys(filter).filter((key) => {
                  const value = filter[key];
                  return (
                    value !== undefined &&
                    value !== null &&
                    value !== "" &&
                    !(Array.isArray(value) && value.length === 0)
                  );
                }).length
              })`}
          </Button>
          {onCreate && (
            <Button
              startIcon={<HiOutlinePlusSm size="1.3rem" />}
              onClick={onCreate}
              size="small"
              variant="contained"
              sx={{ paddingRight: "1rem" }}
            >
              {t("createButton")}
            </Button>
          )}
        </Stack>
      </Box>
      <Collapse in={openFilterBox}>
        <Stack
          padding={1}
          spacing={1}
          bgcolor={palette.background.paper}
          borderRadius="1rem"
          marginTop={1}
          border="1px solid"
          borderColor="grey.200"
        >
          {filterComponent?.({ onChange: handleChangeFilter })}
          <Stack direction="row" spacing={1} justifyContent="space-between">
            <Box>
              {hasActiveFilters && (
                <Button size="small" variant="text" onClick={handleClearFilters}>
                  Clear Filters
                </Button>
              )}
            </Box>
            <Stack direction="row" spacing={1}>
              {onGroupActionClick && (
                <Button size="small" variant="outlined" onClick={onGroupActionClick}>
                  Group Actions
                </Button>
              )}
              <Button size="small" variant="contained" onClick={handleSetFilter}>
                Apply Filters
              </Button>
            </Stack>
          </Stack>
        </Stack>
      </Collapse>
      <Box
        width="100%"
        height="70vh"
        bgcolor="background.paper"
        borderRadius="1rem"
        border="1px solid"
        borderColor="grey.200"
        overflow="hidden"
        marginTop={1}
      >
        <TableContainer sx={{ width: "100%", maxHeight: "100%", overflow: "auto" }}>
          <MuiTable stickyHeader sx={{ width: "100%" }}>
            <TableHead>
              {table.getHeaderGroups().map((headerGroup) => (
                <TableRow key={headerGroup.id} sx={{ "& th": { backgroundColor: "grey.50" } }}>
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
                        borderBottomColor: palette.grey[200],
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
              {!data || isPending
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
                      onClick={() => handleRowClick(row)}
                    >
                      {row.getVisibleCells().map((cell) => (
                        <TableCell
                          key={cell.id}
                          sx={{ borderBottomColor: "grey.200" }}
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
      <TablePagination
        component="div"
        count={data?.total ?? 0}
        page={table.getState().pagination.pageIndex}
        onPageChange={(_, page) => {
          table.setPageIndex(page);
          updateUrlWithPagination(page, pageSize);
        }}
        rowsPerPage={table.getState().pagination.pageSize}
        onRowsPerPageChange={(e) => {
          const newPageSize = Number(e.target.value);
          table.setPageSize(newPageSize);
          updateUrlWithPagination(0, newPageSize);
        }}
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

export default forwardRef(Table) as <T>(
  props: SmartTableComponentProps<T> & { ref?: React.Ref<TableComponentRef> }
) => React.ReactElement;
