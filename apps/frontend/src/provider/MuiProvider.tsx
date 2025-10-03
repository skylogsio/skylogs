"use client";

import { useMemo, type PropsWithChildren } from "react";

import { alpha, inputBaseClasses, menuItemClasses } from "@mui/material";
import { grey } from "@mui/material/colors";
import { extendTheme, ThemeProvider } from "@mui/material/styles";

export default function MuiProvider({ children }: PropsWithChildren<object>) {
  /*
                    info: Read the below document to create new theme
                    @link: https://mui.com/material-ui/experimental-api/css-theme-variables/customization/
                  */
  const theme = useMemo(
    () =>
      extendTheme({
        colorSchemes: {
          light: {
            palette: {
              primary: { light: "#6F9BFF", main: "#4880FF", dark: "#3D6FDF" },
              secondary: { light: "#DDDDDD", main: "#9A9A9A", dark: "#525252" },
              success: { light: "#7BEA85", main: "#13C82B", dark: "#0E8F1F" },
              warning: { light: "#FABF7A", main: "#F28D22", dark: "#B86419" },
              error: { light: "#FF7D76", main: "#E64940", dark: "#A8322C" },
              background: { default: "#F5F6FA", paper: "#FFFFFF" }
            }
          }
        },
        components: {
          MuiPaper: {
            styleOverrides: {
              root: {
                borderRadius: "0.5rem"
              }
            }
          },
          MuiChip: {
            styleOverrides: {
              root: {
                borderRadius: "0.4rem"
              }
            }
          },
          MuiTextField: {
            styleOverrides: {
              root: {
                width: "100%",
                "& input::-webkit-outer-spin-button,& input::-webkit-inner-spin-button": {
                  WebkitAppearance: "none",
                  margin: 0
                },
                "& input::-webkit-inner-spin-button": {
                  WebkitAppearance: "none",
                  margin: 0
                },
                "& input[type=number]": {
                  MozAppearance: "textfield"
                },
                [`& .${inputBaseClasses.root}`]: {
                  borderRadius: "0.55rem",
                  backgroundColor: "#F1F4F9",
                  "&:hover": {
                    backgroundColor: "#E8EFFA"
                  },
                  [`&.${inputBaseClasses.focused}`]: {
                    backgroundColor: "#E8EFFA"
                  },
                  [`&.${inputBaseClasses.disabled}`]: {
                    backgroundColor: `${alpha(grey[600], 0.1)}!important`,
                    color: grey[600]
                  }
                }
              }
            },
            defaultProps: {
              slotProps: {
                input: {
                  disableUnderline: true
                }
              }
            }
          },
          MuiSelect: {
            styleOverrides: {
              root: {
                width: "100%",
                [`& .${inputBaseClasses.root}`]: {
                  borderRadius: "0.55rem",
                  backgroundColor: "#F1F4F9",
                  "&:hover": {
                    backgroundColor: "#E8EFFA"
                  },
                  [`&.${inputBaseClasses.focused}`]: {
                    backgroundColor: "#E8EFFA"
                  },
                  [`&.${inputBaseClasses.disabled}`]: {
                    backgroundColor: `${alpha(grey[600], 0.1)}!important`,
                    color: grey[600]
                  }
                }
              }
            },
            defaultProps: {
              disableUnderline: true
            }
          },
          MuiMenuItem: {
            styleOverrides: {
              root: {
                [`&.${menuItemClasses.selected}`]: {
                  backgroundColor: alpha("#6F9BFF", 0.2)
                }
              }
            }
          },
          MuiButton: {
            styleOverrides: {
              root: {
                boxShadow: "none !important",
                borderRadius: "0.55rem"
              }
            }
          },
          MuiIconButton: {
            styleOverrides: {
              root: {
                borderRadius: "0.4rem"
              }
            }
          }
        }
      }),
    []
  );

  return (
    <ThemeProvider theme={theme} defaultMode="light">
      {children}
    </ThemeProvider>
  );
}
