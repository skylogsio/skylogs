"use client";

import { useRouter, useSearchParams, usePathname } from "next/navigation";
import {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState
} from "react";

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
  useTheme,
  tablePaginationClasses
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

import { fetchTableData } from "@/components/Table/SmartTable/fetchTableData";
import { useCurrentDirection } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

import FilterPanel from "./FilterPanel";
import TableToolbar from "./TableToolbar";
import type { SmartTableProps, TableComponentRef } from "./types";
import {
  areFiltersEqual,
  cleanFilters,
  countActiveFilters,
  filtersToSearchParams,
  omitFilterKeys,
  parseFiltersFromUrl
} from "./utils";

function SmartTableInner<T>(
  {
    title,
    url,
    columns,
    hasCheckbox,
    defaultPage = 0,
    defaultPageSize,
    rowsPerPageOptions = [10, 25, 50, 100],
    onCreate,
    createLabel,
    refetchInterval,
    filterComponent,
    defaultFilters = {},
    searchKey = "name",
    syncSearchToUrl = false,
    onRowClick,
    onGroupActionClick,
    toolbarActions,
    toolbarStart,
    toolbarEnd,
    renderToolbar,
    forceShowFilter = false,
    hideSearch = false,
    excludeFilterKeys = []
  }: SmartTableProps<T>,
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
      pageIndex: pageFromUrl ? Number.parseInt(pageFromUrl) - 1 : defaultPage,
      pageSize: pageSizeFromUrl ? Number.parseInt(pageSizeFromUrl) : (defaultPageSize ?? 10)
    };
  };

  const urlFilters = useMemo(
    () => parseFiltersFromUrl(searchParams.get("filters")),
    [searchParams]
  );

  const [{ pageIndex, pageSize }, setPagination] = useState<PaginationState>(getInitialPagination);
  const [openFilterBox, setOpenFilterBox] = useState(false);
  const [draftFilter, setDraftFilter] = useState<Record<string, unknown>>(() => ({
    ...defaultFilters,
    ...parseFiltersFromUrl(searchParams.get("filters"))
  }));
  const [filterInstanceKey, setFilterInstanceKey] = useState(0);
  const [searchValue, setSearchValue] = useState(() =>
    syncSearchToUrl ? (searchParams.get("q") ?? "") : ""
  );
  const defaultFiltersRef = useRef(defaultFilters);
  defaultFiltersRef.current = defaultFilters;

  useEffect(() => {
    setDraftFilter({ ...defaultFiltersRef.current, ...urlFilters });
  }, [urlFilters]);

  const filterSearchParams = useMemo(() => filtersToSearchParams(urlFilters), [urlFilters]);

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

  const pushParams = useCallback(
    (mutate: (params: URLSearchParams) => void) => {
      const params = new URLSearchParams(searchParams.toString());
      mutate(params);
      router.push(`${pathname}?${params.toString()}`);
    },
    [pathname, router, searchParams]
  );

  const updateUrlWithPagination = (newPageIndex: number, newPageSize: number) => {
    pushParams((params) => {
      params.set("page", String(newPageIndex + 1));
      params.set("perPage", String(newPageSize));
    });
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
    pushParams((params) => {
      params.set("page", String(defaultPage + 1));
      params.set("perPage", String(pageSize));
      if (syncSearchToUrl) {
        if (search) params.set("q", search);
        else params.delete("q");
      }
    });
  };

  function handleChangeFilter(key: string, value: unknown) {
    setDraftFilter((prev) => ({ ...prev, [key]: value }));
  }

  function handleSetFilter() {
    const preserved = Object.fromEntries(
      excludeFilterKeys
        .filter(
          (key) =>
            urlFilters[key] !== undefined && urlFilters[key] !== null && urlFilters[key] !== ""
        )
        .map((key) => [key, urlFilters[key]])
    );
    const panelFilters = omitFilterKeys(cleanFilters(draftFilter), excludeFilterKeys);
    const cleanedFilter = cleanFilters({ ...preserved, ...panelFilters });

    table.setPageIndex(defaultPage);
    pushParams((params) => {
      if (Object.keys(cleanedFilter).length > 0) {
        params.set("filters", encodeURIComponent(JSON.stringify(cleanedFilter)));
      } else {
        params.delete("filters");
      }
      params.set("page", String(defaultPage + 1));
      params.set("perPage", String(pageSize));
    });
    setOpenFilterBox(false);
  }

  function handleClearFilters() {
    const preserved = Object.fromEntries(
      excludeFilterKeys
        .filter(
          (key) =>
            urlFilters[key] !== undefined && urlFilters[key] !== null && urlFilters[key] !== ""
        )
        .map((key) => [key, urlFilters[key]])
    );
    const nextDraft = { ...defaultFilters, ...preserved };

    setDraftFilter(nextDraft);
    setFilterInstanceKey((prev) => prev + 1);
    table.setPageIndex(defaultPage);
    pushParams((params) => {
      const cleanedPreserved = cleanFilters(preserved);
      if (Object.keys(cleanedPreserved).length > 0) {
        params.set("filters", encodeURIComponent(JSON.stringify(cleanedPreserved)));
      } else {
        params.delete("filters");
      }
      params.set("page", String(defaultPage + 1));
      params.set("perPage", String(pageSize));
    });
  }

  function handleResetDraft() {
    setDraftFilter({ ...defaultFilters, ...urlFilters });
    setFilterInstanceKey((prev) => prev + 1);
  }

  const handleRowClick = (row: Row<T>) => {
    onRowClick?.(row.original);
  };

  const activeFilterCount = countActiveFilters(urlFilters, excludeFilterKeys);
  const hasActiveFilters = activeFilterCount > 0;
  const isFilterDirty = !areFiltersEqual(
    omitFilterKeys(draftFilter, excludeFilterKeys),
    omitFilterKeys({ ...defaultFilters, ...urlFilters }, excludeFilterKeys)
  );
  const hasFilterFields = Boolean(filterComponent);
  const showFilter = forceShowFilter || hasFilterFields;

  if (isError) {
    return (
      <Typography variant="h5" color="error" sx={{ mt: "2rem" }}>
        {t("errorOnGettingData")}
      </Typography>
    );
  }

  const columnCount = tableColumns.length;
  const hasRows = Boolean(data?.data?.length);

  return (
    <Box
      sx={{
        display: "flex",
        flexDirection: "column",
        width: 1,
        minHeight: 1,
        gap: 0
      }}
    >
      <TableToolbar
        title={title}
        searchValue={searchValue}
        onSearch={handleSearch}
        hideSearch={hideSearch}
        showFilter={showFilter}
        openFilter={openFilterBox}
        onToggleFilter={() => setOpenFilterBox((prev) => !prev)}
        hasActiveFilters={hasActiveFilters}
        activeFilterCount={activeFilterCount}
        isFilterDirty={isFilterDirty}
        onCreate={onCreate}
        createLabel={createLabel}
        toolbarActions={toolbarActions}
        toolbarStart={toolbarStart}
        toolbarEnd={toolbarEnd}
        renderToolbar={renderToolbar}
      />

      {showFilter && (
        <FilterPanel
          open={openFilterBox}
          isDirty={isFilterDirty}
          hasActiveFilters={hasActiveFilters}
          onApply={handleSetFilter}
          onClear={handleClearFilters}
          onResetDraft={handleResetDraft}
          onGroupActionClick={onGroupActionClick}
        >
          <Box key={filterInstanceKey}>
            {filterComponent?.({
              onChange: handleChangeFilter,
              values: draftFilter,
              setValues: setDraftFilter
            })}
          </Box>
        </FilterPanel>
      )}

      <Box
        sx={{
          width: 1,
          height: "70vh",
          mt: 1,
          bgcolor: "background.paper",
          borderRadius: 3,
          border: `1px solid ${alpha(palette.text.primary, 0.08)}`,
          overflow: "hidden",
          boxShadow: `0 1px 2px ${alpha(palette.common.black, palette.mode === "dark" ? 0.2 : 0.04)}`
        }}
      >
        <TableContainer sx={{ width: 1, maxHeight: 1, overflow: "auto" }}>
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
              {!data || isPending ? (
                Array.from({ length: pageSize }).map((_, index) => (
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
                    <StackEmptyState />
                  </TableCell>
                </TableRow>
              )}
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
          [`& .${tablePaginationClasses.displayedRows}`]: {
            marginLeft: "auto",
            color: palette.text.secondary
          },
          [`& .${tablePaginationClasses.spacer}`]: {
            display: "none"
          },
          [`& .${tablePaginationClasses.selectLabel}`]: {
            color: palette.text.secondary
          },
          [`& .${tablePaginationClasses.input}`]: {
            color: palette.text.secondary
          },
          [`& .${tablePaginationClasses.actions}`]: {
            "& button": {
              transform: `rotateY(${direction === "ltr" ? 0 : "180deg"})`,
              "svg path": {
                color: palette.text.secondary
              },
              "&.Mui-disabled svg path": {
                color: palette.action.disabled
              }
            }
          }
        }}
      />
    </Box>
  );
}

function StackEmptyState() {
  const t = useScopedI18n("table");
  return (
    <Box sx={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 0.75 }}>
      <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
        {t("emptyStateTitle")}
      </Typography>
      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        {t("emptyStateSubtitle")}
      </Typography>
    </Box>
  );
}

export default forwardRef(SmartTableInner) as <T>(
  props: SmartTableProps<T> & { ref?: React.Ref<TableComponentRef> }
) => React.ReactElement;

export type {
  SmartTableProps,
  TableFilterComponentProps,
  SmartTableToolbarSlots,
  ToolbarAction,
  ToolbarActionContext,
  CustomToolbarAction,
  BuiltInToolbarAction,
  TableComponentRef
} from "./types";
