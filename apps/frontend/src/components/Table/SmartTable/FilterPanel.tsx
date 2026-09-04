"use client";

import { type ReactNode } from "react";

import { Box, Button, Collapse, Divider, Stack, Typography, alpha, useTheme } from "@mui/material";
import { HiFilter, HiOutlineRefresh } from "react-icons/hi";

import { useScopedI18n } from "@/locales/client";

interface FilterPanelProps {
  open: boolean;
  children?: ReactNode;
  isDirty: boolean;
  hasActiveFilters: boolean;
  onApply: () => void;
  onClear: () => void;
  onResetDraft: () => void;
  onGroupActionClick?: () => void;
}

export default function FilterPanel({
  open,
  children,
  isDirty,
  hasActiveFilters,
  onApply,
  onClear,
  onResetDraft,
  onGroupActionClick
}: FilterPanelProps) {
  const { palette } = useTheme();
  const t = useScopedI18n("table");

  return (
    <Collapse in={open} timeout={220}>
      <Box
        sx={{
          mt: 1.5,
          borderRadius: 3,
          border: `1px solid ${alpha(palette.text.primary, 0.08)}`,
          bgcolor: palette.background.paper,
          overflow: "hidden",
          boxShadow: `0 8px 24px ${alpha(palette.common.black, palette.mode === "dark" ? 0.28 : 0.06)}`
        }}
      >
        <Stack
          direction="row"
          spacing={1.5}
          sx={{
            alignItems: "center",
            px: 2,
            py: 1.5,
            bgcolor:
              palette.mode === "dark"
                ? alpha(palette.common.white, 0.03)
                : alpha(palette.primary.main, 0.03),
            borderBottom: `1px solid ${alpha(palette.text.primary, 0.06)}`
          }}
        >
          <Box
            sx={{
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              width: 32,
              height: 32,
              borderRadius: 2,
              bgcolor: alpha(palette.primary.main, 0.1),
              color: palette.primary.main
            }}
          >
            <HiFilter size="1rem" />
          </Box>
          <Box sx={{ flex: 1, minWidth: 0 }}>
            <Typography variant="subtitle2" sx={{ fontWeight: 700, lineHeight: 1.2 }}>
              {t("filterPanelTitle")}
            </Typography>
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              {t("filterPanelSubtitle")}
            </Typography>
          </Box>
          {isDirty && (
            <Button
              size="small"
              variant="text"
              startIcon={<HiOutlineRefresh size="0.9rem" />}
              onClick={onResetDraft}
              sx={{ textTransform: "none", color: "text.secondary" }}
            >
              {t("resetDraftFilters")}
            </Button>
          )}
        </Stack>

        <Box sx={{ p: 2 }}>{children}</Box>

        <Divider sx={{ borderColor: alpha(palette.text.primary, 0.06) }} />

        <Stack
          direction="row"
          spacing={1}
          sx={{
            px: 2,
            py: 1.5,
            alignItems: "center",
            justifyContent: "space-between",
            bgcolor:
              palette.mode === "dark"
                ? alpha(palette.common.white, 0.02)
                : alpha(palette.grey[500], 0.04)
          }}
        >
          <Box>
            {hasActiveFilters && (
              <Button
                size="small"
                variant="text"
                color="error"
                onClick={onClear}
                sx={{ textTransform: "none" }}
              >
                {t("clearAllFilters")}
              </Button>
            )}
          </Box>
          <Stack direction="row" spacing={1}>
            {onGroupActionClick && (
              <Button
                size="small"
                variant="outlined"
                onClick={onGroupActionClick}
                sx={{ textTransform: "none" }}
              >
                {t("groupActions")}
              </Button>
            )}
            <Button
              size="small"
              variant="contained"
              onClick={onApply}
              disabled={!isDirty}
              sx={{
                textTransform: "none",
                px: 2,
                borderRadius: 2,
                boxShadow: "none",
                "&:hover": { boxShadow: "none" }
              }}
            >
              {t("applyFilters")}
            </Button>
          </Stack>
        </Stack>
      </Box>
    </Collapse>
  );
}
