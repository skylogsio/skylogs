"use client";

import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";

import {
  alpha,
  Box,
  Chip,
  Divider,
  IconButton,
  InputAdornment,
  Popover,
  Stack,
  TextField,
  Typography,
  type SxProps,
  type Theme
} from "@mui/material";
import { HiOutlineSearch, HiOutlineTag, HiOutlineX } from "react-icons/hi";

import { mergeSx, mixPrimary, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";

const useIsomorphicLayoutEffect = typeof window !== "undefined" ? useLayoutEffect : useEffect;

const tagChipSx: SxProps<Theme> = mergeSx(
  {
    height: 24,
    fontSize: "0.75rem",
    fontWeight: 500,
    borderRadius: "8px",
    color: "text.primary",
    whiteSpace: "nowrap",
    flexShrink: 0,
    maxWidth: "100%",
    "& .MuiChip-label": {
      px: 1,
      overflow: "hidden",
      textOverflow: "ellipsis",
      whiteSpace: "nowrap"
    }
  },
  pillSurfaceSx()
);

const moreChipSx: SxProps<Theme> = mergeSx(
  {
    flexShrink: 0,
    height: 24,
    fontSize: "0.75rem",
    fontWeight: 600,
    borderRadius: "8px",
    cursor: "pointer",
    color: "primary.main",
    transition: "background-color 150ms ease, border-color 150ms ease, transform 150ms ease",
    "& .MuiChip-label": {
      px: 0.9,
      whiteSpace: "nowrap"
    },
    "&:hover": {
      backgroundColor: "#E8E0D4",
      borderColor: mixPrimary(28)
    },
    "&:active": {
      transform: "scale(0.96)"
    }
  },
  pillSurfaceSx(),
  (muiTheme) =>
    muiTheme.applyStyles("dark", {
      "&:hover": {
        backgroundColor: "rgba(255, 255, 255, 0.13)",
        borderColor: mixPrimary(22)
      }
    })
);

const popupTagChipSx: SxProps<Theme> = mergeSx(tagChipSx, {
  maxWidth: 240,
  "& .MuiChip-label": {
    px: 1,
    overflow: "hidden",
    textOverflow: "ellipsis",
    whiteSpace: "nowrap"
  }
});

function computeVisibleCount(
  containerWidth: number,
  tagWidths: number[],
  moreWidth: number,
  gap: number
): number {
  const totalTags = tagWidths.length;
  if (totalTags === 0) return 0;
  if (containerWidth <= 0) return 0;
  if (totalTags === 1) return 1;

  // 1. Check if all tags fit without the "+N" button
  let totalAll = 0;
  for (let i = 0; i < totalTags; i++) {
    totalAll += tagWidths[i] + (i > 0 ? gap : 0);
  }
  if (totalAll <= containerWidth) {
    return totalTags;
  }

  // 2. Otherwise find maximum number of tags that fit alongside the "+N" button
  let runningWidth = 0;
  let maxFit = 0;

  for (let i = 0; i < totalTags; i++) {
    const widthWithThisAndMore = runningWidth + tagWidths[i] + (i > 0 ? gap : 0) + gap + moreWidth;
    if (widthWithThisAndMore <= containerWidth) {
      runningWidth += tagWidths[i] + (i > 0 ? gap : 0);
      maxFit = i + 1;
    } else {
      break;
    }
  }

  return maxFit;
}

export default function TagsCell({
  tags,
  justifyContent = "center",
  sx: extraSx
}: {
  tags: string[];
  justifyContent?: "center" | "flex-start" | "flex-end";
  sx?: SxProps<Theme>;
}) {
  const [visibleCount, setVisibleCount] = useState<number>(() => tags?.length ?? 0);
  const [showMoreAnchorEl, setShowMoreAnchorEl] = useState<HTMLElement | null>(null);
  const [searchTerm, setSearchTerm] = useState("");

  const containerRef = useRef<HTMLDivElement>(null);
  const measureContainerRef = useRef<HTMLDivElement>(null);

  const totalTags = tags?.length ?? 0;

  const updateVisibleCount = useCallback(
    (forcedWidth?: number) => {
      if (!containerRef.current || !measureContainerRef.current || totalTags === 0) return;

      const containerWidth =
        typeof forcedWidth === "number" && forcedWidth > 0
          ? forcedWidth
          : containerRef.current.getBoundingClientRect().width;

      const measureEl = measureContainerRef.current;
      const children = measureEl.children;

      if (children.length < totalTags + 1) return;

      const tagWidths: number[] = [];
      for (let i = 0; i < totalTags; i++) {
        tagWidths.push((children[i] as HTMLElement).getBoundingClientRect().width);
      }
      const moreWidth = (children[totalTags] as HTMLElement).getBoundingClientRect().width;
      const gap = 6;

      const nextCount = computeVisibleCount(containerWidth, tagWidths, moreWidth, gap);
      setVisibleCount((prev) => (prev !== nextCount ? nextCount : prev));
    },
    [totalTags]
  );

  useIsomorphicLayoutEffect(() => {
    updateVisibleCount();

    if (!containerRef.current || typeof ResizeObserver === "undefined") return;

    const observer = new ResizeObserver((entries) => {
      const width = entries[0]?.contentRect?.width;
      updateVisibleCount(typeof width === "number" && width > 0 ? width : undefined);
    });

    observer.observe(containerRef.current);

    return () => {
      observer.disconnect();
    };
  }, [updateVisibleCount]);

  if (!tags || tags.length === 0) {
    return null;
  }

  const effectiveCount = Math.min(visibleCount, tags.length);
  const visibleTags = tags.slice(0, effectiveCount);
  const hiddenTags = tags.slice(effectiveCount);

  const filteredHiddenTags = useMemo(() => {
    if (!searchTerm.trim()) return hiddenTags;
    const lower = searchTerm.toLowerCase();
    return hiddenTags.filter((t) => t.toLowerCase().includes(lower));
  }, [hiddenTags, searchTerm]);

  const handleShowMorePopoverOpen = (event: React.MouseEvent<HTMLElement>) => {
    setShowMoreAnchorEl(event.currentTarget);
  };

  const handleShowMorePopoverClose = () => {
    setShowMoreAnchorEl(null);
    setSearchTerm("");
  };

  const open = Boolean(showMoreAnchorEl);
  const showMorePopoverId = open ? "show-more-tags-popover" : undefined;

  return (
    <>
      <Box
        ref={measureContainerRef}
        aria-hidden="true"
        sx={{
          position: "fixed",
          visibility: "hidden",
          top: -9999,
          left: -9999,
          pointerEvents: "none",
          display: "flex",
          gap: "6px",
          whiteSpace: "nowrap",
          zIndex: -1
        }}
      >
        {tags.map((tag, idx) => (
          <Chip key={idx} variant="filled" size="small" label={tag} sx={tagChipSx} />
        ))}
        <Chip size="small" variant="filled" label={`+${tags.length}`} sx={moreChipSx} />
      </Box>

      <Box
        ref={containerRef}
        sx={mergeSx(
          {
            position: "relative",
            width: "100%",
            maxWidth: "100%",
            minWidth: 0,
            height: 24,
            overflow: "hidden"
          },
          extraSx
        )}
      >
        <Stack
          direction="row"
          spacing={0.75}
          sx={{
            position: "absolute",
            left: 0,
            top: 0,
            bottom: 0,
            right: 0,
            justifyContent,
            alignItems: "center"
          }}
        >
          {visibleTags.map((tag, index) => (
            <Chip key={index} variant="filled" size="small" label={tag} sx={tagChipSx} />
          ))}
          {hiddenTags.length > 0 && (
            <>
              <Chip
                size="small"
                variant="filled"
                label={`+${hiddenTags.length}`}
                onClick={(e) => {
                  e.stopPropagation();
                  handleShowMorePopoverOpen(e);
                }}
                sx={moreChipSx}
              />
              <Popover
                id={showMorePopoverId}
                open={open}
                anchorEl={showMoreAnchorEl}
                onClose={handleShowMorePopoverClose}
                anchorOrigin={{
                  vertical: "bottom",
                  horizontal: justifyContent === "flex-start" ? "left" : "center"
                }}
                transformOrigin={{
                  vertical: "top",
                  horizontal: justifyContent === "flex-start" ? "left" : "center"
                }}
                slotProps={{
                  paper: {
                    onClick: (e: React.MouseEvent) => e.stopPropagation(),
                    sx: (theme) => ({
                      mt: 0.75,
                      borderRadius: "14px",
                      overflow: "hidden",
                      boxShadow:
                        theme.palette.mode === "dark"
                          ? "0 14px 40px rgba(0, 0, 0, 0.55)"
                          : "0 12px 36px rgba(0, 0, 0, 0.14)",
                      border: `1px solid ${alpha(theme.palette.primary.main, 0.22)}`,
                      backgroundColor: theme.palette.background.paper,
                      minWidth: 260,
                      maxWidth: 340
                    })
                  }
                }}
              >
                <Stack
                  direction="row"
                  sx={{
                    alignItems: "center",
                    justifyContent: "space-between",
                    px: 1.5,
                    py: 1
                  }}
                >
                  <Stack direction="row" spacing={0.75} sx={{ alignItems: "center" }}>
                    <HiOutlineTag size={15} color="var(--mui-palette-primary-main)" />
                    <Typography variant="caption" sx={{ fontWeight: 700, fontSize: "0.78rem" }}>
                      More Tags
                    </Typography>
                    <Chip
                      size="small"
                      label={hiddenTags.length}
                      sx={{
                        height: 18,
                        fontSize: "0.68rem",
                        fontWeight: 700,
                        backgroundColor:
                          "color-mix(in srgb, var(--mui-palette-primary-main) 14%, transparent)",
                        color: "primary.main",
                        "& .MuiChip-label": { px: 0.75 }
                      }}
                    />
                  </Stack>
                  <IconButton
                    size="small"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleShowMorePopoverClose();
                    }}
                    sx={{
                      p: 0.5,
                      color: "text.secondary",
                      borderRadius: "6px",
                      "&:hover": {
                        backgroundColor:
                          "color-mix(in srgb, var(--mui-palette-text-secondary) 10%, transparent)"
                      }
                    }}
                    aria-label="Close tags popup"
                  >
                    <HiOutlineX size={14} />
                  </IconButton>
                </Stack>

                <Divider sx={{ borderColor: (theme) => alpha(theme.palette.divider, 0.6) }} />

                {hiddenTags.length > 5 && (
                  <Box sx={{ px: 1.25, pt: 1, pb: 0.5 }}>
                    <TextField
                      size="small"
                      fullWidth
                      placeholder="Filter tags..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      onClick={(e) => e.stopPropagation()}
                      slotProps={{
                        input: {
                          startAdornment: (
                            <InputAdornment
                              position="start"
                              sx={{ color: "text.secondary", mr: 0.75 }}
                            >
                              <HiOutlineSearch size={14} />
                            </InputAdornment>
                          ),
                          sx: {
                            height: 30,
                            fontSize: "0.75rem",
                            borderRadius: "8px",
                            backgroundColor: (theme) =>
                              theme.palette.mode === "dark"
                                ? "rgba(255, 255, 255, 0.05)"
                                : "rgba(0, 0, 0, 0.03)"
                          }
                        }
                      }}
                    />
                  </Box>
                )}

                {/* Tag List */}
                <Box
                  sx={{
                    p: 1.25,
                    maxHeight: 220,
                    overflowY: "auto",
                    "&::-webkit-scrollbar": { width: 5 },
                    "&::-webkit-scrollbar-thumb": {
                      backgroundColor: (theme) => alpha(theme.palette.text.primary, 0.15),
                      borderRadius: 3
                    }
                  }}
                >
                  {filteredHiddenTags.length === 0 ? (
                    <Typography
                      variant="caption"
                      sx={{
                        color: "text.disabled",
                        display: "block",
                        textAlign: "center",
                        py: 1.5
                      }}
                    >
                      No tags match &quot;{searchTerm}&quot;
                    </Typography>
                  ) : (
                    <Stack direction="row" spacing={0.75} useFlexGap sx={{ flexWrap: "wrap" }}>
                      {filteredHiddenTags.map((tag, index) => (
                        <Chip
                          key={`${tag}-${index}`}
                          variant="filled"
                          size="small"
                          label={tag}
                          sx={popupTagChipSx}
                        />
                      ))}
                    </Stack>
                  )}
                </Box>
              </Popover>
            </>
          )}
        </Stack>
      </Box>
    </>
  );
}
