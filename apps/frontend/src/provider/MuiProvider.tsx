"use client";

import { useMemo, type PropsWithChildren } from "react";

import { alpha, inputBaseClasses, menuItemClasses } from "@mui/material";
import { grey } from "@mui/material/colors";
import { createTheme, ThemeProvider } from "@mui/material/styles";

export const ENDPOINT_COLORS = {
  sms: "#4880FF",
  telegram: "#2AABEE",
  teams: "#454DB3",
  call: "#11AC26",
  email: "#F05A28",
  flow: "#ff00ff",
  discord: "#5865F2",
  "matter-most": "#284077"
} as const;

export default function MuiProvider({ children }: PropsWithChildren<object>) {
  /*
    info: Read the below document to create new theme
    @link: https://mui.com/material-ui/experimental-api/css-theme-variables/customization/
  */
  const theme = useMemo(
    () =>
      createTheme({
        cssVariables: {
          colorSchemeSelector: "class"
        },
        colorSchemes: {
          light: {
            palette: {
              mode: "light",
              primary: { light: "#6F9BFF", main: "#4880FF", dark: "#3D6FDF" },
              secondary: { light: "#DDDDDD", main: "#9A9A9A", dark: "#525252" },
              success: { light: "#7BEA85", main: "#13C82B", dark: "#0E8F1F" },
              warning: { light: "#FABF7A", main: "#F28D22", dark: "#B86419" },
              error: { light: "#FF7D76", main: "#E64940", dark: "#A8322C" },
              background: { default: "#F5F6FA", paper: "#FFFFFF" },
              endpoint: ENDPOINT_COLORS
            }
          },
          dark: {
            palette: {
              mode: "dark",
              primary: { light: "#6F9BFF", main: "#4880FF", dark: "#3D6FDF" },
              secondary: { light: "#757575", main: "#B0B0B0", dark: "#E0E0E0" },
              success: { light: "#0E8F1F", main: "#13C82B", dark: "#7BEA85" },
              warning: { light: "#B86419", main: "#F28D22", dark: "#FABF7A" },
              error: { light: "#A8322C", main: "#E64940", dark: "#FF7D76" },
              background: { default: "#18171e", paper: "#28272d" },
              text: {
                primary: "#dddddd",
                secondary: "#bfbfc3"
              },
              endpoint: ENDPOINT_COLORS
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
              root: ({ theme }) => ({
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
                  backgroundColor:
                    theme.palette.mode === "light" ? "#F1F4F9" : "rgba(255, 255, 255, 0.09)",
                  color: theme.palette.text.primary,
                  "&:hover": {
                    backgroundColor:
                      theme.palette.mode === "light" ? "#E8EFFA" : "rgba(255, 255, 255, 0.13)"
                  },
                  [`&.${inputBaseClasses.focused}`]: {
                    backgroundColor:
                      theme.palette.mode === "light" ? "#E8EFFA" : "rgba(255, 255, 255, 0.13)"
                  },
                  [`&.${inputBaseClasses.disabled}`]: {
                    backgroundColor: `${alpha(grey[600], 0.1)}!important`,
                    color: grey[600]
                  },
                  "& input": {
                    color: theme.palette.text.primary
                  },
                  "& textarea": {
                    color: theme.palette.text.primary
                  }
                },
                "& .MuiInputLabel-root": {
                  color: theme.palette.text.secondary,
                  "&.Mui-focused": {
                    color: theme.palette.primary.main
                  }
                },
                "& input::placeholder": {
                  color: theme.palette.text.secondary,
                  opacity: 0.7
                },
                "& textarea::placeholder": {
                  color: theme.palette.text.secondary,
                  opacity: 0.7
                }
              })
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
              root: ({ theme }) => ({
                width: "100%",
                [`& .${inputBaseClasses.root}`]: {
                  borderRadius: "0.55rem",
                  backgroundColor:
                    theme.palette.mode === "light" ? "#F1F4F9" : "rgba(255, 255, 255, 0.09)",
                  color: theme.palette.text.primary,
                  "&:hover": {
                    backgroundColor:
                      theme.palette.mode === "light" ? "#E8EFFA" : "rgba(255, 255, 255, 0.13)"
                  },
                  [`&.${inputBaseClasses.focused}`]: {
                    backgroundColor:
                      theme.palette.mode === "light" ? "#E8EFFA" : "rgba(255, 255, 255, 0.13)"
                  },
                  [`&.${inputBaseClasses.disabled}`]: {
                    backgroundColor: `${alpha(grey[600], 0.1)}!important`,
                    color: grey[600]
                  }
                },
                "& .MuiSelect-icon": {
                  color: theme.palette.text.secondary
                }
              })
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
    <ThemeProvider theme={theme} defaultMode="system">
      {children}
    </ThemeProvider>
  );
}
