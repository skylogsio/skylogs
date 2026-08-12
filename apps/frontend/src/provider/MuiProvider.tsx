"use client";

import { useMemo, type PropsWithChildren } from "react";

import { alpha, inputBaseClasses, menuItemClasses } from "@mui/material";
import { grey } from "@mui/material/colors";
import { createTheme, ThemeProvider, type StorageManager } from "@mui/material/styles";
import Cookies from "js-cookie";

const THEME_KEY = "theme";

function resolveMode(mode: string | undefined | null): "light" | "dark" {
  if (mode === "light" || mode === "dark") return mode;
  if (typeof window !== "undefined") {
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  }
  return "light";
}

function syncThemeCookie(mode: string | undefined | null): void {
  Cookies.set(THEME_KEY, resolveMode(mode));
}

function createStorageManager(): StorageManager {
  return ({ key }) => ({
    get(defaultValue) {
      return Cookies.get(key) ?? defaultValue;
    },
    set(value) {
      if (value === null) {
        Cookies.remove(key, { path: "/" });
        Cookies.remove(THEME_KEY, { path: "/" });
      } else {
        Cookies.set(key, value);
        syncThemeCookie(value);
      }
    },
    subscribe(handler) {
      if (typeof window === "undefined") return () => {};

      let current = Cookies.get(key);

      let mediaQuery: MediaQueryList | null = null;

      const handleMediaChange = () => {
        if (Cookies.get(key) === "system" || Cookies.get(key) === undefined) {
          syncThemeCookie("system");
        }
      };

      if (typeof window !== "undefined") {
        mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
        mediaQuery.addEventListener("change", handleMediaChange);
      }

      const intervalId = window.setInterval(() => {
        const next = Cookies.get(key);
        if (next !== current) {
          current = next;
          syncThemeCookie(next);
          handler(next ?? null);
        }
      }, 1000);

      return () => {
        window.clearInterval(intervalId);
        mediaQuery?.removeEventListener("change", handleMediaChange);
      };
    }
  });
}

if (typeof window !== "undefined") {
  syncThemeCookie(Cookies.get("mui-mode"));
}

const storageManager = createStorageManager();

export const ENDPOINT_COLORS = {
  sms: "#4880FF",
  telegram: "#2AABEE",
  bale: "#00A693",
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
              primary: {
                light: "#D4B896",
                main: "#C4A07A",
                dark: "#9A7548",
                contrastText: "#1C1917"
              },
              secondary: {
                light: "#F0E6D6",
                main: "#E8DCC8",
                dark: "#C4B49A",
                contrastText: "#1C1917"
              },
              success: { light: "#7BEA85", main: "#13C82B", dark: "#0E8F1F" },
              warning: { light: "#FABF7A", main: "#F28D22", dark: "#B86419" },
              error: { light: "#FF7D76", main: "#E64940", dark: "#A8322C" },
              info: { light: "#D4B896", main: "#C4A07A", dark: "#9A7548" },
              background: { default: "#F3EEE6", paper: "#FFFCF7" },
              divider: "#E4D9C8",
              text: {
                primary: "#1C1917",
                secondary: "#5C534A",
                disabled: "#A89F93"
              },
              action: {
                hover: "rgba(196,160,122,.08)",
                selected: "rgba(196,160,122,.14)",
                disabledBackground: "#EDE6DC"
              },
              endpoint: ENDPOINT_COLORS
            }
          },
          dark: {
            palette: {
              mode: "dark",
              primary: {
                light: "#E0C9A8",
                main: "#C8A882",
                dark: "#A8845C",
                contrastText: "#1C1917"
              },
              secondary: {
                light: "#F0E6D6",
                main: "#E8DCC8",
                dark: "#C4B49A",
                contrastText: "#1C1917"
              },
              success: { light: "#0E8F1F", main: "#13C82B", dark: "#7BEA85" },
              warning: { light: "#B86419", main: "#F28D22", dark: "#FABF7A" },
              error: { light: "#A8322C", main: "#E64940", dark: "#FF7D76" },
              info: { light: "#E0C9A8", main: "#C8A882", dark: "#A8845C" },
              background: { default: "#121110", paper: "#1E1B18" },
              divider: "#2E2A26",
              text: {
                primary: "#EDE6DC",
                secondary: "#B8AFA3"
              },
              action: {
                hover: "rgba(200,168,130,.10)",
                selected: "rgba(200,168,130,.16)",
                disabledBackground: "#2A2622"
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
            }
          },
          MuiInput: {
            defaultProps: {
              disableUnderline: true
            }
          },
          MuiFilledInput: {
            defaultProps: {
              disableUnderline: true
            }
          },
          MuiSelect: {
            styleOverrides: {
              root: ({ theme }) => ({
                width: "100%",
                [`& .${inputBaseClasses.root}`]: {
                  borderRadius: "0.55rem",
                  backgroundColor:
                    theme.palette.mode === "light" ? "#F1EBE1" : "rgba(255, 255, 255, 0.09)",
                  color: theme.palette.text.primary,
                  "&:hover": {
                    backgroundColor:
                      theme.palette.mode === "light" ? "#E8E0D4" : "rgba(255, 255, 255, 0.13)"
                  },
                  [`&.${inputBaseClasses.focused}`]: {
                    backgroundColor:
                      theme.palette.mode === "light" ? "#E8E0D4" : "rgba(255, 255, 255, 0.13)"
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
            }
          },
          MuiMenuItem: {
            styleOverrides: {
              root: {
                [`&.${menuItemClasses.selected}`]: {
                  backgroundColor: alpha("#C4A07A", 0.2)
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
    <ThemeProvider
      theme={theme}
      defaultMode="system"
      storageManager={storageManager}
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      {...({ forceThemeRerender: true } as any)}
    >
      {children}
    </ThemeProvider>
  );
}
