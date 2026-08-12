"use client";

import { useColorScheme } from "@mui/material/styles";

export type ThemePreference = "system" | "light" | "dark";
export type ResolvedTheme = "light" | "dark";

function isThemePreference(value: string | undefined): value is ThemePreference {
  return value === "system" || value === "light" || value === "dark";
}

function isResolvedTheme(value: string | undefined | null): value is ResolvedTheme {
  return value === "light" || value === "dark";
}

/**
 * Returns the user's theme preference and the exact theme currently applied.
 * Use `resolvedMode` / `isDark` when you need the effective light/dark appearance.
 */
export function useCurrentTheme() {
  const { mode, systemMode, colorScheme, setMode } = useColorScheme();

  const preference: ThemePreference = isThemePreference(mode) ? mode : "system";

  const resolvedMode: ResolvedTheme = isResolvedTheme(colorScheme)
    ? colorScheme
    : preference === "system"
      ? isResolvedTheme(systemMode)
        ? systemMode
        : "light"
      : preference;

  return {
    /** User-selected preference (`system` | `light` | `dark`) */
    preference,
    /** Alias of `preference` for callers that expect MUI's `mode` naming */
    mode: preference,
    /** Exact applied theme after resolving system preference */
    resolvedMode,
    /** Alias of `resolvedMode` matching MUI's `colorScheme` naming */
    colorScheme: resolvedMode,
    /** OS preference reported by MUI (`light` | `dark` | undefined) */
    systemMode,
    isDark: resolvedMode === "dark",
    isLight: resolvedMode === "light",
    /** False until MUI color scheme has hydrated */
    isReady: Boolean(mode),
    setMode
  };
}
