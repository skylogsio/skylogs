"use client";

import { TextField, useTheme } from "@mui/material";
import { IoSearch } from "react-icons/io5";

import { useScopedI18n } from "@/locales/client";

export default function TopBarSearch() {
  const t = useScopedI18n("wrapper");
  const { palette } = useTheme();

  //TODO: We should add search functionality

  return (
    <TextField
      variant="outlined"
      size="small"
      slotProps={{
        input: {
          startAdornment: <IoSearch size="1.5rem" color={palette.grey[500]} />
        }
      }}
      sx={{
        marginLeft: "2rem",
        marginRight: "auto",
        borderColor: "red !important",
        flex: 1,
        maxWidth: "350px",
        "& .MuiInputBase-root.MuiOutlinedInput-root": {
          backgroundColor: palette.background.default,
          borderRadius: "5rem",
          "&.Mui-focused": {
            "& fieldset": {
              borderWidth: "1px",
              borderColor: palette.grey[400]
            }
          },
          "& fieldset": {
            transition: "all 0.2s linear",
            borderColor: palette.grey[300]
          },
          "& svg": {
            marginRight: "0.5rem"
          }
        }
      }}
      placeholder={t("searchBoxPlaceholder")}
    />
  );
}
