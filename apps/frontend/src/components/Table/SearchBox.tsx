"use client";

import { useState } from "react";

import { Box, Collapse, IconButton, TextField, useTheme } from "@mui/material";
import { HiOutlineSearch, HiOutlineX } from "react-icons/hi";

import { useScopedI18n } from "@/locales/client";

import type { SearchBoxProps } from "./types";

export default function SearchBox({ title }: SearchBoxProps) {
  const { palette } = useTheme();
  const t = useScopedI18n("table");
  const [isSearchBoxOpen, setIsSearchBoxOpen] = useState(false);

  return (
    <>
      <Collapse
        orientation="horizontal"
        in={!isSearchBoxOpen}
        timeout={{ enter: 370 }}
        unmountOnExit
      >
        <IconButton
          onClick={() => setIsSearchBoxOpen((prev) => !prev)}
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
      <Collapse orientation="horizontal" in={isSearchBoxOpen} unmountOnExit>
        <Box>
          <TextField
            size="small"
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
                  <IconButton onClick={() => setIsSearchBoxOpen((prev) => !prev)}>
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
