"use client";

import { useState, useRef, useCallback } from "react";

import { Box, Collapse, IconButton, TextField, useTheme } from "@mui/material";
import { HiOutlineSearch, HiOutlineX } from "react-icons/hi";

import { useScopedI18n } from "@/locales/client";

import type { SearchBoxProps } from "./types";

export default function SearchBox({ title, onSearch }: SearchBoxProps) {
  const { palette } = useTheme();
  const t = useScopedI18n("table");

  const [isOpen, setIsOpen] = useState(false);
  const [searchText, setSearchText] = useState("");

  const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleSearchChange = useCallback(
    (value: string) => {
      setSearchText(value);

      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }

      debounceTimer.current = setTimeout(() => {
        onSearch?.(value);
      }, 300);
    },
    [onSearch]
  );

  const handleToggleSearchBox = useCallback(() => {
    setIsOpen((prev) => !prev);
  }, []);

  return (
    <>
      <Collapse orientation="horizontal" in={!isOpen} timeout={{ enter: 370 }} unmountOnExit>
        <IconButton
          onClick={handleToggleSearchBox}
          sx={{
            backgroundColor: palette.background.paper,
            border: "1px solid",
            borderColor: palette.secondary.light,
            color: palette.secondary.dark,
            padding: "0.4rem"
          }}
        >
          <HiOutlineSearch size="1.1rem" color={palette.secondary.dark} />
        </IconButton>
      </Collapse>
      <Collapse orientation="horizontal" in={isOpen} unmountOnExit>
        <Box>
          <TextField
            size="small"
            value={searchText}
            onChange={(event) => handleSearchChange(event.target.value)}
            sx={{
              "& .MuiInputBase-root": {
                backgroundColor: `${palette.background.paper} !important`,
                paddingRight: "0 !important",
                paddingLeft: "0.5rem"
              },
              "& .MuiInputBase-input": {
                paddingY: "0.3rem",
                paddingLeft: "0.3rem",
                fontSize: "0.8rem"
              },
              "& .MuiOutlinedInput-notchedOutline": {
                borderColor: palette.secondary.light
              }
            }}
            slotProps={{
              input: {
                placeholder: t("searchBox.title", { title }),
                startAdornment: <HiOutlineSearch size="1.4rem" color={palette.secondary.dark} />,
                endAdornment: (
                  <IconButton onClick={handleToggleSearchBox}>
                    <HiOutlineX size="1rem" color={palette.secondary.dark} />
                  </IconButton>
                )
              }
            }}
          />
        </Box>
      </Collapse>
    </>
  );
}
