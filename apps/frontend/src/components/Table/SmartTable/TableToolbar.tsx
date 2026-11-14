"use client";

import { type MouseEventHandler, type ReactNode } from "react";

import { Badge, Box, Button, IconButton, Stack, Typography } from "@mui/material";
import { motion } from "framer-motion";
import { HiOutlinePlusSm, HiFilter } from "react-icons/hi";

import { useScopedI18n } from "@/locales/client";

import SearchControl from "./SearchControl";
import type { SmartTableToolbarSlots, ToolbarAction, ToolbarActionContext } from "./types";
import { isBuiltInToolbarAction } from "./utils";

interface TableToolbarProps {
  title?: string;
  titleExtra?: ReactNode;
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
  titleExtra,
  renderToolbar
}: TableToolbarProps) {
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
      startIcon={<HiFilter size="1rem" />}
      onClick={onToggleFilter}
      aria-expanded={openFilter}
      sx={[
        (theme) => {
          const isActive = openFilter || hasActiveFilters;
          return {
            height: 33,
            minHeight: 33,
            borderRadius: "10px",
            px: 1.5,
            fontSize: "0.8125rem",
            textTransform: "none",
            fontWeight: 550,
            letterSpacing: "0.01em",
            border: "1px solid",
            transition: "all 0.18s cubic-bezier(0.22, 1, 0.36, 1)",
            "& .MuiButton-startIcon": {
              mr: 0.65
            },
            ...(isActive
              ? {
                  borderColor: `color-mix(in srgb, ${theme.palette.primary.main} 45%, transparent)`,
                  bgcolor: `color-mix(in srgb, ${theme.palette.primary.main} 12%, transparent)`,
                  color: "primary.main",
                  boxShadow: `0 2px 8px color-mix(in srgb, ${theme.palette.primary.main} 18%, transparent)`,
                  ...theme.applyStyles("dark", {
                    borderColor: `color-mix(in srgb, ${theme.palette.primary.main} 55%, transparent)`,
                    bgcolor: `color-mix(in srgb, ${theme.palette.primary.main} 18%, transparent)`,
                    color: "primary.light",
                    boxShadow: `0 2px 8px color-mix(in srgb, ${theme.palette.primary.main} 25%, transparent)`
                  }),
                  "&:hover": {
                    borderColor: `color-mix(in srgb, ${theme.palette.primary.main} 60%, transparent)`,
                    bgcolor: `color-mix(in srgb, ${theme.palette.primary.main} 18%, transparent)`
                  }
                }
              : {
                  borderColor: "color-mix(in srgb, var(--mui-palette-text-primary) 12%, transparent)",
                  bgcolor: theme.palette.background.paper,
                  color: "text.secondary",
                  boxShadow: "0 1px 2px rgba(0, 0, 0, 0.03)",
                  ...theme.applyStyles("dark", {
                    bgcolor: "rgba(18, 0, 0, 0.05)",
                    borderColor: `color-mix(in srgb, ${theme.palette.text.primary} 12%, transparent)`
                  }),
                  "&:hover": {
                    borderColor: `color-mix(in srgb, ${theme.palette.primary.main} 35%, transparent)`,
                    bgcolor: `color-mix(in srgb, ${theme.palette.primary.main} 8%, transparent)`,
                    color: "primary.main",
                    ...theme.applyStyles("dark", {
                      bgcolor: "rgba(255, 255, 255, 0.09)",
                      color: "primary.light"
                    })
                  }
                }),
          };
        }
      ]}
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
                  fontSize: "0.65rem",
                  fontWeight: 700,
                  height: 17,
                  minWidth: 17,
                  borderRadius: "999px",
                  px: 0.5
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
        bgcolor: "primary.main",
        color: "primary.contrastText",
        "&:hover": {
          bgcolor: "primary.dark"
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
      <Stack
        direction="row"
        spacing={2}
        sx={{
          alignItems: "center",
          flexWrap: "wrap",
          rowGap: 1
        }}
      >
        {titleNode}
        {titleExtra}
      </Stack>
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
