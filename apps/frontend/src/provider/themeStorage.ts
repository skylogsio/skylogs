import type { StorageManager } from "@mui/material/styles";
import Cookies from "js-cookie";

export type ThemeInitialMode = "light" | "dark" | "system";
export type ResolvedTheme = "light" | "dark";

export const MUI_MODE_KEY = "mui-mode";
export const THEME_KEY = "theme";

const COOKIE_OPTIONS = { path: "/", sameSite: "lax" as const, expires: 365 };

export function isThemeInitialMode(value: string | undefined | null): value is ThemeInitialMode {
  return value === "light" || value === "dark" || value === "system";
}

export function resolveTheme(mode: string | undefined | null): ResolvedTheme {
  if (mode === "light" || mode === "dark") return mode;
  if (typeof window !== "undefined") {
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  }
  return "light";
}

function readCookie(key: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  return Cookies.get(key);
}

export function getStoredMode(): string | undefined {
  if (typeof window === "undefined") return undefined;
  try {
    return window.localStorage.getItem(MUI_MODE_KEY) ?? readCookie(MUI_MODE_KEY) ?? undefined;
  } catch {
    return readCookie(MUI_MODE_KEY);
  }
}

export function persistMode(mode: string | null): void {
  if (typeof window === "undefined") return;
  if (mode === null) {
    try {
      window.localStorage.removeItem(MUI_MODE_KEY);
    } catch {
    }
    Cookies.remove(MUI_MODE_KEY, { path: "/" });
    Cookies.remove(THEME_KEY, { path: "/" });
    return;
  }
  try {
    window.localStorage.setItem(MUI_MODE_KEY, mode);
  } catch {
  }
  Cookies.set(MUI_MODE_KEY, mode, COOKIE_OPTIONS);
  Cookies.set(THEME_KEY, resolveTheme(mode), COOKIE_OPTIONS);
}

export function createThemeStorageManager(): StorageManager {
  return ({ key }) => ({
    get(defaultValue) {
      return getStoredMode() ?? readCookie(key) ?? defaultValue;
    },
    set(value) {
      persistMode(value);
    },
    subscribe(handler) {
      if (typeof window === "undefined") return () => {};

      let current = getStoredMode() ?? readCookie(key);

      const handleMediaChange = () => {
        const stored = getStoredMode() ?? readCookie(key);
        if (stored === "system" || stored === undefined) {
          persistMode(stored ?? "system");
        }
      };

      const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
      mediaQuery.addEventListener("change", handleMediaChange);

      const handleStorage = (event: StorageEvent) => {
        if (event.key !== MUI_MODE_KEY && event.key !== key) return;
        const next = event.newValue;
        if (next !== current) {
          current = next ?? undefined;
          persistMode(next);
          handler(next);
        }
      };
      window.addEventListener("storage", handleStorage);

      return () => {
        mediaQuery.removeEventListener("change", handleMediaChange);
        window.removeEventListener("storage", handleStorage);
      };
    }
  });
}
