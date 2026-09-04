"use client";

import { useState, useRef, useCallback, useEffect } from "react";

import {
  Box,
  Collapse,
  IconButton,
  InputAdornment,
  TextField,
  alpha,
  useTheme
} from "@mui/material";
import { HiOutlineSearch, HiOutlineX } from "react-icons/hi";

import { useScopedI18n } from "@/locales/client";

interface SearchControlProps {
  title?: string;
  value?: string;
  onSearch?: (searchText: string) => void;
}

export default function SearchControl({ title, value = "", onSearch }: SearchControlProps) {
  const { palette } = useTheme();
  const t = useScopedI18n("table");

  const [isOpen, setIsOpen] = useState(Boolean(value));
  const [searchText, setSearchText] = useState(value);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    setSearchText(value);
    if (value) setIsOpen(true);
  }, [value]);

  useEffect(() => {
    if (isOpen) {
      const frame = requestAnimationFrame(() => {
        inputRef.current?.focus();
      });
      return () => cancelAnimationFrame(frame);
    }
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    function handlePointerDown(event: MouseEvent | TouchEvent) {
      const target = event.target as Node | null;
      if (!target || containerRef.current?.contains(target)) return;

      if (searchText.trim()) return;

      setIsOpen(false);
    }

    document.addEventListener("mousedown", handlePointerDown);
    document.addEventListener("touchstart", handlePointerDown);

    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
      document.removeEventListener("touchstart", handlePointerDown);
    };
  }, [isOpen, searchText]);

  const emitSearch = useCallback(
    (next: string) => {
      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }
      debounceTimer.current = setTimeout(() => {
        onSearch?.(next);
      }, 300);
    },
    [onSearch]
  );

  const handleSearchChange = (next: string) => {
    setSearchText(next);
    emitSearch(next);
  };

  const handleClear = () => {
    setSearchText("");
    onSearch?.("");
    inputRef.current?.focus();
  };

  const iconButtonSx = {
    width: 33,
    height: 33,
    borderRadius: 2,
    border: "1px solid",
    borderColor: alpha(palette.text.primary, 0.12),
    bgcolor: palette.background.paper,
    color: palette.text.secondary,
    "&:hover": {
      bgcolor: alpha(palette.primary.main, 0.06),
      borderColor: alpha(palette.primary.main, 0.3),
      color: palette.primary.main
    }
  } as const;

  return (
    <Box ref={containerRef} sx={{ display: "inline-flex", alignItems: "center" }}>
      <Collapse orientation="horizontal" in={!isOpen} timeout={220} unmountOnExit>
        <IconButton
          onClick={() => setIsOpen(true)}
          aria-label={t("searchBox.title", { title })}
          size="small"
          sx={iconButtonSx}
        >
          <HiOutlineSearch size="1rem" />
        </IconButton>
      </Collapse>

      <Collapse orientation="horizontal" in={isOpen} timeout={220} unmountOnExit>
        <TextField
          size="small"
          value={searchText}
          onChange={(event) => handleSearchChange(event.target.value)}
          placeholder={t("searchBox.title", { title })}
          inputRef={inputRef}
          sx={{
            minWidth: { xs: 150, sm: 210 },
            "& .MuiInputBase-root": {
              height: 33,
              borderRadius: 2,
              px: 1.2,
              bgcolor: palette.background.paper,
              fontSize: 12.5,
              transition: "box-shadow 0.2s ease, border-color 0.2s ease",
              "&:hover": {
                bgcolor: palette.background.paper
              },
              "&.Mui-focused": {
                boxShadow: `0 0 0 3px ${alpha(palette.primary.main, 0.12)}`
              }
            },
            "& .MuiInputBase-input": {
              px: 0.8
            },
            "& .MuiInputAdornment-root": {
              margin: 0
            },
            "& .MuiOutlinedInput-notchedOutline": {
              borderColor: alpha(palette.text.primary, 0.12)
            },
            "&:hover .MuiOutlinedInput-notchedOutline": {
              borderColor: alpha(palette.primary.main, 0.35)
            }
          }}
          slotProps={{
            input: {
              startAdornment: (
                <InputAdornment position="start">
                  <Box sx={{ display: "flex", color: "text.secondary" }}>
                    <HiOutlineSearch size="0.95rem" />
                  </Box>
                </InputAdornment>
              ),
              endAdornment: (
                <InputAdornment position="end">
                  <IconButton
                    size="small"
                    onClick={handleClear}
                    aria-label="Clear search"
                    sx={{ p: 0.25 }}
                  >
                    <HiOutlineX size="0.85rem" />
                  </IconButton>
                </InputAdornment>
              )
            }
          }}
        />
      </Collapse>
    </Box>
  );
}
