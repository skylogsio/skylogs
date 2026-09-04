export default {
  table: {
    errorOnGettingData: "Error fetching data",
    createButton: "Create",
    filterButton: "Filter",
    applyFilters: "Apply Filters",
    clearAllFilters: "Clear all",
    resetDraftFilters: "Reset",
    activeFilters: "Active",
    filterPanelTitle: "Filters",
    filterPanelSubtitle: "Narrow results, then apply",
    groupActions: "Group Actions",
    totalResults: "{count} results",
    emptyStateTitle: "No results found",
    emptyStateSubtitle: "Try adjusting search or filters",
    row: {
      labelWithCount: "Showing {from}–{to} of {count}",
      labelWithoutCount: "Showing {from}–{to} of more than {to}",
      perPage: "Rows per page"
    },
    searchBox: {
      title: "Search in {title}"
    }
  }
} as const;
