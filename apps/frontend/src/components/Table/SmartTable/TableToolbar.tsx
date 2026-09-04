"use client";

import { type MouseEventHandler, type ReactNode } from "react";

import { Badge, Box, Button, IconButton, Stack, Typography, alpha, useTheme } from "@mui/material";
import { motion } from "framer-motion";
import { HiOutlinePlusSm, HiFilter } from "react-icons/hi";

import { useScopedI18n } from "@/locales/client";

import SearchControl from "./SearchControl";
import type { SmartTableToolbarSlots, ToolbarAction, ToolbarActionContext } from "./types";
import { isBuiltInToolbarAction } from "./utils";

interface TableToolbarProps {
  title?: string;
  searchTitle?: string;
  searchValue: string;
  onSearch: (value: string) => void;
  hideSearch?: boolean;
  showFilter: boolean;
  openFilter: boolean;
  onToggleFilter: () => void;
  hasActiveFilters: boolean;
  activeFilterCount: number;
  isFilterDirty: boolean;
  onCreate?: MouseEventHandler<HTMLButtonElement>;
  createLabel?: string;
  toolbarActions?: ToolbarAction[];
  toolbarStart?: ReactNode;
  toolbarEnd?: ReactNode;
  renderToolbar?: (slots: SmartTableToolbarSlots) => ReactNode;
}

const DEFAULT_ACTIONS: ToolbarAction[] = ["search", "filter", "create"];

export default function TableToolbar({
  title,
  searchTitle,
  searchValue,
  onSearch,
  hideSearch,
  showFilter,
  openFilter,
  onToggleFilter,
  hasActiveFilters,
  activeFilterCount,
  isFilterDirty,
  onCreate,
  createLabel,
  toolbarActions = DEFAULT_ACTIONS,
  toolbarStart,
  toolbarEnd,
  renderToolbar
}: TableToolbarProps) {
  const { palette } = useTheme();
  const t = useScopedI18n("table");

  const actionCtx: ToolbarActionContext = {
    openFilter,
    toggleFilter: onToggleFilter,
    hasActiveFilters,
    activeFilterCount,
    isFilterDirty,
    searchValue,
    onSearch
  };

  const searchNode = hideSearch ? null : (
    <SearchControl title={searchTitle ?? title} value={searchValue} onSearch={onSearch} />
  );

  const filterNode = showFilter ? (
    <Button
      size="small"
      variant="outlined"
      startIcon={<HiFilter size="0.95rem" />}
      onClick={onToggleFilter}
      aria-expanded={openFilter}
      sx={{
        height: 33,
        minHeight: 33,
        borderRadius: 2,
        px: 1.35,
        fontSize: 12.5,
        textTransform: "none",
        fontWeight: 400,
        "& .MuiButton-startIcon": {
          mr: 0.5
        },
        borderColor:
          openFilter || hasActiveFilters
            ? alpha(palette.primary.main, 0.45)
            : alpha(palette.text.primary, 0.12),
        bgcolor:
          openFilter || hasActiveFilters
            ? alpha(palette.primary.main, 0.1)
            : palette.background.paper,
        color: openFilter || hasActiveFilters ? palette.primary.main : palette.text.secondary,
        "&:hover": {
          borderColor: alpha(palette.primary.main, 0.45),
          bgcolor: alpha(palette.primary.main, 0.08)
        }
      }}
    >
      {t("filterButton")}
      <Box
        component={motion.span}
        initial={false}
        animate={{
          gridTemplateColumns: hasActiveFilters || isFilterDirty ? "1fr" : "0fr",
          marginLeft: hasActiveFilters || isFilterDirty ? 8 : 0,
          opacity: hasActiveFilters || isFilterDirty ? 1 : 0
        }}
        transition={{ duration: 0.24, ease: [0.22, 1, 0.36, 1] }}
        sx={{
          display: "inline-grid",
          verticalAlign: "middle",
          alignItems: "center"
        }}
      >
        <Box sx={{ overflow: "hidden", minWidth: 0 }}>
          <Box
            component={motion.span}
            initial={false}
            animate={{
              scale: hasActiveFilters || isFilterDirty ? 1 : 0.6
            }}
            transition={{ duration: 0.2, ease: [0.22, 1, 0.36, 1] }}
            sx={{
              display: "inline-flex",
              transformOrigin: "center left"
            }}
          >
            <Badge
              badgeContent={activeFilterCount || "!"}
              color={isFilterDirty && !hasActiveFilters ? "warning" : "primary"}
              sx={{
                "& .MuiBadge-badge": {
                  position: "static",
                  transform: "none",
                  fontSize: "0.6rem",
                  height: 16,
                  minWidth: 16,
                  borderRadius: 1
                }
              }}
            />
          </Box>
        </Box>
      </Box>
    </Button>
  ) : null;

  const createNode = onCreate ? (
    <IconButton
      onClick={onCreate}
      aria-label={createLabel ?? t("createButton")}
      size="small"
      sx={{
        width: 33,
        height: 33,
        borderRadius: 2,
        bgcolor: palette.primary.main,
        color: palette.primary.contrastText,
        "&:hover": {
          bgcolor: palette.primary.dark
        }
      }}
    >
      <HiOutlinePlusSm size="1.05rem" />
    </IconButton>
  ) : null;

  const builtIns: Record<"search" | "filter" | "create", ReactNode | null> = {
    search: searchNode,
    filter: filterNode,
    create: createNode
  };

  const resolvedActions = (toolbarActions ?? DEFAULT_ACTIONS).filter((action) => {
    if (action === "filter") return showFilter;
    if (action === "search") return !hideSearch;
    if (action === "create") return Boolean(onCreate);
    return true;
  });

  const renderActions = (actions: ToolbarAction[]) => (
    <Stack
      direction="row"
      spacing={0.75}
      useFlexGap
      sx={{ alignItems: "center", flexWrap: "wrap" }}
    >
      {actions.map((action) => {
        if (isBuiltInToolbarAction(action)) {
          const node = builtIns[action];
          return node ? <Box key={action}>{node}</Box> : null;
        }

        const content =
          typeof action.render === "function" ? action.render(actionCtx) : action.render;

        return <Box key={action.id}>{content}</Box>;
      })}
    </Stack>
  );

  const actionsRow = renderActions(resolvedActions);

  const titleNode = title ? (
    <Typography
      variant="h5"
      component="h1"
      sx={{
        fontSize: { xs: 22, sm: 26 },
        fontWeight: 700,
        letterSpacing: "-0.02em",
        lineHeight: 1.2
      }}
    >
      {title}
    </Typography>
  ) : null;

  if (renderToolbar) {
    return (
      <>
        {renderToolbar({
          title: titleNode,
          search: searchNode,
          filter: filterNode,
          create: createNode,
          actions: actionsRow
        })}
      </>
    );
  }

  return (
    <Stack
      direction={{ xs: "column", sm: "row" }}
      spacing={1.5}
      sx={{
        alignItems: { xs: "stretch", sm: "center" },
        justifyContent: "space-between",
        width: 1
      }}
    >
      {titleNode}
      <Stack
        direction="row"
        spacing={0.75}
        useFlexGap
        sx={{
          alignItems: "center",
          flexWrap: "wrap",
          justifyContent: { xs: "flex-start", sm: "flex-end" }
        }}
      >
        {toolbarStart}
        {actionsRow}
        {toolbarEnd}
      </Stack>
    </Stack>
  );
}
